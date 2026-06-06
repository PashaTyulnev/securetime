# SecureTime

## Übersicht

SecureTime ist eine moderne Webanwendung für Sicherheitsfirmen und Objektschutz-Unternehmen zur Verwaltung von Schichten, Mitarbeitern, Objekten und Abwesenheiten.

Der Fokus liegt auf:
- einfacher Bedienung
- schneller Einsatzplanung
- Mehrsprachigkeit
- Zusammenarbeit mit Subunternehmen
- Einhaltung von Arbeitszeitregeln
- zentraler Organisation statt Excel-/WhatsApp-Chaos

Domain:
https://securetime.de


---

# Kernfeatures

## Schichtplanung

- Erstellung und Verwaltung von Schichten
- Planung nach:
    - Woche
    - 2 Wochen
    - Monat
- Schnelles Umplanen von Mitarbeitern
- Mehrere Mitarbeiter pro Objekt möglich
- Planung pro Objektposition

### Arbeitszeitlogik

Berücksichtigung von:
- Arbeitszeitgesetz
- Ruhezeiten
- Pausenregelungen
- Soll-Besetzung / Soll-Plan

---

# Objektverwaltung

## Objekte

Verwaltung von Sicherheitsobjekten wie:
- Veranstaltungen
- Gebäude
- Baustellen
- Parkplätze
- Clubs
- Einkaufszentren
- Zirkus
- usw.

## Positionen innerhalb eines Objekts

Beispiele:
- Eingang A
- Eingang B
- Parkplatz
- Backstage
- Notausgang
- Streife

Jede Position kann separat geplant werden.

---

# Mitarbeiterverwaltung

- Mitarbeiter anlegen
- Rollenverwaltung
- Rechteverwaltung
- Mitarbeiter einzelnen Schichten zuweisen
- Übersicht über Einsätze

## Rollen

Beispiele:
- Admin
- Normaler Benutzer
- Partner/Subunternehmen

---

# Partnerfirmen / Subunternehmen

- Verwaltung externer Sicherheitsfirmen
- Partner in Schichtplanung integrierbar
- Gemeinsame Einsatzplanung möglich

---

# Kontakte & Auftraggeber

## Kontakte

- Kontakte an Objekte anhängbar
- Ansprechpartner verwalten
- Telefonnummern / E-Mail-Adressen speichern

## Auftraggeber

- Auftraggeberverwaltung
- Kontakte direkt zuweisbar

---

# Abwesenheiten

## Mitarbeiter-Abwesenheiten

Mitarbeiter können:
- Urlaub einreichen
- Krankheit melden
- Abwesenheiten verwalten

## Admin-Funktionen

- Abwesenheiten genehmigen
- Abwesenheiten ablehnen
- Übersicht aller Anträge

## Kalenderintegration

- Abwesenheiten im Schichtkalender sichtbar

---

# Mehrsprachigkeit

Unterstützte Sprachen:
- Deutsch
- Russisch
- Arabisch

Weitere Sprachen später möglich.

---

# Demo-Modus

## Demand-Shifts generieren (`app:demo:generate-demand-shifts`)

- Repliziert das 2-Wochen-Template (27.04.–10.05.2026) zyklisch als Wochenregeln in die Zukunft
- Wochenmuster wird beibehalten (Wochentag-Flags werden 1:1 kopiert, keine Auflösung in Einzeltage)
- Löscht vor der Generierung alle `demand_shift` ab `--from` FK-sicher (inkl. abhängiger Shifts), Referenzdaten bleiben unverändert
- Idempotent: mehrfaches Ausführen erzeugt denselben Endzustand
- Optionen: `--from=2026-05-11`, `--months=6`, `--dry-run`

## Shifts generieren (`app:demo:generate-shifts`)

- Repliziert echte Shifts (27.04.–10.05.2026) als 2-Wochen-Muster zyklisch in die Zukunft
- Mappt `demand_shift_id` automatisch auf die neu erzeugten DemandShifts (Match über Facility, Name, Zeiten, On-Call, Wochentag, Gültigkeitszeitraum)
- Nutzt die bestehenden Shift-Validatoren (Qualifikation, Zeit-/Ruhezeit-Regeln, Custom-Regeln, Abwesenheiten) plus harte Prüfung „1 Schicht pro Mitarbeiter und Tag"
- Bei fehlendem DemandShift-Match oder Regelverstoß: Fehler + Rollback (keine kaputten Demo-Daten)
- Löscht vor der Generierung alle Shifts ab `--from`, Referenzdaten bleiben unverändert
- Voraussetzung: `app:demo:generate-demand-shifts` muss vorher laufen
- Optionen: `--from=2026-05-11`, `--months=6`, `--dry-run`

## Zeiterfassung generieren (`app:demo:generate-time-tracking`)

- Repliziert echte Zeiterfassung (27.04.–10.05.2026) als 2-Wochen-Muster zyklisch, nur bis heute (keine Zukunft)
- Erhält Checkin/Pause/Checkout-Gruppen: `parent_id` wird auf die neue Parent-ID der Kopie gemappt (Parent zuerst, dann Kinder chronologisch)
- Verschiebt `timestamp` und `created_at` um die gleiche Tagesdifferenz, respektiert den Chronologie-Listener
- Erzeugt nur Einträge an Tagen, an denen der User eine Shift hat; Tage ohne Shift werden übersprungen
- Löscht vor der Generierung alle Einträge ab `--from` (FK-sicher Kinder zuerst), Referenzdaten bleiben unverändert
- Voraussetzung: `app:demo:generate-shifts` muss vorher laufen
- Optionen: `--from=2026-05-11`, `--until=today` (oder Datum), `--dry-run`

Empfohlene Ausführungsreihenfolge: `generate-demand-shifts` → `generate-shifts` → `generate-time-tracking`.
