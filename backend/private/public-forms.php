<?php
declare(strict_types=1);
namespace Smartdock;
require_once __DIR__.'/dealer-access.php';

function form_language(array $data): string {
    $language=$data['language']??'de';if(!is_string($language)||!in_array($language,['de','en','fr','nl','es'],true))throw new \InvalidArgumentException('Invalid language.');return $language;
}
function form_guard(array $config,array $data,string $purpose,int $limit=5): void {
    $key=hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'',$config['rate_key']);
    if(!rate_limit($config['state_directory'],$purpose.'-ip-'.$key,time(),$limit))reply(429,['error'=>'Zu viele Anfragen. Bitte später erneut versuchen.']);
    if(!empty($data['sd_website'])||empty($_SESSION['public_form_started'])||time()-(int)$_SESSION['public_form_started']<2)reply(400,['error'=>'Bitte das Formular auf der Website verwenden und erneut versuchen.']);
}
function form_uuid(): string {
    $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);$h=bin2hex($bytes);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
}
function public_inquiry(\PDO $db,array $config,array $data,string $type): never {
    form_guard($config,$data,$type,5);
    try {
        $email=normalized_email(access_field($data,'email',254));$language=form_language($data);$company=access_field($data,'company',200,false);$phone=access_field($data,'phone',50,false);
        if(($data['privacy']??false)!==true)throw new \InvalidArgumentException('Privacy required.');
        if($type==='contact') {
            $name=access_field($data,'name',200);$message=$data['message']??'';
            if(!is_string($message)||trim($message)===''||strlen($message)>12000||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$message))throw new \InvalidArgumentException('Message invalid.');$message=trim($message);
        }else {
            $first=access_field($data,'firstName',95);$last=access_field($data,'lastName',95);$name=$first.' '.$last;
            $clientReference=access_field($data,'referenceNumber',36);if(!preg_match('/\A[a-zA-Z0-9-]{6,36}\z/D',$clientReference))throw new \InvalidArgumentException('Reference invalid.');
            $keys=['street','houseNumber','postalCode','city','country','skus','configuration','motorCount','motorType','joystickType','ecuCount','moduleAllocation','tcuCount','acuCount','wcuCount','tcu','bugstrahlruder','heckstrahlruder','acu','wcu','horn','ankerwinde1','ankerwinde2','ankerwinch1','ankerwinch2','installationService','thrusterManufacturer','thrusterType'];
            $selection=[];foreach($keys as $key)if(array_key_exists($key,$data))$selection[$key]=$data[$key];
            foreach(['motorCount','ecuCount','tcuCount','acuCount','wcuCount'] as $key)if(isset($selection[$key])&&(!is_int($selection[$key])||$selection[$key]<0||$selection[$key]>64))throw new \InvalidArgumentException('Counts invalid.');
            $selection['client_reference']=$clientReference;$selection['review_required']=true;
            // Browser prices, discounts, dealer assignment and status are never
            // accepted as authoritative values. Staff must check the request.
            $selectionJson=json_encode($selection,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);if(strlen($selectionJson)>12000)throw new \InvalidArgumentException('Selection too large.');
        }
    }catch(\InvalidArgumentException|\JsonException){reply(400,['error'=>'Bitte Pflichtfelder und Datenschutzhinweis prüfen.']);}
    $recipient=$config['inquiry_email']??'';if($recipient==='')reply(503,['error'=>'Der Anfrageempfang ist noch nicht eingerichtet.']);
    $ref=form_uuid();$db->beginTransaction();
    try {
        if($type==='contact') {
            $q=$db->prepare('INSERT INTO sd_contact_requests(public_reference,contact_name,email,company,phone,message,language,privacy_notice_version) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$ref,$name,$email,$company?:null,$phone?:null,$message,$language,'public-forms-2026-10']);$id=(int)$db->lastInsertId();
            $mailType='contact_notification';$refs=['contact_request_id'=>$id];$subject='SMARTDOCK: Neue Kontaktanfrage';
        }else {
            $dedup=hash('sha256',$email.'|'.$clientReference,true);$q=$db->prepare('SELECT public_reference FROM sd_configuration_requests WHERE idempotency_key=? FOR UPDATE');$q->execute([$dedup]);$existing=$q->fetchColumn();if($existing){$db->commit();reply(200,['id'=>$existing,'ok'=>true]);}
            $q=$db->prepare('INSERT INTO sd_configuration_requests(public_reference,idempotency_key,contact_name,email,company,phone,language,configuration_version,selection_json,privacy_notice_version,privacy_acknowledged_at) VALUES(?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6))');
            $q->execute([$ref,$dedup,$name,$email,$company?:null,$phone?:null,$language,'website-2026-10',$selectionJson,'public-forms-2026-10']);$id=(int)$db->lastInsertId();
            $mailType='configuration_notification';$refs=['configuration_request_id'=>$id];$subject='SMARTDOCK: Neue Konfigurationsanfrage';
        }
        $body="Neue Anfrage: ".$ref."\nName: ".$name."\nE-Mail: ".$email."\nUnternehmen: ".$company."\nTelefon: ".$phone."\n\n".($type==='contact'?$message:'Die Konfiguration steht in der Administration. Auswahl und Preise müssen fachlich geprüft werden. Es wurde kein verbindliches Angebot erzeugt.')."\n\nAdministration: ".$config['origin'].'/admin/';
        queue_mail($db,$config,$mailType,$recipient,$subject,$body,$type.'-'.$ref,null,null,$refs);$db->commit();
    }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
    dispatch_mail($db,$config);reply(200,['ok'=>true,'id'=>$ref]);
}
function newsletter_consent(string $language): string {
    $texts=[
        'de'=>'Abonniere den SMARTDOCK Newsletter und erfahre als Erste von neuen Produkten, Updates und exklusiven Angeboten. Mit dem Abonnieren stimmst du unserer Datenschutzerklärung zu. Du kannst dich jederzeit wieder abmelden.',
        'en'=>'Subscribe to the SMARTDOCK newsletter and be the first to hear about new products, updates and exclusive offers. By subscribing you agree to our privacy policy. You can unsubscribe at any time.',
        'fr'=>'Abonnez-vous à la newsletter SMARTDOCK et soyez les premiers informés des nouveaux produits, mises à jour et offres exclusives. En vous abonnant, vous acceptez notre politique de confidentialité. Vous pouvez vous désabonner à tout moment.',
        'nl'=>'Abonneer je op de SMARTDOCK nieuwsbrief en hoor als eerste over nieuwe producten, updates en exclusieve aanbiedingen. Door je te abonneren ga je akkoord met onze privacyverklaring. Je kunt je op elk moment weer afmelden.',
        'es'=>'Suscríbete al newsletter de SMARTDOCK y entérate antes que nadie de los nuevos productos, las novedades y las ofertas exclusivas. Al suscribirte aceptas nuestra política de privacidad. Puedes darte de baja en cualquier momento.',
    ];return $texts[$language];
}
function newsletter_request(\PDO $db,array $config,array $data): never {
    form_guard($config,$data,'newsletter',3);
    try{$email=normalized_email(access_field($data,'email',254));$language=form_language($data);}catch(\InvalidArgumentException){reply(400,['error'=>'Bitte eine gültige E-Mail-Adresse verwenden.']);}
    $result=['ok'=>true,'pending'=>true];
    $key=hash_hmac('sha256',$email,$config['rate_key']);if(!rate_limit($config['state_directory'],'newsletter-address-'.$key,time(),1))reply(200,$result);
    // Verify a deliverable domain before storing fake/typo addresses. DNS failures
    // do not bypass the confirmation link; no subscriber becomes active here.
    if(!(PHP_SAPI==='cli-server'&&getenv('SD_BACKEND_TEST_RUN')==='1')) {
        $domain=substr($email,strrpos($email,'@')+1);
        if(!checkdnsrr($domain,'MX')&&!checkdnsrr($domain,'A')&&!checkdnsrr($domain,'AAAA'))reply(400,['error'=>'Die Domain der E-Mail-Adresse ist nicht erreichbar. Bitte prüfen.']);
    }
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT * FROM sd_newsletter_subscribers WHERE email_normalized=? FOR UPDATE');$q->execute([$email]);$subscriber=$q->fetch();
        if($subscriber&&in_array($subscriber['status'],['confirmed','blocked'],true)){$db->commit();reply(200,$result);}
        if($subscriber&&$subscriber['status']==='pending') {
            $q=$db->prepare("SELECT id FROM sd_newsletter_tokens WHERE subscriber_id=? AND purpose='confirm' AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP(6) LIMIT 1");$q->execute([$subscriber['id']]);if($q->fetch()){$db->commit();reply(200,$result);}
        }
        $consent=newsletter_consent($language);$version='newsletter-2026-10';
        if(!$subscriber){$q=$db->prepare('INSERT INTO sd_newsletter_subscribers(email,email_normalized,language,consent_version,consent_text) VALUES(?,?,?,?,?)');$q->execute([$email,$email,$language,$version,$consent]);$id=(int)$db->lastInsertId();}
        else {$id=(int)$subscriber['id'];$q=$db->prepare("UPDATE sd_newsletter_subscribers SET status='pending',language=?,consent_version=?,consent_text=?,consented_at=UTC_TIMESTAMP(6),confirmed_at=NULL,unsubscribed_at=NULL WHERE id=?");$q->execute([$language,$version,$consent,$id]);}
        $q=$db->prepare("UPDATE sd_newsletter_tokens SET consumed_at=UTC_TIMESTAMP(6) WHERE subscriber_id=? AND consumed_at IS NULL");$q->execute([$id]);
        $q=$db->prepare("INSERT INTO sd_newsletter_events(subscriber_id,event_type,consent_version,consent_text) VALUES(?,'signup_requested',?,?)");$q->execute([$id,$version,$consent]);
        $raw=bin2hex(random_bytes(32));$cancel=bin2hex(random_bytes(32));
        $q=$db->prepare("INSERT INTO sd_newsletter_tokens(subscriber_id,purpose,token_hash,expires_at) VALUES(?,'confirm',?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 24 HOUR))");$q->execute([$id,hash('sha256',$raw,true)]);$tokenId=(int)$db->lastInsertId();
        $q=$db->prepare("INSERT INTO sd_newsletter_tokens(subscriber_id,purpose,token_hash,expires_at) VALUES(?,'unsubscribe',?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 1 YEAR))");$q->execute([$id,hash('sha256',$cancel,true)]);
        queue_mail($db,$config,'newsletter_confirmation',$email,'SMARTDOCK: Newsletter-Anmeldung bestätigen',"Bitte bestätigen Sie Ihre Newsletter-Anmeldung über diesen Link:\n\n".access_link($config,'newsletter-confirm',$raw)."\n\nDer Bestätigungslink gilt 24 Stunden. Erst nach Ihrer Bestätigung erhalten Sie den Newsletter. Wenn Sie dies nicht angefordert haben, ignorieren Sie diese E-Mail.\n\nAnmeldung abbrechen oder abmelden:\n".access_link($config,'newsletter-unsubscribe',$cancel),'newsletter-confirm-'.$tokenId,null,null,['subscriber_id'=>$id,'newsletter_token_id'=>$tokenId]);
        $db->commit();
    }catch(\PDOException $error){if($db->inTransaction())$db->rollBack();if($error->getCode()!=='23000')throw $error;reply(200,$result);}
    catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
    dispatch_mail($db,$config);reply(200,$result);
}
function newsletter_page(array $config,string $action): never {
    access_limit($config,'newsletter-link');$db=database($config);$method=$_SERVER['REQUEST_METHOD']??'GET';$purpose=$action==='newsletter-confirm'?'confirm':'unsubscribe';
    if(!in_array($method,['GET','POST'],true))access_html(405,'Nicht erlaubt','<p>Methode nicht erlaubt.</p>');
    if($method==='GET'&&isset($_GET['token'])) {
        $raw=$_GET['token'];if(!is_string($raw)||!preg_match('/\A[a-f0-9]{64}\z/D',$raw))access_html(403,'Link nicht gültig','<p>Bitte einen aktuellen Link verwenden.</p>');
        session_regenerate_id(true);$_SESSION['newsletter-'.$purpose]=hash('sha256',$raw,true);header('Referrer-Policy: no-referrer');header('Location: /api/index.php?action='.$action,true,303);exit;
    }
    $q=$db->prepare('SELECT * FROM sd_newsletter_tokens WHERE token_hash=? AND purpose=? AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP(6)');$q->execute([$_SESSION['newsletter-'.$purpose]??'',$purpose]);$token=$q->fetch();
    if(!$token)access_html(403,'Link nicht gültig','<p>Der Link ist abgelaufen oder wurde bereits verwendet.</p>');
    if($method==='POST') {
        if(($_SERVER['HTTP_ORIGIN']??'')!==$config['origin']||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))access_html(403,'Anfrage nicht erlaubt','<p>Bitte die Seite neu öffnen.</p>');
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM sd_newsletter_subscribers WHERE id=? FOR UPDATE');$q->execute([$token['subscriber_id']]);$subscriber=$q->fetch();
            $q=$db->prepare('SELECT id FROM sd_newsletter_tokens WHERE id=? AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP(6) FOR UPDATE');$q->execute([$token['id']]);
            if(!$q->fetch()||!$subscriber||$subscriber['status']==='blocked'||($purpose==='confirm'&&$subscriber['status']!=='pending')){$db->rollBack();access_html(403,'Link nicht gültig','<p>Bitte einen aktuellen Link verwenden.</p>');}
            $sql=$purpose==='confirm'?"UPDATE sd_newsletter_subscribers SET status='confirmed',confirmed_at=UTC_TIMESTAMP(6) WHERE id=?":"UPDATE sd_newsletter_subscribers SET status='unsubscribed',unsubscribed_at=UTC_TIMESTAMP(6) WHERE id=?";$q=$db->prepare($sql);$q->execute([$subscriber['id']]);
            $q=$db->prepare('UPDATE sd_newsletter_tokens SET consumed_at=UTC_TIMESTAMP(6) WHERE id=?');$q->execute([$token['id']]);
            if($purpose==='unsubscribe'){$q=$db->prepare("UPDATE sd_newsletter_tokens SET consumed_at=UTC_TIMESTAMP(6) WHERE subscriber_id=? AND purpose='confirm' AND consumed_at IS NULL");$q->execute([$subscriber['id']]);}
            $q=$db->prepare('INSERT INTO sd_newsletter_events(subscriber_id,event_type,consent_version,consent_text) VALUES(?,?,?,?)');$q->execute([$subscriber['id'],$purpose==='confirm'?'confirmed':'unsubscribed',$subscriber['consent_version'],$subscriber['consent_text']]);$db->commit();
            unset($_SESSION['newsletter-'.$purpose]);access_html(200,$purpose==='confirm'?'Anmeldung bestätigt':'Abgemeldet','<p>'.($purpose==='confirm'?'Ihre Newsletter-Anmeldung ist bestätigt.':'Ihre Adresse ist vom Newsletter abgemeldet.').'</p>');
        }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
    }
    $title=$purpose==='confirm'?'Newsletter-Anmeldung bestätigen':'Vom Newsletter abmelden';access_html(200,$title,'<p>Bitte bestätigen Sie die gewünschte Aktion.</p>'.access_form($title));
}
