# Timeout Rueckblick

Diese Datei hilft dir bei zwei Dingen:

1. den naechsten Timeout sauber zu erfassen
2. nach einem bereits erfolgten Neustart noch die vorhandenen Spuren zu pruefen

## Diagnose Beim Naechsten Vorfall

Script:
[timeout_diagnose.sh](/Volumes/Daten/sites/misbuero/wp-content/plugins/cmx-misbuero/includes/help/timeout_diagnose.sh)

Beispiel:

```bash
sudo bash includes/help/timeout_diagnose.sh rentify.misbuero.ch
```

Wichtig:
- moeglichst vor einem Plesk-/Server-Neustart ausfuehren
- moeglichst mit Root-Rechten starten
- die erzeugten Dateien aus dem Ausgabeordner sichern

## Was Du Nach Einem Neustart Noch Pruefen Kannst

Plesk:
- `Websites & Domains > rentify.misbuero.ch > Logs`
- nach Uhrzeit des Ausfalls filtern
- auf diese Meldungen achten:
  - `upstream timed out`
  - `504 Gateway Time-out`
  - `PHP Fatal error`
  - `Allowed memory size exhausted`
  - `Maximum execution time exceeded`

Shell-Logs:
- Vhost-Logs:
  - `/var/www/vhosts/system/rentify.misbuero.ch/logs/error_log`
  - `/var/www/vhosts/system/rentify.misbuero.ch/logs/proxy_error_log`
  - `/var/www/vhosts/system/rentify.misbuero.ch/logs/access_log`
  - `/var/www/vhosts/system/rentify.misbuero.ch/logs/proxy_access_log`
- Systemdienste:
  - `journalctl -u nginx --since "2 hours ago"`
  - `journalctl -u apache2 --since "2 hours ago"`
  - `journalctl -u mariadb --since "2 hours ago"`
  - `journalctl -u mysql --since "2 hours ago"`
  - `journalctl -u plesk-php*-fpm --since "2 hours ago"`
- Kernel/OOM:
  - `journalctl -k --since "6 hours ago" | grep -Ei "oom|out of memory|killed process"`

MySQL:
- `mysqladmin processlist`
- `mysql -e "SHOW FULL PROCESSLIST;"`
- falls vorhanden:
  - Slow-Query-Log

## Was Nach Einem Neustart Meist Verloren Ist

Diese Daten lassen sich nach einem Neustart oft nicht mehr sauber beweisen:

- welche PHP-FPM-Worker genau blockiert waren
- welche MySQL-Queries in diesem Moment haengten
- wie hoch Load, RAM und Swap exakt waehrend des Ausfalls waren
- welcher Request den Engpass unmittelbar ausgeloest hat

## Was Der Letzte Rentify-Fall Eher Nicht War

Die Startseite `https://rentify.misbuero.ch/` war bei der letzten Pruefung schnell und technisch leicht.

Die pluginseitige Startseiten-Logik sitzt in
[startseite_fix.php](/Volumes/Daten/sites/misbuero/wp-content/plugins/cmx-misbuero/includes/startseite_fix.php)
und macht dort nur einfache `the_content`-Filter und CSS-Ausgabe.

Wenn ein kompletter Server-Neustart das Problem sofort behebt, ist die wahrscheinliche Ursache eher:

- PHP-FPM-Worker festgelaufen oder voll
- MySQL/MariaDB blockiert oder ueberlastet
- nginx/Apache Reverse-Proxy-Timeout
- RAM-/Swap-Druck
- temporarer Cache-/Opcode-Zustand
