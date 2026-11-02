-- Einmalig nach Schemaabgleich und Backup ausschließlich auf der Testdatenbank.
-- Bestehende reine Händlerpasswörter und sämtliche Adminpasswörter bleiben erhalten.
ALTER TABLE sd_users ADD COLUMN login_realm ENUM('dealer','staff') NOT NULL DEFAULT 'dealer',
 DROP INDEX uq_sd_user_email,
 ADD UNIQUE KEY uq_sd_user_realm_email(login_realm,email_normalized);
-- Eine historische Einladung darf das Löschen ihres gesperrten Admin-Absenders
-- nicht verhindern. Der Einladungsdatensatz bleibt als Verlauf erhalten.
ALTER TABLE sd_dealer_invitations DROP FOREIGN KEY sd_dealer_invitations_ibfk_2,
 MODIFY invited_by BIGINT UNSIGNED NULL,
 ADD CONSTRAINT fk_sd_dealer_invitation_actor FOREIGN KEY(invited_by) REFERENCES sd_users(id) ON DELETE SET NULL;
START TRANSACTION;
UPDATE sd_users u SET login_realm='staff'
 WHERE EXISTS(SELECT 1 FROM sd_staff_roles s WHERE s.user_id=u.id)
 OR EXISTS(SELECT 1 FROM sd_admin_audit_events a WHERE a.target_type='user' AND a.target_id=u.id AND a.action IN ('staff.invited','bootstrap.admin_created','bootstrap.pending'));
-- Keine Adminpasswörter in Händlerkonten kopieren. Diese Personen setzen ihr
-- separates Händlerpasswort über den bestehenden E-Mail-Link selbst fest.
INSERT INTO sd_users(first_name,last_name,email,email_normalized,email_verified_at,status,login_realm)
 SELECT u.first_name,u.last_name,u.email,u.email_normalized,u.email_verified_at,'invited','dealer'
 FROM sd_users u WHERE u.login_realm='staff'
 AND EXISTS(SELECT 1 FROM sd_dealer_memberships m WHERE m.user_id=u.id);
UPDATE sd_dealer_memberships m JOIN sd_users staff ON staff.id=m.user_id AND staff.login_realm='staff'
 JOIN sd_users dealer ON dealer.email_normalized=staff.email_normalized AND dealer.login_realm='dealer'
 SET m.user_id=dealer.id;
UPDATE sd_dealer_access_requests r JOIN sd_users staff ON staff.id=r.user_id AND staff.login_realm='staff'
 JOIN sd_users dealer ON dealer.email_normalized=staff.email_normalized AND dealer.login_realm='dealer'
 SET r.user_id=dealer.id;
-- Alte Händler-Links am bisherigen Admin-Datensatz dürfen kein Adminpasswort ändern.
UPDATE sd_user_tokens t JOIN sd_users u ON u.id=t.user_id AND u.login_realm='staff'
 SET t.revoked_at=UTC_TIMESTAMP(6)
 WHERE t.purpose IN ('set_password','reset_password','approve_membership') AND t.revoked_at IS NULL;
COMMIT;
