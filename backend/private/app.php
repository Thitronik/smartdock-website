<?php
declare(strict_types=1);
namespace Smartdock;
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/staff-management.php';
require_once __DIR__ . '/newsletter.php';

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
    require_once __DIR__.'/dealer-access.php';
    require_once __DIR__.'/public-forms.php';
    if($action==='staff-invitation')staff_invitation_page($config);
    if(in_array($action,['newsletter-confirm','newsletter-unsubscribe'],true))newsletter_page($config,$action);
    if(in_array($action,['dealer-confirm','dealer-password','dealer-approve'],true))access_token_page($config,$action);
    if (in_array($action,['bootstrap','activate-admin'],true)) {
        require_once __DIR__ . '/bootstrap.php';
        if($action==='activate-admin'){require_once __DIR__.'/staff-activation.php';staff_activation_request($config);}
        bootstrap_request($config);
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $read = in_array($action, ['session', 'admin', 'dealer','finder','newsletter-admin'], true);
    if (($read && $method !== 'GET') || (!$read && $method !== 'POST')) reply(405, ['error' => 'Methode nicht erlaubt.']);
    $data = [];
    if (!$read) {
        if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $config['origin']
            || !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) reply(403, ['error' => 'Anfrage nicht erlaubt.']);
        if ($action === 'newsletter-upload') {
            if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 5300000) reply(413, ['error'=>'Das Bild darf höchstens 5 MB groß sein.']);
            if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'multipart/form-data') reply(415, ['error'=>'Bilddatei erforderlich.']);
        } else {
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) reply(413, ['error' => 'Anfrage zu groß.']);
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') reply(415, ['error' => 'JSON erforderlich.']);
        $raw = file_get_contents('php://input', false, null, 0, 16385);
        if ($raw === false || strlen($raw) > 16384) reply(413, ['error' => 'Anfrage zu groß.']);
        try { $data = json_decode($raw, false, 16, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { reply(400, ['error' => 'Ungültige Anfrage.']); }
        if (!$data instanceof \stdClass) reply(400, ['error' => 'Ungültige Anfrage.']);
        $data = (array)$data;
        }
    }
    $db = database($config);
    $user = current_user($db, $config);
    if ($action === 'session') {$_SESSION['public_form_started']??=time();reply(200, ['csrf' => $_SESSION['csrf'], 'user' => $user ? public_user($user) : null]);}
    if ($action === 'login') login($db, $config, $data);
    if ($action === 'dealer-request') dealer_request($db,$config,$data);
    if ($action === 'password-request') dealer_password_request($db,$config,$data);
    if ($action === 'newsletter-request') newsletter_request($db,$config,$data);
    if ($action === 'contact-request') public_inquiry($db,$config,$data,'contact');
    if ($action === 'configuration-request') public_inquiry($db,$config,$data,'configuration');
    if ($action === 'finder') {
        $rows=$db->query("SELECT id,company_name,name_de,name_en,name_fr,original_address,street,house_number,postal_code,city,country_code,original_country,public_email,public_phone,website,website_secondary,latitude,longitude FROM sd_dealer WHERE status='active' AND finder_status='approved' AND latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY company_name")->fetchAll();
        reply(200,['dealers'=>$rows]);
    }
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
    if(in_array($action,['newsletter-upload','newsletter-admin','newsletter-save','newsletter-preview','newsletter-test','newsletter-send'],true))newsletter_route($db,$config,$user,$action,$data);
    if ($action === 'admin') {
        reply(200, [
            'user'=>public_user($user),
            'staffManagement'=>staff_management_snapshot($db,$config,$user),
            'dealers'=>$db->query('SELECT id,company_name,original_address,street,house_number,postal_code,city,country_code,public_email,public_phone,website,latitude,longitude,status,finder_status FROM sd_dealer ORDER BY company_name')->fetchAll(),
            'memberships'=>$db->query('SELECT m.id,m.dealer_id,m.user_id,m.status,u.first_name,u.last_name,u.email,u.status AS user_status FROM sd_dealer_memberships m JOIN sd_users u ON u.id=m.user_id ORDER BY m.requested_at DESC')->fetchAll(),
            'requests'=>$db->query("SELECT r.*,u.email FROM sd_dealer_access_requests r JOIN sd_users u ON u.id=r.user_id ORDER BY r.created_at DESC")->fetchAll(),
            'mailStatus'=>$db->query('SELECT status,COUNT(*) AS count FROM sd_mail_outbox GROUP BY status')->fetchAll(),
            'contacts'=>$db->query('SELECT id,public_reference,contact_name,email,company,phone,message,language,status,created_at FROM sd_contact_requests ORDER BY created_at DESC LIMIT 100')->fetchAll(),
            'configurations'=>$db->query('SELECT id,public_reference,contact_name,email,company,phone,selection_json,price_snapshot_json,status,created_at FROM sd_configuration_requests ORDER BY created_at DESC LIMIT 100')->fetchAll(),
            'newsletterSubscribers'=>$db->query('SELECT email,status,created_at,confirmed_at FROM sd_newsletter_subscribers ORDER BY created_at DESC')->fetchAll(),
            'newsletterStatus'=>$db->query('SELECT status,COUNT(*) AS count FROM sd_newsletter_subscribers GROUP BY status')->fetchAll(),
        ]);
    }
    if($action==='staff-invite'){staff_invite($db,$config,$user,$data);}
    if($action==='staff-delete'){staff_delete($db,$config,$user,$data);}
    if($action==='staff-revoke'){staff_revoke($db,$config,$user,$data);}
    if($action==='dealer-confirmation-resend'){dealer_confirmation_reminder($db,$config,$user,$data);}
    if($action==='dealer-update') {
        require_staff($user,['admin','approver']);
        $id=filter_var($data['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)reply(400,['error'=>'Firma fehlt.']);
        try {
            $v=[];foreach(['company_name'=>254,'original_address'=>2000,'street'=>200,'house_number'=>50,'postal_code'=>32,'city'=>200,'country_code'=>2,'public_email'=>254,'public_phone'=>100,'website'=>2048] as $key=>$max)$v[$key]=access_field($data,$key,$max,in_array($key,['company_name','country_code'],true));
            $v['country_code']=strtoupper($v['country_code']);if(!preg_match('/\A[A-Z]{2}\z/D',$v['country_code']))throw new \InvalidArgumentException();
            if($v['public_email']!=='')$v['public_email']=normalized_email($v['public_email']);
            if($v['website']!==''&&(!filter_var($v['website'],FILTER_VALIDATE_URL)||!in_array(strtolower(parse_url($v['website'],PHP_URL_SCHEME)??''),['http','https'],true)))throw new \InvalidArgumentException();
            $lat=$data['latitude']??'';$lng=$data['longitude']??'';
            if($lat===''&&$lng===''){$lat=null;$lng=null;}elseif(!is_numeric($lat)||!is_numeric($lng)||abs((float)$lat)>90||abs((float)$lng)>180)throw new \InvalidArgumentException();
        }catch(\InvalidArgumentException){reply(400,['error'=>'Bitte Firmenname, Ländercode, öffentliche Kontakte und Koordinaten prüfen.']);}
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT id FROM sd_dealer WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetch()){$db->rollBack();reply(404,['error'=>'Firma nicht gefunden.']);}
            $q=$db->prepare('UPDATE sd_dealer SET company_name=?,original_address=?,street=?,house_number=?,postal_code=?,city=?,country_code=?,public_email=?,public_phone=?,website=?,latitude=?,longitude=? WHERE id=?');
            $q->execute([...array_values($v),$lat,$lng,$id]);audit($db,(int)$user['id'],'dealer.updated','dealer',$id,['fields'=>array_keys($v)]);$db->commit();
        }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
        reply(200,['ok'=>true]);
    }
    if($action==='membership-delete') {
        require_staff($user,['admin','approver']);
        $id=filter_var($data['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$id)reply(400,['error'=>'Zugang fehlt.']);
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT m.id,m.status,u.email_normalized FROM sd_dealer_memberships m JOIN sd_users u ON u.id=m.user_id WHERE m.id=? FOR UPDATE');$q->execute([$id]);$target=$q->fetch();
            if(!$target){$db->rollBack();reply(404,['error'=>'Zugang nicht gefunden.']);}
            if(!access_can_delete($target['status'],$target['email_normalized']===staff_owner_email())){$db->rollBack();reply(409,['error'=>'Nur zuvor gesperrte Zugänge dürfen gelöscht werden. Das Marketing-Hauptkonto ist geschützt.']);}
            $q=$db->prepare("UPDATE sd_mail_outbox SET status='cancelled' WHERE status IN ('queued','failed') AND user_token_id IN (SELECT id FROM sd_user_tokens WHERE membership_id=?)");$q->execute([$id]);
            // Delete only this company access, not other memberships or the firm.
            $q=$db->prepare("DELETE FROM sd_dealer_memberships WHERE id=? AND status='suspended'");$q->execute([$id]);
            audit($db,(int)$user['id'],'membership.deleted','membership',$id,[]);$db->commit();
        }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
        reply(200,['ok'=>true]);
    }
    if ($action === 'membership-decision') {
        require_staff($user, ['admin','approver']);
        $id=filter_var($data['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $decision=$data['status'] ?? '';
        if (!$id || !in_array($decision,['approved','rejected','suspended'],true)) reply(400,['error'=>'Ungültige Freigabe.']);
        $db->beginTransaction();
        try {
            if($decision==='approved') {
                approve_dealer_membership($db,$config,$id,$user);$db->commit();dispatch_mail($db,$config);reply(200,['ok'=>true]);
            }
            $q=$db->prepare('SELECT id,status FROM sd_dealer_memberships WHERE id=? FOR UPDATE'); $q->execute([$id]); $old=$q->fetch();
            if (!$old) { $db->rollBack(); reply(404,['error'=>'Zuordnung nicht gefunden.']); }
            $q=$db->prepare('UPDATE sd_dealer_memberships SET status=?,decided_at=UTC_TIMESTAMP(6),decided_by=? WHERE id=?');$q->execute([$decision,$user['id'],$id]);
            $q=$db->prepare("UPDATE sd_dealer_access_requests SET status='rejected' WHERE membership_id=?");$q->execute([$id]);
            audit($db,$user['id'],'membership.decision','membership',$id,['from'=>$old['status'],'to'=>$decision]); $db->commit();
        } catch (\Throwable $error) { if($db->inTransaction())$db->rollBack();throw $error; }
        reply(200,['ok'=>true]);
    }
    if($action==='membership-company') {
        require_staff($user,['admin','approver']);
        $id=filter_var($data['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$dealerId=filter_var($data['dealer_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$id||!$dealerId)reply(400,['error'=>'Bitte eine Firma auswählen.']);
        $db->beginTransaction();
        try {
            $q=$db->prepare("SELECT id,dealer_id,user_id FROM sd_dealer_memberships WHERE id=? FOR UPDATE");$q->execute([$id]);$old=$q->fetch();
            $q=$db->prepare("SELECT id FROM sd_dealer WHERE id=? AND status='active'");$q->execute([$dealerId]);
            if(!$old||!$q->fetch()){$db->rollBack();reply(400,['error'=>'Der Zugang muss vorhanden und die gewählte Firma aktiv sein.']);}
            if((int)$old['dealer_id']===$dealerId){$db->rollBack();reply(200,['ok'=>true]);}
            $q=$db->prepare('SELECT id FROM sd_dealer_memberships WHERE dealer_id=? AND user_id=?');$q->execute([$dealerId,$old['user_id']]);if($q->fetch()){$db->rollBack();reply(409,['error'=>'Dieser Mitarbeiter ist der Firma bereits zugeordnet.']);}
            $q=$db->prepare("UPDATE sd_dealer_memberships SET dealer_id=?,status='pending',decided_at=NULL,decided_by=NULL WHERE id=?");$q->execute([$dealerId,$id]);
            $q=$db->prepare("UPDATE sd_user_tokens SET revoked_at=UTC_TIMESTAMP(6) WHERE membership_id=? AND consumed_at IS NULL AND revoked_at IS NULL");$q->execute([$id]);
            audit($db,(int)$user['id'],'membership.company','membership',$id,['from'=>$old['dealer_id'],'to'=>$dealerId]);$db->commit();
        }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
        reply(200,['ok'=>true]);
    }
    if($action==='mail-retry') {require_staff($user,['admin']);dispatch_mail($db,$config);reply(200,['ok'=>true]);}
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

function staff_mail_verified(\PDO $db,array $user): bool {
    $q=$db->prepare("SELECT id FROM sd_admin_audit_events WHERE action='staff.email_verified' AND target_type='user' AND target_id=? AND JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.email_hash'))=? LIMIT 1");
    $q->execute([$user['id'],hash('sha256',$user['email_normalized'])]);
    return !empty($user['email_verified_at']) && (bool)$q->fetchColumn();
}

function current_user(\PDO $db,array $config): ?array {
    if (empty($_SESSION['auth_id']) || empty($_SESSION['proof'])) return null;
    $q=$db->prepare('SELECT s.id AS session_id,s.staff_mfa_verified_at,u.* FROM sd_user_sessions s JOIN sd_users u ON u.id=s.user_id WHERE s.id=? AND s.session_hash=? AND s.csrf_hash=? AND s.revoked_at IS NULL AND s.expires_at>UTC_TIMESTAMP(6) AND s.last_seen_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE)');
    $q->execute([$_SESSION['auth_id'],hash('sha256',$_SESSION['proof'],true),hash('sha256',$_SESSION['csrf'],true)]);$user=$q->fetch();
    if (!$user || !active_user($user)) { unset($_SESSION['auth_id'],$_SESSION['proof']);return null; }
    $user['roles']=[];
    if (staff_address_authorized($db,$config,$user) && staff_mail_verified($db,$user) && !empty($user['staff_mfa_verified_at']) && staff_secret($config,$user['email_normalized']) !== '') {
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
        if (!staff_address_authorized($db,$config,$user) || !staff_mail_verified($db,$user) || !$secret) reply(401,['error'=>'Anmeldung nicht möglich.']);
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
