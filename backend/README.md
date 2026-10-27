# Serverseitige Anmeldung – Grundlage für die Hetzner-Testumgebung

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

Native PHP-8.4-Installation: `node tools/check-backend.mjs` prüft Syntax und 47
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
