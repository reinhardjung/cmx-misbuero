<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

Ja — grundsätzlich JA, aber nur unter bestimmten Voraussetzungen.

🧾 Was passiert mit einer PAIN.001 Datei?

Die PAIN.001 ist eine standardisierte XML-Datei für Zahlungsaufträge (SEPA / ISO 20022).
Du erzeugst damit keine Zahlung direkt, sondern einen Zahlungsauftrag, den Du Deiner Bank übergibst.

✅ So läuft der Prozess korrekt ab
PAIN.001 in Mis Büro erzeugen
Upload bei Deiner Bank
z. B. bei PostFinance, UBS oder Raiffeisen
Bank prüft die Datei
Du gibst die Zahlung frei (manuell oder automatisch)
Zahlungen werden ausgeführt
⚠️ Wichtige Voraussetzungen

Damit das wirklich funktioniert, muss Deine PAIN.001 sauber sein:

1. IBAN / QR-IBAN korrekt
Schweizer Format korrekt validiert
QR-IBAN nur mit QR-Referenz
2. Referenzen stimmen
QR-Rechnung → QR-Referenz
Normale Zahlung → Creditor Reference oder keine
3. Währung & Beträge
CHF sauber formatiert (kein Komma-Fehler)
4. Debitor / Creditor Daten
Name & Adresse vollständig
🏦 Bank-spezifische Unterschiede

Nicht jede Bank ist gleich streng:

PostFinance → sehr strikt (XML muss 100% korrekt sein)
UBS → ebenfalls strikt
Raiffeisen → etwas toleranter, aber auch ISO-konform
🔐 Freigabe ist immer nötig

Wichtig:
👉 Die Zahlung wird NIE automatisch ausgeführt, nur weil Du eine PAIN.001 erzeugst.

Du musst:

im E-Banking freigeben oder
ein EBICS/automatisiertes System nutzen
💡 Empfehlung für Mis Büro

Wenn Du das sauber machen willst:

PAIN.001 Export anbieten
Optional:
Validierung vor Export
Fehlermeldungen (z. B. IBAN falsch)
Vorschau der Zahlungen
Status: „bereit für Bankupload“
🧠 Fazit
Ja, Du kannst mit PAIN.001 Zahlungen ausführen
Aber nur indirekt über Deine Bank
Mis Büro ist der Generator, nicht die ausführende Instanz

Wenn Du willst, zeige ich Dir als nächsten Schritt:

👉 Wie Du in Deinem Plugin eine 100% bankkonforme PAIN.001 (CH optimiert) erzeugst (inkl. PHP-Code, strukturiert nach ISO 20022)
