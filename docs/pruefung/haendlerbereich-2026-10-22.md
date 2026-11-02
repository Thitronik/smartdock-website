# Händlerbereich – Prüfung vom 22. Oktober 2026

Ausgangsstand: `799073bebd99f4ec275b08cf97c20f82e6c48456` (main).
Main und staging hatten beim Fetch denselben Dateibaum. Offener PR #28 betrifft
Händlerfinder und ebenfalls die beiden kompilierten Anwendungspakete; vor Merge
Überschneidung prüfen und die Menüergänzungen erhalten.

## Gewollte Änderungen

- Händlerbereich als zusätzlicher Link rechts im geöffneten Desktop-Burgermenü und
  als Link im Mobilmenü. Der Link lädt eine eigene Seite vollständig; kein
  unbekannter React-Routerpfad wird angesprungen.
- Neue responsive Login-/Zugangsanfrage-Oberfläche in fünf Sprachen.
- Öffentliche, ausdrücklich bezeichnete Gestaltungsvorschau mit sechs Kacheln.
- Keine echten Unterlagen, Benutzerkonten, privaten Daten, Mails oder Datenbankzugriffe.
- Vorhandene Angebote und Händlerfinder-Datensätze werden nicht verändert.

## Technischer Nachweis

`node tools/check.mjs` führt dieselben Prüfungen aus wie `npm run check`, weil der
gebündelte Node-24-Runtime hier kein npm beiliegt. Git wurde über das gebündelte
Fallback-Binary verwendet; `git fetch --all --prune` und `node tools/doctor.mjs`
wurden ausgeführt. Projekt hat laut package-lock keine externen npm-Abhängigkeiten.

63 Tests, beide Builds, JavaScript-Syntax, Ressourcen und 60 historische Redirectfälle
waren vor Einbindung der sechs Bilder erfolgreich. Abschließendes Ergebnis siehe PR.

Browserprüfung der lokalen Pages-Ausgabe bei 1280 × 720 und 375 × 812:
Desktop- und Mobilmenü zeigen den Link, normale Navigation öffnet die Händlerseite.
Eine Testanfrage mit allen Pflichtfeldern und ohne optionales Telefon zeigt die
Zusammenfassung einschließlich `smartdock@thitronik.de`, Newsletterstatus „Nein“
und dem vorgesehenen Freischalttext. HTML-Sonderzeichen in „Beispiel <Firma>“
erscheinen als Text. Die Angaben existieren nur im offenen DOM, nicht in Browserstorage.

## Grenzen

Echte Authentifizierung, Zugriffsschutz, sieben Tage gültige Freigabelinks,
Mailversand, Datenbanktabellen, Newsletter-Double-Opt-in und das Marketingkonto
sind Anschlussarbeit in Schritt 2. Der genannte Account wurde nicht angelegt.
Es wurden keine Zugangswerte in Dateien übernommen.

Kein vollständiger Vorher-/Nachher-Screenshotvergleich der bisherigen Website;
für die neue Händlerseite gibt es keine bestehende Ausgangsseite. Root-Build ist
technisch geprüft, Browserinteraktionen wurden zunächst am Pages-Build geprüft.
Eine veröffentlichte Staging-Nachprüfung ist erst nach einem Merge möglich.

Nach Einbindung der Bilder: alle sechs Bilder im Browser geladen und visuell geprüft.
Kachelraster bei 1280 px dreispaltig, bei 375 px einspaltig; kein horizontaler
Überlauf (scrollWidth = innerWidth = 375). Der Freischaltbutton in der Zusammenfassung
ist deaktiviert und nennt den Ablauf nach sieben Tagen.

## Gestaltung an vorhandene Unterseiten angeglichen

Nach Rückmeldung von Adrian wird die bestehende `/assets/style.css` eingebunden.
Die Händlerseite verwendet deren Farbvariablen und Schriftfamilie statt einer
eigenen grünlichen Palette. Im Browser mit Produktseite abgeglichen:
Hintergrund `rgb(24, 27, 32)`, primäre Buttons `rgb(204, 255, 0)`, Schrift Outfit.
Headerlogo ist dieselbe Datei `db32cac9a31619468d321af737c640a2.png` wie auf der
bestehenden Produktseite. Seiteneinstieg mit Breadcrumb, zentriertem Titel und
Abständen folgt der Produktseite. Formulare und Kacheln erhalten neutrale Flächen.
Sprachmenü und Burgermenü verwenden native Details-Elemente, Escape schließt sie.
DE/EN-Sprachwechsel und Menüs mobil geprüft, 375 px ohne horizontalen Überlauf.
Der neue Händlerkopf ist eine statische Umsetzung nach dem bestehenden Header;
er lädt keine zweite React-Anwendung. Startseite und vorhandene Unterseiten
wurden nicht umgestaltet.

## Gemeinsame Navigation statt statischem Nachbau

Ausgangsstand für die Korrektur: staging `bbaaeb870a90d80daf672bbef0fe31863b3f58d3`.
Der Händlerbereich verwendet jetzt die vorhandene React-Seitenhülle und damit
exakt dieselbe `gH`-Headerkomponente sowie den Footer wie die Startseite.
Die fünf Händlerpfade sind eigene Routen der Anwendung. Die Inhaltserzeugung ist
als explizite Mount-Funktion gekapselt; Händler-CSS ist vollständig auf
`.sd-dealer-portal` begrenzt. Der separate statische Header samt eigener
Menü-/Sprachlogik wurde entfernt. Sprachwechsel erhält den Händlerpfad.

Lokaler Desktopvergleich im Pages-Build: Position, Breite, Höhe sowie Vorder- und
Hintergrundfarben sämtlicher Headerbuttons und des Logos sind beim Wechsel
Händlerbereich → Startseite identisch. Wechsel erfolgt auch innerhalb derselben
Anwendung; die geladene Händler-CSS verändert die Startseite nicht.
Mobil bei 375 × 812 sind ebenfalls sämtliche Headermaße zwischen EN-Startseite
und EN-Händlerbereich identisch; genau ein Header und kein horizontaler Überlauf.
Originales Mobilmenü → Händlerbereich, Logo → Startseite und DE→EN-Sprachwechsel
innerhalb des Händlerbereichs geprüft. Root-Einstieg auf Port 4173 und Pages-Einstieg
auf Port 4174 erfolgreich. Neue Regression prüft beide Buildvarianten und alle
fünf Sprachziele. Gesamtprüfung: 64 Tests, beide Builds, 60 Redirectfälle erfolgreich.
