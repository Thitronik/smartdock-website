# Öffentliche Formulare in der Hetzner-Testumgebung

Ausgangspunkt: 4785c0291430d17fa3ba628d7595c18eaa4dc56d (Händlerabläufe).

Nur test.smartdock.de verbindet die bestehenden öffentlichen Formulare mit PHP. GitHub Pages behält seinen Vorschaumodus. Das bestehende Layout bleibt erhalten; beim Kontakt- und Konfiguratorformular wird ein Datenschutzhinweis ergänzt. In den beiden übernommenen Anwendungspaketen ändert sich ausschließlich die Newsletter-Erfolgsmeldung in fünf Sprachen: Eine abgesendete Anmeldung ist noch keine bestätigte Anmeldung.

Newsletter: Honeypot, Mindestdauer, CSRF, Domainprüfung und IP-/Adresslimits; Bestätigungslink 24 Stunden, explizites POST und einmalige Verwendung; Abmeldung ebenfalls mit ausdrücklicher Bestätigung. Empfänger werden erst nach Bestätigung aktiv. Mails enthalten einen Abmeldelink. Keine Kampagnenfunktion oder Versand über externe Anbieter.

Kontakt- und Konfigurationsanfragen: Speicherung mit Referenz und Benachrichtigung ausschließlich an die private Einstellung inquiry_email. Die Administration zeigt die letzten 100 Anfragen sowie Newsletter-Statuszahlen. Konfigurationsdaten werden als zu prüfende Auswahl gespeichert; vom Browser gelieferte Preise, Rabatte, Status und Händlerzuordnungen sind keine autoritativen Werte. Ein verbindliches Angebot erfordert eine fachliche Prüfung.

## Installation

Vorher vollständiges Backup von smartda_test sichern. Migration 004_public_forms.sql einmalig nach 003 anwenden, danach die privaten PHP-Dateien einschließlich public-forms.php und mail.php gemeinsam aktualisieren. inquiry_email ausschließlich in der privaten config.php konfigurieren. Anschließend gebaute preview.js, admin-server.js und beide Anwendungspakete auf die geschützte Testseite übertragen. Der bestehende Mailworker verarbeitet die neuen Transaktionsmails ebenfalls.

Keine Zugangsdaten, Kundendaten oder Testdatenbank-Dumps ins Repository übernehmen. Keine Änderung der Live-Domain oder des Hostinger-Hostings.

## Prüfung und offene Nachweise

Lokale Website-Prüfung: 76 Tests, Root-/Pages-Build und 60 Redirect-Fälle bestanden. PHP-Syntax und 52 Sicherheitsprüfungen bestanden. Die MariaDB-HTTP-Integration ergänzt Kontakte, idempotente Konfigurationsanfragen, manipulierte Preise und Newsletter-Bestätigung/Abmeldung ohne GET-Nebenwirkungen.

Offen vor Deployment: veröffentlichter MariaDB-Lauf, Backup/Migration 004, reale Mailzustellung und vollständiger Browserdurchlauf einschließlich Mobilansicht. Die neuen Newsletter-Transaktionsseiten und Mailtexte sind derzeit deutsch; die öffentlichen Formulare und Erfolgsmeldungen bleiben in fünf Sprachen verfügbar. Diese Einschränkung vor vollständiger Sprachfreigabe beheben.
