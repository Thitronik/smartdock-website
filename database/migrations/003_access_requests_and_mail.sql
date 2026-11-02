-- Additive upgrade after 001 + 002. Back up and inspect the TEST database first.
-- Does not grant access, subscribe contacts or send mail. Run exactly once.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE sd_dealer_access_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id BIGINT UNSIGNED NOT NULL,
 membership_id BIGINT UNSIGNED NULL,
 first_name VARCHAR(200) NOT NULL,
 last_name VARCHAR(200) NOT NULL,
 company_name VARCHAR(254) NOT NULL,
 street VARCHAR(200) NOT NULL,
 house_number VARCHAR(50) NOT NULL,
 postal_code VARCHAR(32) NOT NULL,
 city VARCHAR(200) NOT NULL,
 country VARCHAR(100) NOT NULL,
 phone VARCHAR(100) NULL,
 language VARCHAR(10) NOT NULL DEFAULT 'de',
 privacy_version VARCHAR(64) NOT NULL,
 status ENUM('email_pending','pending','approved','rejected') NOT NULL DEFAULT 'email_pending',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY uq_sd_access_membership(membership_id),
 KEY ix_sd_access_user(user_id,status),
 KEY ix_sd_access_pending(status,created_at),
 CONSTRAINT fk_sd_access_user FOREIGN KEY(user_id) REFERENCES sd_users(id) ON DELETE CASCADE,
 CONSTRAINT fk_sd_access_membership FOREIGN KEY(membership_id) REFERENCES sd_dealer_memberships(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sd_user_tokens
 ADD access_request_id BIGINT UNSIGNED NULL,
 ADD CONSTRAINT fk_sd_token_access_request FOREIGN KEY(access_request_id) REFERENCES sd_dealer_access_requests(id) ON DELETE CASCADE;

ALTER TABLE sd_mail_outbox
 MODIFY message_type ENUM('newsletter_confirmation','configuration_confirmation','configuration_notification','dealer_email_confirmation','dealer_approval_notification','dealer_password','dealer_decision','staff_activation') NOT NULL,
 ADD user_id BIGINT UNSIGNED NULL,
 ADD user_token_id BIGINT UNSIGNED NULL,
 ADD payload_encrypted MEDIUMBLOB NULL,
 ADD CONSTRAINT fk_sd_mail_user FOREIGN KEY(user_id) REFERENCES sd_users(id) ON DELETE SET NULL,
 ADD CONSTRAINT fk_sd_mail_user_token FOREIGN KEY(user_token_id) REFERENCES sd_user_tokens(id) ON DELETE SET NULL;
