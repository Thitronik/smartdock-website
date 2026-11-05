# BOOTE auf der Produktseite – 5. November 2026

Ausgang: `3b6f4e0`, inhaltlich GitHub `e3ff267` aus PR 63.
Auftrag: BOOTE oberhalb von float ergänzen, das Bild-/Textraster erhalten und die
bereitgestellte PDF beim Klick groß auf der Seite anzeigen.

## Inhalt und Darstellung

Quelle ist die vom Auftraggeber bereitgestellte „Boote Magazin.pdf“, BOOTE 11/2026,
„Anlegen ohne Steuerstand“, gedruckte Seiten 88–90. Die PDF-Datei ist unverändert
übernommen (zwei Doppelseiten einschließlich der mitgelieferten Anzeige).
SHA-256: `730cbd91d6e411a71d8a1ecbf1be79fbecd0cc4fb103ece339e72cacbc679928`.
Die erste Doppelseite ist als JPEG-Vorschau gerendert. Es werden keine Aussagen
des Artikels als eigene technische Zusagen übernommen.

Desktop-Reihenfolge:

1. Einleitung: Bild links, Text rechts.
2. BOOTE: Text links, Artikelvorschau rechts.
3. float: Bild links, Text rechts.
4. Plug & Play: Text links, Bild rechts.
5. ECU: Bild links, Text rechts.
6. TCU: Text links, Bild rechts.

Mobil bleibt der Text jeweils vor dem Bild. Beide BOOTE-Schaltflächen öffnen
einen nativen modalen Dialog mit beschriftetem Titel, Schließen, Zoom sowie
zusätzlichen Links zum Öffnen im neuen Tab und Herunterladen. Escape und Klick
auf den Hintergrund schließen den Dialog. Fokus kehrt zum auslösenden Button
zurück; beim Schließen wird das Dokument entladen und die Seitensperre aufgehoben.
Die neuen UI-Texte liegen in allen fünf Sprachen vor; Französisch verwendet
formelle Ansprache. Nichtdeutsche Fassungen kennzeichnen den deutschsprachigen Artikel.

## PDF-Betrachter

Ein bloßes PDF-Iframe blieb im Prüfbrowser leer. Der eigene Betrachter verwendet
deshalb lokal bereitgestelltes PDF.js 5.6.205 aus der vorhandenen Laufzeit,
einschließlich Apache-Lizenz und vollständigem wasm-Ordner mit dessen Lizenzen.
Die bereitgestellte PDF verwendet JPEG-2000-Bilder; deren OpenJPEG-Decoder ist
für die Bildanzeige notwendig. Bildfehler werden nicht ignoriert. Bibliothek,
Worker und PDF werden erst beim Öffnen geladen, ohne externe CDN-Verbindungen.
Die Dekodierung ist nicht von einem Browser-PDF-Plugin abhängig.

Quelle zur Render-API: https://mozilla.github.io/pdf.js/examples/.
Die eigene Integration liegt in src/product-press.js und src/product-press.css;
im übernommenen Anwendungspaket bleiben nur die Einbindung und die Rasterwechsel.

## Prüfung

- 93 Tests sowie Root-/Pages-Build und 60 Redirectfälle bestanden.
- Neuer Regressionstest wertet die tatsächliche Produktkomponente aus und prüft
  das vollständige Wechselraster. Weitere Prüfung: modaler Titel, verzögertes
  Laden, beide URL-Basen und Schließen über Escape.
- Browser lokal: sechs Rasterzeilen mit korrekter Desktop-Ausrichtung, zwei
  vollständig gerenderte PDF-Seiten, Zoom, Escape, Schließen-Schaltfläche und
  Hintergrundklick. Fokuswiederherstellung und Entladen nach Schließen geprüft.
- Mobil bei 390 px: Dialog 374 px breit, keine Seitenüberbreite, beide Seiten
  sichtbar; Zoom und zusätzliche PDF-Links stehen zur Verfügung.
- Pages-/FR-Einstieg: PDF-Ansicht mit beiden gerenderten Seiten und korrektem
  Präfix /smartdock-website geprüft.
- Die unveränderte Live-Ausgangsdatei wurde vor dem Upload abgeglichen.
- Live und Test über WebFTP veröffentlicht; BOOTE oberhalb float und das
  Wechselraster auf www.smartdock.de/produkt/ im Browser bestätigt.
- Live-Dialog: beide Doppelseiten vollständig gerendert, Status leer und
  Schließen erfolgreich. Live-Belege: /private/tmp/smartdock-boote-live-section.png
  und /private/tmp/smartdock-boote-live-viewer.png.
- Große Dateien luden in der Prüfverbindung verzögert; die PDF-Darstellung
  wurde nach vollständigem Laden visuell bestätigt.

Lokale Belege: /private/tmp/smartdock-boote-section.png,
/private/tmp/smartdock-boote-viewer.png und
/private/tmp/smartdock-boote-mobile-viewer.png.
