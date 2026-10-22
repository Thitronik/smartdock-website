# Orientierung im Repository

## Eine Ablage pro Zweck

| Gesucht | Quelle |
|---|---|
| Ziele, Team, verbindlicher Umfang | [Projektübersicht](projektuebersicht.md) |
| Verbindliche Arbeitsregeln | [AGENTS.md](../AGENTS.md); CLAUDE.md verweist darauf |
| Arbeitsbeginn, Befehle, PR-Ablauf | [CONTRIBUTING.md](../CONTRIBUTING.md) |
| Erster Arbeitstag, GitHub-Begriffe und parallele Agenten | [Teameinstieg](arbeitsweise/teameinstieg.md) |
| Gute Aufträge für Menschen und KI | [AI First](arbeitsweise/ai-first.md) |
| Offene Arbeit und Bearbeitungsstand | [GitHub Issues](https://github.com/Thitronik/smartdock-website/issues) |
| Architekturfragen und dauerhafte Entscheidungen | [Entscheidungsübersicht](entscheidungen/README.md) |
| Build, Herkunft und schrittweiser Umbau | [Migrationsplan](migration/aufbau.md) |
| Veröffentlichung und datierte Nachweise | [Vorschau](betrieb/vorschau.md) |
| Informationen und Zugänge für Hetzner/Brevo | [Betrieb vorbereiten](betrieb/zugaenge-und-start.md); aktueller Stand in [#20](https://github.com/Thitronik/smartdock-website/issues/20) |
| Ursprünglicher Export und geprüfte Einstiege | [Bestandsaufnahme](pruefung/bestandsaufnahme-2026-10-21.md) mit CSV-Belegen |
| Aktueller Vergleich mit smartdock.de, behobene Abweichungen und Prüfgrenzen | [Live-Abgleich vom 22.10.2026](pruefung/live-abgleich-2026-10-22.md) |
| Beobachtete Preisfälle und offene Prüfungen | [Konfigurator-Prüftabelle](pruefung/konfigurator-prueftabelle.md) |
| PDF-Beschriftung, Downloads und Regression | [PDF-Prüfung vom 22.10.2026](pruefung/pdf-zwischensumme-2026-10-22.md) |
| PDF-Seitenumbrüche und Motor-/Hersteller-/Länderwechsel | [Ergänzende Prüfung vom 22.10.2026](pruefung/pdf-seitenumbruch-und-wechsel-2026-10-22.md) |
| Händlerfinder, Karte, Standortfälle und Einbaupartner-Auswahl | [Händlerfinder-Prüfung vom 22.10.2026](pruefung/haendlerfinder-2026-10-22.md) |

Historische CSV-Dateien bleiben datiert und unverändert. Aktuelle Arbeit wird in
Issues gepflegt, statt dieselbe Aufgabenliste in mehreren Dokumenten zu führen.

## Wo wird etwas geändert?

| Anliegen | Zuerst lesen / bearbeiten | Beachten |
|---|---|---|
| FAQ | [content/faqs.json](../content/faqs.json) | Fünf Sprachfelder, IDs und technische Aussagen erhalten |
| Händler / Anleitungen | [content/dealers.json](../content/dealers.json), [content/anleitungen.json](../content/anleitungen.json) | Veröffentlichte Kontaktdaten bzw. PDF-Ziele prüfen |
| Händlerkarte / Standort / Suche | [src/dealer-finder.js](../src/dealer-finder.js) | Browser-Gegenprobe und Regression für beide Kartenansichten |
| Übersetzungen | [content/](../content/) und [Sprachbefund #9](https://github.com/Thitronik/smartdock-website/issues/9) | Viele Texte liegen noch in den Anwendungspaketen; JSON ist keine vollständige Textquelle |
| HTML / URL-Einstiege | [src/page.html](../src/page.html), [src/routes.json](../src/routes.json), [src/apps.json](../src/apps.json) | React-Innenseiten liegen noch im Bündel; ein neuer HTML-Einstieg allein erzeugt keine neue Seite |
| Vorschau-Formulare | [src/preview.js](../src/preview.js) | Lokale Vorschau, kein produktiver Versand |
| Modulregeln / Preise / PDF | [src/module-allocation.js](../src/module-allocation.js), [src/pdf-pagination.js](../src/pdf-pagination.js), [Prüftabelle](pruefung/konfigurator-prueftabelle.md) | Weitere Regeln liegen im Bündel; fachliche Preisquelle bleibt offen |
| Bilder und PDFs | [public/](../public/) | Originalmedien erhalten; URLs und Herkunft berücksichtigen |
| Übernommene React-/CSS-Pakete | [vendor/website/](../vendor/website/) | Noch kompiliert; gezielte Änderungen mit Regression, schrittweiser Ersatz |
| Build / Tests / Vorschau-Server | [tools/](../tools/), [package.json](../package.json) | Root und Pages prüfen; nur erlaubte Website-Quellen veröffentlichen |
| Automatische Prüfungen / Deploy | [.github/workflows/](../.github/workflows/) | Staging veröffentlicht die Vorschau; main keine Produktion |
| Datenbankvorbereitung | [database/README.md](../database/README.md) | Nicht aktiv angebunden; keine automatische Migration |

`dist/`, `_site/` und `node_modules/` werden erzeugt. Sie sind keine Bearbeitungsorte.
Das öffentliche Repository enthält keine privaten Downloads, Kundendaten oder Zugangswerte.
