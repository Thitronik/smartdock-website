# Serverseitige Anmeldung – Grundlage für die Hetzner-Testumgebung

## Händlerabläufe – 28.10.2026

Migration 003 ergänzt persönliche Zugangsanfragen und eine verschlüsselte,
dauerhafte Transaktionsmail-Warteschlange. Eine Anfrage erzeugt zunächst eine
Person ohne Passwort und ohne Rechte. Erst die einmalige E-Mail-Bestätigung
(24 Stunden) erzeugt eine offene Mitarbeiterzuordnung und die Benachrichtigung
an die privat konfigurierte Geschäftsführungsadresse. Der Freigabelink gilt
sieben Tage und verlangt zusätzlich eine aktive Admin-/Approver-Sitzung.
GET-Aufrufe bestätigen oder genehmigen nichts. Staff-Mail-Bestätigung und TOTP
bleiben Voraussetzungen der Administration.

Persönliche Freigaben und Kartenfreigaben sind unabhängig. Die Verwaltung kann
offene Mitarbeiter einer bestehenden Firma zuordnen und öffentliche Firmendaten
einschließlich Koordinaten pflegen. Passwortlinks gelten eine Stunde, werden nur
gehasht gespeichert und können einmal verwendet werden. Passwortänderungen
widerrufen laufende Sitzungen; gesperrte Personen/Firmen oder fehlende persönliche
Freigaben verhindern die Nutzung. Der Händler-Passwortablauf gilt nicht für Staff.

Die Testseite lädt den Händlerfinder aus den ausdrücklich veröffentlichten
Datenbankfirmen. GitHub Pages bleibt eine Vorschau ohne schreibenden Backendzugriff.
Die sechs Händlerkacheln enthalten weiterhin keine vertraulichen Unterlagen;
deren Inhalte werden noch vorbereitet.

`mail-worker.php` ist ausschließlich ein privater CLI-Einstieg. Auf Hetzner läuft
er mit `/usr/bin/php84` alle fünf Minuten exklusiv, zusätzlich erfolgt nach
Formulartransaktionen ein begrenzter Versandversuch. Nach Fehlern bleiben Aufträge
erneut versuchbar, nach fünf Versuchen ist eine manuelle Prüfung erforderlich.
Abgelaufene/widerrufene Tokens werden nicht verschickt; Tokenpayloads werden nach
Versand oder Abbruch entfernt. Versand ist Text/HTML mit festem SMARTDOCK-Absender.
Ein Mailserver bestätigt keine garantierte oder exakt einmalige Zustellung.

Prüfung: 52 reine PHP-Sicherheitsfälle sowie MariaDB-HTTP-Integration für Anfrage,
Bestätigung, Anmeldung, Zuordnung, unabhängige Freigaben, Passwortsetzung/-reset,
Token-Replay und Sitzungswiderruf. Eine tatsächliche Anmeldung mit erfundenem Konto
wurde auf der Testseite abgelehnt. Der erste Admin bestätigte den Mailempfang und
die Aktivierung selbst. Ein echter neuer Händlerdurchlauf mit zugänglichem
Testpostfach und die angemeldete CMS-Sichtprüfung stehen noch aus.

Kundennewsletter, Konfigurations- und Kontaktformulare sind durch diesen
Händlerausbau noch nicht angeschlossen. Die folgenden Abschnitte dokumentieren
die ursprüngliche Anmeldegrundlage und deren Installation.

**Nur in der getrennten Hetzner-Testumgebung installiert; keine Produktionsfreigabe.**
Die Website bleibt hinter Verzeichnisschutz. Private Konfiguration und Implementation
liegen außerhalb des Webroots. Der anonyme Sitzungsaufruf wurde auf Hetzner geprüft;
er erreicht die Testdatenbank per verifiziertem TLS. Echte Konten sind noch nicht
angelegt oder freigeschaltet. Die Admin-Oberfläche wird ausschließlich auf der
Testdomain angeschlossen; GitHub Pages zeigt weiterhin den geschlossenen Einstieg.

## Enthaltener Umfang

- Anmeldung aktiver Benutzer mit bestätigter E-Mail und gesetztem Passwort.
- Interne Konten benötigen zusätzlich einen exakten Eintrag in einer privaten
  Freigabeliste, eine aktive Datenbankrolle und einen TOTP-Einmalcode. Eine Domain
  oder Händlerfreigabe allein berechtigt niemals zur Verwaltung.
- Händlerkonten benötigen eine freigegebene persönliche Firmenzuordnung. Die
  Kartenfreigabe der Firma ist davon unabhängig.
- Serverseitige Sitzungen: Secure-/HttpOnly-/SameSite-Cookies, neue Sitzungskennung
  nach Anmeldung, acht Stunden absolute Laufzeit, 30 Minuten Inaktivitätslimit.
  Benutzerstatus, Rollen und private Freigabeliste werden pro Aufruf neu geprüft.
- CSRF und feste Origin für alle schreibenden Aufrufe, Prepared Statements,
  Anmelderatenbegrenzung und atomarer Schutz gegen wiederverwendete Einmalcodes.
- Admin-Leseansicht sowie getrennte Mitarbeiter- und Kartenfreigabe mit
  Datenbanktransaktion und Audit-Eintrag. Keine E-Mail aus einer Freigabe.

## Dateiaufteilung bei der späteren Installation

| Quelle | Ziel innerhalb des Hosting-Accounts |
|---|---|
| public/index.php | public_html/smartdock-test/api/index.php |
| private/app.php und private/security.php | smartdock-private-test/ außerhalb von public_html |
| private/config.example.php | Private Vorlage für smartdock-private-test/config.php |
| Tests und Dokumentation | Niemals hochladen |

Bei Hetzner ist public_html ein symbolischer Link. Der API-Unterordner erhält
deshalb per Apache SetEnv SMARTDOCK_PRIVATE_DIR den absoluten privaten Hosting-Pfad.
Der PHP-Einstieg verweigert ausdrücklich einen Konfigurationsordner innerhalb des
öffentlichen Document Root.

Die private Konfiguration muss vollständig sein. Fehlt sie, liefert der öffentliche
Einstieg nur 503. Laufzeit verwendet ausschließlich den R/W-Benutzer der Testdatenbank.
Die Datenbankverbindung verlangt die offizielle Hetzner-CA und Serverprüfung; nur
der isolierte lokale CI-Server darf ohne TLS verwendet werden.
Die Freigabeliste, Datenbankpasswörter, TOTP-Schlüssel, Sitzungen und Rate-Limit-Dateien
gehören niemals ins Repository oder einen öffentlichen Build. Private Ordner erhalten
0700, Konfiguration und Zustandsdateien 0600. Die Konfiguration erlaubt ausschließlich
die Test-Origin, keine Produktionsdomain.

`state` hält Rate-Limit-Zähler und zuletzt verwendete TOTP-Zeitschritte; `sessions`
hält PHP-Sitzungsdaten. Zusätzlich bindet sd_user_sessions jede Anmeldung an Hashes
von Sitzungsnachweis und CSRF, Ablauf und Widerruf. TOTP-Schlüssel werden noch nicht
von einer Oberfläche eingerichtet. Keine geheime Beispielkonfiguration übernehmen.

## Noch offen vor produktivem Betrieb

- Private Installation und MariaDB-Verbindung auf Hetzner sind geprüft; vollständige
  Zugriffstests mit tatsächlich verwendeter PHP-Version durchführen.
- Anmeldeseite und erste Listen-/Freigabeansicht der Admin-Testoberfläche prüfen;
  Händleroberfläche, Firmenpflege und Inhalteverwaltung ergänzen.
- Weitere Kontoanlagen und Wiederherstellung ergänzen. Die einmalige Einrichtung
  des ersten Administratorkontos ist implementiert; auf Hetzner steht die
  persönliche Aktivierung noch aus.
- Händleranfragen und Einladungen, Passwortvergabe/-zurücksetzen, E-Mail-Verifikation,
  Freigabelinks mit maximal sieben Tagen und einmaliger Verwendung.
- SMTP/Outbox-Erweiterung, Newsletter-Double-Opt-in, Abmeldung und Spam-Schutz.
- Geschützte Downloads, Datensicherung/Rücksicherung, Monitoring, Aufbewahrung,
  Migration der Bestandsdaten, Endabnahme und Produktionsfreigabe.

## Prüfungen

Native PHP-8.4-Installation: `node tools/check-backend.mjs` prüft Syntax und 52
Sicherheitsfälle. Optional akzeptiert das Werkzeug PHP_BINARY oder PHP_WASM_CLI.
Die lokale WASM-Prüfung ersetzt weder Apache noch MariaDB.

Der separate GitHub-Workflow erstellt eine kurzlebige MariaDB 11.8 und testet
`node tools/backend.integration.mjs` unter PHP 8.4 mit ausschließlich fiktiven Daten.
Der CI-Router simuliert HTTPS nur auf einem lokalen Testserver. Router, Fixtures,
CI-Zugangswerte und Tests dürfen niemals auf Hetzner installiert werden. Es werden
keine Live-Datenbanken angesprochen und keine Nachrichten versendet.

Die erste Administration wird ausschließlich über einen befristeten privaten
Einrichtungslink angelegt. Zieladresse und Token-Hash stehen außerhalb des
Webroots; Passwort und Authenticator-Code trägt die berechtigte Person selbst
ein. Sobald eine Mitarbeiterrolle existiert, ist dieser Weg geschlossen. Der
Authenticator-Schlüssel wird mit AES-256-GCM verschlüsselt außerhalb des
Webroots gespeichert und an die jeweilige Identität gebunden. Der private
Hauptschlüssel darf beim Aktualisieren der Anwendung nicht ersetzt werden.

Nach dieser Einrichtung bleibt das Konto zunächst `invited`, ohne bestätigte
E-Mail und ohne Mitarbeiterrolle. Erst der einmalige Aktivierungslink an die
persönlich freigegebene Adresse und die anschließende bewusste Bestätigung per
POST erteilen den ersten Adminzugang. GET-Aufrufe von Mail-Scannern aktivieren
nichts. Tokens laufen nach sieben Tagen ab und werden ausschließlich gehasht in
`sd_user_tokens` gespeichert. Freigabeliste UND exakte Mitarbeiterdomain gelten
bei Einrichtung, Aktivierung, Anmeldung und jeder bestehenden Admin-Sitzung.
Ein zu einer E-Mail passender Verifikationsnachweis im Audit ist zusätzlich
erforderlich; frühere direkte Ersteinrichtungen ersetzen ihn nicht.

Der Absender `mail_from` muss ein vorhandenes Hetzner-Postfach der SMARTDOCK-Domain
sein. Ohne diese private Konfiguration bleibt die Ersteinrichtung geschlossen.
Der Versand nutzt Hetzners lokalen Mailtransfer mit festem From/Envelope-From.
Eine Annahme durch den Mailtransfer beweist keine Zustellung: Diese ist erst
nach tatsächlichem Empfang und Aktivierung geprüft. Ein Commitfehler nach
Mailannahme kann einen ungültigen Link verursachen; Wiederherstellung/erneute
Einladung und weitere Mitarbeiter-Einladungen bleiben offen. Es gibt noch
keinen allgemeinen SMTP-/Newsletter-Worker.
