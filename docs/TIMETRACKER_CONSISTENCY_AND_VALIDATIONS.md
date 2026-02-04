# TimeTracker: Inkonsistenzen und Validierungen

## Analyse der Export-Daten (timetracker_export.sql)

### Gefundene Inkonsistenzen (durch Auto-Checkout / automatische Pausen)

1. **Checkout-Zeitpunkt vor letztem Checkin (timestamp_not_monotonic)**  
   Wenn beim Checkout automatisch Pause + Checkin nachgetragen werden, werden diese mit `checkoutTimestamp - X Minuten` erzeugt. Wenn der übergebene Checkout-Zeitpunkt (z. B. vom Auto-Checkout-Command) **vor** dem so berechneten Checkin liegt oder Zeitzonen/Rundung dazu führen, entstehen Einträge mit:
   - **Checkout-Timestamp < Checkin-Timestamp** (innerhalb derselben Schicht, sortiert nach ID).

   Beispiele aus dem Export:
   - **Shift 50 (User 20):** Checkin id=54 06:29, Checkout id=55 06:00 (auto_check_out=1) → Checkout vor Checkin.
   - **Shift 52 (User 44):** Checkin id=61 18:24, Checkout id=62 18:00 (auto_check_out=1).
   - **Shift 56 (User 33):** Checkin id=70 06:20, Checkout id=71 06:00 (auto_check_out=1).
   - **Shift 286 (User 23):** Pause 09:30, Checkin 10:15, Checkout id=303 **06:00** (auto_check_out=1) → Checkout weit vor Pause/Checkin.

2. **Ursache**  
   - Auto-Checkout setzt Checkout auf `mainCheckin + 12,5h`; bei Rundung oder 12h statt 12,5h kann ein zu früher Zeitpunkt übergeben werden.  
   - Nach dem Einfügen von Pause + Checkin wird der **ursprüngliche** `checkoutTimestamp` für den Checkout-Eintrag verwendet. Liegt dieser vor dem eingefügten Checkin (oder entsteht das durch Zeitzonen), ist die Reihenfolge der Timestamps inkonsistent.

3. **Weitere mögliche Fehlerbilder**  
   - Zwei Checkouts für dieselbe Schicht (parent).  
   - Pause ohne folgenden Checkin (offene Pause).  
   - Kind-Einträge mit Timestamp vor dem Haupt-Checkin.  
   - Schicht ohne Checkout (offene Schicht) – kann gewollt sein.

---

## Durchgeführte Code-Anpassung

- **TimeTrackerService::checkout()**  
  Nach dem Einfügen der automatischen Pause + Checkin wird geprüft:  
  `checkoutTimestamp <= checkinAfterPause`.  
  Falls ja, wird der Checkout-Zeitpunkt auf `checkinAfterPause + 1 Minute` gesetzt.  
  Damit ist der Checkout immer **nach** dem zuletzt eingefügten Checkin und die Reihenfolge bleibt konsistent.

---

## Empfohlene Validierungen (zum Vermeiden weiterer Inkonsistenzen)

### 1. Beim Erzeugen / Speichern von Einträgen (TimeTrackerService)

| Regel | Beschreibung | Wo prüfen |
|-------|--------------|-----------|
| **Checkout nach letztem Ereignis** | Der Checkout-Timestamp muss **strikt nach** dem Timestamp des letzten Eintrags der Schicht liegen (nach ID sortiert). | `checkout()`, vor `createTimeTrackerEntry(..., checkout, ...)` |
| **Monotonie pro Schicht** | Jeder neue Eintrag (pause, checkin, checkout) einer Schicht muss einen Timestamp haben, der **≥** dem Timestamp des zuletzt eingefügten Eintrags ist (oder explizit erlaubte Backdates mit klaren Regeln). | Beim Persistieren von Pause/Checkin/Checkout |
| **Genau ein Checkout pro Schicht** | Pro Haupt-Checkin (parent_id) darf höchstens ein Eintrag vom Typ `checkout` existieren. | Vor dem Anlegen eines Checkout-Eintrags |
| **Pause immer mit Checkin abschließen** | Vor einem Checkout darf keine Pause ohne folgenden Checkin stehen (keine offene Pause). | Bereits durch Ablauf (Pause → Checkin) gewährleistet; optional als Konsistenz-Check |

### 2. Auto-Checkout-Command

| Regel | Beschreibung |
|-------|--------------|
| **Checkout-Zeitpunkt nach „jetzt“** | Berechneter Checkout = mainCheckin + 12,5h. Wenn dieser Zeitpunkt **vor** dem aktuellen Zeitpunkt liegt (z. B. Zeitzone), mindestens 1 Minute nach dem letzten Checkin der Schicht verwenden. |
| **Keine Rundung unter 12,5h** | 12,5 Stunden exakt verwenden (750 Minuten), keine Abrundung auf volle Stunden. |

### 3. Konsistenz-Check (vorhanden)

- **Command:** `php bin/console app:time-tracker-consistency-check`  
  Prüft alle Schichten auf:
  - Timestamp vor Haupt-Checkin
  - Timestamps nicht monoton steigend (ID-Reihenfolge)
  - Mehrere Checkouts pro Schicht
  - Checkout bei offener Pause
  - Pause ohne folgenden Checkin (zwei Pausen hintereinander)
  - Schicht ohne Checkout (nur Hinweis, offene Schicht kann legitim sein)

### 4. Optionale DB-Constraints / Doctrine

- **Check:** Pro `parent_id` (bzw. Haupt-Checkin-ID) höchstens ein Eintrag mit `type = 'checkout'`.  
  In Doctrine/DB nur mit Trigger oder periodischem Check sinnvoll umsetzbar; Anwendungslösung in `checkout()` ist vorgesehen.

---

## Zusammenfassung

- **Export:** Enthält mehrere Schichten, bei denen der Checkout-Timestamp **vor** dem letzten Checkin liegt (typisch bei Auto-Checkout + automatischer Pause).
- **Anpassung:** In `checkout()` wird der Checkout-Zeitpunkt nach dem Einfügen von Pause/Checkin so korrigiert, dass er immer **nach** dem Checkin nach der Pause liegt.
- **Prüfung:** `app:time-tracker-consistency-check` zeigt bestehende Inkonsistenzen in der DB.
- **Weitere Absicherung:** Beim Anlegen von Checkout und bei automatischen Pausen/Checkins die obigen Validierungen einhalten (Checkout nach letztem Ereignis, Monotonie, ein Checkout pro Schicht).
