-- ENTWURF: noch nicht auf Hetzner ausgeführt. MariaDB 10.6+.
-- Nur nach Schemaabgleich/Backup zunächst in einer getrennten Testdatenbank anwenden.
-- Migration 001 nicht erneut ausführen. Keine Daten, Konten oder Rollen werden angelegt.
-- DDL ist nicht als Ganzes rückrollbar; bei Teilfehlern stoppen und Bestand prüfen.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE sd_users (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 first_name VARCHAR(200) NOT NULL,
 last_name VARCHAR(200) NOT NULL,
 email VARCHAR(254) NOT NULL,
 email_normalized VARCHAR(254) COLLATE utf8mb4_bin NOT NULL,
 password_hash VARCHAR(255) NULL,
 password_set_at DATETIME(6) NULL,
 email_verified_at DATETIME(6) NULL,
 status ENUM('invited','active','suspended') NOT NULL DEFAULT 'invited',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY uq_sd_user_email(email_normalized)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sd_dealer (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 source_system VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 source_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 company_name VARCHAR(254) NOT NULL,
 name_de VARCHAR(254) NULL,
 name_en VARCHAR(254) NULL,
 name_fr VARCHAR(254) NULL,
 original_address TEXT NULL,
 street VARCHAR(200) NULL,
 house_number VARCHAR(50) NULL,
 postal_code VARCHAR(32) NULL,
 city VARCHAR(200) NULL,
 region VARCHAR(200) NULL,
 country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 original_country VARCHAR(100) NULL,
 public_email VARCHAR(254) NULL,
 public_phone VARCHAR(100) NULL,
 website VARCHAR(2048) NULL,
 website_secondary VARCHAR(2048) NULL,
 latitude DECIMAL(10,7) NULL,
 longitude DECIMAL(10,7) NULL,
 status ENUM('active','archived') NOT NULL DEFAULT 'active',
 finder_status ENUM('unlisted','pending','approved','suspended') NOT NULL DEFAULT 'unlisted',
 finder_approved_at DATETIME(6) NULL,
 finder_approved_by BIGINT UNSIGNED NULL,
 source_created_at DATETIME(6) NULL,
 source_updated_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY uq_sd_dealer_source(source_system,source_id),
 KEY ix_sd_dealer_finder(status,finder_status),
 CONSTRAINT ck_sd_dealer_source CHECK ((source_system IS NULL AND source_id IS NULL) OR (source_system IS NOT NULL AND source_id IS NOT NULL)),
 CONSTRAINT ck_sd_dealer_coordinates CHECK ((latitude IS NULL AND longitude IS NULL) OR (latitude IS NOT NULL AND longitude IS NOT NULL AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)),
 CONSTRAINT fk_sd_dealer_approver FOREIGN KEY(finder_approved_by) REFERENCES sd_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sd_dealer_memberships (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 dealer_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
 requested_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 decided_at DATETIME(6) NULL,
 decided_by BIGINT UNSIGNED NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY uq_sd_dealer_membership(dealer_id,user_id),
 KEY ix_sd_membership_status(status,requested_at),
 KEY ix_sd_membership_user(user_id,status),
 CONSTRAINT fk_sd_membership_dealer FOREIGN KEY(dealer_id) REFERENCES sd_dealer(id) ON DELETE CASCADE,
 CONSTRAINT fk_sd_membership_user FOREIGN KEY(user_id) REFERENCES sd_users(id) ON DELETE CASCADE,
 CONSTRAINT fk_sd_membership_approver FOREIGN KEY(decided_by) REFERENCES sd_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sd_staff_roles (
 user_id BIGINT UNSIGNED NOT NULL,
 role ENUM('admin','approver','editor') NOT NULL,
 granted_by BIGINT UNSIGNED NULL,
 granted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 revoked_at DATETIME(6) NULL,
 PRIMARY KEY(user_id,role),
 CONSTRAINT fk_sd_staff_user FOREIGN KEY(user_id) REFERENCES sd_users(id) ON DELETE CASCADE,
 CONSTRAINT fk_sd_staff_granter FOREIGN KEY(granted_by) REFERENCES sd_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sd_user_tokens (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id BIGINT UNSIGNED NOT NULL,
 membership_id BIGINT UNSIGNED NULL,
 purpose ENUM('verify_email','set_password','reset_password','approve_membership') NOT NULL,
 token_hash BINARY(32) NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 consumed_at DATETIME(6) NULL,
 revoked_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY uq_sd_user_token_hash(token_hash),
 KEY ix_sd_user_token_expiry(expires_at),
 KEY ix_sd_user_token_owner(user_id,purpose),
 CONSTRAINT ck_sd_user_token_expiry CHECK (expires_at > created_at),
 CONSTRAINT ck_sd_approval_token CHECK (purpose <> 'approve_membership' OR (membership_id IS NOT NULL AND expires_at <= DATE_ADD(created_at, INTERVAL 7 DAY))),
 CONSTRAINT fk_sd_user_token_user FOREIGN KEY(user_id) REFERENCES sd_users(id) ON DELETE CASCADE,
 CONSTRAINT fk_sd_user_token_membership FOREIGN KEY(membership_id) REFERENCES sd_dealer_memberships(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sd_user_sessions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id BIGINT UNSIGNED NOT NULL,
 session_hash BINARY(32) NOT NULL,
 csrf_hash BINARY(32) NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 last_seen_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 staff_mfa_verified_at DATETIME(6) NULL,
 revoked_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY uq_sd_session_hash(session_hash),
 KEY ix_sd_session_user(user_id,revoked_at),
 KEY ix_sd_session_expiry(expires_at),
 CONSTRAINT ck_sd_session_expiry CHECK(expires_at > created_at),
 CONSTRAINT fk_sd_session_user FOREIGN KEY(user_id) REFERENCES sd_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Einwilligung/Bestätigung/Abmeldung bleiben in den bestehenden Newslettertabellen.
-- Keine automatische Newsletteranmeldung aus Firma, Benutzerkonto oder Freigabe.
CREATE TABLE sd_dealer_newsletter_subscriptions (
 dealer_id BIGINT UNSIGNED NOT NULL,
 subscriber_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(dealer_id,subscriber_id),
 CONSTRAINT fk_sd_dealer_newsletter_dealer FOREIGN KEY(dealer_id) REFERENCES sd_dealer(id) ON DELETE CASCADE,
 CONSTRAINT fk_sd_dealer_newsletter_subscriber FOREIGN KEY(subscriber_id) REFERENCES sd_newsletter_subscribers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sd_admin_audit_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 actor_user_id BIGINT UNSIGNED NULL,
 action VARCHAR(64) NOT NULL,
 target_type ENUM('dealer','user','membership','content','staff_role') NOT NULL,
 target_id BIGINT UNSIGNED NULL,
 details_json JSON NULL,
 occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 KEY ix_sd_admin_event_time(occurred_at),
 KEY ix_sd_admin_event_target(target_type,target_id),
 CONSTRAINT fk_sd_admin_event_actor FOREIGN KEY(actor_user_id) REFERENCES sd_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
