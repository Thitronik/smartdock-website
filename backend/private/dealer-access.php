<?php
declare(strict_types=1);
namespace Smartdock;
require_once __DIR__.'/mail.php';

function access_field(array $data,string $key,int $max,bool $required=true): string {
    if(!is_string($data[$key]??''))throw new \InvalidArgumentException('Invalid field.');
    $value=trim($data[$key]??'');
    if(($required&&$value==='')||strlen($value)>$max||preg_match('/[\x00-\x1F\x7F]/',$value))throw new \InvalidArgumentException('Invalid field.');
    return $value;
}
function access_limit(array $config,string $purpose,string $email=''): void {
    $ip=hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'',$config['rate_key']);
    if(!rate_limit($config['state_directory'],$purpose.'-ip-'.$ip,time(),15)||($email!==''&&!rate_limit($config['state_directory'],$purpose.'-email-'.hash_hmac('sha256',$email,$config['rate_key']),time(),3)))reply(429,['error'=>'Zu viele Anfragen. Bitte später erneut versuchen.']);
}
function access_token(\PDO $db,int $userId,string $purpose,?int $membershipId=null,?int $requestId=null,int $hours=24): array {
    $raw=bin2hex(random_bytes(32));
    $q=$db->prepare('INSERT INTO sd_user_tokens(user_id,purpose,token_hash,membership_id,access_request_id,expires_at) VALUES(?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL ? HOUR))');
    $q->execute([$userId,$purpose,hash('sha256',$raw,true),$membershipId,$requestId,$hours]);
    return ['id'=>(int)$db->lastInsertId(),'raw'=>$raw];
}
function access_link(array $config,string $action,string $raw): string {return $config['origin'].'/api/index.php?action='.$action.'&token='.$raw;}
function queue_dealer_confirmation(\PDO $db,array $config,array $user,int $requestId): void {
    $q=$db->prepare("UPDATE sd_user_tokens SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND purpose='verify_email' AND access_request_id=? AND consumed_at IS NULL AND revoked_at IS NULL");$q->execute([$user['id'],$requestId]);
    $token=access_token($db,(int)$user['id'],'verify_email',null,$requestId);
    queue_mail($db,$config,'dealer_email_confirmation',$user['email'],'SMARTDOCK: E-Mail-Adresse bestätigen',"Bitte bestätigen Sie Ihre Händler-Zugangsanfrage:\n\n".access_link($config,'dealer-confirm',$token['raw'])."\n\nDer Link ist 24 Stunden gültig. Die Bestätigung erteilt noch keinen Zugang. Wenn Sie keine Anfrage gestellt haben, ignorieren Sie diese E-Mail.",'dealer-confirm-'.$token['id'],(int)$user['id'],$token['id']);
}
function dealer_confirmation_reminder(\PDO $db,array $config,array $staff,array $data): never {
    require_staff($staff,['admin','approver']);
    $id=filter_var($data['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$id)reply(400,['error'=>'Zugangsanfrage fehlt.']);
    access_limit($config,'dealer-reminder',(string)$id);
    $db->beginTransaction();
    try {
        $q=$db->prepare("SELECT u.* FROM sd_users u JOIN sd_dealer_access_requests r ON r.user_id=u.id WHERE r.id=? AND r.status='email_pending' AND u.status='invited' AND u.email_verified_at IS NULL AND NOT EXISTS(SELECT 1 FROM sd_staff_roles s WHERE s.user_id=u.id) FOR UPDATE");$q->execute([$id]);$target=$q->fetch();
        if(!$target){$db->rollBack();reply(409,['error'=>'Diese Anfrage wartet nicht mehr auf eine E-Mail-Bestätigung.']);}
        queue_dealer_confirmation($db,$config,$target,$id);
        audit($db,(int)$staff['id'],'dealer.confirmation_resent','user',(int)$target['id'],['request_id'=>$id]);
        $db->commit();
    }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
    dispatch_mail($db,$config);reply(200,['ok'=>true]);
}
function dealer_request(\PDO $db,array $config,array $data): never {
    try {
        $email=normalized_email(access_field($data,'email',254));
        $limits=['first_name'=>200,'last_name'=>200,'company'=>254,'street'=>200,'house_number'=>50,'postal_code'=>32,'city'=>200,'country'=>100,'phone'=>100];$v=[];
        foreach($limits as $key=>$max)$v[$key]=access_field($data,$key,$max,$key!=='phone');
        if(($data['privacy']??false)!==true)throw new \InvalidArgumentException('Privacy required.');
        $language=$data['language']??'de';if(!in_array($language,['de','en','fr','nl','es'],true))throw new \InvalidArgumentException('Invalid language.');
    }catch(\InvalidArgumentException){reply(400,['error'=>'Bitte die Pflichtfelder und den Datenschutzhinweis prüfen.']);}
    access_limit($config,'dealer-request',$email);
    $response=['ok'=>true,'message'=>'Wenn eine neue Zugangsanfrage möglich ist, erhalten Sie eine E-Mail zur Bestätigung.'];
    if(!empty($data['website']))reply(200,$response);
    $db->beginTransaction();
    try {
        // Never overwrite an existing person or grant an affiliation from a public form.
        $q=$db->prepare('SELECT * FROM sd_users WHERE email_normalized=? FOR UPDATE');$q->execute([$email]);
        if($existing=$q->fetch()){
            if($existing['status']==='invited'&&$existing['email_verified_at']===null){
                $q=$db->prepare("SELECT r.id FROM sd_dealer_access_requests r WHERE r.user_id=? AND r.status='email_pending' AND NOT EXISTS(SELECT 1 FROM sd_staff_roles s WHERE s.user_id=r.user_id) ORDER BY r.id DESC LIMIT 1 FOR UPDATE");$q->execute([$existing['id']]);
                if($pending=$q->fetch())queue_dealer_confirmation($db,$config,$existing,(int)$pending['id']);
            }
            $db->commit();dispatch_mail($db,$config);reply(200,$response);
        }
        $q=$db->prepare('INSERT INTO sd_users(first_name,last_name,email,email_normalized) VALUES(?,?,?,?)');$q->execute([$v['first_name'],$v['last_name'],$email,$email]);$userId=(int)$db->lastInsertId();
        $q=$db->prepare('INSERT INTO sd_dealer_access_requests(user_id,first_name,last_name,company_name,street,house_number,postal_code,city,country,phone,language,privacy_version) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$userId,$v['first_name'],$v['last_name'],$v['company'],$v['street'],$v['house_number'],$v['postal_code'],$v['city'],$v['country'],$v['phone']?:null,$language,'dealer-access-2026-10']);$requestId=(int)$db->lastInsertId();
        queue_dealer_confirmation($db,$config,['id'=>$userId,'email'=>$email],$requestId);
        $db->commit();
    }catch(\PDOException $error){if($db->inTransaction())$db->rollBack();if($error->getCode()!=='23000')throw $error;reply(200,$response);}
    catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
    dispatch_mail($db,$config);reply(200,$response);
}
function dealer_password_request(\PDO $db,array $config,array $data): never {
    try{$email=normalized_email(access_field($data,'email',254));}catch(\InvalidArgumentException){reply(400,['error'=>'Bitte eine gültige E-Mail-Adresse eingeben.']);}
    access_limit($config,'password-request',$email);
    $db->beginTransaction();
    try {
        $q=$db->prepare("SELECT u.* FROM sd_users u WHERE email_normalized=? AND status IN ('active','invited') AND email_verified_at IS NOT NULL AND EXISTS(SELECT 1 FROM sd_dealer_memberships m JOIN sd_dealer d ON d.id=m.dealer_id WHERE m.user_id=u.id AND m.status='approved' AND d.status='active') AND NOT EXISTS(SELECT 1 FROM sd_staff_roles s WHERE s.user_id=u.id) FOR UPDATE");$q->execute([$email]);$user=$q->fetch();
        if($user){queue_dealer_password($db,$config,$user);}
        $db->commit();
    }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
    dispatch_mail($db,$config);reply(200,['ok'=>true,'message'=>'Falls ein freigeschalteter Händlerzugang vorliegt, erhalten Sie eine E-Mail zum Festlegen Ihres Passworts.']);
}
function queue_dealer_password(\PDO $db,array $config,array $user): void {
    $q=$db->prepare("UPDATE sd_user_tokens SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND purpose IN ('set_password','reset_password') AND consumed_at IS NULL AND revoked_at IS NULL");$q->execute([$user['id']]);
    $token=access_token($db,(int)$user['id'],empty($user['password_hash'])?'set_password':'reset_password',null,null,1);
    queue_mail($db,$config,'dealer_password',$user['email'],'SMARTDOCK: Passwort festlegen',"Ihr persönlicher Händlerzugang ist freigeschaltet. Legen Sie Ihr Passwort über diesen Link fest:\n\n".access_link($config,'dealer-password',$token['raw'])."\n\nDer Link gilt eine Stunde und kann einmal verwendet werden. Falls Sie dies nicht angefordert haben, ignorieren Sie diese E-Mail.",'dealer-password-'.$token['id'],(int)$user['id'],$token['id']);
}
function access_html(int $status,string $title,string $content): never {
    require_once __DIR__.'/bootstrap.php';http_response_code($status);header('Content-Type: text/html; charset=UTF-8');header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    echo bootstrap_document($title,'<h1>'.access_escape($title).'</h1>'.$content);exit;
}
function access_escape(string $text): string {return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function confirm_dealer_request(\PDO $db,array $config,array $token,array $user): void {
    $q=$db->prepare("SELECT * FROM sd_dealer_access_requests WHERE id=? AND user_id=? AND status='email_pending' FOR UPDATE");$q->execute([$token['access_request_id'],$user['id']]);$request=$q->fetch();
    if(!$request)throw new \RuntimeException('Request unavailable.');
    $q=$db->prepare('UPDATE sd_users SET email_verified_at=UTC_TIMESTAMP(6) WHERE id=?');$q->execute([$user['id']]);
    // A public request never changes an existing firm's public address or approval.
    // Only an unambiguous full company/address match may reuse its company record.
    $q=$db->prepare("SELECT id FROM sd_dealer WHERE status='active' AND company_name=? AND street=? AND house_number=? AND postal_code=? AND city=? AND original_country=? LIMIT 2 FOR UPDATE");
    $q->execute([$request['company_name'],$request['street'],$request['house_number'],$request['postal_code'],$request['city'],$request['country']]);$matches=$q->fetchAll();
    if(count($matches)===1)$dealerId=(int)$matches[0]['id'];
    else {
        // Imported international addresses remain intact. Staff can assign the
        // pending employee to an existing company before approving the request.
        $country=strtoupper(trim($request['country']));$countries=['DEUTSCHLAND'=>'DE','GERMANY'=>'DE','FRANCE'=>'FR','FRANKREICH'=>'FR','SPAIN'=>'ES','ESPAÑA'=>'ES','NEDERLAND'=>'NL','NETHERLANDS'=>'NL','AUSTRALIA'=>'AU','AUSTRALIEN'=>'AU','SWITZERLAND'=>'CH','SCHWEIZ'=>'CH'];
        $country=$countries[$country]??(preg_match('/\A[A-Z]{2}\z/D',$country)?$country:'ZZ');
        $q=$db->prepare("INSERT INTO sd_dealer(company_name,street,house_number,postal_code,city,country_code,original_country,finder_status) VALUES(?,?,?,?,?,?,?,'pending')");
        $q->execute([$request['company_name'],$request['street'],$request['house_number'],$request['postal_code'],$request['city'],$country,$request['country']]);$dealerId=(int)$db->lastInsertId();
    }
    $q=$db->prepare('INSERT INTO sd_dealer_memberships(dealer_id,user_id) VALUES(?,?)');$q->execute([$dealerId,$user['id']]);$membershipId=(int)$db->lastInsertId();
    $q=$db->prepare("UPDATE sd_dealer_access_requests SET status='pending',membership_id=? WHERE id=?");$q->execute([$membershipId,$request['id']]);
    $approval=access_token($db,(int)$user['id'],'approve_membership',$membershipId,(int)$request['id'],168);
    $lines=['Neue bestätigte Händler-Zugangsanfrage.',''];
    foreach(['first_name'=>'Vorname','last_name'=>'Nachname','company_name'=>'Unternehmen','street'=>'Straße','house_number'=>'Hausnummer','postal_code'=>'PLZ','city'=>'Stadt','country'=>'Land','phone'=>'Telefon'] as $key=>$label)$lines[]=$label.': '.($request[$key]??'');
    $lines[]='E-Mail: '.$user['email'];$lines[]='';$lines[]='Händler für den Händlerbereich freischalten!';$lines[]=access_link($config,'dealer-approve',$approval['raw']);$lines[]='';$lines[]='7 Tage gültig. Nur nach Anmeldung eines persönlich freigeschalteten Thitronik-Mitarbeiters. Die Kartenfreigabe erfolgt separat in der Administration.';
    $recipient=$config['dealer_approval_email']??'';if($recipient==='')throw new \RuntimeException('Approval recipient unavailable.');
    queue_mail($db,$config,'dealer_approval_notification',$recipient,'SMARTDOCK: Händlerzugang prüfen',implode("\n",$lines),'dealer-approval-'.$approval['id'],(int)$user['id'],$approval['id']);
}
function approve_dealer_membership(\PDO $db,array $config,int $id,array $staff): void {
    require_staff($staff,['admin','approver']);
    $q=$db->prepare("SELECT m.*,u.email,u.password_hash,u.email_verified_at,u.status AS user_status FROM sd_dealer_memberships m JOIN sd_users u ON u.id=m.user_id JOIN sd_dealer d ON d.id=m.dealer_id WHERE m.id=? AND d.status='active' FOR UPDATE");$q->execute([$id]);$membership=$q->fetch();
    if(!$membership||!$membership['email_verified_at']||$membership['user_status']==='suspended')throw new \InvalidArgumentException('Eine bestätigte E-Mail und eine aktive Firma sind erforderlich.');
    if($membership['status']==='approved')return;
    $q=$db->prepare("UPDATE sd_dealer_memberships SET status='approved',decided_at=UTC_TIMESTAMP(6),decided_by=? WHERE id=?");$q->execute([$staff['id'],$id]);
    $q=$db->prepare("UPDATE sd_dealer_access_requests SET status='approved' WHERE membership_id=?");$q->execute([$id]);
    audit($db,(int)$staff['id'],'membership.decision','membership',$id,['from'=>$membership['status'],'to'=>'approved']);
    queue_dealer_password($db,$config,['id'=>$membership['user_id'],'email'=>$membership['email'],'password_hash'=>$membership['password_hash']]);
}
function access_form(string $button,string $extra=''): string {return '<form method="post"><input type="hidden" name="csrf" value="'.access_escape($_SESSION['csrf']).'">'.$extra.'<button class="primary-button" style="background:#bceb02;border-color:#bceb02;color:#1d1d1b;white-space:normal">'.access_escape($button).'</button></form>';}
function access_token_page(array $config,string $action): never {
    $db=database($config);access_limit($config,'token-page');$method=$_SERVER['REQUEST_METHOD']??'GET';
    if(!in_array($method,['GET','POST'],true))access_html(405,'Nicht erlaubt','<p>Methode nicht erlaubt.</p>');
    if($method==='GET'&&isset($_GET['token'])) {
        $raw=$_GET['token'];if(!is_string($raw)||!preg_match('/\A[a-f0-9]{64}\z/D',$raw))access_html(403,'Ungültiger Link','<p>Bitte einen aktuellen Link anfordern.</p>');
        session_regenerate_id(true);$_SESSION['access-token-'.$action]=hash('sha256',$raw,true);
        header('Referrer-Policy: no-referrer');header('Location: /api/index.php?action='.$action,true,303);exit;
    }
    $hash=$_SESSION['access-token-'.$action]??'';
    $purpose=$action==='dealer-confirm'?['verify_email']:($action==='dealer-approve'?['approve_membership']:['set_password','reset_password']);
    $q=$db->prepare("SELECT t.*,u.email,u.email_normalized,u.status AS user_status,u.email_verified_at FROM sd_user_tokens t JOIN sd_users u ON u.id=t.user_id WHERE t.token_hash=? AND t.consumed_at IS NULL AND t.revoked_at IS NULL AND t.expires_at>UTC_TIMESTAMP(6)");$q->execute([$hash]);$token=$q->fetch();
    if(!$token||!in_array($token['purpose'],$purpose,true)||$token['user_status']==='suspended'||($action==='dealer-confirm'&&!$token['access_request_id']))access_html(403,'Link nicht gültig','<p>Der Link ist abgelaufen oder wurde bereits verwendet. Bitte einen neuen Link anfordern.</p>');
    $staff=null;
    if($action==='dealer-approve') {
        $staff=current_user($db,$config);
        if(!$staff||!array_intersect($staff['roles']??[],['admin','approver']))access_html(403,'Anmeldung erforderlich','<p>Bitte mit Ihrem freigeschalteten Thitronik-Konto <a href="/admin/" target="_blank" rel="noopener">die Administration in einem neuen Tab öffnen und anmelden</a>. Kehren Sie anschließend zu dieser Seite zurück und laden Sie sie neu.</p>');
    }
    $error='';
    if($method==='POST') {
        if(($_SERVER['HTTP_ORIGIN']??'')!==$config['origin']||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))access_html(403,'Anfrage nicht erlaubt','<p>Bitte die Seite neu öffnen.</p>');
        $password='';
        if($action==='dealer-password') {
            $password=$_POST['password']??'';$repeat=$_POST['repeat']??'';
            if(!is_string($password)||strlen($password)<12||strlen($password)>72||!is_string($repeat)||!hash_equals($password,$repeat))$error='<p role="alert">Bitte zweimal dasselbe Passwort mit 12 bis 72 Zeichen eingeben.</p>';
        }
        if($error==='') {
            $db->beginTransaction();
            try {
                $q=$db->prepare('SELECT * FROM sd_users WHERE id=? FOR UPDATE');$q->execute([$token['user_id']]);$owner=$q->fetch();
                $q=$db->prepare('SELECT * FROM sd_user_tokens WHERE id=? AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP(6) FOR UPDATE');$q->execute([$token['id']]);$fresh=$q->fetch();
                if(!$fresh||!$owner||$owner['status']==='suspended'){$db->rollBack();access_html(403,'Link nicht gültig','<p>Bitte einen neuen Link anfordern.</p>');}
                if($action==='dealer-confirm')confirm_dealer_request($db,$config,$fresh,$owner);
                elseif($action==='dealer-approve') {
                    $q=$db->prepare("SELECT id FROM sd_dealer_memberships WHERE id=? AND status='pending' FOR UPDATE");$q->execute([$fresh['membership_id']]);
                    if(!$q->fetch()){$db->rollBack();access_html(409,'Bereits entschieden','<p>Diese Anfrage wurde bereits bearbeitet. Änderungen sind nur direkt in der Administration möglich.</p>');}
                    approve_dealer_membership($db,$config,(int)$fresh['membership_id'],$staff);
                }
                else {
                    $q=$db->prepare("SELECT m.id FROM sd_dealer_memberships m JOIN sd_dealer d ON d.id=m.dealer_id WHERE m.user_id=? AND m.status='approved' AND d.status='active' LIMIT 1");$q->execute([$owner['id']]);
                    $s=$db->prepare('SELECT user_id FROM sd_staff_roles WHERE user_id=? LIMIT 1');$s->execute([$owner['id']]);
                    if(!$q->fetch()||$s->fetch()||!$owner['email_verified_at']){$db->rollBack();access_html(403,'Kein Händlerzugang','<p>Bitte an SMARTDOCK wenden.</p>');}
                    $q=$db->prepare("UPDATE sd_users SET password_hash=?,password_set_at=UTC_TIMESTAMP(6),status='active' WHERE id=?");$q->execute([password_hash($password,PASSWORD_DEFAULT),$owner['id']]);
                    $q=$db->prepare('UPDATE sd_user_sessions SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND revoked_at IS NULL');$q->execute([$owner['id']]);
                    $q=$db->prepare("UPDATE sd_user_tokens SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND purpose IN ('set_password','reset_password') AND id<>? AND consumed_at IS NULL");$q->execute([$owner['id'],$fresh['id']]);
                }
                $q=$db->prepare('UPDATE sd_user_tokens SET consumed_at=UTC_TIMESTAMP(6) WHERE id=?');$q->execute([$fresh['id']]);$db->commit();
                unset($_SESSION['access-token-'.$action]);session_regenerate_id(true);dispatch_mail($db,$config);
                $text=$action==='dealer-confirm'?'Ihre E-Mail-Adresse ist bestätigt. Die Geschäftsführung prüft nun Ihre Zugangsanfrage.':($action==='dealer-approve'?'Der persönliche Mitarbeiterzugang ist freigegeben. Die Person erhält eine E-Mail zum Festlegen des Passworts.':'Ihr Passwort wurde gespeichert. Sie können sich jetzt im Händlerbereich anmelden.');
                access_html(200,'Erfolgreich','<p>'.access_escape($text).'</p><p><a href="/haendlerbereich/">Zum Händlerbereich</a></p>');
            }catch(\Throwable $exception){if($db->inTransaction())$db->rollBack();throw $exception;}
        }
    }
    $title=$action==='dealer-confirm'?'E-Mail bestätigen':($action==='dealer-approve'?'Mitarbeiter freischalten':'Passwort festlegen');
    $extra=$action==='dealer-password'?'<label>Neues Passwort<input name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label><label>Passwort wiederholen<input name="repeat" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label>':'';
    access_html(200,$title,'<p>'.access_escape($token['email']).'</p>'.$error.access_form($title,$extra));
}
