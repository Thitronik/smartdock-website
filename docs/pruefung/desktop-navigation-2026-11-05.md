# Desktop-Navigation – 5. November 2026

Ausgang: `3186cdc`, inhaltlich GitHub `d2e5660` (PR 62).

Die Desktop-Kopfleiste beider Anwendungen zeigt ab dem bestehenden XL-Breakpoint
Produkt und Funktionen als normale Textlinks statt der beiden CTA-Buttons.
Die vorhandenen übersetzten Linkdaten, aktive Markierung und Navigationshandler
werden wiederverwendet. Im Burger-Menü bleiben alle bisherigen Links und die
Buttons Konfigurator/Händlerfinder erhalten. Die Aktionsbuttons im Seiteninhalt
sind unverändert.

Prüfung: 91 Tests, Root-/Pages-Build und 60 Redirectfälle bestanden. Der neue
Regressionstest führt die Desktop-Linkausdrücke beider Anwendungen aus und prüft
Linktyp, Reihenfolge, Ziele und Navigation sowie den erhaltenen mobilen
Konfigurator. Browserprüfung bei 1280/1440 px und 390 px: DE-Produktlink funktioniert,
FR-Position-Hold verlinkt auf /fr/produit und /fr/fonctions, Klick auf Fonctions führt
zur Funktionsseite. Mobiles Menü enthält weiterhin Produkt, Funktionen,
Installation, FAQ, Kontakt, Konfigurator, Händlerfinder und Händlerbereich.

Belege: /private/tmp/smartdock-navigation-desktop.png und
/private/tmp/smartdock-navigation-mobile.png. Die Änderung ist auf Live und Test veröffentlicht.
Vor dem Upload entsprachen beide Live-Anwendungsdateien noch dem Ausgangsstand.
Nach dem Upload wurden beide Pakete und HTML-Einstiege auf Live und Test bytegenau
mit dem geprüften Build verglichen. Der Live-Browser zeigt Produkt/Funktionen oben
und alle bisherigen Menüpunkte in der Mobilansicht. Weitere Belege:
/private/tmp/smartdock-navigation-live.png und /private/tmp/smartdock-navigation-live-mobile.png.
