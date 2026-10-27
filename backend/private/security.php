<?php
declare(strict_types=1);
namespace Smartdock;

function normalized_email(string $email): string {
    $email = trim($email);
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('Ungültige E-Mail-Adresse.');
    }
    // Matches the application's documented case-insensitive login identity.
    return strtolower($email);
}

function staff_allowed(string $email, array $allowlist): bool {
    try { $email = normalized_email($email); } catch (\InvalidArgumentException) { return false; }
    return in_array($email, $allowlist, true);
}

function active_user(array $user): bool {
    return ($user['status'] ?? '') === 'active'
        && !empty($user['email_verified_at']) && !empty($user['password_set_at'])
        && !empty($user['password_hash']);
}

function dealer_allowed(array $user, array $membership, array $dealer): bool {
    return active_user($user) && ($membership['status'] ?? '') === 'approved'
        && ($dealer['status'] ?? '') === 'active'
        && (int)($membership['user_id'] ?? 0) === (int)($user['id'] ?? -1)
        && (int)($membership['dealer_id'] ?? 0) === (int)($dealer['id'] ?? -1);
}

function base32_decode(string $encoded): string {
    if (!preg_match('/^[A-Z2-7]{32,128}$/D', $encoded)) throw new \InvalidArgumentException('Ungültiger TOTP-Schlüssel.');
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = 0; $value = 0; $bytes = '';
    foreach (str_split($encoded) as $character) {
        $value = ($value << 5) | strpos($alphabet, $character); $bits += 5;
        if ($bits >= 8) { $bits -= 8; $bytes .= chr(($value >> $bits) & 255); $value &= (1 << $bits) - 1; }
    }
    return $bytes;
}

function totp_code(string $secret, int $step, int $digits = 6): string {
    if ($step < 0 || !in_array($digits, [6, 8], true)) throw new \InvalidArgumentException('Ungültige TOTP-Parameter.');
    $counter = pack('N2', intdiv($step, 4294967296), $step % 4294967296);
    $hash = hash_hmac('sha1', $counter, base32_decode($secret), true);
    $offset = ord($hash[19]) & 15;
    $binary = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
    return str_pad((string)($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

function totp_step(string $secret, string $code, int $time): ?int {
    if (!preg_match('/^[0-9]{6}$/D', $code)) return null;
    $step = intdiv($time, 30);
    foreach ([0, -1, 1] as $delta) {
        if ($step + $delta >= 0 && hash_equals(totp_code($secret, $step + $delta), $code)) return $step + $delta;
    }
    return null;
}

// All mutable rate/replay data stays outside the webroot. flock makes concurrent
// login attempts atomic. No email, IP, code, password or secret is stored here.
function locked_state(string $directory, string $key, callable $change): mixed {
    if (!is_dir($directory) || !is_writable($directory)) throw new \RuntimeException('Private state unavailable.');
    $path = $directory . '/' . hash('sha256', $key) . '.json';
    $file = fopen($path, 'c+');
    if (!$file) throw new \RuntimeException('Private state unavailable.');
    chmod($path, 0600);
    try {
        if (!flock($file, LOCK_EX)) throw new \RuntimeException('Private state unavailable.');
        $raw = stream_get_contents($file);
        $state = $raw === '' ? [] : json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        [$next, $result] = $change($state);
        $json = json_encode($next, JSON_THROW_ON_ERROR);
        rewind($file); if (!ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) {
            throw new \RuntimeException('Private state unavailable.');
        }
        return $result;
    } finally { flock($file, LOCK_UN); fclose($file); }
}

function rate_limit(string $directory, string $key, int $now, int $maximum = 10): bool {
    return locked_state($directory, $key, static function(array $state) use ($now, $maximum): array {
        $start = (int)($state['start'] ?? 0);
        $count = ($now - $start >= 900 || $now < $start) ? 0 : (int)($state['count'] ?? 0);
        if ($count === 0) $start = $now;
        $allowed = $count < $maximum;
        return [['start' => $start, 'count' => min($count + 1, $maximum + 1)], $allowed];
    });
}

function consume_totp(string $directory, string $key, int $step): bool {
    return locked_state($directory, $key, static function(array $state) use ($step): array {
        $previous = (int)($state['step'] ?? -1);
        return [['step' => max($previous, $step)], $step > $previous];
    });
}
