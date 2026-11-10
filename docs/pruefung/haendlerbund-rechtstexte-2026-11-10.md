# Händlerbund-Rechtstexte – 10. November 2026

Ausgang: 1e905e8 aus PR 66. Auftraggeber stellt fünf ZIP-Pakete bereit:
1 Deutsch, 2 Englisch, 3 Französisch, 4 Niederländisch, 5 Spanisch.
Jedes Paket enthält Impressum.pdf und Datenschutzerklaerung.pdf. Alle zehn
Original-PDFs liegen unverändert unter docs/rechtstexte/2026-11-10/.
Das Manifest dokumentiert SHA-256 der PDFs sowie normalisierten Original- und
veröffentlichten Text. Die Dateien sind Belegmaterial, keine öffentlich angebotenen
Downloads; sie werden nicht in den Website-Build aufgenommen.

Die bisherigen selbst formulierten Abschnitte sind vollständig durch den
bereitgestellten Wortlaut ersetzt. Einzige freigegebene inhaltliche Abweichung:
Finkenweg 11-15 im Impressum wird auf ausdrückliche Rückfrage des Auftraggebers
zu Finkenweg 9–15 berichtigt. Die Verantwortlichen-Anschrift im Datenschutz
lautet bereits 9-15. Keine eigenständige juristische oder sprachliche Redaktion.

Auslesen mit Poppler pdftotext/pdftohtml; Originalseiten visuell geprüft.
XML-Zeilen und Schriftattribute sind in strukturierte Textblöcke übernommen;
Zeilen-/Seitenumbrüche im Fließtext entfernt, Zeichenbestand auf Vollständigkeit
abgeglichen. Hervorhebungen und Adresszeilen bleiben erhalten. Darstellung als
React-Textknoten statt importiertem HTML. Web-/E-Mail-Adressen sind anklickbar;
lange URLs umbrechen innerhalb der Inhaltsbreite. Für bereits gecachte Renderer
bleiben die bisherigen Section-Felder mit demselben neuen Wortlaut verfügbar.

96 Tests, Root-/Pages-Build und 60 Redirectfälle bestanden. Regression prüft
alle zehn PDF-Hashes, die vollständigen veröffentlichten Texte, alte Renderer-
Datenstruktur, sichere Links und Root-/Pages-Datenpfade. Bestehende Prüfungen
wurden an den gelieferten Wortlaut angepasst (z.B. kein zusätzlicher Hetzner-
Abschnitt, weil im gelieferten Dokument nicht enthalten).

Browser lokal: zehn Rechtsseiten mit Titel, Sprache, vollständigem Ende und
richtiger Anschrift. Mobil 390 px für alle fünf Datenschutzsprachen und DE-
Impressum ohne Überlauf. Pages-EN-Datenschutz samt Präfix geprüft.
Live: alle zehn Rechtsseiten nach Veröffentlichung geprüft; deutsche Anschrift,
französische Mobilansicht und Footer-Verlinkung bestätigt. Gemeinsame Rechtsseiten
bedienen auch bestehende Footer-/Formularlinks und Sprach-/Alias-Einstiege.
Keine Änderungen an Formularversand, Anmeldung, Cookie-/Trackingverhalten oder Preisen.

Veröffentlichung über WebFTP auf Live und Test. Vorher entsprach das Hauptpaket
dem Ausgangsstand. Nachher Hauptpaket, Renderer, Daten und Impressum-Einstieg
bytegleich bestätigt. Nach dem Kompatibilitätsupdate mussten die öffentlichen
Leserechte der JSON-Datei korrigiert werden; danach vollständiger HTTP-Abruf
und bytegleicher Datenabgleich auf Live und Test erfolgreich. Die französische
Rechtsseite wurde anschließend erneut vollständig im Browser geladen.

Belege: /private/tmp/smartdock-legal-before.png,
/private/tmp/smartdock-haendlerbund-live-impressum.png und
/private/tmp/smartdock-haendlerbund-live-mobile.png.
Auf PR 66 aufgebaut; vorherige offene PRs bleiben unberührt. Keine allgemeine
juristische Freigabe behauptet: umgesetzt wird das beauftragte Händlerbund-Material.
