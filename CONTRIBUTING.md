# Gemeinsam an SMARTDOCK arbeiten

Die verbindlichen Regeln für Menschen, Codex und Claude Code stehen in
[AGENTS.md](AGENTS.md). Die [AI-First-Anleitung](docs/arbeitsweise/ai-first.md)
zeigt, wie aus einer Idee ein prüfbarer Auftrag wird.
Wer neu einsteigt, beginnt mit dem [Teameinstieg](docs/arbeitsweise/teameinstieg.md).

## Vom Auftrag zur Änderung

1. Vorhandene [Issues](https://github.com/Thitronik/smartdock-website/issues) und
   [offene PRs](https://github.com/Thitronik/smartdock-website/pulls) prüfen.
   Ein vorhandenes Issue ergänzen, statt dieselbe Aufgabe erneut anzulegen.
2. `git fetch --all --prune`, dann `npm run doctor`. Fremde Änderungen erhalten.
   Falls main und staging inhaltlich auseinanderliegen, erst die betroffenen
   Commits und PRs ansehen. Die größere Commitzahl allein bestimmt keine Basis.
3. Einen eigenen Branch `codex/…` oder `claude/…` vom passenden aktuellen Stand
   erstellen. Bei gleichem Dateibaum ist `origin/main` die gemeinsame Basis.
4. Ziel und Fertig-Kriterien im Issue festhalten. Quellen anhand der
   [Repository-Übersicht](docs/README.md) auswählen und eine kleine Änderung umsetzen.
5. `npm run check` ausführen und die betroffenen Abläufe prüfen. Der PR nennt
   Ergebnis, Belege und verbleibende Einschränkungen.
6. PR nach `staging` vorlegen. Der Merge veröffentlicht die Vorschau. Nach deren
   Prüfung wird der konkrete Stand über einen PR von `staging` nach `main` übernommen.
   Merge-Verantwortung und Produktionsfreigabe sind in AGENTS.md geregelt.

## Befehle

Voraussetzungen: Git und Node 24. Aktuell benötigt das Projekt keine zusätzlichen
npm-Pakete; `npm ci` prüft trotzdem den versionierten Paketstand.

| Befehl | Zweck |
|---|---|
| `npm run doctor` | Schneller, lesender Projektcheck: richtiges Repository, Node, Branch, Änderungen, lokal bekannte Remote-Stände |
| `npm run --silent doctor -- --json` | Dieselben Fakten als JSON ohne npm-Vorspann, Dateiinhalte oder Zugangswerte |
| `npm ci` | Abhängigkeiten gemäß Lockdatei installieren |
| `npm run check:repo` | Lokale Markdown-Dateilinks und Regeln für versionierte Dateien prüfen |
| `npm test` | Build- und Repository-Regressionstests ausführen |
| `npm run check` | Repository prüfen, Tests ausführen, Root und Pages bauen und prüfen |
| `npm run build` / `npm run build:pages` | Nur die jeweilige Ausgabe neu bauen |
| `npm run preview` | Gebautes `dist/` unter `http://127.0.0.1:4173/` ansehen |
| `npm run preview:pages` | Gebautes `_site/` unter `http://127.0.0.1:4174/smartdock-website/` ansehen |
| `npm run verify:reference` | Einmalige Übernahme mit der historischen Exportreferenz vergleichen |

`doctor` ruft weder GitHub noch Datenbanken auf und verändert keine Dateien. Vorher
fetchen und offene PRs gesondert ansehen; lokale Remote-Referenzen können veraltet sein.
Ein sauberer Git-Stand ist noch kein erfolgreicher Build. Der Referenzvergleich darf
bei gewollten späteren Website-Änderungen abweichen; Abweichungen im PR erklären.

Nach Quelländerungen neu bauen und den Browser neu laden. Die Vorschau-Server bauen
nicht automatisch. Mit `Strg+C` stoppen.

## Passende Prüfung

| Änderung | Zusätzlich zu `npm run check` |
|---|---|
| Dokumentation / Arbeitswerkzeuge | Links, Befehle und erwartete Fehlermeldungen prüfen |
| FAQ / Inhalte | Betroffene Texte und Sprachfassungen im Browser lesen |
| URLs / Build / Navigation | Root und Pages, direkte URLs, Query/Hash und Navigation prüfen |
| Konfigurator / Preise / PDF | Fachliche Quelle und Fälle aus der [Prüftabelle](docs/pruefung/konfigurator-prueftabelle.md) abgleichen |
| Sichtbare Oberfläche | Ausgangs-Commit und Vorher-/Nachher-Ansichten für Desktop/Mobil festhalten; Abläufe und Tastaturbedienung vergleichen, gewollte Fehlerkorrekturen benennen |

## Übergabe

Das Issue hält Ziel und offenen Stand; der PR hält Änderung und Prüfbelege. Eine
dauerhafte Architekturentscheidung gehört in die [Entscheidungsübersicht](docs/entscheidungen/README.md).
Chatverläufe ersetzen diese Ablage nicht. Zugangsdaten und personenbezogene Testdaten
gehören nicht in das öffentliche Repository oder seine Issues.

Technischer Branchschutz und Teamzugriffe sind weiterhin offen. Dokumentierte Regeln
sind kein Nachweis, dass GitHub sie bereits technisch erzwingt.
Max hat den Branchschutz Adrian als Repository-Admin zugeordnet. Die konkreten
Einstellungen und die anschließende Kontrolle stehen in
[#27](https://github.com/Thitronik/smartdock-website/issues/27).
