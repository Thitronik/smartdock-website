# Lokale QR-Erzeugung

`qrcodegen.js` stammt unverändert von Project Nayuki:
https://www.nayuki.io/res/qr-code-generator-library/qrcodegen.js

Heruntergeladen am 27.10.2026. Die MIT-Lizenz ist vollständig im Dateikopf
enthalten. Die Bibliothek wird vom eigenen Server ausgeliefert, nicht von einem
CDN geladen. `bootstrap-qr.js` erzeugt das SVG ausschließlich im Browser.

Provisionierungsformat: `otpauth://totp/` mit identischem Issuer in Label und
Parameter, SHA-1, sechs Ziffern und 30 Sekunden. Der Schlüssel wird weder an einen
QR-Dienst gesendet noch in der URL einer HTTP-Anfrage abgelegt.
