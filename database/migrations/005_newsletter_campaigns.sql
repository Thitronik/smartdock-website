-- Run once on the backed-up test database after 004. Never run on production automatically.
CREATE TABLE sd_newsletter_campaigns (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 audience ENUM('dealer','customer') NOT NULL,
 content_json JSON NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 status ENUM('draft','queued') NOT NULL DEFAULT 'draft',
 recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 queued_at DATETIME(6) NULL,
 CONSTRAINT fk_sd_campaign_author FOREIGN KEY(created_by) REFERENCES sd_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE sd_mail_outbox
 MODIFY message_type ENUM('newsletter_confirmation','configuration_confirmation','configuration_notification','dealer_email_confirmation','dealer_approval_notification','dealer_password','dealer_decision','staff_activation','contact_notification','newsletter_campaign','newsletter_test') NOT NULL,
 ADD campaign_id BIGINT UNSIGNED NULL,
 ADD CONSTRAINT fk_sd_mail_campaign FOREIGN KEY(campaign_id) REFERENCES sd_newsletter_campaigns(id) ON DELETE SET NULL;
