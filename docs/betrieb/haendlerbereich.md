# Händlerbereich – Oberfläche und Anschlussplan

Stand: 22. Oktober 2026. Auftrag von Adrian: zuerst Website/GitHub-Vorschau,
Hetzner-Datenbank erst in einem zweiten Schritt verändern. Dieser Stand verändert
keine Datenbank, verschickt keine E-Mails und richtet keine echten Konten ein.

## Ziel und aktueller Umfang

GitHub ist die gemeinsame Entwicklungs- und Staging-Basis, Hetzner das spätere
Betriebsziel. Newsletterdaten sollen in der eigenen Hetzner-Datenbank liegen.
Brevo ist dafür künftig nicht der führende Datenspeicher. Das separate interne
Newsletter-Tool ist eine spätere Aufgabe, kein Bestandteil dieses PRs.

Im rechten Burgermenü führt ein Link zum Händlerbereich, mobil ebenfalls im Menü.
Die Oberfläche ist in DE/EN/FR/NL/ES vorhanden. Login und Passwort-Zurücksetzen zeigen
transparent, dass die Serveranbindung fehlt. Es gibt keine Demo-Anmeldung, keine
Passwortspeicherung und keine veröffentlichten geschützten Unterlagen.

Die Zugangsanfrage verlangt Vorname, Nachname, Unternehmen, Straße, Hausnummer,
PLZ, Stadt, Land und E-Mail. Telefon ist optional. Vorschau mit erfundenen Testdaten:
Die Zusammenfassung zeigt alle eingegebenen Angaben, Newsletterwunsch und den
geplanten Empfänger smartdock@thitronik.de. Sie ist keine echte Versandbestätigung.
Die Texte, insbesondere Datenschutz und Einwilligung, brauchen vor Echtbetrieb
fachliche Freigabe. Die Newsletterauswahl ist optional und standardmäßig aus.

## Schritt 2: Datenmodell vorbereiten und separat freigeben

Keine SQL-Migration liegt in diesem PR. Die vorhandene Erstmigration nicht erneut
ausführen. Tabellen und Verzeichnisse müssen vor Änderungen direkt geprüft werden.

- `sd_dealer`: Händlerstammdaten mit den obigen Formularfeldern, eindeutiger
  normalisierter E-Mail und Status `pending`, `approved`, `rejected`, `suspended`.
  Freigabezeitpunkt und freigebende Person protokollieren. Die öffentliche
  Händlerfinder-Liste bleibt ein gesonderter Datenbestand.
- `sd_newletter_dealer`: vom Auftrag vorgegebener Tabellenname (Schreibweise ohne
  „s“ in „newsletter“ vor Migration bestätigen). Verknüpfung zum Händler, separater
  Newsletterstatus, Einwilligungstext und -version, Bestätigungs-/Abmeldezeitpunkt.
  Ein Händlerdatensatz bedeutet keine Newsletterfreigabe. Bei nicht gewählter
  Newsletteroption keinen aktiven Abonnenten anlegen.
- Zugangsdaten, Sessions, Passwort-Setzen/Zurücksetzen und Freigabevorgänge brauchen
  eigene sichere Strukturen. Passwort-Hashes statt Klartext; keine Passwörter in
  Benachrichtigungen. Geschäftsführung mit benannten internen Konten und Rollen.
- Bestehende Newsletter-/Token-/Outboxtabellen auf Wiederverwendung prüfen;
  die aktuelle Outbox kennt noch keine Händleranfrage als Nachrichtentyp.

## Freischaltung und E-Mail

1. Server validiert und normalisiert die Anfrage, prüft Bot-Schutz und begrenzt
   Anfragen und Wiederholungen. Die Browser-Botfalle ist nur eine Ergänzung.
2. E-Mail-Besitz bestätigen; Anfrage, Status und Benachrichtigungsauftrag konsistent
   speichern. Newsletterbestätigung separat führen. Noch kein geschützter Zugriff.
3. Benachrichtigung an **smartdock@thitronik.de**: alle Formularangaben,
   Anfragekennung, Zeitpunkt und Newsletterwunsch. Formulardaten HTML-escapen;
   Nutzereingaben nicht ungeprüft in Mailheader übernehmen.
4. Buttontext: **„Händler für den Händlerbereich freischalten!“**. Link öffnet einen
   geschützten Prüfbereich für die Geschäftsführung. Ein GET, Mail-Vorschau oder
   Linkscanner darf niemals freischalten. Tatsächliche Freigabe nur durch eine
   berechtigte Sitzung und geschützte POST-Aktion; einmalige Tokens mit exakt 7 Tagen Gültigkeit ab Ausstellung. Abgelaufene Links erlauben keine Freigabe; ein erneuter Versand erzeugt einen neuen Token und entwertet den alten. Ablauf serverseitig prüfen.
5. Nach Freigabe erhält der Händler eine befristete Einladung zum Passwortsetzen.
   Erst freigegebene Konten mit gültiger Sitzung können geschützte Seiten öffnen.
   Sperrung entzieht bestehende Zugriffsrechte; Status serverseitig nachprüfen.

Jede geschützte Seite und jeder Download benötigt eine serverseitige Rechteprüfung.
Kein privater Inhalt darf in GitHub Pages, öffentliche JSON-Dateien oder Browserpakete
gepackt werden. Sessions: sichere HttpOnly-Cookies, Ablauf, Abmeldung und CSRF-Schutz.
Anmeldung und Passwort-Reset mit neutralen Antworten gegen Kontoermittlung absichern.

## Späteres Newsletter-Tool

Separate interne Oberfläche mit E-Mail/Passwort und Rollen, Empfängergruppen
(Händler/Kunden), Vorlagen, Text- und HTML-Version, Vorschau und Testversand.
Versand erst nach bewusster Freigabe eines konkreten Inhalts und Empfängerkreises.
Abmeldung, Sperrlisten, Rückläufer, Warteschlange und Wiederholungen berücksichtigen.
Einwilligungen und Händlerzugang bleiben unabhängig. SMTP/Versanddienst sowie
SPF/DKIM/DMARC und Versandlimits anhand der tatsächlichen Hetzner-Umgebung prüfen.
Ein eigener Mailserver ist damit nicht beschlossen.

## Abnahme vor Echtbetrieb

- Keine Speicherung und kein Versand in der öffentlichen Vorschau.
- Direkter Zugriff auf Unterseiten und Downloads ohne Freigabe wird serverseitig verweigert.
- Anfrage, Ablehnung, Freigabe, Passwortsetzen, Sperrung und Abmeldung mit Testkonten prüfen.
- Linkscanner und wiederholte Klicks schalten keinen Händler unbeabsichtigt frei.
- Double-Opt-in, Bot-Prüfung und Rate-Limits vor Bestätigungsmails prüfen.
- Datenbankmigration, echtes Mailing und Hetzner-Veröffentlichung bleiben Schritt 2.

## Ergänzungen von Adrian

Das vorgesehene Konto ist `marketing@thitronik.de`. Die Rolle wird vor der Anlage abgestimmt. Es wurde noch nicht
angelegt, da noch kein Authentifizierungsbackend angeschlossen ist und Datenbankänderungen
auf Schritt 2 verschoben sind. Kein Passwort ist Bestandteil des Repositorys.

Die öffentliche Gestaltungsvorschau enthält sechs Kacheln mit eigens generierten
SMARTDOCK-Bildern: News/Termine, Werkstattunterlagen, Einbaurichtzeiten, Schulungen/Videos,
Beratung/Unterstützung, Bestellen/Herunterladen. Inhalte liefert das Team später.
Kacheln enthalten noch keine Links zu privaten Dateien und keine erfundenen Unterlagen.

## Aktualisierte Architektur vom 27. Oktober 2026

Die ursprüngliche Annahme eines gemeinsamen Firmen-/Kontostatus ist durch Adrians
neue Vorgabe ersetzt: eine Firma kann mehrere persönliche Mitarbeiterkonten haben.
Kartenfreigabe und Mitarbeiterfreigaben sind unabhängig. Firmenkontakt und Loginadresse
müssen nicht identisch sein. Maßgeblich ist der neue
[Verwaltungs- und Zugangsplan](verwaltung-und-zugaenge.md).
Die öffentliche Händlerliste und Mitarbeiterzugänge verwenden später denselben
Firmenstamm mit getrennten Freigaben. Das alte Konzept einer separaten zweiten
Newsletterkontaktliste ist durch eine Zuordnung zu den bestehenden Abonnements ersetzt.
