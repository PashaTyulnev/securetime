#!/bin/bash
# Deploy-Script für app.securetime.de (zentrale Codebase)
# Tailwind wird lokal gebaut (KAS-Server kann Tailwind-Binary nicht ausführen).

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

source <(grep -E '^SSH_' "$SCRIPT_DIR/.env.local" | sed 's/="\(.*\)"/=\1/' | sed "s/='\\(.*\\)'/=\\1/")

REMOTE_APP="${SSH_DIRECTORY%/public}"

echo "=== [1/5] Tailwind CSS bauen ==="
cd "$SCRIPT_DIR"
php bin/console tailwind:build --minify

echo "=== [2/5] Archiv erstellen ==="
ARCHIVE="/tmp/securetime_deploy_$(date +%s).tar.gz"
tar -czf "$ARCHIVE" \
  --exclude='./.git' \
  --exclude='./.env.local' \
  --exclude='./var/cache' \
  --exclude='./var/log' \
  --exclude='./node_modules' \
  --exclude='./*.Zone.Identifier' \
  -C "$SCRIPT_DIR" .
echo "  Archiv: $ARCHIVE ($(du -sh "$ARCHIVE" | cut -f1))"

echo "=== [3/5] Upload & Deployment auf Server ==="
python3 << PYTHON
import paramiko, os, sys

SSH_IP       = '${SSH_IP}'
SSH_USER     = '${SSH_USER}'
SSH_PASSWORD = '${SSH_PASSWORD}'
REMOTE_APP   = '${REMOTE_APP}'
ARCHIVE      = '${ARCHIVE}'

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(SSH_IP, username=SSH_USER, password=SSH_PASSWORD, timeout=30)

# Upload Archiv
print('  → Archiv hochladen...')
sftp = client.open_sftp()
remote_archive = f'/tmp/{os.path.basename(ARCHIVE)}'
sftp.put(ARCHIVE, remote_archive)
sftp.close()
print(f'  ✓ hochgeladen nach {remote_archive}')

# Entpacken (--keep-old-files nicht nötig, wir überschreiben)
steps = [
    ('entpacken',              f'tar -xzf {remote_archive} -C {REMOTE_APP} && rm {remote_archive}'),
    ('composer install',       f'cd {REMOTE_APP} && composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5'),
    ('migrations default',     f'cd {REMOTE_APP} && php bin/console doctrine:migrations:migrate --no-interaction 2>&1'),
    ('migrations admin',       f'cd {REMOTE_APP} && php bin/console doctrine:migrations:migrate --em=admin --no-interaction 2>&1'),
    ('cache:clear',            f'cd {REMOTE_APP} && php bin/console cache:clear 2>&1 | tail -3'),
    ('cache:warmup',           f'cd {REMOTE_APP} && php bin/console cache:warmup 2>&1 | tail -3'),
]

for label, cmd in steps:
    print(f'  → {label}...')
    stdin, stdout, stderr = client.exec_command(cmd, timeout=180)
    exit_code = stdout.channel.recv_exit_status()
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    if out:
        for line in out.splitlines()[-5:]:
            print(f'     {line}')
    if exit_code != 0 and err:
        print(f'  ✗ Fehler: {err[:300]}')
        sys.exit(1)
    else:
        print(f'  ✓ {label} OK')

client.close()
PYTHON

echo "=== [4/5] Lokales Archiv aufräumen ==="
rm -f "$ARCHIVE"

echo ""
echo "=== [5/5] Deploy abgeschlossen ==="
echo "  → https://app.securetime.de"
