#!/bin/bash
# Deploy-Script für demo.securetime.de
# Tailwind wird lokal gebaut und per SFTP hochgeladen,
# da der KAS-Server den Tailwind-Binary nicht ausführen kann (Seccomp-Filter).

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# .env.local lesen
source <(grep -E '^SSH_' "$SCRIPT_DIR/.env.local" | sed 's/=\(.*\)/=\1/')

echo "=== Tailwind CSS bauen ==="
cd "$SCRIPT_DIR"
php bin/console tailwind:build --minify

echo "=== CSS auf Server hochladen ==="
python3 << PYTHON
import paramiko, os

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(
    hostname='${SSH_IP}',
    username='${SSH_USER}',
    password='${SSH_PASSWORD}',
    timeout=30
)

BASE = '/www/htdocs/w01f177d/${SSH_DIRECTORY}'
LOCAL = '${SCRIPT_DIR}'

sftp = client.open_sftp()
sftp.put(f'{LOCAL}/var/tailwind/app.built.css', f'{BASE}/var/tailwind/app.built.css')
print("  app.built.css hochgeladen")
sftp.close()

# Cache leeren
stdin, stdout, stderr = client.exec_command(f'cd {BASE} && php bin/console cache:clear 2>&1')
stdout.channel.set_combine_stderr(True)
print(stdout.read().decode())

client.close()
PYTHON

echo "=== Deploy abgeschlossen ==="
