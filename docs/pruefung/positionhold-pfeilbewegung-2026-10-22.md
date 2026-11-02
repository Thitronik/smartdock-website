# Dezente Bewegung der Position-Hold-Pfeile

Ausgangsstand: staging `9dc956aff90264a3961bb414a27efdcaa6f7dccb`.
Vier Pfeile bewegen sich synchron maximal 5 px Richtung Mittelpunkt und zurück.
Ein Zyklus dauert 5 Sekunden, mit ease-in-out. Kreis, Text, Größe und vorhandene
SVG-Rotation/Zentrierung bleiben erhalten; dafür wird die zusätzliche CSS-Eigenschaft
`translate` statt einer Überschreibung von `transform` animiert.

Die lesbare Ergänzung in src/positionhold-motion.css wird beim Build an das bestehende
Position-Hold-Stylesheet angehängt. Keine Änderungen an übernommenen Bundles.
Die Auswahl gilt für die vier SVGs der vorhandenen, sprachunabhängig beschrifteten
Pfeilgrafik. Bei prefers-reduced-motion: reduce bleibt die Grafik unbewegt.

65 bestehende Tests, beide Builds und 60 Redirectfälle erfolgreich. Im Browser
Root-/Pages-Einstieg sowie Desktop und 375 px geprüft: genau vier animierte SVGs,
5 s Zyklus; Vorzeichen der gemessenen Verschiebung zeigen oben/unten/links/rechts
jeweils zur Mitte. Werte verändern sich über die Zeit, Rotation bleibt erhalten.
