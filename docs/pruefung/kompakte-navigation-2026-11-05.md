# Kompakte Navigation und mobile Reihenfolge – 5. November 2026

Ausgang: d87829f (PR 65), bereits live. Desktop: FAQ, Kontakt, Händlerbereich,
Konfigurator, Händlerfinder. Die Gruppe bleibt zentriert und verwendet gap-6:
24 px Abstand wie zwischen den Hero-Buttons. Live im DOM gemessen: Hero 24 px,
Navigation 24 px, Gruppenmittelpunkt 639,996 px bei Viewportmittelpunkt 640 px.
Buttongrößen und bestehende Klickhandler sind unverändert.

Zusatzauftrag während der Umsetzung: Mobil Produkt, Funktionen, FAQ, Kontakt,
Händlerbereich, Konfigurator, Händlerfinder. Installation entfällt nur im Menü,
bleibt weiterhin über den Footer erreichbar. Händlerbereich steht vor den beiden
Aktionsbuttons. Beide Header-Anwendungen sind geändert, Sprachrouten bleiben.

94 Tests, Root-/Pages-Build und 60 Redirectfälle bestanden. Bestehender
Regressionstest prüft Desktop-Reihenfolge, Ziele, Klickhandler in fünf Sprachen
sowie Zentrierungsklasse, Abstand und mobile Filter-/Aktionsreihenfolge.
Browser lokal und live: Desktop 1280 px und Mobil 390 px. Beide Reihenfolgen
visuell bestätigt. Vorherbeleg /private/tmp/smartdock-menu2-before.png;
Nachher /private/tmp/smartdock-menu3-live-desktop.png und
/private/tmp/smartdock-menu3-live-mobile.png.

Live und Test veröffentlicht; beide JS-Pakete und der Startseiteneinstieg werden
bytegenau mit dem geprüften Build abgeglichen. Kein Formularversand oder Kontoeingriff.
Auf PR 65 aufgebaut; bestehende offene PRs bleiben unberührt.
