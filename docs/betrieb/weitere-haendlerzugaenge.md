# Weitere Händlerzugänge

Alle aktiven Admins können unter Händlerdaten → Händlerbereich – weitere Zugänge per E-Mail einladen. Die Berechtigungen zur Verwaltung von Adminrechten sind davon unabhängig. Weitere Händlerzugänge erhalten ausschließlich Zugang zum Händlerbereich.

Die Einladung gilt sieben Tage und einmalig. Vor- und Nachname sowie ein persönliches Passwort werden auf der Einrichtungsseite erfasst; Position und Telefon sind bei unabhängigen Zugängen optional. Offene Einladungen und fertige Zugänge werden ausschließlich in der Rubrik weitere Zugänge dargestellt. Sperren, Löschen und bewusstes späteres Zuordnen funktionieren wie bisher.

Technisch nutzt der bestehende Mitgliedschafts-/Einladungsablauf einen eindeutig gekennzeichneten privaten Systemcontainer (`source_system=smartdock-internal`, `source_id=extra-access`). Dieser hat keine Anschrift, öffentliche E-Mail oder Koordinaten und wird aus der Händlerdatentabelle der Oberfläche ausgeblendet. Eine Kartenfreigabe wird serverseitig abgewiesen. Es gibt keine Schemaänderung. Der feste Ländercode ZZ bezeichnet den technischen Container, keinen Händlerstandort.

Automatische Newsletteraufnahme berücksichtigt wie bisher Abmeldungen. Persönliche Zugangsadressen zählen exakt als Händlerempfänger; ihre Domain wird nicht als zusätzliche Händlerfirma interpretiert.

Prüfung: MariaDB-HTTP-Integration für nicht angemeldete Nutzer, Händler, normalen Admin, Einladungsannahme ohne Firmendaten, Händlerlogin ohne Adminrechte, offene Einladungen, Duplikate und verweigerte Kartenfreigabe.

Gesperrte Händler-Mitgliedschaften können gelöscht und neu eingeladen werden. Das Händlerkonto behält sein Passwort; andere Firmenzugänge bleiben erhalten. Adminkonten sind davon vollständig getrennt. Die Abschlussseite begrüßt die Person ohne Navigationslinks.

## Getrennte Anmeldungen

Dieselbe E-Mail-Adresse kann zwei getrennte Konten besitzen (`login_realm=dealer` beziehungsweise `staff`). Händlerkonten haben ein eigenes Passwort ohne Authenticator; Adminkonten behalten Passwort und MFA. Getrennte Sitzungscookies verhindern, dass Anmeldung, Abmeldung oder Passwortwechsel des einen Bereichs den anderen Bereich verändern. Eine Händleranmeldung kann keine Adminrechte erhalten, auch nicht durch Änderung des API-Portalparameters. Admins müssen für den Händlerbereich wie Händler eingeladen und dort angemeldet werden.

Migration 007 wird nach Schemaabgleich und Backup einmalig auf der Testdatenbank ausgeführt. Reine Händlerkonten behalten ihre Passwörter. Bereits gemischte Konten werden in zwei Datensätze getrennt; die Händler-Mitgliedschaften wechseln zum neuen Händlerdatensatz. Das Adminpasswort wird niemals kopiert. Diese Personen setzen über „Passwort vergessen?“ ein separates Händlerpasswort. Alte Händlerpasswortlinks am Admin-Datensatz werden widerrufen. Keine automatische Mail wird durch die Migration ausgelöst.
