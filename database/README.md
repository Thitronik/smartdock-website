# SMARTDOCK Datenbank

## Newsletterstudio – 30.10.2026

Migration `005_newsletter_campaigns.sql` wurde nach einer privaten SQL-Sicherung
auf **smartda_test** ausgeführt; phpMyAdmin bestätigte beide Anweisungen erfolgreich.
Nicht erneut ausführen. Sie ergänzt `sd_newsletter_campaigns` und Kampagnenbezüge
sowie zwei Mailtypen in der Outbox. Die Produktivdatenbank wurde nicht verändert.
Der Testversand ist aktiviert, der Gruppenversand bleibt bis zum Abschluss der
Versandprüfung deaktiviert. Details: [Newsletterstudio](../docs/betrieb/newsletterstudio.md).

## Aktueller Teststand – 28.10.2026

Die getrennte Hetzner-Datenbank `smartda_test` enthält Migrationen 001, 002, 003 und
004 (öffentliche Formulare, installiert am 28.10.2026). Diese Migrationen dort **nicht erneut ausführen**. Vor 003 wurde eine private
vollständige SQL-Sicherung erstellt und der Bestand mit 13 Tabellen geprüft.
Nach 003: 14 Tabellen; `sd_dealer_access_requests` speichert persönliche Anfragen.
Die Outbox enthält zusätzliche Transaktionsmailtypen, Benutzer-/Tokenbezüge und
verschlüsselte Payloads. Die Produktivdatenbank bleibt unverändert.

Neun Firmen aus dem bereitgestellten Hostinger-Export wurden anhand eindeutiger
Quellkennungen in die Testdatenbank importiert. Originalanschriften, Koordinaten
und Sprachvarianten bleiben erhalten. Keine Benutzerkonten, Mitgliedschaften,
Newslettereinwilligungen oder Einladungen wurden daraus erzeugt. Kartenstatus
zunächst `unlisted`; Veröffentlichung erfolgt bewusst über die Administration.

Die folgenden historischen Abschnitte beschreiben frühere Installationsstände.
Sie sind keine Anleitung zum erneuten Ausführen der Erstmigrationen.

Erstmigration für MariaDB 10.6 oder neuer. Am 21.10.2026 auf der vorgesehenen
Hetzner-Datenbank ausgeführt (MariaDB 11.8.6). Ausgangsbestand: keine Tabellen.
Schema aus Commit `9e09cf0f898db6acf20b675196ec108a0ec24f78`.
Alle fünf CREATE-TABLE-Anweisungen wurden erfolgreich ausgeführt; anschließend
wurden alle fünf Tabellen mit SHOW CREATE TABLE abgefragt und ihre Bestände
mit COUNT(*) geprüft: jeweils 0 Datensätze. Die Tabellenübersicht bestätigt
InnoDB und utf8mb4_unicode_ci. Die Migration nicht erneut ausführen.

Backend, Double-Opt-in und Mailversand sind noch nicht angebunden oder getestet.
Die Website bleibt im Vorschaumodus.

## Tabellen

| Tabelle | Zweck |
| --- | --- |
| sd_newsletter_subscribers | Adresse, Anmeldestatus und Einwilligung |
| sd_newsletter_tokens | Hashes befristeter Bestätigungs- und Abmeldetokens |
| sd_newsletter_events | Verlauf von Anmeldung, Bestätigung und Abmeldung |
| sd_configuration_requests | Kontaktdaten und versionierte Konfiguration |
| sd_mail_outbox | Warteschlange für späteren serverseitigen Mailversand |

## Einmaliger Import

1. In phpMyAdmin die ausdrücklich vorgesehene Datenbank auswählen.
2. `SELECT DATABASE(), VERSION();` und `SHOW TABLES;` ausführen. MariaDB-Version
   prüfen und vorhandene Struktur sichern. Bei bestehenden `sd_`-Tabellen zunächst
   deren Struktur vergleichen; diese Erstmigration ist kein Upgrade.
3. `migrations/001_initial.sql` importieren. Sie erstellt ausschließlich fünf
   Tabellen. DDL ist nicht als Ganzes rückrollbar. Bei Fehlern stoppen, vorhandene
   Teilergebnisse prüfen und nicht blind erneut importieren.
4. Mit `SHOW CREATE TABLE` für jede Tabelle Spalten, JSON-Prüfung, eindeutige
   Indizes und Fremdschlüssel prüfen. Alle fünf Tabellen müssen leer sein.
5. Den erfolgreichen Import mit Zielumgebung, Zeit und Git-Commit dokumentieren.

## Vor Anschluss der Formulare

- Das Backend läuft auf dem Server; Datenbank- und SMTP-Zugangsdaten bleiben
  außerhalb des öffentlichen Repositorys und Webroots. GitHub Pages greift
  niemals direkt auf die Datenbank zu.
- Pro Verbindung UTC setzen. E-Mails serverseitig validieren und eine einheitliche
  Normalisierungsregel verwenden (Leerraum entfernen, Domain kleinschreiben;
  lokale Teile nicht ungeprüft verändern). Prepared Statements und Transaktionen
  verwenden; Schemaeinrichtung und Laufzeit haben getrennte Berechtigungen.
- Newsletter startet immer als `pending`. Zufällige Tokens aus mindestens 32
  kryptografisch sicheren Bytes erzeugen, nur SHA-256-Hashes als 32 Bytes speichern.
  Bestätigung prüft Zweck, Ablauf und einmalige Verwendung atomar. Anmeldung,
  Ereignis, Token und Mailauftrag gemeinsam in einer Transaktion speichern.
- Die Einwilligungstexte sind versioniert. Konfigurationsanfragen erteilen keine
  Newsletter-Einwilligung. Die Ereignistabelle ist eine Verlaufshilfe und kein
  manipulationssicheres Audit-System.
- Konfigurationen gegen erlaubte Module prüfen und Preise serverseitig berechnen;
  keine vom Browser übermittelten Preise ungeprüft übernehmen. Nur ausdrücklich
  abgesendete Anfragen speichern; Entwürfe bleiben zunächst lokal.
- Mailversand benötigt einen gesonderten SMTP-Worker mit Sperren, Wiederholungen
  und deduplizierten Aufträgen. SMTP garantiert keinen exakt einmaligen Versand.
  Keine Newsletter-Kampagnen sind durch dieses Schema eingerichtet.
- Schutz vor Missbrauch, restriktives CORS für die Stage, nichtöffentliche
  Verwaltungszugriffe und getrennte Testdatenbank vor realem Betrieb ergänzen.
- Aufbewahrungsfristen festlegen. Tokenlinks in Outbox-Payloads sind kurzlebige
  Geheimnisse: nach Versand/Ablauf entfernen. Outbox-Empfänger und Payloads bei
  Löschanfragen ausdrücklich mitbereinigen; SET NULL anonymisiert sie nicht.

## Entwurf für Firmen und persönliche Konten – 27.10.2026

[Migration 002](migrations/002_dealers_and_users.sql) ist vorbereitet, **nicht
installiert**. Sie ergänzt Firmen, persönliche Benutzer, unabhängige Mitgliedschaften,
interne Rollen, Tokens/Sessions, Newsletterzuordnung und Verwaltungsereignisse.
Die fünf obigen Bestandstabellen bleiben unverändert. Tabellen/SQL sind vom
Website-Build ausgeschlossen. Zunächst in einer getrennten MariaDB-Testdatenbank
prüfen; die Erstmigration 001 nicht erneut ausführen.

Der verbindliche aktuelle [Ablauf für Verwaltung und Zugänge](../docs/betrieb/verwaltung-und-zugaenge.md)
ersetzt die frühere Annahme „ein Händler = ein Login“. Firmenfreigabe für die Karte
und Mitarbeiterfreigaben sind voneinander unabhängig. Die öffentliche Admin-Ansicht ist geschlossen. Eine lokale Gestaltungsvorschau
enthält nur erfundene Beispieldaten; echte Konten werden erst auf Hetzner eingerichtet.
