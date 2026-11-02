-- Erst nach Backup auf der Testdatenbank ausführen.
CREATE TABLE sd_dealer_invitations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 dealer_id BIGINT UNSIGNED NOT NULL,
 email VARCHAR(254) COLLATE utf8mb4_bin NOT NULL,
 token_hash BINARY(32) NOT NULL UNIQUE,
 invited_by BIGINT UNSIGNED NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 consumed_at DATETIME(6) NULL,
 revoked_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(dealer_id) REFERENCES sd_dealer(id),
 FOREIGN KEY(invited_by) REFERENCES sd_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE sd_dealer_memberships ADD COLUMN position VARCHAR(200) NULL, ADD COLUMN phone VARCHAR(100) NULL;

ALTER TABLE sd_mail_outbox MODIFY message_type ENUM('newsletter_confirmation','configuration_confirmation','configuration_notification','dealer_email_confirmation','dealer_approval_notification','dealer_password','dealer_decision','staff_activation','contact_notification','newsletter_campaign','newsletter_test','dealer_invitation') NOT NULL;
