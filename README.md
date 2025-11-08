# Basis-App mit User-Authentifizierung + Google Authenticator

1. Enthält User Entity mit email, password, roles und googleAuthenticatorSecret.
2. Implementiert User-Authentifizierung mit Passwort und Google Authenticator.
3. Navbar erstellt
4. Eingeloggter User wird angezeigt

## Entity Erstellung - Situationen
1. Auftragsgeber - Adresse + Kontakte
2. Objekte die vom Auftraggeber bewacht werden müssen - Adresse
3. Mitarbeiter die an einen Auftragsgeber gebunden sind
4. Abteilungen

## Folgende Systematik wird verwendet:
Controller haben Klasse für die Seite selbst + 2 Ordner. Data und Component API.
Die Data API wird nur JSON Daten liefern oder erstellen.
Die Component API wird HTML Komponenten liefern.

## Erstellung vom Mitarbeitern
1. Es wird ein User/Mitarbeiter erstellt ohne Passwort
2. Passworterstellung wird per E-mail-Link angestoßen

## Modals
Im Ordner Templates/Modals gibt es ein modals.html.twig. Es muss immer Extended werden wenn man Modals erstellt.
Des Weiteren gibt es dazu immer einen Stimulus Controller der das Modal Verhalten steuert.

## Objekte- Wizard
1. Auftragsgeber
1.1. Kontakte dazu 
1.2. Adressen dazu
