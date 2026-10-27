<?php
declare(strict_types=1);
// Copy OUTSIDE public_html to smartdock-private-test/config.php.
// No real addresses, credentials or TOTP secrets belong in GitHub.
return [
    'origin' => 'https://test.smartdock.de',
    'dsn' => 'mysql:host=DATABASE_HOST;dbname=TEST_DATABASE;charset=utf8mb4',
    'database_user' => 'READ_WRITE_USER',
    'database_password' => '',
    'database_ssl_ca' => __DIR__ . '/sqlca.pem', // official Hetzner database CA
    'state_directory' => __DIR__ . '/state',
    'session_directory' => __DIR__ . '/sessions',
    'mfa_directory' => __DIR__ . '/mfa', // encrypted enrollment records, 0700
    'rate_key' => '', // at least 32 random bytes, stored as hex
    'dummy_password_hash' => '', // bcrypt/Argon2 hash for uniform login failure
    'staff_allowlist' => [], // exact normalized addresses, never domain wildcard
    'staff_totp_secrets' => [], // normalized email => private Base32 secret
    // Optional first-account setup only: token_hash (SHA-256), email, expires_at.
    // After the first role is created, this setup endpoint stays closed.
    'bootstrap' => [],
];
