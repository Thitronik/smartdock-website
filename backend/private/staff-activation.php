<?php
declare(strict_types=1);
namespace Smartdock;

// Caller holds the first-admin lock and a database transaction. The existing
// user was selected by the private operator-approved setup, never by form input.
function staff_activation_pending(\PDO $db,array $config,array $user): void {
    $id=(int)$user['id'];$email=$user['email_normalized'];
    if(!staff_allowed($email,$config['staff_allowlist'],$config['staff_email_domain'] ?? 'thitronik.de')||$email!==($config['bootstrap']['email'] ?? ''))throw new \RuntimeException('Personal approval required.');
    $q=$db->prepare("UPDATE sd_users SET status='invited',email_verified_at=NULL WHERE id=?");$q->execute([$id]);
    $q=$db->prepare('UPDATE sd_staff_roles SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND revoked_at IS NULL');$q->execute([$id]);
    $q=$db->prepare('UPDATE sd_user_sessions SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND revoked_at IS NULL');$q->execute([$id]);
    $q=$db->prepare("UPDATE sd_user_tokens SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND purpose='verify_email' AND consumed_at IS NULL AND revoked_at IS NULL");$q->execute([$id]);
    $token=bin2hex(random_bytes(32));
    $q=$db->prepare("INSERT INTO sd_user_tokens(user_id,purpose,token_hash,expires_at) VALUES(?,'verify_email',UNHEX(?),DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 7 DAY))");$q->execute([$id,hash('sha256',$token)]);$tokenId=(int)$db->lastInsertId();
    audit($db,$id,'bootstrap.pending','user',$id,['method'=>'private-operator-setup','token_id'=>$tokenId]);
    if(!staff_activation_mail($config,$email,$token))throw new \RuntimeException('Activation mail unavailable.');
}

function staff_activation_reconfirm(\PDO $db,array $config,array $user): never {
    $finish=static function(string $message,int $status=200):never{http_response_code($status);echo bootstrap_document('SMARTDOCK · E-Mail nachbestätigen','<h1>E-Mail bestätigen</h1><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p>');exit;};
    if(empty($config['mail_from']))$finish('Die Nachbestätigung wartet auf den eingerichteten E-Mail-Versand. Der Adminzugang bleibt bis zur Bestätigung gesperrt.',503);
    $q=$db->prepare("SELECT id FROM sd_admin_audit_events WHERE action IN ('bootstrap.admin_created','bootstrap.pending') AND target_type='user' AND target_id=? LIMIT 1");$q->execute([$user['id']]);
    if(!$q->fetchColumn()||!in_array($user['status'],['active','invited'],true)||!$user['password_hash']||!staff_secret($config,$user['email_normalized']))$finish('Für dieses Konto ist eine separate persönliche Freigabe erforderlich.',403);
    if(($_SERVER['REQUEST_METHOD'] ?? '')==='POST'){
        if(($_SERVER['HTTP_ORIGIN'] ?? '')!==$config['origin']||!is_string($_POST['csrf'] ?? null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))$finish('Anfrage nicht erlaubt.',403);
        if(!rate_limit($config['state_directory'],'staff-reconfirm-'.hash('sha256',$user['email_normalized']),time(),3))$finish('Zu viele Versuche. Bitte später erneut versuchen.',429);
        $lock=fopen($config['state_directory'].'/bootstrap.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Activation unavailable.');chmod($config['state_directory'].'/bootstrap.lock',0600);
        try{
            $db->beginTransaction();$q=$db->prepare('SELECT * FROM sd_users WHERE id=? FOR UPDATE');$q->execute([$user['id']]);$current=$q->fetch();
            if(!$current||!in_array($current['status'],['active','invited'],true)||staff_mail_verified($db,$current)){$db->rollBack();$finish('Für dieses Konto ist keine Nachbestätigung verfügbar.',409);}
            $q=$db->prepare('SELECT user_id FROM sd_staff_roles WHERE user_id<>? AND revoked_at IS NULL LIMIT 1');$q->execute([$current['id']]);
            if($q->fetchColumn()){$db->rollBack();$finish('Weitere Zugänge benötigen eine separate persönliche Freigabe.',403);}
            staff_activation_pending($db,$config,$current);$db->commit();
            $finish('Der Aktivierungslink wurde zum Versand an Ihr persönlich freigegebenes Thitronik-Postfach übergeben. Passwort und Authenticator bleiben erhalten. Bitte bestätigen Sie die E-Mail-Adresse über diesen Link.');
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    echo bootstrap_document('SMARTDOCK · E-Mail nachbestätigen','<h1>E-Mail nachbestätigen</h1><p>Für den bereits eingerichteten Adminzugang fehlt noch die Bestätigung des Thitronik-Postfachs. Bis dahin ist die Administration gesperrt. Passwort und Authenticator bleiben erhalten.</p><form method="post" action="/api/index.php?action=bootstrap"><input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8').'"><button class="primary-button">Aktivierungslink per E-Mail anfordern</button></form>');exit;
}

function staff_activation_mail(array $config,string $email,string $token): bool {
    $from=$config['mail_from'] ?? '';
    if(!is_string($from)||!preg_match('/^[a-zA-Z0-9._+-]+@smartdock\.de$/D',$from))return false;
    $url=$config['origin'].'/api/index.php?action=activate-admin&token='.$token;
    $body="Ihr persoenlich freigegebener SMARTDOCK-Adminzugang wartet auf Ihre E-Mail-Bestaetigung.\n\nOeffnen Sie diesen Link und bestaetigen Sie die Aktivierung:\n".$url."\n\nDer Link ist einmalig und 7 Tage gueltig. Erst danach koennen Sie sich mit Passwort und Authenticator anmelden.\nFalls Sie diese Einrichtung nicht veranlasst haben, aktivieren Sie den Zugang nicht.\n";
    // CI capture is impossible on Apache/FPM and never sends real messages.
    if(PHP_SAPI==='cli-server'&&getenv('SD_BACKEND_TEST_RUN')==='1'){
        return file_put_contents(getenv('SD_TEST_STATE').'/activation-mail.json',json_encode(['to'=>$email,'body'=>$body],JSON_THROW_ON_ERROR))!==false;
    }
    return mail($email,'SMARTDOCK: Adminzugang bestaetigen',$body,['From'=>'SMARTDOCK <'.$from.'>','Content-Type'=>'text/plain; charset=UTF-8','MIME-Version'=>'1.0'],'-f'.$from);
}

function staff_activation_request(array $config): never {
    header('Content-Type: text/html; charset=utf-8');header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    $finish=static function(string $message,int $status=403):never{http_response_code($status);echo bootstrap_document('SMARTDOCK · E-Mail bestätigen','<h1>E-Mail bestätigen</h1><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p>');exit;};
    $method=$_SERVER['REQUEST_METHOD'] ?? '';
    if(!in_array($method,['GET','POST'],true))$finish('Methode nicht erlaubt.',405);
    $ip=hash_hmac('sha256',$_SERVER['REMOTE_ADDR'] ?? '',$config['rate_key']);
    if(!rate_limit($config['state_directory'],'staff-activation-'.$ip,time(),40))$finish('Zu viele Versuche. Bitte später erneut versuchen.',429);
    if($method==='GET'&&isset($_GET['token'])){
        $token=$_GET['token'];if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token))$finish('Der Aktivierungslink ist ungültig oder abgelaufen.');
        session_regenerate_id(true);$_SESSION['staff_activation_hash']=hash('sha256',$token);
        header('Location: /api/index.php?action=activate-admin',true,303);exit;
    }
    header('Referrer-Policy: same-origin');
    $hash=$_SESSION['staff_activation_hash'] ?? '';
    if(!preg_match('/^[a-f0-9]{64}$/D',$hash))$finish('Bitte den Aktivierungslink aus Ihrer E-Mail öffnen.');
    $db=database($config);
    $query="SELECT t.id AS token_id,t.user_id,u.* FROM sd_user_tokens t JOIN sd_users u ON u.id=t.user_id WHERE t.token_hash=UNHEX(?) AND t.purpose='verify_email' AND t.consumed_at IS NULL AND t.revoked_at IS NULL AND t.expires_at>UTC_TIMESTAMP(6) AND u.status='invited' AND u.email_verified_at IS NULL";
    $q=$db->prepare($query);$q->execute([$hash]);$user=$q->fetch();
    if(!$user||!staff_allowed($user['email_normalized'],$config['staff_allowlist'],$config['staff_email_domain'] ?? 'thitronik.de')||$user['email_normalized']!==($config['bootstrap']['email'] ?? ''))$finish('Der Aktivierungslink ist ungültig oder abgelaufen.');
    // This endpoint can only complete the operator-approved FIRST admin setup.
    $q=$db->prepare("SELECT id FROM sd_admin_audit_events WHERE action='bootstrap.pending' AND target_type='user' AND target_id=? AND JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.token_id'))=? LIMIT 1");$q->execute([$user['user_id'],(string)$user['token_id']]);
    if(!$q->fetchColumn()||(int)$db->query('SELECT COUNT(*) FROM sd_staff_roles WHERE revoked_at IS NULL')->fetchColumn()>0)$finish('Für dieses Konto ist eine persönliche Freigabe erforderlich.');
    if($method==='POST'){
        if(($_SERVER['HTTP_ORIGIN'] ?? '')!==$config['origin']||!is_string($_POST['csrf'] ?? null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))$finish('Anfrage nicht erlaubt.');
        $lock=fopen($config['state_directory'].'/bootstrap.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Activation unavailable.');
        chmod($config['state_directory'].'/bootstrap.lock',0600);
        try{
            $db->beginTransaction();$q=$db->prepare($query.' FOR UPDATE');$q->execute([$hash]);$user=$q->fetch();
            if(!$user||!staff_allowed($user['email_normalized'],$config['staff_allowlist'],$config['staff_email_domain'] ?? 'thitronik.de')||$user['email_normalized']!==($config['bootstrap']['email'] ?? '')||(int)$db->query('SELECT COUNT(*) FROM sd_staff_roles WHERE revoked_at IS NULL')->fetchColumn()>0){$db->rollBack();$finish('Der Aktivierungslink ist nicht mehr gültig.');}
            $q=$db->prepare('UPDATE sd_user_tokens SET consumed_at=UTC_TIMESTAMP(6) WHERE id=? AND consumed_at IS NULL');$q->execute([$user['token_id']]);
            $q=$db->prepare("UPDATE sd_users SET status='active',email_verified_at=UTC_TIMESTAMP(6) WHERE id=?");$q->execute([$user['user_id']]);
            $q=$db->prepare("INSERT INTO sd_staff_roles(user_id,role,granted_by) VALUES(?,'admin',?) ON DUPLICATE KEY UPDATE granted_at=UTC_TIMESTAMP(6),granted_by=VALUES(granted_by),revoked_at=NULL");$q->execute([$user['user_id'],$user['user_id']]);
            audit($db,(int)$user['user_id'],'staff.email_verified','user',(int)$user['user_id'],['email_hash'=>hash('sha256',$user['email_normalized']),'token_id'=>(int)$user['token_id']]);
            $db->commit();unset($_SESSION['staff_activation_hash']);session_regenerate_id(true);
            $finish('Ihre E-Mail-Adresse ist bestätigt. Der persönlich freigegebene Adminzugang ist aktiv. Sie können sich jetzt unter /admin/ mit Passwort und Authenticator anmelden.',200);
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    echo bootstrap_document('SMARTDOCK · Adminzugang bestätigen','<h1>Adminzugang bestätigen</h1><p>Dieser Schritt bestätigt den Zugriff auf Ihr Thitronik-Postfach und aktiviert den persönlich freigegebenen Adminzugang.</p><form method="post" action="/api/index.php?action=activate-admin"><input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8').'"><button class="primary-button">E-Mail bestätigen und Adminzugang aktivieren</button></form>');exit;
}
