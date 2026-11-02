# Live-Umzug am 02.11.2026

Ausgangsstand: staging 22bbb6c165f3a3c12647747250598ba0776373cf. Der Nutzer hat den vollständigen Umzug auf smartdock.de und Hetzner ausdrücklich freigegeben.

## Trennung

- Produktivdomain: https://www.smartdock.de/; smartdock.de wird kanonisch weitergeleitet.
- Produktivbau: `node tools/build.mjs --production`; entfernt den Vorschaufooter, gibt öffentliche Seiten zur Indexierung frei und erzeugt robots.txt/sitemap.xml. Admin und Händlerportal bleiben noindex.
- Produktivdatenbank: smartda_db0; smartda_test bleibt getrennt.
- Öffentlicher Pfad: public_html/smartdock-live. Private Implementierung, Konfiguration, MFA und Zustand: smartdock-private-live außerhalb des Webroots.
- Interne Kontakt-, Konfigurations- und Freigabemeldungen gehen produktiv wieder an smartdock@thitronik.de. Persönliche Nachrichten bleiben beim jeweiligen Empfänger; der gewählte Einbauhändler erhält produktiv seine Nachricht direkt.
- Konten, Passwörter, Rollen, MFA, Händler- und Newsletterdaten werden erhalten. Testsitzungen, Testanfragen, alte Einladungen/Passworttokens und Versandaufträge werden nicht übernommen. Die fünf leeren bisherigen Tabellen von smartda_db0 bleiben mit Präfix before_live_20261102_ erhalten. Neue Fremdschlüsselnamen bekommen live_ als Präfix.
- Staff und Händler behalten getrennte Host-Cookies, Sessions und Passwörter. Produktivcookies tragen keinen Test-Suffix.

## Rücknahme und Nachweis

Vor Übernahme: vollständiger SQL-Export der Quelle und der bisherigen Live-Struktur. Private Konfiguration und MFA-Schlüssel werden nicht ins Repository aufgenommen. Bestehende Hostinger-Ziele und Hetzner-DNS-Zone werden vor Änderung dokumentiert. MX, SPF und DKIM bleiben erhalten; vorhandenes DMARC wird in Hetzner ergänzt.

Nach Umschaltung müssen HTTPS, Root/WWW, öffentliche Seiten, Finder-API, anonyme Sessions, getrennte Anmeldungen, PDF, interne Empfänger, Cron und Zertifikate geprüft werden. Hostinger erst nach erfolgreicher DNS-/HTTPS-Prüfung stilllegen. Laufender Installationsnachweis wird nach Abschluss ergänzt.
