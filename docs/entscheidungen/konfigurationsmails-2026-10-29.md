# Konfigurationsanfragen und Mailbestätigungen

Die Website nimmt weiterhin unverbindliche Konfigurationsanfragen entgegen,
keine Bestellungen. Eine Anfrage erzeugt atomar ihren gespeicherten Preisstand
und die zugehörigen Mailaufträge. Wiederholtes Absenden derselben Referenz
erzeugt keine zusätzlichen Aufträge.

- Intern: Kundendaten, Anschrift, Komponenten, Einzelpreise, Rabatte, Steuer,
  Gesamtpreis und gewählter Einbauhändler stehen in der Mail und Administration.
- Kunde: Bestätigung mit einem serverseitig erzeugten PDF-Anhang.
- Einbauhändler: zusätzliche Benachrichtigung nur bei gewähltem Einbauservice.
  Empfänger und Firmenname stammen aus dem freigegebenen Datenbankeintrag,
  niemals aus einer vom Browser übermittelten Empfängeradresse.
- Preise werden aus der bestehenden Konfiguratorlogik auf dem Server berechnet.
  Die Übernahme der Preisregeln ist keine neue fachliche Preisfreigabe.
- Frühere Anfragen ohne Preisstand erhalten keine nachträglich erfundenen Preise.

## Test und Produktivumschaltung

Auf `test.smartdock.de` gehen interne und Einbauhändler-Benachrichtigungen an
den konfigurierten Testempfänger `a.richter@thitronik.de`. Die Händler-Mail
trägt ausdrücklich einen Testhinweis. Kundenmails bleiben beim Antragsteller.
Bei Testläufen ausschließlich die autorisierte Testadresse verwenden.

Vor Produktivfreigabe `inquiry_email` wieder auf `smartdock@thitronik.de`
setzen und den Produktivhost sowie die Einbauhändler-Empfänger prüfen.
Die bestehende Produktivseite wurde nicht verändert.

## Nachweis

Commit `534ffe1`: 79 Website-Tests, 52 PHP-Sicherheitsprüfungen,
4096 Preisvergleiche; GitHub-Integrationstest mit separater MariaDB bestanden.
Dieser prüft zwei/drei Mailaufträge, PDF-Anhang, Wiederholungen, manipulierte
Preise und Händleradressen, fehlende Anschrift und nicht vorhandene Händler.

Hetzner-Test `2910-C2`, 29.10.2026: WCU 1250 EUR plus Einbau 2000 EUR,
Gesamt 3250 EUR. Alle drei Mails um 12:04 Uhr in Outlook angekommen.
PDF aus dem tatsächlichen Mailanhang heruntergeladen und visuell geprüft;
Anschrift, Komponenten, Gesamtsumme und Händler entsprechen dem Admineintrag.
Keine echte Händler-Mail versendet. Keine Schemaänderung erforderlich.
