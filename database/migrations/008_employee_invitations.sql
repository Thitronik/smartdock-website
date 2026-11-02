-- Einmalig nach Backup auf smartda_test. Bestehende Admin-Einladungen bleiben unverändert.
ALTER TABLE sd_dealer_invitations ADD COLUMN requires_approval TINYINT(1) NOT NULL DEFAULT 0;
