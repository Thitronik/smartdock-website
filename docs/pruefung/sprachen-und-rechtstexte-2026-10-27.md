# Sprachen und Rechtstexte: Prüfung vom 27. Oktober 2026

Aufgaben: [#9](https://github.com/Thitronik/smartdock-website/issues/9) und die
rechtliche Prüfung aus [#6](https://github.com/Thitronik/smartdock-website/issues/6).
Basis: staging `6b91151be6ab3733dfaae8262849ad3c318d6679`, Zweig `claude/sprachen-nl-es`.
Geprüft wurde der lokal gebaute Stand unter `http://127.0.0.1:4173/`. Die Nachprüfung
der veröffentlichten Vorschau steht nach dem Merge noch aus.

## Teil 1: Sprachbefunde #9 – korrigiert

Methode: Alle 116 HTML-Einstiege wurden mit Chrome headless gerendert. Titel,
`lang`-Attribut, Beschreibung und sichtbarer Text wurden gegen 578 deutsche Texte
aus den Wörterbüchern beider Anwendungspakete geprüft (nur Texte, die sich von
EN/FR unterscheiden). Zusätzlich Sichtprüfung im Browser.

| Befund vorher | Ursache | Änderung |
|---|---|---|
| NL/ES-Startseite und Kontakt zeigen den deutschen Newsletter-Block | Im Hauptpaket fehlten den Wörterbüchern NL/ES die elf `newsletter_*`-Schlüssel | Texte aus dem bereits übersetzten Position-Hold-Paket übernommen, unverändert |
| `/nl/functies/`, `/es/funciones/`: Titel „Funktionen - SMARTDOCK“, deutsche Beschreibung, ES-Brotkrumen „Startseite > Funktionen“ | Titel-/Beschreibungsauswahl kannte nur DE/EN/FR | „Functies“/„Funciones“, Beschreibung NL/ES, Brotkrumen „Inicio > Funciones“ |
| `/nl/installatie/`, `/es/instalacion/`: Titel „Installation - SMARTDOCK“ | Fester Titel ohne Sprachwahl | „Installatie“/„Instalación“ über die vorhandene Fünf-Sprachen-Hilfe |
| Alte Features-Familie (`/features/`, `/en/features-legacy/`, `/fr/fonctionnalites/`, `/fr-fr/fonctionnalites/`, `/nl/functionaliteiten/`, `/es/funciones-legado/`, `/ch/features/`) zeigt in FR/NL/ES deutsch-englischen Mischtext | Seiten wurden nie übersetzt und sind nirgends verlinkt | **Entscheidung Max, 27.10.2026:** Weiterleitung auf die aktuelle Funktionsseite derselben Sprache; Query und Hash bleiben erhalten |

Die neuen NL/ES-Beschreibungen der Funktionsseite sind eigene Übersetzungen der
vorhandenen DE/EN/FR-Texte. Alle übrigen neuen Texte stammen wörtlich aus dem
Position-Hold-Paket. **Sprachliche Freigabe durch Muttersprachler bleibt offen.**

Nachher: Kein deutscher Wörterbuchtext mehr auf NL-/ES-/EN-/FR-Seiten. Verbleibende
Treffer sind gewollt: Gesetzesnamen auf den Barrierefreiheitsseiten
(„Barrierefreiheitsstärkungsgesetz“) und „SMARTDOCK Module“ auf EN.

Regression: zwei neue Tests in [tools/build.test.mjs](../../tools/build.test.mjs) –
Newsletter-Schlüssel, Titel und Brotkrumen in NL/ES sowie fünf Weiterleitungen mit
und ohne Schrägstrich, Query und Hash. `npm run check`: 67 Tests bestanden.

### Nebenbefunde, nicht geändert

- Die statischen HTML-Dateien tragen vor dem Laden der Anwendung für alle Sprachen
  `lang="de"` und den Titel „SMARTDOCK · Vorschau“. Suchmaschinen ohne JavaScript
  sehen deshalb keine Sprachfassung. Vor dem Produktivstart prüfen.
- Nach dem Laden falsches `lang`: `/en/dealer-finder/` und `/fr-fr/revendeurs/` (`de-DE`),
  `/fr-fr/` (`de-DE`).
- Die Weiterleitung der alten Features-Seiten erfolgt im Browser. Beim Produktivhosting
  zusätzlich serverseitige 301-Weiterleitungen einrichten.

## Teil 2: Rechtstexte und externe Dienste – Befunde zur Freigabe

**Dies ist keine Rechtsberatung und keine Freigabe.** Die Tabelle trennt beobachtete
Fakten von Empfehlungen. Rechtstexte wurden nicht geändert. Entscheidung und
Formulierung liegen bei der zuständigen Person bzw. dem Datenschutzbeauftragten
(laut Datenschutzerklärung Dipl.-Ing. Karsten Dreyer).

### Tatsächlich geladene Dienste (ohne Einwilligung, leerer Browser-Speicher)

| Seite | Externe Verbindungen vor jeder Cookie-Auswahl |
|---|---|
| Startseite `/` | `fonts.googleapis.com` (3), `player.vimeo.com` (2 eingebettete Videos 1206062183 und 1208103894) |
| Händlerfinder `/haendlerfinder/` | `fonts.googleapis.com`, `fonts.gstatic.com`, `c.tile.openstreetmap.org`; Code enthält zusätzlich CARTO-Kacheln (`basemaps.cartocdn.com`) |
| Nach „Alle akzeptieren“ (laut Code) | Google Analytics 4 / Tag Manager, Kennung `G-5DDPJH2NRW`; in der Vorschau deaktiviert |

Weitere externe Adressen im Code: `cdnjs.cloudflare.com` (nur bei PDF-Anzeige im neuen
Fenster), `horizons-cdn.hostinger.com` (Altbestand des Website-Baukastens). Links auf
Google Maps, Instagram, Facebook und YouTube sind reine Links ohne Datenübertragung beim Seitenaufruf.

### Datenschutzerklärung (DE, `/datenschutz/`)

| # | Befund | Empfehlung zur Entscheidung |
|---|---|---|
| D1 | **Vimeo fehlt**, obwohl zwei Videos ohne Einwilligung geladen werden | Vimeo erst nach Einwilligung laden (Platzhalter mit Klick) oder Videos selbst hosten; in jedem Fall Abschnitt ergänzen. Die lokalen Quellvideos (MP4) liegen vor |
| D2 | **Google Fonts fehlt**; Schriften werden ohne Einwilligung von Google-Servern geladen | Schriften (Inter/Sora/Montserrat) lokal ausliefern; dann entfällt die Übertragung. Gilt als gängige Empfehlung seit LG München I, Az. 3 O 17493/20 |
| D3 | **OpenStreetMap/CARTO fehlt** (Kartenkacheln im Händlerfinder) | Abschnitt ergänzen; prüfen, ob die Karte erst nach Klick lädt. Für dauerhaften kommerziellen Betrieb die OSM-Nutzungsregeln der Kachelserver beachten |
| D4 | **Cookiebot** wird beschrieben, ist aber nicht eingebunden; die Website hat ein eigenes Einwilligungsbanner | Abschnitt durch Beschreibung des tatsächlichen Banners (Speicherung der Auswahl im Browser) ersetzen |
| D5 | **Google Analytics: „Advanced Consent Mode“** beschrieben (Pings ohne Einwilligung). Laut Code wird GA erst nach Einwilligung geladen | Text an tatsächliches Verhalten anpassen oder Verhalten bewusst festlegen. Entscheiden, ob GA nach dem Umzug überhaupt weiter genutzt wird |
| D6 | **CloudFront** beschrieben; kein CloudFront-Aufruf beobachtet. Bisheriger Hoster (Hostinger) und künftiger Hoster (Hetzner) nicht genannt | Nach Hosting-Entscheidung (#20) Hoster als Auftragsverarbeiter nennen, CloudFront streichen falls nicht genutzt; Speicherdauer der Server-Logfiles angeben |
| D7 | **Formulare fehlen**: Kontakt-/Konfigurationsanfrage (Name, E-Mail, Telefon, Boot, Konfiguration, PDF), Händlerbereich-Zugangsanfrage | Je Formular Zweck, Rechtsgrundlage, Empfänger, Speicherdauer ergänzen, sobald das Backend (#20) feststeht |
| D8 | **Newsletter**: Abschnitt vorhanden, aber ohne Double-Opt-in-Ablauf, Protokollierung und Versanddienstleister | Nach Entscheidung für Brevo: Brevo als Auftragsverarbeiter, Double-Opt-in und Protokollierung beschreiben |
| D9 | **Standortfreigabe** im Händlerfinder und lokale Speicherung von Konfigurationen im Browser nicht beschrieben | Kurz ergänzen (Verarbeitung nur im Browser, falls zutreffend) |
| D10 | Abschnitt **„Bestellungen“** mit Versand/Zahlung; die Website nimmt derzeit nur Anfragen an | Prüfen, ob es Bestellungen über die Website gibt; sonst streichen oder anpassen |
| D11 | Kein Stand/Datum der Erklärung | Datum ergänzen |
| D12 | EN/FR/NL/ES-Fassungen sind Übersetzungen derselben Struktur | Jede Änderung an D1–D11 in allen fünf Sprachen nachziehen |

### Impressum (DE, `/impressum/`)

| # | Befund | Empfehlung zur Entscheidung |
|---|---|---|
| I1 | Zeile „Zuständige Aufsichtsbehörde für audiovisuelle Mediendienste:“ **ohne Angabe** | Behörde eintragen, falls zutreffend; sonst Zeile streichen |
| I2 | Unter „Berufshaftpflichtversicherung“ steht „Carsten A. Höneise e.K.“; dem Anschein nach ein Versicherungsmakler, nicht der Versicherer | Prüfen, ob die Angabe für Thitronik verpflichtend ist; falls ja, Versicherer und Geltungsbereich angeben |
| I3 | Geschäftsführer Mark Thietje, HRB 11453 (AG Kiel), USt-IdNr. DE268454642, Telefon/Fax/E-Mail vorhanden | Aktualität bestätigen |
| I4 | Hinweis zur Verbraucherschlichtung vorhanden; kein Link zur EU-OS-Plattform | In Ordnung: Die OS-Plattform wurde 2025 eingestellt, ein Link wäre veraltet |
| I5 | Kein Hinweis „Verantwortlich für den Inhalt“ nach § 18 Abs. 2 MStV | Nur nötig bei journalistisch-redaktionellen Inhalten; bewusst entscheiden |

### Produktangaben

Nicht inhaltlich geprüft. Technische Kompatibilitätsaussagen (Motoren, Strahlruder,
Ankerwinden), „Made in Germany“ und der Markenhinweis im Fußbereich brauchen eine
fachliche Bestätigung durch die Produktverantwortlichen. Die Prüfung ist offen.

## Offen nach diesem Stand

- Muttersprachliche Freigabe der NL/ES-Texte (#9).
- Entscheidungen zu D1–D12 und I1–I5 durch die zuständige Person bzw. den
  Datenschutzbeauftragten, danach Textänderung in allen fünf Sprachen.
- Technische Umsetzung von D1–D3 (Vimeo-Einwilligung oder Selbsthosting, lokale Schriften,
  Karte nach Klick) als eigene Änderung nach Entscheidung.
- Nachprüfung der veröffentlichten Vorschau nach dem Merge nach staging.
