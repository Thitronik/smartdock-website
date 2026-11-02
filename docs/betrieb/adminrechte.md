# Adminrechte

Alle freigeschalteten Admins sehen die vollständige Adminliste und können weitere persönliche @thitronik.de-Adminzugänge einladen. Andere Verwaltungsfunktionen bleiben für Admins verfügbar. E-Mail-Aktivierung, Passwort und Authenticator bleiben für neue Admins erforderlich.

Nur die bereits eingerichteten Admin-Konten marketing@thitronik.de und smartdock@thitronik.de können andere Adminzugänge sperren, Einladungen widerrufen und zuvor gesperrte Adminzugänge löschen. Beide Hauptkonten sind gegenseitig und gegen Selbstsperrung/-löschung geschützt. Die E-Mail-Adresse allein vergibt keine Rechte: aktive Adminrolle, Postfachnachweis und MFA bleiben Pflicht.

Für andere Admins sind die Aktionsbuttons sichtbar, aber deaktiviert. Dieselben Einschränkungen gelten serverseitig bei direkten API-Aufrufen. Die vollständige Adminliste enthält keine Passwort- oder Authenticator-Daten.

Regression: normaler Admin sieht Liste und kann einladen; seine Einladung kann mit E-Mail/Passwort/MFA aktiviert werden. Direkte Sperr-/Löschversuche scheitern. Beide Hauptkonten können verwalten und keines der beiden Hauptkonten kann gesperrt oder gelöscht werden. Nur isolierte CI-Testkonten werden dafür erzeugt.

## Rückmeldung bei weiteren Händlerzugängen

Rückmeldungen werden direkt im Einladungsformular angezeigt. Die allgemeine Admin-Statusmeldung wird gezielt angesprochen; zuvor landeten Fehler im ausgeblendeten Newsletterstudio. Während des Aufrufs ist der Button deaktiviert. Nach erfolgreicher Anlage wird der konkrete Outbox-Status zurückgegeben: an den Mailserver übergeben, ausstehend oder fehlgeschlagen. Dies ist kein Nachweis über den Eingang im Zielpostfach.

Ein Händlerkonto ist vom Adminkonto unabhängig, auch bei identischer E-Mail-Adresse. Eine Händlereinladung bezieht sich ausschließlich auf das Händlerkonto und verändert weder Adminpasswort noch MFA oder Adminrollen. Bereits bestehende Händlerkonten verwenden ihr Händlerpasswort; neue Händlerkonten richten ein eigenes Passwort ein. Die technische Trennung und Migration 007 sind in [Weitere Händlerzugänge](weitere-haendlerzugaenge.md) beschrieben.
