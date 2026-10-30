<?php
declare(strict_types=1);
namespace Smartdock;

// The owner is an existing, verified account. This never bootstraps or grants it a role.
function staff_owner_email(): string { return 'marketing@thitronik.de'; }
function staff_is_owner(array $user): bool {
    return ($user['email_normalized']??'')===staff_owner_email() && in_array('admin',$user['roles']??[],true);
}
function require_staff_owner(array $user): void {
    if(!staff_is_owner($user))reply(403,['error'=>'Nur das Marketing-Hauptkonto verwaltet Adminzugänge.']);
}
function staff_address_authorized(\PDO $db,array $config,array $user): bool {
    $email=$user['email_normalized'];
    if(!str_ends_with($email,'@thitronik.de'))return false;
    if(staff_allowed($email,$config['staff_allowlist'],$config['staff_email_domain']??'thitronik.de'))return true;
    $q=$db->prepare("SELECT s.user_id FROM sd_staff_roles s JOIN sd_users owner ON owner.id=s.granted_by JOIN sd_staff_roles os ON os.user_id=owner.id AND os.role='admin' AND os.revoked_at IS NULL WHERE s.user_id=? AND s.role='admin' AND s.revoked_at IS NULL AND owner.email_normalized=? AND owner.status='active'");
    $q->execute([$user['id'],staff_owner_email()]);return (bool)$q->fetchColumn();
}
function staff_management_snapshot(\PDO $db,array $config,array $user): ?array {
    if(!staff_is_owner($user))return null;
    $rows=$db->query("SELECT u.id,u.first_name,u.last_name,u.email,u.email_normalized,u.status,MAX(s.revoked_at IS NULL AND s.role='admin') AS active_role FROM sd_users u LEFT JOIN sd_staff_roles s ON s.user_id=u.id WHERE s.user_id IS NOT NULL OR EXISTS(SELECT 1 FROM sd_admin_audit_events a WHERE a.target_id=u.id AND a.target_type='user' AND a.action='staff.invited') GROUP BY u.id ORDER BY u.email")->fetchAll();
    foreach($rows as &$row){
        $row['protected']=$row['email_normalized']===staff_owner_email();
        // Display the shared marketing account as a team in the admin list.
        if($row['protected']){$row['first_name']='Marketing';$row['last_name']='';}
        unset($row['email_normalized']);
    }
    return ['ownerEmail'=>staff_owner_email(),'accounts'=>$rows];
}
function staff_invite(\PDO $db,array $config,array $owner,array $data): never {
    require_staff_owner($owner);
    try{$email=normalized_email(access_field($data,'email',254));$first=access_field($data,'first_name',200);$last=access_field($data,'last_name',200);}catch(\InvalidArgumentException){reply(400,['error'=>'Bitte Name und gültige E-Mail-Adresse eingeben.']);}
    if(!str_ends_with($email,'@thitronik.de')||$email===staff_owner_email())reply(400,['error'=>'Nur weitere persönliche @thitronik.de-Adressen können eingeladen werden.']);
    access_limit($config,'staff-invite',$email);
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT * FROM sd_users WHERE id=? FOR UPDATE');$q->execute([$owner['id']]);$q->fetch();
        $q=$db->prepare('SELECT id FROM sd_users WHERE email_normalized=? FOR UPDATE');$q->execute([$email]);
        if($q->fetchColumn()){$db->rollBack();reply(409,['error'=>'Diese Adresse ist bereits vorhanden. Bestehende Konten werden nicht durch eine Einladung überschrieben.']);}
        $q=$db->prepare("INSERT INTO sd_users(first_name,last_name,email,email_normalized,status) VALUES(?,?,?,?,'invited')");$q->execute([$first,$last,$email,$email]);$id=(int)$db->lastInsertId();
        $token=access_token($db,$id,'verify_email',null,null,7*24);
        audit($db,(int)$owner['id'],'staff.invited','user',$id,['token_id'=>$token['id']]);
        queue_mail($db,$config,'staff_activation',$email,'SMARTDOCK: Einladung zur Administration',"Du wurdest vom SMARTDOCK Marketing-Hauptkonto zur Administration eingeladen.\n\nBestätige deine E-Mail-Adresse und richte dein persönliches Passwort und den Authenticator ein:\n\n".access_link($config,'staff-invitation',$token['raw'])."\n\nE-Mail-Adresse für die spätere Anmeldung: ".$email."\nDer Link ist einmalig und 7 Tage gültig. Ohne diese Einrichtung ist kein Adminzugang möglich.",'staff-invitation-'.$token['id'],$id,$token['id']);
        $db->commit();
    }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    dispatch_mail($db,$config);reply(200,['ok'=>true]);
}
function staff_revoke(\PDO $db,array $config,array $owner,array $data): never {
    require_staff_owner($owner);$id=filter_var($data['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$id)reply(400,['error'=>'Konto fehlt.']);
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT * FROM sd_users WHERE id=? FOR UPDATE');$q->execute([$id]);$target=$q->fetch();
        if(!$target||$target['email_normalized']===staff_owner_email()){$db->rollBack();reply(403,['error'=>'Das Marketing-Hauptkonto kann nicht gesperrt oder gelöscht werden.']);}
        $q=$db->prepare("SELECT id FROM sd_admin_audit_events WHERE action='staff.invited' AND target_type='user' AND target_id=? UNION SELECT user_id FROM sd_staff_roles WHERE user_id=? LIMIT 1");$q->execute([$id,$id]);
        if(!$q->fetchColumn()){$db->rollBack();reply(404,['error'=>'Kein Adminzugang.']);}
        $q=$db->prepare("UPDATE sd_staff_roles SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND revoked_at IS NULL");$q->execute([$id]);
        $q=$db->prepare("UPDATE sd_users SET status='suspended' WHERE id=?");$q->execute([$id]);
        foreach(['sd_user_sessions','sd_user_tokens'] as $table){$q=$db->prepare("UPDATE $table SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND revoked_at IS NULL");$q->execute([$id]);}
        audit($db,(int)$owner['id'],'staff.revoked','user',$id,[]);$db->commit();
    }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    reply(200,['ok'=>true]);
}
function staff_invitation_page(array $config): never {
    require_once __DIR__.'/bootstrap.php';
    header('Content-Type: text/html; charset=UTF-8');header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    $finish=static function(string $message,int $status=403):never{http_response_code($status);echo bootstrap_document('SMARTDOCK · Admin-Einladung','<h1>Admin-Einladung</h1><p>'.access_escape($message).'</p><p><a href="/admin/">Zur Admin-Anmeldung</a></p>');exit;};
    $method=$_SERVER['REQUEST_METHOD']??'';if(!in_array($method,['GET','POST'],true))$finish('Methode nicht erlaubt.',405);
    if(!rate_limit($config['state_directory'],'staff-setup-'.hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'',$config['rate_key']),time(),40))$finish('Zu viele Versuche. Bitte später erneut versuchen.',429);
    if($method==='GET'&&isset($_GET['token'])){
        $token=$_GET['token'];if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token))$finish('Link ungültig.');
        $hash=hash('sha256',$token);if(($_SESSION['staff_invite_hash']??'')!==$hash)unset($_SESSION['staff_invite_secret']);
        session_regenerate_id(true);$_SESSION['staff_invite_hash']=$hash;header('Location: /api/index.php?action=staff-invitation',true,303);exit;
    }
    header('Referrer-Policy: same-origin');$hash=$_SESSION['staff_invite_hash']??'';
    if(!preg_match('/^[a-f0-9]{64}$/D',$hash))$finish('Bitte den Einladungslink aus deiner E-Mail öffnen.');
    $db=database($config);
    $sql="SELECT u.*,t.id AS token_id,a.actor_user_id AS inviter FROM sd_user_tokens t JOIN sd_users u ON u.id=t.user_id JOIN sd_admin_audit_events a ON a.target_id=u.id AND a.target_type='user' AND a.action='staff.invited' AND JSON_UNQUOTE(JSON_EXTRACT(a.details_json,'$.token_id'))=CAST(t.id AS CHAR) JOIN sd_users owner ON owner.id=a.actor_user_id JOIN sd_staff_roles os ON os.user_id=owner.id AND os.role='admin' AND os.revoked_at IS NULL WHERE t.token_hash=UNHEX(?) AND t.purpose='verify_email' AND t.revoked_at IS NULL AND t.consumed_at IS NULL AND t.expires_at>UTC_TIMESTAMP(6) AND u.status='invited' AND u.password_hash IS NULL AND u.email_verified_at IS NULL AND owner.email_normalized=? AND owner.status='active'";
    $q=$db->prepare($sql);$q->execute([$hash,staff_owner_email()]);$user=$q->fetch();
    if(!$user||!str_ends_with($user['email_normalized'],'@thitronik.de')||$user['email_normalized']===staff_owner_email())$finish('Der Link ist ungültig, abgelaufen oder widerrufen.');
    $_SESSION['staff_invite_secret']??=base32_encode(random_bytes(20));$secret=$_SESSION['staff_invite_secret'];$error='';
    if($method==='POST'){
        if(($_SERVER['HTTP_ORIGIN']??'')!==$config['origin']||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))$finish('Anfrage nicht erlaubt.');
        $password=$_POST['password']??null;$repeat=$_POST['repeat']??null;$otp=$_POST['otp']??null;
        if(!is_string($password)||!is_string($repeat)||strlen($password)<12||strlen($password)>72||!hash_equals($password,$repeat))$error='Passwörter müssen übereinstimmen und 12 bis 72 Zeichen lang sein.';
        elseif(!is_string($otp)||totp_step($secret,$otp,time())===null)$error='Bitte den aktuellen Code aus deiner Authenticator-App eingeben.';
        else{
            $stored=false;$db->beginTransaction();
            try{
                $q=$db->prepare($sql.' FOR UPDATE');$q->execute([$hash,staff_owner_email()]);$fresh=$q->fetch();
                if(!$fresh){$db->rollBack();$finish('Der Link ist nicht mehr gültig.');}
                store_staff_secret($config,$fresh['email_normalized'],$secret);$stored=true;
                $q=$db->prepare("UPDATE sd_users SET password_hash=?,password_set_at=UTC_TIMESTAMP(6),email_verified_at=UTC_TIMESTAMP(6),status='active' WHERE id=?");$q->execute([password_hash($password,PASSWORD_DEFAULT),$fresh['id']]);
                $q=$db->prepare("INSERT INTO sd_staff_roles(user_id,role,granted_by) VALUES(?,'admin',?)");$q->execute([$fresh['id'],$fresh['inviter']]);
                $q=$db->prepare('UPDATE sd_user_tokens SET consumed_at=UTC_TIMESTAMP(6) WHERE id=?');$q->execute([$fresh['token_id']]);
                audit($db,(int)$fresh['id'],'staff.email_verified','user',(int)$fresh['id'],['email_hash'=>hash('sha256',$fresh['email_normalized']),'token_id'=>(int)$fresh['token_id']]);
                $db->commit();unset($_SESSION['staff_invite_hash'],$_SESSION['staff_invite_secret']);session_regenerate_id(true);$finish('Dein Adminzugang ist aktiv. Melde dich mit deiner E-Mail-Adresse, deinem Passwort und Authenticator an.',200);
            }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();if($stored)unlink(mfa_path($config,$user['email_normalized']));throw $e;}
        }
    }
    $e=static fn(string $s):string=>access_escape($s);$provisioning='otpauth://totp/'.rawurlencode('SMARTDOCK:'.$user['email_normalized']).'?secret='.$secret.'&issuer=SMARTDOCK&algorithm=SHA1&digits=6&period=30';
    echo bootstrap_document('SMARTDOCK · Adminzugang einrichten','<h1>Adminzugang einrichten</h1><p>E-Mail-Adresse: <strong>'.$e($user['email']).'</strong></p><p>In deiner Authenticator-App ein zeitbasiertes Konto hinzufügen und den QR-Code scannen.</p><details class="setup-qr"><summary>QR-Code anzeigen</summary><div id="setup-qr" data-provisioning="'.$e($provisioning).'"></div></details><details class="setup-secret"><summary>Einrichtungsschlüssel anzeigen</summary><code>'.$e($secret).'</code></details><form method="post" action="/api/index.php?action=staff-invitation"><input type="hidden" name="csrf" value="'.$e($_SESSION['csrf']).'"><label>Neues Passwort<input name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label><label>Passwort wiederholen<input name="repeat" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label><label>Aktueller Authenticator-Code<input name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label><p role="status">'.$e($error).'</p><button class="primary-button">E-Mail bestätigen und Adminzugang aktivieren</button></form>');exit;
}
