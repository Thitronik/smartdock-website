# Weitere Händlerzugänge

Alle aktiven Admins können unter Händlerdaten → Händlerbereich – weitere Zugänge per E-Mail einladen. Diese Aktion ist unabhängig von der nur dem Hauptkonto erlaubten Vergabe von Adminrechten. Weitere Händlerzugänge erhalten ausschließlich Zugang zum Händlerbereich.

Die Einladung gilt sieben Tage und einmalig. Vor- und Nachname sowie ein persönliches Passwort werden auf der Einrichtungsseite erfasst; Position und Telefon sind bei unabhängigen Zugängen optional. Offene Einladungen und fertige Zugänge werden ausschließlich in der Rubrik weitere Zugänge dargestellt. Sperren, Löschen und bewusstes späteres Zuordnen funktionieren wie bisher.

Technisch nutzt der bestehende Mitgliedschafts-/Einladungsablauf einen eindeutig gekennzeichneten privaten Systemcontainer (`source_system=smartdock-internal`, `source_id=extra-access`). Dieser hat keine Anschrift, öffentliche E-Mail oder Koordinaten und wird aus der Händlerdatentabelle der Oberfläche ausgeblendet. Eine Kartenfreigabe wird serverseitig abgewiesen. Es gibt keine Schemaänderung. Der feste Ländercode ZZ bezeichnet den technischen Container, keinen Händlerstandort.

Automatische Newsletteraufnahme berücksichtigt wie bisher Abmeldungen. Persönliche Zugangsadressen zählen exakt als Händlerempfänger; ihre Domain wird nicht als zusätzliche Händlerfirma interpretiert.

Prüfung: MariaDB-HTTP-Integration für nicht angemeldete Nutzer, Händler, normalen Admin, Einladungsannahme ohne Firmendaten, Händlerlogin ohne Adminrechte, offene Einladungen, Duplikate und verweigerte Kartenfreigabe.
