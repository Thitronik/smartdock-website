-- Additive TEST upgrade after 003. Inspect and back up before running once.
SET NAMES utf8mb4;
SET time_zone = '+00:00';
CREATE TABLE sd_contact_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 public_reference CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 contact_name VARCHAR(200) NOT NULL,
 email VARCHAR(254) NOT NULL,
 company VARCHAR(200) NULL,
 phone VARCHAR(100) NULL,
 message TEXT NOT NULL,
 language VARCHAR(10) NOT NULL DEFAULT 'de',
 privacy_notice_version VARCHAR(64) NOT NULL,
 privacy_acknowledged_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 status ENUM('new','in_progress','completed','archived') NOT NULL DEFAULT 'new',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY uq_sd_contact_reference(public_reference),
 KEY ix_sd_contact_status(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sd_mail_outbox
 MODIFY message_type ENUM('newsletter_confirmation','configuration_confirmation','configuration_notification','dealer_email_confirmation','dealer_approval_notification','dealer_password','dealer_decision','staff_activation','contact_notification') NOT NULL,
 ADD newsletter_token_id BIGINT UNSIGNED NULL,
 ADD contact_request_id BIGINT UNSIGNED NULL,
 ADD CONSTRAINT fk_sd_mail_newsletter_token FOREIGN KEY(newsletter_token_id) REFERENCES sd_newsletter_tokens(id) ON DELETE SET NULL,
 ADD CONSTRAINT fk_sd_mail_contact FOREIGN KEY(contact_request_id) REFERENCES sd_contact_requests(id) ON DELETE SET NULL;
