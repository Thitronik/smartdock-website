# Händlerfinder: Funktionsprüfung und Korrekturen vom 22.10.2026

Ausgangspunkt: `main` **799073bebd99f4ec275b08cf97c20f82e6c48456**.
Verglichen wurden [smartdock.de](https://www.smartdock.de/haendlerfinder)
und lokale Root-/Pages-Builds. Der Stand der veröffentlichten Nachprüfung
steht im zugehörigen PR und in [#6](https://github.com/Thitronik/smartdock-website/issues/6).

## Befunde und Änderungen

1. **Mobiler Kartenabsturz auf Live und im übernommenen Code:** Bei 375 × 812 px
   einen Händler in der Liste wählen. Beim Einblenden der zuvor versteckten Karte
   berechnet Leaflets animiertes `flyTo` ungültige Werte: `Invalid LatLng object:
   (NaN, NaN)`. Die gemeinsame Kartensteuerung wartet jetzt auf eine sichtbare
   Kartengröße und berücksichtigt Größenwechsel mit `ResizeObserver`.
   Die Auswahl zentriert unmittelbar statt mit der fehlerhaften Fluganimation.
   Beim Verlassen/Neuladen der Kartenansicht wird auch eine bereits entfernte
   Leaflet-Kartenfläche berücksichtigt.
2. **Kartenbreite:** Die feste 800-px-Breite des eigenständigen Händlerfinders
   passt nicht auf kleine Bildschirme. Auf Mobilgeräten füllt die Karte nun die
   verfügbare Breite; auf breiten Desktopansichten bleiben die bisherigen 800 px
   erhalten. Farben, Schrift, Karten, Händlerinhalte und Filter bleiben erhalten.
3. **Tastatur:** Händlerkarten waren ausschließlich klickbare `div`s, Marker
   unbeschriftet. Karten sind jetzt per Enter/Leertaste auswählbar und nennen ihren
   Auswahlzustand; Marker tragen den Händlernamen. Enter auf einem Marker übernimmt
   den Händler auch in die Auswahl, statt nur das Popup zu öffnen.
   Dies gilt ebenfalls für die Einbaupartner-Auswahl im Konfigurator.
4. **Standortfehler waren unsichtbar:** Verweigerung, nicht verfügbare Position,
   Zeitüberschreitung und fehlende Browserunterstützung erhalten verständliche
   Meldungen in DE/EN/FR/NL/ES. Der eigenständige Händlerfinder zeigt auch den
   laufenden Abruf an und benennt die Suchfelder für Hilfstechnologien.
5. **Filter und Koordinaten:** Leerräume am Anfang/Ende einer Suche werden
   ignoriert. Ein Filterwechsel im eigenständigen Finder verwirft die bisherige
   Auswahl und beendet die Standortsortierung. Ungültige Koordinaten werden dort
   von der Karte ausgeschlossen; Händler bleiben in der Liste. Koordinaten mit
   Breiten-/Längengrad null sind gültig. Ladefehler sind lokalisiert.

Die gemeinsame Implementierung liegt in [src/dealer-finder.js](../../src/dealer-finder.js).
Die Einbindung in das weiterhin kompilierte Paket bleibt gezielt; kein Framework-
oder Hostingwechsel, keine Änderung von Preisen oder Händlerkontakten.

## Prüfumfang

| Bereich | Nachweis |
|---|---|
| Händlerdaten | Alle neun öffentlichen Datensätze einschließlich aller Felder und Pagination am 22.10.2026 gegen die Live-API verglichen: identisch; eine Seite, neun Einträge |
| Suche | 28 Browserfälle auf Live und lokalem Pages-Build: unbeschränkt, alle fünf Länder, alle neun Städte, Großschreibung, jede PLZ, unbekannte Stadt/PLZ und kombinierte passende/widersprüchliche Filter; gleiche Trefferlisten |
| Zusätzliche Filterregression | Führende/nachfolgende Leerzeichen, ungültige Koordinaten, Sortierung mit fehlenden Koordinaten; automatisiert |
| Marker | Alle neun Händler einzeln über die Stadt gefiltert; Marker, Name/Adresse im Popup und Auswahl per Enter geprüft |
| Liste/Karte | Wiederholter Wechsel auf 375 × 812 px, Kartenauswahl und Rückkehr zur Liste; kein Absturz, Zoom und Popup-Schließen geprüft |
| Root und Pages | Alle fünf Sprachen direkt aufgerufen, Schweiz gefiltert, Händler per Tastatur ausgewählt und Popup geöffnet; Namen und beide externen NAGY-Links korrekt |
| Mobil | 375 × 812 px; Dokument- und Inhaltsbreite 360 px (15 px Scrollleiste), Kartenbreite rund 327 px; kein horizontales Überlaufen in den fünf Sprachfassungen |
| Desktop | Live und lokale Vorschau bei 1280 × 900 px visuell verglichen; bisherige Farben, Schrift, Filter und Kartenaufteilung erhalten |
| Standortfehler | 20 echte Browserdurchläufe mit kontrollierten Antworten: vier Fehlertypen × fünf Sprachen; Meldung sichtbar, erneute Suche möglich |
| Standorterfolg | Fünf Browserdurchläufe mit synthetischer Position an der öffentlich bekannten Thitronik-Adresse: Thitronik zuerst mit 0,0 km, neun Händler plus Standortmarker; anschließender CH-Filter zeigt nur NAGY und entfernt den Standortmodus |
| Daten nicht verfügbar | Ladefehler in jeder Sprache über einen lokalen Testserver simuliert und als sichtbare Fehlermeldung geprüft |
| Konfigurator | Deutschland, eine Ankerwinde, Einbau: fünf deutsche Händler; Thitronik per Tastatur, anschließend mobil CPS TEAM gewählt; kein Kartenabsturz |
| PDF und lokale Anfrage | CPS TEAM im tatsächlich heruntergeladenen zweiseitigen PDF, 3.250 € vollständig im Preisblock; beide Seiten gerendert und angesehen. Synthetische Testanfrage lokal gespeichert, Bestätigung nennt CPS TEAM; kein Versand |

Standortfälle wurden ausschließlich auf einem lokalen Testserver mit gezielt
vorgegebenen Browser-API-Antworten ausgeführt. Es wurde kein privater Standort
ausgelesen. Dies prüft die Anwendung nach Erfolg/Fehler der Standort-API, aber
nicht die Genauigkeit eines Gerätesensors oder die Betriebssystem-Freigabemaske.
Ein kurzer Gegencheck auf einem echten Telefon mit freiwilliger Standortfreigabe
bleibt eine Team-Abnahme vor Produktivstart.

Die Tests stehen in [tools/dealer-finder.test.mjs](../../tools/dealer-finder.test.mjs)
und laufen mit `npm run check`: **73 Tests bestanden**, Root-/Pages-Build und
60 Redirect-Fälle erfolgreich. Sie prüfen die ausgelieferte Komponente zusätzlich
zur gemeinsamen Hilfslogik. Screenshots, lokale Testdaten und PDFs liegen
unversioniert unter `.migration-dealer-20261022/`; keine echten Kundenangaben.

## Grenzen und Übergabe

- Kein pauschaler Nachweis für alle Geräte, Browser oder Barrierefreiheitsnormen.
  Externe Kartenkacheln bleiben von OpenStreetMap bzw. CARTO abhängig.
- Der PDF-Export bleibt rasterisiert; die bereits dokumentierte Einschränkung
  aus [der PDF-Prüfung](pdf-seitenumbruch-und-wechsel-2026-10-22.md) bleibt bestehen.
- Der Branch-Schutz ist **noch nicht eingerichtet**. Max hat den Admin-Schritt
  Adrian zugeordnet; konkrete Einstellungen und Abnahme in
  [#27](https://github.com/Thitronik/smartdock-website/issues/27).
- Teamzugänge/fachliche Zuständigkeiten, Sprachbefunde #9, fachliche Preisfreigabe
  #5 und rechtliche Abnahme bleiben offen. Hetzner und echter Versand sind nicht
  Teil dieser Korrektur.
