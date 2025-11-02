# Basis-App mit User-Authentifizierung + Google Authenticator

1. Enthält User Entity mit email, password, roles und googleAuthenticatorSecret.
2. Implementiert User-Authentifizierung mit Passwort und Google Authenticator.
3. Navbar erstellt
4. Eingeloggter User wird angezeigt


## Folgende Systematik wird verwendet:
Controller haben Klasse für die Seite selbst + 2 Ordner. Data und Component API.
Die Data API wird nur JSON Daten liefern oder erstellen.
Die Component API wird HTML Komponenten liefern.

## Erstellung vom Mitarbeitern
1. Es wird ein User/Mitarbeiter erstellt ohne Passwort
2. Passworterstellung wird per Email-Link angestoßen

## Modals
Im Ordner Templates/Modals gibt es ein modals.html.twig. Es muss immer Extended werden wenn man Modals erstellt.
Des Weiteren gibt es dazu immer einen Stimulus Controller der das Modal Verhalten steuert.
