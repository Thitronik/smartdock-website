# Newsletterstudio

## GitHub-Abgleich

GitHub ist die gemeinsame Quelle des Website-Codes. Änderungen der Hetzner-Testseite
werden mit Prüfungen im Arbeitsbranch versioniert und über einen Pull Request nach
staging übernommen. Der Nutzer hat diesen fortlaufenden Abgleich am 30.10.2026
beauftragt. Ein Testdeployment allein gilt nicht als abgeschlossener GitHub-Abgleich.
Offene Prüfungen werden dokumentiert; fehlgeschlagene CI wird nicht übergangen.
Private Konfigurationen, Zugangsdaten, Datenbankinhalte und zur Laufzeit hochgeladene
Bilder gehören nicht in das öffentliche Repository. Dafür sind separate Backups nötig.

Stand 30.10.2026, Ausgangscommit `7470477b4b9e0bff33b88776da97f4cbecf86788`.

Die Administration bietet unter `#newsletter-studio` die Gruppen Händler und
Endkunden, CSV-Export, gespeicherte Entwürfe, eine HTML-Vorschau und Testversand.
Die Vorlage verwendet das SMARTDOCK-Logo und #bceb02. Inhalte werden als Text
escaped; Links und optionale Bilder müssen HTTPS verwenden.

Nur aktive Newsletter-Abonnenten sind Empfänger. Die Händlerzuordnung erfolgt
anhand aktiver Händlerdaten: genaue öffentliche E-Mail-Adresse oder genaue Domain.
Allgemeine Mailanbieter werden nicht pauschal einer Firma zugeordnet. Händlerdaten
allein erzeugen kein Newsletter-Abonnement. Die Zuordnung verleiht keine Zugriffsrechte.

Kampagnen werden serverseitig gespeichert. Vor dem Versand werden Revision und
Empfängerliste erneut geprüft. Ein wiederholter Versandauftrag derselben Kampagne
erzeugt keine weiteren Aufträge. Vor der Zustellung prüft die Outbox erneut den
Abostatus. Jede Kampagnenmail enthält einen persönlichen Abmeldelink. Die vorhandene
Outbox verarbeitet die Warteschlange portionsweise; der reguläre Worker muss laufen.
Ein erfolgreicher PHP-mail-Aufruf bestätigt nur die Übergabe, keine Postfachzustellung.

## Private Konfiguration

`newsletter-settings.php` liegt neben der privaten `config.php`, außerhalb des
Webroots und Repositorys. Die Optionen sind `newsletter_send_enabled`,
`newsletter_test_enabled`, `newsletter_test_recipient` und `newsletter_footer`.
Der feste Absender ist `newsletter@smartdock.de`. Die Testadresse wird serverseitig
festgelegt und kann nicht durch Formulardaten überschrieben werden.

Auf der Testseite ist nur Testversand aktiviert. Interne Testnachrichten gehen an
die ausdrücklich freigegebene Testadresse; persönliche Aktivierungslinks bleiben
beim Kontoinhaber. Vor Produktivstart müssen interne Benachrichtigungen wieder an
das vorgesehene SMARTDOCK-Postfach gehen; keine Testüberschreibung übernehmen.

## Verifikation und offene Punkte

- Migration 005 erfolgreich auf smartda_test; vorher private Sicherung.
- Adminansicht: zwei aktive Abonnenten, je einer pro Gruppe; Entwurf gespeichert,
  Vorschau mit Logo gerendert, CSV mit genau einem Endkunden geprüft.
- Eine Testmail an die freigegebene interne Testadresse wurde am 30.10. um 12:11
  von Hornetsecurity empfangen und als Infomail zurückgehalten. Outlook-Zustellung
  ist damit noch nicht bestätigt. Keine Allowlist oder Filterregel geändert.
- Gruppenversand ist weiterhin deaktiviert; kein Newsletter an eine Gruppe versendet.
- JavaScript-/Build-Checks und PHP-WASM-Prüfungen bestanden. Vollständiger
  Kampagnen-Integrationstest mit nativer MariaDB und mobile Sichtprüfung stehen aus.
- Keine Bounce-Auswertung und kein List-Unsubscribe-One-Click-Header vorhanden.
  Der sichtbare Abmeldelink nutzt den bestehenden Bestätigungsablauf.

## Bilder vom PC – 30.10.2026

Admins können im Editor JPG, PNG und WebP bis 5 MiB hochladen. Der separate
Multipart-Endpunkt verlangt dieselbe Sitzung, Adminrolle, Origin- und CSRF-Prüfung
wie andere Verwaltungsaktionen. Die Datei wird anhand des Inhalts geprüft
(maximal 12 Megapixel, 6.000 Pixel pro Seite), mit GD dekodiert und als PNG mit
maximal 1.600 Pixel Breite neu geschrieben. Originaldateiname und Metadaten werden
nicht übernommen. SVG und andere Formate sind ausgeschlossen.

Die Dateien liegen unter `media/newsletter/` im Webroot der jeweiligen Installation
und sind für E-Mail-Empfänger öffentlich abrufbar. Die Bildadresse wird beim Speichern
in `sd_newsletter_campaigns.content_json.image_url` abgelegt; keine weitere Migration
ist notwendig. „Bild entfernen“ entfernt die Zuordnung, nicht die Datei, damit bereits
versendete E-Mails ihr Bild behalten. Unbenutzte Uploads bleiben ebenfalls erhalten.
Bei Deployments darf dieses Laufzeitverzeichnis nicht durch ein Build ersetzt werden;
Backups müssen es zusätzlich zur Datenbank enthalten.

Auf test.smartdock.de wurde ein vorhandenes öffentliches SMARTDOCK-Logo vom PC
hochgeladen, erfolgreich geladen und in einem Testentwurf gespeichert. Es wurde
keine E-Mail ausgelöst. PHP-Validierungstests decken PNG, ungültige Inhalte, SVG,
Dateigröße und Bilddimensionen ab. Frontend-/Build- und PHP-Prüfungen bestanden.
