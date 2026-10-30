<?php
declare(strict_types=1);
namespace Smartdock;

function newsletter_settings(array $config): array {
    $path=__DIR__.'/newsletter-settings.php';
    return is_file($path)?array_replace($config,require $path):$config;
}

function newsletter_image_info(string $bytes): array {
    if(strlen($bytes)>5*1024*1024)throw new \InvalidArgumentException('Das Bild darf höchstens 5 MB groß sein.');
    $info=@getimagesizefromstring($bytes);
    if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true))throw new \InvalidArgumentException('Bitte ein gültiges JPG-, PNG- oder WebP-Bild auswählen.');
    if($info[0]<1||$info[1]<1||$info[0]>6000||$info[1]>6000||$info[0]*$info[1]>12000000)throw new \InvalidArgumentException('Das Bild darf höchstens 12 Megapixel und 6.000 Pixel pro Seite haben.');
    return $info;
}

function newsletter_upload(array $file,array $config): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new \InvalidArgumentException('Bitte ein Bild bis 5 MB auswählen und erneut hochladen.');
    $bytes=file_get_contents($file['tmp_name'],false,null,0,5*1024*1024+1);
    if($bytes===false)throw new \RuntimeException('Image read failed');
    $info=newsletter_image_info($bytes);
    if(!function_exists('imagecreatefromstring'))throw new \InvalidArgumentException('Die Bildverarbeitung ist auf dem Server noch nicht verfügbar.');
    $source=@imagecreatefromstring($bytes);
    if(!$source)throw new \InvalidArgumentException('Die Bilddatei konnte nicht gelesen werden.');
    // Re-encode decoded pixels: no original filename, metadata or executable payload survives.
    $width=min(1600,$info[0]);$height=max(1,(int)round($info[1]*$width/$info[0]));
    $image=imagecreatetruecolor($width,$height);
    imagealphablending($image,false);imagesavealpha($image,true);
    imagecopyresampled($image,$source,0,0,0,0,$width,$height,$info[0],$info[1]);
    imagedestroy($source);
    $directory=dirname($_SERVER['SCRIPT_FILENAME'],2).'/media/newsletter';
    if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory))throw new \RuntimeException('Image directory unavailable');
    $name=bin2hex(random_bytes(24)).'.png';$path=$directory.'/'.$name;
    try{if(!imagepng($image,$path,6))throw new \RuntimeException('Image write failed');chmod($path,0644);}
    finally{imagedestroy($image);}
    return rtrim($config['origin'],'/').'/media/newsletter/'.$name;
}

function newsletter_group(string $email,array $dealers): string {
    $domain=strtolower(substr(strrchr(trim($email),'@')?:'',1));
    $generic=['gmail.com','googlemail.com','outlook.com','hotmail.com','live.com','icloud.com','me.com','gmx.de','gmx.net','web.de','yahoo.com','yahoo.de','aol.com','t-online.de','mail.de','freenet.de','proton.me','protonmail.com'];
    foreach($dealers as $dealer){
        if(($dealer['status']??'active')!=='active')continue;
        $address=strtolower(trim($dealer['public_email']??''));
        // Exact published addresses are dealers even on a shared mail provider.
        if($address!==''&&$address===strtolower(trim($email)))return 'dealer';
        if($domain!==''&&!in_array($domain,$generic,true)&&$domain===substr(strrchr($address,'@')?:'',1))return 'dealer';
    }
    return 'customer';
}
function newsletter_recipients(\PDO $db,string $group): array {
    $dealers=$db->query("SELECT public_email,status FROM sd_dealer WHERE status='active'")->fetchAll();
    return array_values(array_filter($db->query("SELECT id,email,email_normalized,language,consented_at FROM sd_newsletter_subscribers WHERE status='confirmed' ORDER BY id")->fetchAll(),fn($s)=>newsletter_group($s['email'],$dealers)===$group));
}
function newsletter_content(array $data): array {
    $out=[];
    foreach(['subject'=>160,'title'=>160,'text'=>8000,'button_label'=>80,'button_url'=>1000,'image_url'=>1000] as $field=>$max){
        $v=$data[$field]??'';
        if(!is_string($v)||strlen($v)>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$v)||($field!=='text'&&strpbrk($v,"\r\n")!==false))throw new \InvalidArgumentException('Invalid newsletter content');
        $out[$field]=trim($v);
    }
    if(!$out['subject']||!$out['title']||!$out['text'])throw new \InvalidArgumentException('Required content');
    foreach(['button_url','image_url'] as $f)if($out[$f]!==''&&(!filter_var($out[$f],FILTER_VALIDATE_URL)||parse_url($out[$f],PHP_URL_SCHEME)!=='https'||parse_url($out[$f],PHP_URL_USER)!==null))throw new \InvalidArgumentException('HTTPS URL required');
    if((bool)$out['button_label']!==(bool)$out['button_url'])throw new \InvalidArgumentException('Button needs label and URL');
    return $out;
}
function newsletter_html(array $content,string $unsubscribe,array $config): string {
    $e=static fn($s)=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $logo=$config['origin'].'/media/positionhold/sd-logo_variant_green-white_rgb_png-DJz8V.png';
    return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#eceeef;color:#1d1d1b;font:16px/1.6 Arial,sans-serif"><table role="presentation" width="100%"><tr><td align="center"><table role="presentation" width="600" style="width:100%;max-width:600px;background:white"><tr><td style="background:#191c20;padding:28px;text-align:left"><img alt="SMARTDOCK" width="240" style="max-width:100%;height:auto" src="'.$e($logo).'"></td></tr><tr><td style="padding:32px"><h1 style="font-size:28px">'.$e($content['title']).'</h1>'.($content['image_url']?'<img alt="" width="536" style="width:100%;height:auto" src="'.$e($content['image_url']).'">':'').'<p>'.nl2br($e($content['text'])).'</p>'.($content['button_url']?'<p><a style="display:inline-block;background:#bceb02;color:#1d1d1b;padding:14px 24px;border-radius:24px;font-weight:bold;text-decoration:none" href="'.$e($content['button_url']).'">'.$e($content['button_label']).'</a></p>':'').'</td></tr><tr><td style="padding:24px;background:#f1f2f3;font-size:13px">'.nl2br($e($config['newsletter_footer']??'SMARTDOCK · Thitronik GmbH')).'<p><a href="'.$e($config['origin'].'/impressum/').'">Impressum</a> · <a href="'.$e($config['origin'].'/datenschutz/').'">Datenschutz</a></p><p><a href="'.$e($unsubscribe).'">Newsletter abmelden</a></p></td></tr></table></td></tr></table></body></html>';
}
function newsletter_ready(\PDO $db): bool {
    return (bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='sd_newsletter_campaigns'")->fetchColumn();
}
function newsletter_route(\PDO $db,array $config,array $user,string $action,array $data): never {
    require_staff($user,['admin']);
    $config=newsletter_settings($config);
    if($action==='newsletter-upload') {
        access_limit($config,'newsletter-upload',(string)$user['id']);
        try { $url=newsletter_upload($_FILES['image']??[], $config); }
        catch(\InvalidArgumentException $e){reply(400,['error'=>$e->getMessage()]);}
        reply(200,['url'=>$url]);
    }
    $ready=newsletter_ready($db);
    if($action==='newsletter-admin'){
        $dealers=$db->query("SELECT public_email,status FROM sd_dealer WHERE status='active'")->fetchAll();
        $subscribers=$db->query('SELECT email,status,created_at,language FROM sd_newsletter_subscribers ORDER BY email_normalized')->fetchAll();
        foreach($subscribers as &$s)$s['group']=newsletter_group($s['email'],$dealers);unset($s);
        $campaigns=$ready?$db->query("SELECT c.*, (SELECT COUNT(*) FROM sd_mail_outbox m WHERE m.campaign_id=c.id AND m.message_type='newsletter_campaign' AND m.status='sent') AS sent, (SELECT COUNT(*) FROM sd_mail_outbox m WHERE m.campaign_id=c.id AND m.message_type='newsletter_campaign' AND m.status='failed') AS failed, (SELECT COUNT(*) FROM sd_mail_outbox m WHERE m.campaign_id=c.id AND m.message_type='newsletter_campaign' AND m.status='cancelled') AS cancelled FROM sd_newsletter_campaigns c ORDER BY c.id DESC LIMIT 50")->fetchAll():[];
        reply(200,['subscribers'=>$subscribers,'campaigns'=>$campaigns,'ready'=>$ready,'sendEnabled'=>!empty($config['newsletter_send_enabled']),'testEnabled'=>!empty($config['newsletter_test_enabled']),'testRecipient'=>$config['newsletter_test_recipient']??$user['email'],'sender'=>'newsletter@smartdock.de']);
    }
    if(!$ready)reply(503,['error'=>'Die Newsletter-Datenbankerweiterung ist noch nicht installiert.']);
    if($action==='newsletter-save'){
        try{$content=newsletter_content($data);$group=$data['audience']??'';if(!in_array($group,['dealer','customer'],true))throw new \InvalidArgumentException();}catch(\InvalidArgumentException){reply(400,['error'=>'Bitte Betreff, Überschrift, Text und gültige HTTPS-Links prüfen.']);}
        $id=(int)($data['id']??0);$json=json_encode($content,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        if($id){$q=$db->prepare("UPDATE sd_newsletter_campaigns SET content_json=?,audience=?,revision=revision+1 WHERE id=? AND revision=? AND status='draft'");$q->execute([$json,$group,$id,(int)($data['revision']??0)]);if(!$q->rowCount())reply(409,['error'=>'Entwurf wurde zwischenzeitlich geändert oder bereits versendet. Bitte neu öffnen.']);}
        else{$q=$db->prepare('INSERT INTO sd_newsletter_campaigns(audience,content_json,created_by) VALUES(?,?,?)');$q->execute([$group,$json,$user['id']]);$id=(int)$db->lastInsertId();}
        reply(200,['id'=>$id]);
    }
    $id=(int)($data['id']??0);$q=$db->prepare('SELECT * FROM sd_newsletter_campaigns WHERE id=?');$q->execute([$id]);$campaign=$q->fetch();
    if(!$campaign)reply(404,['error'=>'Newsletter nicht gefunden.']);
    $content=json_decode($campaign['content_json'],true,16,JSON_THROW_ON_ERROR);
    $recipients=newsletter_recipients($db,$campaign['audience']);
    $fingerprint=hash_hmac('sha256',json_encode([$id,$campaign['revision'],array_column($recipients,'id')]),$config['rate_key']);
    if($action==='newsletter-preview')reply(200,['html'=>newsletter_html($content,'#test-abmeldung',$config),'count'=>count($recipients),'fingerprint'=>$fingerprint,'revision'=>(int)$campaign['revision']]);
    if(!in_array($action,['newsletter-test','newsletter-send'],true))reply(404,['error'=>'Aktion nicht gefunden.']);
    if(empty($config[$action==='newsletter-test'?'newsletter_test_enabled':'newsletter_send_enabled'])||empty($config['newsletter_footer']))reply(503,['error'=>'Newsletterversand ist noch nicht freigegeben. Absender und Fußzeile müssen zuerst eingerichtet werden.']);
    access_limit($config,'newsletter-admin-send',(string)$user['id']);
    if($action==='newsletter-test'){
        // Only the administrator or the private, fixed test mailbox; never a client-supplied recipient.
        $recipient=$config['newsletter_test_recipient']??$user['email'];
        queue_mail($db,$config,'newsletter_test',$recipient,'[TEST] '.$content['subject'],$content['text'],'newsletter-test-'.bin2hex(random_bytes(16)),null,null,[],[],newsletter_html($content,'#test-abmeldung',$config));
        dispatch_mail($db,$config);reply(200,['ok'=>true,'recipient'=>$recipient]);
    }
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT * FROM sd_newsletter_campaigns WHERE id=? FOR UPDATE');$q->execute([$id]);$locked=$q->fetch();
        if($locked['status']!=='draft'){$db->commit();reply(200,['ok'=>true,'alreadyQueued'=>true]);}
        $recipients=newsletter_recipients($db,$locked['audience']);
        $current=hash_hmac('sha256',json_encode([$id,$locked['revision'],array_column($recipients,'id')]),$config['rate_key']);
        if(!hash_equals($current,(string)($data['fingerprint']??''))){$db->rollBack();reply(409,['error'=>'Inhalt oder Empfängerliste haben sich geändert. Bitte Vorschau erneut öffnen.']);}
        if(!count($recipients)||count($recipients)>1000){$db->rollBack();reply(409,['error'=>'Versand ist für 1 bis 1.000 aktive Empfänger möglich.']);}
        $content=json_decode($locked['content_json'],true,16,JSON_THROW_ON_ERROR);
        foreach($recipients as $s){
            $token=bin2hex(random_bytes(32));$q=$db->prepare("INSERT INTO sd_newsletter_tokens(subscriber_id,purpose,token_hash,expires_at) VALUES(?,'unsubscribe',?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 YEAR))");$q->execute([$s['id'],hash('sha256',$token,true)]);$tokenId=(int)$db->lastInsertId();
            $link=access_link($config,'newsletter-unsubscribe',$token);
            queue_mail($db,$config,'newsletter_campaign',$s['email'],$content['subject'],$content['text']."\n\n".$content['button_url']."\n\n".$config['newsletter_footer']."\n\nNewsletter abmelden: ".$link,'newsletter-campaign-'.$id.'-'.$s['id'],null,null,['subscriber_id'=>$s['id'],'newsletter_token_id'=>$tokenId],[],newsletter_html($content,$link,$config));
            $q=$db->prepare('UPDATE sd_mail_outbox SET campaign_id=? WHERE deduplication_key=?');$q->execute([$id,'newsletter-campaign-'.$id.'-'.$s['id']]);
        }
        $q=$db->prepare("UPDATE sd_newsletter_campaigns SET status='queued',recipient_count=?,queued_at=UTC_TIMESTAMP(6) WHERE id=?");$q->execute([count($recipients),$id]);
        audit($db,(int)$user['id'],'newsletter.queued','newsletter_campaign',$id,['recipients'=>count($recipients)]);$db->commit();
    }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    dispatch_mail($db,$config);reply(200,['ok'=>true]);
}
