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

function staff_allowed(string $email, array $allowlist, string $domain = 'thitronik.de'): bool {
    try { $email = normalized_email($email); } catch (\InvalidArgumentException) { return false; }
    return substr($email,strrpos($email,'@')+1) === $domain && in_array($email, $allowlist, true);
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

function base32_encode(string $bytes): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits=0;$value=0;$encoded='';
    foreach(str_split($bytes) as $byte){$value=($value<<8)|ord($byte);$bits+=8;while($bits>=5){$bits-=5;$encoded.=$alphabet[($value>>$bits)&31];}$value&=(1<<$bits)-1;}
    if($bits)$encoded.=$alphabet[($value<<(5-$bits))&31];return $encoded;
}

function mfa_path(array $config,string $email): string {
    return $config['mfa_directory'].'/'.hash_hmac('sha256',$email,$config['rate_key']).'.key';
}
function mfa_key(array $config): string {
    if(!function_exists('openssl_encrypt')||!function_exists('openssl_decrypt'))throw new \RuntimeException('MFA encryption unavailable.');
    return hash_hmac('sha256','smartdock-mfa-encryption-v1',$config['rate_key'],true);
}
function staff_secret(array $config,string $email): string {
    if(!empty($config['staff_totp_secrets'][$email]))return $config['staff_totp_secrets'][$email];
    if(empty($config['mfa_directory']))return '';
    $file=mfa_path($config,$email);if(!is_file($file))return '';
    $bytes=file_get_contents($file);if($bytes===false||strlen($bytes)<33||substr($bytes,0,4)!=='SDM1')throw new \RuntimeException('MFA unavailable.');
    $secret=openssl_decrypt(substr($bytes,32),'aes-256-gcm',mfa_key($config),OPENSSL_RAW_DATA,substr($bytes,4,12),substr($bytes,16,16),$email);
    if($secret===false)throw new \RuntimeException('MFA unavailable.');return $secret;
}
function store_staff_secret(array $config,string $email,string $secret): void {
    $directory=$config['mfa_directory'];if(!is_dir($directory)||!is_writable($directory))throw new \RuntimeException('MFA directory unavailable.');
    $nonce=random_bytes(12);$tag='';$cipher=openssl_encrypt($secret,'aes-256-gcm',mfa_key($config),OPENSSL_RAW_DATA,$nonce,$tag,$email,16);
    if($cipher===false||strlen($tag)!==16)throw new \RuntimeException('MFA encryption unavailable.');
    $bytes='SDM1'.$nonce.$tag.$cipher;
    $file=@fopen(mfa_path($config,$email),'x');if(!$file)throw new \RuntimeException('MFA already configured.');
    try{chmod(mfa_path($config,$email),0600);if(fwrite($file,$bytes)!==strlen($bytes)||!fflush($file))throw new \RuntimeException('MFA unavailable.');}finally{fclose($file);}
}

// Deletion never substitutes for the explicit suspension step.
function access_can_delete(string $status,bool $protected=false): bool {
    return $status==='suspended'&&!$protected;
}
