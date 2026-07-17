# Mis Buero Packages

Ziel: so schnell wie moeglich ein schlankes, sauberes Free-Plugin fuer WordPress.org bereitstellen. Das Free-Plugin muss als eigenstaendiges Produkt nutzbar sein und darf keine Business-Funktionen als gesperrte Trialware enthalten.

## Pakete

### `mis-buero`

Kostenloses WordPress.org-Plugin.

Module:

- `customers` - Kontakte/Kunden als Basis fuer Belege und Projekte
- `invoices` - Belege
- `documents` - Dokumente
- `projects` - Projekte
- `budget` - Budget
- `settings` - Basis-Einstellungen
- `pdf` - PDF-Ausgabe, soweit fuer Belege noetig und WordPress.org-konform
- `qr_invoice` - QR-Rechnung, soweit ohne externe Pflichtdienste nutzbar
- `suppliers` - Lieferanten, soweit fuer Belege/Budget noetig

Nicht in Free:

- Lizenzpruefung
- Trial-Logik
- externe Dienste ohne aktive Zustimmung
- versteckte Business-Module
- Cloud-, Mail-, Kalender-, Banken- oder Automationsfunktionen

### `mis-buero-business`

Kostenpflichtiges Add-on ausserhalb WordPress.org. Haengt von `mis-buero` ab.

Module:

- `bookings` - Buchungen
- `camt` - Banken/CAMT
- `emails` - E-Mails
- `calendar` - Kalender
- `automation` - Automationen
- `advanced_reports` - erweiterte Auswertungen

### `mis-buero-modules`

Optionale Add-ons ausserhalb WordPress.org.

Module:

- `payrexx`
- `facturx`
- `treuhand`
- `openai`

### Branchenspezifische Add-ons

Branchenspezifische Funktionen sollen eigene Add-ons bleiben, z. B. `mis-buero-carent`, damit das WordPress.org-Free-Plugin klein und allgemein bleibt.

## Source of Truth

- `src/` bleibt die Legacy-Live-Struktur, solange die Migration laeuft.
- Fuer `mis-buero` ist `src/kontakte`, `src/artikel`, `src/belege`, `src/dokumente`, `src/projekte` und `src/budget` aktuell bewusst die Source of Truth, damit die Free-ZIP dieselbe Admin-UI und dieselben Metaboxen nutzt wie das bestehende Mis Buero.
- `packages/mis-buero` enthaelt nur den Free-Bootstrap, Sprachdateien und Paket-Metadaten. Die Free-ZIP kopiert die erlaubten Legacy-Module beim Build.
- `packages/` bleibt die neue Zielstruktur fuer kuenftig wirklich migrierte Module.
- Ein Modul darf nur an einem offiziellen Ort gepflegt werden.
- Sobald ein Modul migriert ist, ist `packages/<package>/src/<module>/` die Source of Truth.
- Keine parallele Fachlogik in `src/` und `packages/` pflegen.

## Build und Artefakte

- `dist/` ist generiert.
- `tmp/` ist generiert.
- Release-ZIPs werden aus `packages/` gebaut.
- Build-Artefakte werden nicht als Quellcode gepflegt.

## WordPress.org-Free-Checkliste

Vor dem Submit von `mis-buero`:

- Plugin laeuft ohne Business-Add-on sinnvoll.
- Plugin-Header, `readme.txt`, License und Textdomain sind sauber.
- Keine Trialware, keine harten Upsells, keine Remote-Requests ohne Zustimmung.
- Alle Eingaben sind mit Nonces und Capabilities geschuetzt.
- Alle Daten werden sanitisiert, validiert und escaped.
- Keine Debug-Ausgaben, keine PHP-Warnings, keine Deprecated Notices.
- Keine geheimen Tokens, keine Instanzdaten, keine Kundendaten im Paket.
- Vendor-Code nur soweit noetig und ohne Tests/Dev-Artefakte im ZIP.
- ZIP laesst sich lokal installieren, aktivieren, deaktivieren und loeschen.
- CPTs, Admin-Menues, Basis-Einstellungen und Kern-Workflows funktionieren im Free-Plugin.

## Migration

1. Free-Zielumfang in `packages/mis-buero` stabilisieren.
2. Erst ein kleines echtes Modul migrieren und im ZIP testen.
3. Aktivierung, Autoloading, Config und Modul-Laden pruefen.
4. Modul fuer Modul verschieben.
5. Nach jeder Migration: lokales ZIP bauen, installieren, aktivieren und Smoke-Test.
