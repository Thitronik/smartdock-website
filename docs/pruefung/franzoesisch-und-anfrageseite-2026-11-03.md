# Französisch und Rechtsseiten – 3. November 2026

Ausgang: lokaler Commit `5821739` (GitHub-Inhalt identisch mit PR 61 vor dieser Änderung). Auftrag: französische Ansprache, Typografie, Werbeaussagen und Pflichtangaben prüfen; keine Shop- und Zahlungsbedingungen auf SMARTDOCK.

## Umsetzung

- Französisch: Siezen in Hauptseiten, FAQ, Position Hold einschließlich Metadaten und Mitarbeiter-Einladung. Geschützte Leerzeichen vor `: ; ! ?`, französische Apostrophe und Satzabschlüsse in Fließtexten. Überschriften und Feldbezeichnungen bleiben ohne erzwungenen Punkt. Spanische Fragen in Wörterbüchern und FAQ haben bereits öffnende Fragezeichen.
- Unbelegte pauschale Leistungsbehauptungen wie „höchste Sicherheitsstandards“, millimetergenaues Manövrieren, perfekte Integration, garantierte Antwort innerhalb 24 Stunden und Betrieb ohne technisches Vorwissen wurden in der französischen Fassung sachlicher formuliert. Technische Kompatibilitätslisten, Preise und Modulzuordnung bleiben erhalten. Die FAQ erläutert die Verantwortung des Skippers; keine Sicherheitsgarantie für jede Situation.
- Die sichtbaren Rechtsseiten verwenden jetzt die lesbare Quelle `content/legal-pages.json` und die gemeinsame Darstellung `src/legal-pages.js`. Alle fünf Sprachen nennen Finkenweg 9–15 und erläutern die unverbindliche Anfrage statt eines Kaufs. § 5 DDG ersetzt den veralteten TMG-Verweis im Impressum.
- AGB, Widerruf und Versand/Zahlung entfallen aus beiden öffentlichen Footern. Alte Adressen bleiben als Weiterleitung zur Datenschutzseite der jeweiligen Sprache erreichbar, stehen nicht mehr in der produktiven Sitemap und tragen `noindex,follow`. Die Alt-Komponenten und Shop-Wörterbucheinträge wurden entfernt. Veraltete Rechtsdatensätze in den alten DE/EN-Übersetzungsdateien werden nicht mehr mit ausgeliefert.
- Die geschützten Händler-Rubriken „Bestellungen“ und „Rücksendungen & Reklamationen“ sind keine öffentliche Kaufabwicklung und bleiben erhalten.
- Tatsächlich eingebundene Dienste Google Fonts, Vimeo und OpenStreetMap werden weiterhin benannt. Nicht eingebundene Shopdienste und veraltete Angaben zu Cookiebot, CloudFront, Hostinger und Brevo werden nicht als verwendete Dienste ausgegeben. Google Analytics ist im aktuellen Programm deaktiviert (`J5`/Position-Hold-Entsprechung); keine Behauptung eines aktiven Analytics-Dienstes in der neuen Erklärung.

## Quellen und Grenzen

Grundlage für Unternehmensdaten und Datenschutzstruktur sind [Thitronik-Impressum](https://www.thitronik.de/impressum/) und [Thitronik-Datenschutz](https://www.thitronik.de/datenschutz/), abgeglichen am 03.11.2026. Fremde CMS-/Agentur-/Shop-/Tracking-Angaben wurden nicht übernommen. Die Texte wurden auf die tatsächlichen SMARTDOCK-Funktionen formuliert und sind keine ungeprüfte Kopie.

- [§ 5 DDG](https://www.gesetze-im-internet.de/ddg/__5.html)
- [Französische Typografie, OECD](https://www.oecd.org/fr/publications/guide-de-style-de-l-ocde-quatrieme-edition_180b00bc-fr/full-report/component-14.html)
- [DGCCRF: irreführende Geschäftspraktiken](https://www.economie.gouv.fr/dgccrf/les-fiches-pratiques/pratiques-commerciales-trompeuses-les-cles-pour-les-reconnaitre-et-sen-premunir?language=fr)
- [Hetzner-Anbieterangaben](https://www.hetzner.com/de/legal/legal-notice/)
- DAME-Nominierung: [Bericht mit Nennung der Shortlist](https://floatmagazin.de/en/places/mets-but-make-it-excellent-dame-design-awards/); [NMEA-Verzeichnis](https://web.nmea.org/directory/results/results.aspx?affcode=Manufacturer) nennt Thitronik. Keine Ausweitung zu einer Produktzertifizierung.

Ein generelles gesetzliches Verbot jedes Superlativs wurde nicht behauptet. Maßgeblich sind irreführende/unbelegte Aussagen. Dies ist eine redaktionelle und technische Prüfung, keine abschließende Freigabe nach französischem Wettbewerbs- oder Verbraucherrecht. Händlerbund soll die endgültigen Rechtstexte und die Verbraucherstreitbeilegung prüfen. Offen sind insbesondere verbindliche Speicherfristen, dokumentierte Auftragsverarbeitungsverträge und die Rechtsgrundlage/Einwilligungssteuerung der tatsächlich eingebundenen externen Inhalte. Diese Angaben wurden nicht erfunden. Die neue Datenschutzerklärung ersetzt keine technische Einwilligungssperre.

## Validierung

- Vollständiger Node-/Build-Check mit Preis-/Modulregression. Die historischen Preisfixtures bleiben unverändert; beim Namensvergleich werden nur gerade und typografische Apostrophe gleichgesetzt.
- Neue Regressionen prüfen formelle französische Ansprache, geschützte Fragezeichen, fünf Rechtsfassungen ohne Shop-Zahlungsbedingungen, Footer und Sitemap/Indexierung der Altadressen.
- Lokaler Browser: französische Startseite, Produkt, Funktionen, Installation, FAQ, Konfigurator, Kontakt, Händlerfinder, Position Hold, Impressum, Datenschutz, Barrierefreiheit sowie alte CGV-Adresse geprüft. Dabei wurde eine weitere direkte Du-Anrede auf der Installationsseite gefunden und korrigiert.
- Desktop-/Mobilansichten der Rechtsseiten geprüft (1280 px / 390 px, kein horizontaler Überlauf). Keine Formulare abgesendet, keine Konten oder E-Mail-Empfänger verändert. Live und Test veröffentlicht. Elf relevante Dateien pro Umgebung bytegenau mit dem Build verglichen. Live im Browser: FR-Impressum, FAQ, Position Hold, Weiterleitung von /fr/cgv sowie EN-Impressum geprüft; keine alte Adresse oder Shop-Zahlungstexte in diesen Ansichten. Screenshots: /private/tmp/smartdock-french-legal-live.png und /private/tmp/smartdock-french-legal-mobile.png.
