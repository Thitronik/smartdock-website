<?php
declare(strict_types=1);
namespace Smartdock;
require_once __DIR__ . '/security.php';

function reply(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

function database(array $config): \PDO {
    $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
    ];
    if (!empty($config['database_ssl_ca'])) {
        if (!is_readable($config['database_ssl_ca'])) throw new \RuntimeException('Database CA unavailable.');
        $options[\PDO::MYSQL_ATTR_SSL_CA] = $config['database_ssl_ca'];
        $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    } elseif (!str_starts_with($config['dsn'], 'mysql:host=127.0.0.1;')) {
        throw new \RuntimeException('Verified database TLS required.');
    }
    $db = new \PDO($config['dsn'], $config['database_user'], $config['database_password'], $options);
    $db->exec("SET time_zone = '+00:00'");
    return $db;
}

function run(array $config): never {
    $origin = 'https://' . ($_SERVER['HTTP_HOST'] ?? '');
    if (($_SERVER['HTTPS'] ?? '') !== 'on' || $origin !== $config['origin']) reply(403, ['error' => 'Zugriff nicht erlaubt.']);
    if (strlen($config['rate_key'] ?? '') < 64 || empty($config['dummy_password_hash'])) {
        reply(503, ['error' => 'Die Testanmeldung ist noch nicht eingerichtet.']);
    }
    if (!is_dir($config['session_directory']) || !is_writable($config['session_directory'])) throw new \RuntimeException('Session directory unavailable.');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_save_path($config['session_directory']);
    session_name('__Host-smartdock_test');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    if (!session_start()) throw new \RuntimeException('Session unavailable.');
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    $action = (string)($_GET['action'] ?? 'session');
    if ($action === 'bootstrap') {
        require_once __DIR__ . '/bootstrap.php';
        bootstrap_request($config);
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $read = in_array($action, ['session', 'admin', 'dealer'], true);
    if (($read && $method !== 'GET') || (!$read && $method !== 'POST')) reply(405, ['error' => 'Methode nicht erlaubt.']);
    $data = [];
    if (!$read) {
        if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $config['origin']
            || !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) reply(403, ['error' => 'Anfrage nicht erlaubt.']);
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) reply(413, ['error' => 'Anfrage zu groß.']);
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') reply(415, ['error' => 'JSON erforderlich.']);
        $raw = file_get_contents('php://input', false, null, 0, 16385);
        if ($raw === false || strlen($raw) > 16384) reply(413, ['error' => 'Anfrage zu groß.']);
        try { $data = json_decode($raw, false, 16, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { reply(400, ['error' => 'Ungültige Anfrage.']); }
        if (!$data instanceof \stdClass) reply(400, ['error' => 'Ungültige Anfrage.']);
        $data = (array)$data;
    }
    $db = database($config);
    $user = current_user($db, $config);
    if ($action === 'session') reply(200, ['csrf' => $_SESSION['csrf'], 'user' => $user ? public_user($user) : null]);
    if ($action === 'login') login($db, $config, $data);
    if ($action === 'logout') {
        if (isset($_SESSION['auth_id'])) {
            $q = $db->prepare('UPDATE sd_user_sessions SET revoked_at=UTC_TIMESTAMP(6) WHERE id=?'); $q->execute([$_SESSION['auth_id']]);
        }
        $_SESSION = []; session_destroy();
        setcookie(session_name(), '', ['expires' => time()-3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
        reply(200, ['ok' => true]);
    }
    if (!$user) reply(401, ['error' => 'Bitte anmelden.']);
    if ($action === 'dealer') {
        $q=$db->prepare("SELECT d.id,d.company_name FROM sd_dealer_memberships m JOIN sd_dealer d ON d.id=m.dealer_id WHERE m.user_id=? AND m.status='approved' AND d.status='active'");
        $q->execute([$user['id']]); $companies=$q->fetchAll();
        if (!$companies) reply(403, ['error'=>'Kein freigeschalteter Händlerzugang.']);
        reply(200, ['companies'=>$companies]);
    }
    require_staff($user);
    if ($action === 'admin') {
        reply(200, [
            'user'=>public_user($user),
            'dealers'=>$db->query('SELECT id,company_name,original_address,street,house_number,postal_code,city,country_code,public_email,public_phone,website,status,finder_status FROM sd_dealer ORDER BY company_name')->fetchAll(),
            'memberships'=>$db->query('SELECT m.id,m.dealer_id,m.user_id,m.status,u.first_name,u.last_name,u.email,u.status AS user_status FROM sd_dealer_memberships m JOIN sd_users u ON u.id=m.user_id ORDER BY m.requested_at DESC')->fetchAll(),
        ]);
    }
    if ($action === 'membership-decision') {
        require_staff($user, ['admin','approver']);
        $id=filter_var($data['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $decision=$data['status'] ?? '';
        if (!$id || !in_array($decision,['approved','rejected','suspended'],true)) reply(400,['error'=>'Ungültige Freigabe.']);
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT id,status FROM sd_dealer_memberships WHERE id=? FOR UPDATE'); $q->execute([$id]); $old=$q->fetch();
            if (!$old) { $db->rollBack(); reply(404,['error'=>'Zuordnung nicht gefunden.']); }
            $q=$db->prepare('UPDATE sd_dealer_memberships SET status=?,decided_at=UTC_TIMESTAMP(6),decided_by=? WHERE id=?');$q->execute([$decision,$user['id'],$id]);
            audit($db,$user['id'],'membership.decision','membership',$id,['from'=>$old['status'],'to'=>$decision]); $db->commit();
        } catch (\Throwable $error) { if($db->inTransaction())$db->rollBack();throw $error; }
        reply(200,['ok'=>true]);
    }
    if ($action === 'finder-decision') {
        require_staff($user,['admin','approver']);
        $id=filter_var($data['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$status=$data['status'] ?? '';
        if (!$id || !in_array($status,['approved','unlisted','suspended'],true)) reply(400,['error'=>'Ungültige Kartenfreigabe.']);
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT id,finder_status FROM sd_dealer WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch();
            if (!$old) {$db->rollBack();reply(404,['error'=>'Firma nicht gefunden.']);}
            $q=$db->prepare("UPDATE sd_dealer SET finder_status=?,finder_approved_at=CASE WHEN ?='approved' THEN UTC_TIMESTAMP(6) ELSE NULL END,finder_approved_by=CASE WHEN ?='approved' THEN ? ELSE NULL END WHERE id=?");
            $q->execute([$status,$status,$status,$user['id'],$id]);audit($db,$user['id'],'dealer.finder','dealer',$id,['from'=>$old['finder_status'],'to'=>$status]);$db->commit();
        }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
        reply(200,['ok'=>true]);
    }
    reply(404,['error'=>'Nicht gefunden.']);
}

function current_user(\PDO $db,array $config): ?array {
    if (empty($_SESSION['auth_id']) || empty($_SESSION['proof'])) return null;
    $q=$db->prepare('SELECT s.id AS session_id,s.staff_mfa_verified_at,u.* FROM sd_user_sessions s JOIN sd_users u ON u.id=s.user_id WHERE s.id=? AND s.session_hash=? AND s.csrf_hash=? AND s.revoked_at IS NULL AND s.expires_at>UTC_TIMESTAMP(6) AND s.last_seen_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE)');
    $q->execute([$_SESSION['auth_id'],hash('sha256',$_SESSION['proof'],true),hash('sha256',$_SESSION['csrf'],true)]);$user=$q->fetch();
    if (!$user || !active_user($user)) { unset($_SESSION['auth_id'],$_SESSION['proof']);return null; }
    $user['roles']=[];
    if (staff_allowed($user['email_normalized'],$config['staff_allowlist']) && !empty($user['staff_mfa_verified_at']) && staff_secret($config,$user['email_normalized']) !== '') {
        $q=$db->prepare('SELECT role FROM sd_staff_roles WHERE user_id=? AND revoked_at IS NULL');$q->execute([$user['id']]);$user['roles']=$q->fetchAll(\PDO::FETCH_COLUMN);
    }
    $q=$db->prepare('UPDATE sd_user_sessions SET last_seen_at=UTC_TIMESTAMP(6) WHERE id=?');$q->execute([$user['session_id']]);
    return $user;
}

function login(\PDO $db,array $config,array $data): never {
    if (!is_string($data['email'] ?? null) || !is_string($data['password'] ?? null) || strlen($data['password'])>1024 || !is_string($data['otp'] ?? '')) reply(400,['error'=>'Ungültige Anmeldung.']);
    try {$email=normalized_email($data['email']);}catch(\InvalidArgumentException){$email='invalid';}
    $key=hash_hmac('sha256',$email,$config['rate_key']);$ip=hash_hmac('sha256',$_SERVER['REMOTE_ADDR'] ?? '',$config['rate_key']);
    if (!rate_limit($config['state_directory'],'login-ip-'.$ip,time(),40)) reply(429,['error'=>'Zu viele Versuche. Bitte später erneut versuchen.']);
    if (!rate_limit($config['state_directory'],'login-account-'.$key,time())) reply(429,['error'=>'Zu viele Versuche. Bitte später erneut versuchen.']);
    $q=$db->prepare('SELECT * FROM sd_users WHERE email_normalized=? LIMIT 1');$q->execute([$email]);$user=$q->fetch();
    $valid=password_verify($data['password'],$user['password_hash'] ?? $config['dummy_password_hash']);
    if (!$valid || !$user || !active_user($user)) reply(401,['error'=>'Anmeldung nicht möglich.']);
    $q=$db->prepare('SELECT role FROM sd_staff_roles WHERE user_id=? AND revoked_at IS NULL');$q->execute([$user['id']]);$roles=$q->fetchAll(\PDO::FETCH_COLUMN);
    $staff=false;
    if ($roles) {
        $secret=staff_secret($config,$email);
        if (!staff_allowed($email,$config['staff_allowlist']) || !$secret) reply(401,['error'=>'Anmeldung nicht möglich.']);
        $step=totp_step($secret,$data['otp'] ?? '',time());
        if ($step===null || !consume_totp($config['state_directory'],'totp-'.$key,$step)) reply(401,['error'=>'Anmeldung nicht möglich.']);
        $staff=true;
    } else {
        $q=$db->prepare("SELECT m.id FROM sd_dealer_memberships m JOIN sd_dealer d ON d.id=m.dealer_id WHERE m.user_id=? AND m.status='approved' AND d.status='active' LIMIT 1");$q->execute([$user['id']]);
        if (!$q->fetch()) reply(401,['error'=>'Anmeldung nicht möglich.']);
    }
    // Re-authentication revokes the old login instead of leaving a parallel session.
    if (!empty($_SESSION['auth_id'])) {$q=$db->prepare('UPDATE sd_user_sessions SET revoked_at=UTC_TIMESTAMP(6) WHERE id=?');$q->execute([$_SESSION['auth_id']]);}
    if (!session_regenerate_id(true)) throw new \RuntimeException('Session unavailable.');
    $_SESSION=['csrf'=>bin2hex(random_bytes(32)),'proof'=>bin2hex(random_bytes(32))];
    $q=$db->prepare('INSERT INTO sd_user_sessions(user_id,session_hash,csrf_hash,expires_at,staff_mfa_verified_at) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 8 HOUR),?)');
    $q->execute([$user['id'],hash('sha256',$_SESSION['proof'],true),hash('sha256',$_SESSION['csrf'],true),$staff?gmdate('Y-m-d H:i:s'):null]);$_SESSION['auth_id']=(int)$db->lastInsertId();
    reply(200,['ok'=>true,'csrf'=>$_SESSION['csrf']]);
}

function public_user(array $user): array {return ['id'=>$user['id'],'firstName'=>$user['first_name'],'lastName'=>$user['last_name'],'roles'=>$user['roles'] ?? []];}
function require_staff(array $user,array $roles=['admin','approver']): void {
    if (!array_intersect($user['roles'] ?? [],$roles)) reply(403,['error'=>'Keine Verwaltungsberechtigung.']);
}
function audit(\PDO $db,int $userId,string $action,string $target,int $targetId,array $details): void {
    $q=$db->prepare('INSERT INTO sd_admin_audit_events(actor_user_id,action,target_type,target_id,details_json) VALUES(?,?,?,?,?)');$q->execute([$userId,$action,$target,$targetId,json_encode($details,JSON_THROW_ON_ERROR)]);
}
