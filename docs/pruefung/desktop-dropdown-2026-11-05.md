# Desktop-Dropdown – 5. November 2026

Ausgang: a44cc9c (PR 64), bereits live. Auftrag: Konfigurator/Händlerfinder im
Desktop-Dropdown statt Produkt/Funktionen/Installation; oben Produkt/Funktionen
behalten. Die mobile Navigation bleibt vollständig.

Beide übernommenen Header (Hauptanwendung und Position Hold) zeigen nun FAQ,
Kontakt, Konfigurator, Händlerfinder und Händlerbereich. Konfigurator ist als
gefüllter grüner Button, Händlerfinder als grüner Outline-Button gestaltet.
Vorhandene übersetzte Routen und Klickhandler schließen das Menü beim Wechsel.
Nur der Desktop-Dropdown-Ausdruck ist geändert; keine Produkt-/Preis-/Kontologik.

94 Tests, Root-/Pages-Build und 60 Redirectfälle bestanden. Neuer Regressionstest
wertet beide tatsächlichen Desktop-Ausdrücke aus: Reihenfolge, Linkziele und
Klickhandler in fünf Sprachen, mobile Liste vorhanden. Browser: Desktop 1280 px,
Klick Konfigurator DE, Position Hold FR mit Klick Revendeurs, Mobil 390 px mit
vollständigem geöffnetem Menü geprüft. Keine Formulare abgesendet.

Live und Test veröffentlicht. Beide JS-Pakete und der Produkteinstieg wurden
bytegenau mit dem Build abgeglichen. Live-Dropdown visuell bestätigt.
Belege: /private/tmp/smartdock-menu-before.png, /private/tmp/smartdock-menu-desktop.png,
/private/tmp/smartdock-menu-mobile.png, /private/tmp/smartdock-menu-live.png.
Die bestehenden PRs 61–64 bleiben offen; dieser Stand baut auf PR 64 auf.
