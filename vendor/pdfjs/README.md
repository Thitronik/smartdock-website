# PDF.js 5.6.205

Quelle: das offizielle Paket `pdfjs-dist`, Version 5.6.205, aus der vorhandenen Codex-Laufzeit.
Upstream: https://github.com/mozilla/pdf.js
Lizenz: Apache-2.0, siehe LICENSE.txt.

`build/pdf.min.mjs` und `build/pdf.worker.min.mjs` sind unverändert kopiert,
nur die Dateiendung ist für verlässliche JavaScript-MIME-Typen auf dem bestehenden
Webhosting in `.js` geändert. Browsermodule werden erst beim Öffnen des
Presse-PDF-Betrachters geladen. Keine externen CDN-Verbindungen.

Der vollständige wasm-Ordner einschließlich aller mitgelieferten Lizenzen ist enthalten.
Die bereitgestellte BOOTE-PDF verwendet JPEG-2000-Bilder (JPXDecode); dafür ist
der OpenJPEG-Decoder nötig. Bildfehler werden ausdrücklich nicht ignoriert.
