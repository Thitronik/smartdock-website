# Firmen, Mitarbeiter und interne Verwaltung

Stand: 27. Oktober 2026. Von Adrian bestätigtes Ziel: eine Händlerfirma kann mehrere
Mitarbeiterkonten haben. Die öffentliche Händlerfreigabe und Mitarbeiterfreigaben
sind unabhängig. Interne Teamkonten erhalten rollenabhängigen Verwaltungszugriff.

## Aktuell umgesetzt: überprüfbare Grundlage

`/admin/` zeigt öffentlich ausschließlich einen geschlossenen Einstieg. Es gibt
keinen öffentlichen Link und noch keine echte Anmeldung. Die Gestaltungsvorschau
mit erfundenen Beispieldaten ist nur auf localhost verfügbar. Sie ruft keine API
auf, speichert keine Daten dauerhaft und versendet keine E-Mails.

Die native Seitenhülle liefert denselben Header/Footer wie die Startseite.
Der deutschsprachige interne Entwurf enthält:

- Übersicht über Firmen, Mitarbeiter, Kartenfreigaben und offene Anfragen.
- Firmen suchen, bearbeiten, neu anlegen und Kartenanzeige separat umschalten.
- Mitarbeiter zuordnen, einzeln freigeben, ablehnen oder sperren. Firmenwechsel
  verlangt erneute Freigabe; andere Mitarbeiter werden nicht mitgeändert.
- Einladungen/Passwortlinks als lokale Aktion vorbereiten, ohne echte Tokens oder Versand.
- Titel/Beschreibungen der sechs Händlerkacheln als lokale Entwürfe bearbeiten.
- Sichtbares Rollenkonzept und Einordnung des späteren Newsletter-Tools.

Öffentliche Website-Inhalte werden durch diese Vorschau nicht verändert. Uploads,
geschützte Dateien, veröffentlichte Inhaltsversionen und das eigentliche CMS-Backend
sind noch nicht implementiert. Ein Team-/Marketingkonto wurde nicht angelegt.

## Datenmodell: Entwurf, noch nicht installiert

[Migration 002](../../database/migrations/002_dealers_and_users.sql) ergänzt acht
Tabellen und verändert die fünf installierten Tabellen nicht. Erst auf einer
getrennten MariaDB-Testdatenbank nach Backup und Schemaabgleich prüfen. Keine
Ausführung auf Hetzner in diesem Arbeitsschritt. SQL ist noch nicht gegen eine
laufende MariaDB ausgeführt; Node-Tests sind kein SQL-Installationsnachweis.

| Tabelle | Zuständigkeit |
| --- | --- |
| sd_dealer | Firmenstammdaten, öffentliche Kontakte, Koordinaten, separate Kartenfreigabe |
| sd_users | Persönliche Identität, Login-E-Mail, Passwort-Hash, E-Mail-Bestätigung, Kontostatus |
| sd_dealer_memberships | Benutzer–Firma-Zuordnung mit eigener Prüfung und Freigabe |
| sd_staff_roles | Interne Rollen admin, approver und editor; keine Selbstvergabe |
| sd_user_tokens | Nur Hashes befristeter, einmaliger Bestätigungs-/Einladungs-/Reset-/Freigabelinks |
| sd_user_sessions | Serverseitige Sessions, Ablauf/Widerruf und vorgesehener MFA-Nachweis |
| sd_dealer_newsletter_subscriptions | Firmenzuordnung zu bestehenden Newsletter-Abonnements |
| sd_admin_audit_events | Verlauf interner Änderungen, ohne Passwörter oder Tokenwerte |

Die frühere Idee `sd_newletter_dealer` wird damit durch eine Zuordnung zu den
bestehenden Newslettertabellen ersetzt. Newsletterstatus und Einwilligung werden
nicht parallel in einer zweiten Kontaktliste geführt. Eine Firma oder
Mitarbeiterfreigabe erzeugt kein Abonnement.

Eine öffentliche Firma benötigt keine Loginadresse. Persönliche Konten müssen
nicht dieselbe Adresse wie der öffentliche Firmenkontakt verwenden. Mehrere
Firmen dürfen dieselbe öffentliche Kontaktadresse haben. Die persönliche,
normalisierte Loginadresse ist dagegen eindeutig.

## Freigabe- und Zugriffsregeln für das spätere Backend

- Händlerfinder gibt ausschließlich öffentliche Felder aktiver Firmen mit
  `finder_status=approved` aus; Kartendarstellung erfordert gültige Koordinaten.
- Mitarbeiterzugang erfordert aktives, E-Mail-bestätigtes Benutzerkonto mit
  gesetztem Passwort, aktive Firma und freigegebene Mitgliedschaft. Die
  Kartenfreigabe der Firma ist dafür keine Bedingung.
- Firmenzuordnung ist keine automatische Berechtigung aufgrund einer Maildomain.
  Firmenwechsel im CMS setzt die betroffene Mitgliedschaft zurück auf Prüfung.
- Neue Zugangsanfragen von Mitarbeitern werden einer vorhandenen Firma zugeordnet
  oder zusammen mit einer neuen Firmenanfrage geprüft. Firmen nicht ungeprüft
  anhand gleicher Namen zusammenführen. Öffentliche Anmeldedaten sind kein
  Rollen-/Statusauftrag und können keine internen Rollen erteilen.
- Geschäftsführung bekommt eine Benachrichtigung an smartdock@thitronik.de mit
  Person, Firma und den eingereichten Angaben. Der Freigabelink gilt exakt sieben
  Tage. GET/Linkscanner verändern keine Freigabe. Erst eine berechtigte interne
  Sitzung plus CSRF-geschützte POST-Aktion bestätigt die Zuordnung.
- Einladung zum erstmaligen Passwortsetzen ersetzt bei neuen Konten keinen
  Passwort-Reset eines bestehenden Kontos. Zweck, Zielkonto, Mitgliedschaft,
  Ablauf, Widerruf und einmalige Verwendung werden serverseitig geprüft.
  Ein erneuter Link entwertet den vorherigen Link desselben Zwecks.
- Admin darf Rollen verwalten, Approver Firmen-/Mitarbeiterfreigaben, Editor
  freigegebene Inhaltsbereiche pflegen. Rolle bei jeder Serveraktion prüfen;
  Ausblenden von Buttons allein schützt keine Daten. Letzten Administrator nicht
  versehentlich sperren; erste Adminanlage über einen getrennten sicheren Bootstrap.
- Sichere Passwort-Hashes, HttpOnly/Secure/SameSite-Sessioncookies, Sessionrotation,
  Ablauf und Widerruf, neutrale Login-/Resetantworten, Rate-Limits und Bot-Schutz
  einrichten. Sperrung wirkt auf bestehende Sitzungen. MFA-Einrichtung für interne
  Konten inklusive Wiederherstellung ist noch als eigene Serverfunktion zu bauen.
- Private Unterlagen niemals ins öffentliche Repository/Pages-Verzeichnis laden.
  Inhalte- und Dateiverwaltung benötigt nichtöffentlichen Speicher und serverseitige
  Prüfung bei jedem Abruf. Keine ausführbaren Uploads; Vorschau-/Freigabeversionen
  und Wiederherstellung vor produktiver Redaktion ergänzen.

Die bestehende sd_mail_outbox kennt bislang keine Benutzer-/Freigabemails. Eine
separate Erweiterung und ein getesteter Versandworker sind vor echtem Versand
nötig. Migration 002 implementiert noch keinen Mailversand und keine MFA-Geheimnisse.

## Die neun bestehenden Händler

Der lokale Hostinger-Export wird weder ins öffentliche Repo hochgeladen noch in
Demo-Daten umgewandelt. [Importvorbereitung](../../tools/prepare-dealer-import.mjs)
erzeugt ausschließlich einen privaten Dry-Run außerhalb dieses Repositorys:

```sh
node tools/prepare-dealer-import.mjs /privater/pfad/export.json /privater/pfad/importvorschau.json
```

Das Ergebnis bewahrt Quellkennung, vollständige internationale Anschrift,
Sprachfassungen, öffentliche Kontaktdaten und Koordinaten. Ländercodes werden
vereinheitlicht. Keine Benutzer, Mitgliedschaften, Rollen, Newsletter oder
Einladungen werden daraus erzeugt. Kartenfreigabe der neun Firmen muss Adrian noch
bestätigen; bis zur bewusst freigegebenen Datenbankumschaltung bleibt der vorhandene
öffentliche JSON-Händlerbestand unverändert. Keine Firmen dadurch verschwinden lassen.

Ein späterer Import muss Quelle und bestehende Quellkennungen transaktional abgleichen,
einen Änderungsbericht erzeugen und bei ungeklärten Konflikten stoppen. Der Dry-Run
ist noch kein schreibender oder wiederholbarer Datenbankimport.

## Nächster Arbeitsschritt auf Hetzner

1. Separate geschützte Test-Subdomain und Testdatenbank verifizieren.
2. Migration 002 dort ausführen und SHOW CREATE TABLE/Fremdschlüssel prüfen.
3. PHP-Backend mit obigen Zugriffsregeln, persönlichem Admin-Bootstrap und MFA bauen.
4. Anfragen, Freigaben, Einladungen, Reset und geschützte Downloads Ende-zu-Ende testen.
5. Erst danach bestehende Händler bewusst importieren und die Oberfläche auf echte
   APIs umstellen. Produktionsumschaltung und echte Einladungsmails separat freigeben.

## Verbindliche interne Zugangsregel

Der Einstieg ist ausschließlich direkt über /admin erreichbar, ohne Links in
öffentlicher Navigation, Footer oder Sitemap. Die GitHub-Pages-Ansicht ist geschlossen;
die fiktive Gestaltungsvorschau ist nur lokal verfügbar. Das ist noch keine
Authentifizierung: Auf Hetzner müssen Seite und sämtliche Admin-API-Endpunkte
serverseitig geschützt werden, bevor echte Daten eingebunden werden.

Nur explizit freigegebene Teamkonten dürfen eine interne Rolle erhalten. Eine
Adresse mit der Domain thitronik.de allein reicht nicht. Die vier initial freigegebenen Adressen werden ausschließlich privat hinterlegt.

Die Liste liegt später in nichtöffentlich zugänglicher Konfiguration auf Hetzner.
Zugriff erfordert zusätzlich bestätigte E-Mail, aktives persönliches Konto,
interne Rolle und gültige Sitzung mit Zwei-Faktor-Prüfung. Keine Selbstregistrierung
für interne Rollen; Händlerfreigaben berechtigen niemals zum Adminzugang.
Passwortzurücksetzen vergibt oder erweitert keine Rolle. Die vier Konten sind
noch nicht eingerichtet. Unberechtigte API-Anfragen müssen 401/403 zurückgeben.
