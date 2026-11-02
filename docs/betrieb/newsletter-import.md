# Newsletter-Import und Händlerzuordnung

Stand 02.11.2026. Nur Testumgebung; kein Live-Umzug.

Die geschützte Newsletterverwaltung kann vorbereitete JSON-Kontakte mit `email`, optional `language` und `created_at` übernehmen. Quelle: Brevo, Hostinger oder Händlerbestand. Eine Transaktion pro Import; keine Mail wird ausgelöst. Dubletten werden normalisiert, bestehende Abmeldungen und Sperren bleiben erhalten. Historische Übernahme wird mit Quelle protokolliert; sie wird nicht als neue Postfachbestätigung dargestellt. Kontaktdateien gehören niemals ins Repository.

Aktive Firmendomains ordnen Newsletter-Abonnenten automatisch der Händlergruppe zu. Auch freigegebene persönliche Händlerzugänge und offene, gültige Einladungen zählen. Bei gemeinsamen Mailanbietern zählt nur die exakte Händleradresse. Beim Einladen oder Freigeben eines Händlerzugangs wird die Adresse automatisch aufgenommen, sofern sie nicht bereits abgemeldet oder gesperrt ist. Der Abmeldelink bleibt nutzbar.

Neue öffentliche Anmeldungen werden zusätzlich auf ein enges Muster geprüft: Gmail/Googlemail mit mindestens sechs punktgetrennten Teilen, davon mindestens drei einzelne Buchstaben. Solche Anmeldungen werden vor Speicherung und Versand abgewiesen. Dies erkennt ein konkretes auffälliges Muster, keine sichere Aussage über die Echtheit eines Postfachs; andere Fake-Adressen können weiterhin durchkommen und echte Adressen können das Muster ebenfalls erfüllen. Altimporte werden von dieser Heuristik nicht verworfen. Vorhandene Format-/DNS-Prüfung, Honeypot und Anfragelimits bleiben aktiv.

Prüfung: PHP-Syntax, isolierte Filter-/Gruppenprüfungen sowie MariaDB-Integration für Adminrechte, stillen und wiederholbaren Import, Dubletten und den Schutz von Abmeldungen.
