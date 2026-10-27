# Serverseitige Anmeldung – Grundlage für die Hetzner-Testumgebung

**Noch nicht auf Hetzner installiert oder an die Oberfläche angeschlossen.**
Die Website auf Hetzner ist weiterhin die statische Vorschau hinter Verzeichnisschutz.
Diese Backend-Grundlage wird separat geprüft; sie aktiviert keine echten Konten.

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

Die private Konfiguration muss vollständig sein. Fehlt sie, liefert der öffentliche
Einstieg nur 503. Laufzeit verwendet ausschließlich den R/W-Benutzer der Testdatenbank.
Die Freigabeliste, Datenbankpasswörter, TOTP-Schlüssel, Sitzungen und Rate-Limit-Dateien
gehören niemals ins Repository oder einen öffentlichen Build. Private Ordner erhalten
0700, Konfiguration und Zustandsdateien 0600. Die Konfiguration erlaubt ausschließlich
die Test-Origin, keine Produktionsdomain.

`state` hält Rate-Limit-Zähler und zuletzt verwendete TOTP-Zeitschritte; `sessions`
hält PHP-Sitzungsdaten. Zusätzlich bindet sd_user_sessions jede Anmeldung an Hashes
von Sitzungsnachweis und CSRF, Ablauf und Widerruf. TOTP-Schlüssel werden noch nicht
von einer Oberfläche eingerichtet. Keine geheime Beispielkonfiguration übernehmen.

## Noch offen vor produktivem Betrieb

- Private Installation und MariaDB-Verbindung auf Hetzner prüfen; vollständige
  Zugriffstests mit tatsächlich verwendeter PHP-Version durchführen.
- Admin-/Händleroberfläche anschließen; Firmenpflege und Inhalteverwaltung ergänzen.
- Sichere Kontoanlage, persönliche Zwei-Faktor-Einrichtung und Wiederherstellung.
- Händleranfragen und Einladungen, Passwortvergabe/-zurücksetzen, E-Mail-Verifikation,
  Freigabelinks mit maximal sieben Tagen und einmaliger Verwendung.
- SMTP/Outbox-Erweiterung, Newsletter-Double-Opt-in, Abmeldung und Spam-Schutz.
- Geschützte Downloads, Datensicherung/Rücksicherung, Monitoring, Aufbewahrung,
  Migration der Bestandsdaten, Endabnahme und Produktionsfreigabe.

## Prüfungen

Native PHP-8.4-Installation: `node tools/check-backend.mjs` prüft Syntax und 41
Sicherheitsfälle. Optional akzeptiert das Werkzeug PHP_BINARY oder PHP_WASM_CLI.
Die lokale WASM-Prüfung ersetzt weder Apache noch MariaDB.

Der separate GitHub-Workflow erstellt eine kurzlebige MariaDB 11.8 und testet
`node tools/backend.integration.mjs` unter PHP 8.4 mit ausschließlich fiktiven Daten.
Der CI-Router simuliert HTTPS nur auf einem lokalen Testserver. Router, Fixtures,
CI-Zugangswerte und Tests dürfen niemals auf Hetzner installiert werden. Es werden
keine Live-Datenbanken angesprochen und keine Nachrichten versendet.
