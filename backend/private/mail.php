<?php
declare(strict_types=1);
namespace Smartdock;

// Tokens remain encrypted at rest. The encryption key never leaves private config.
function mail_key(array $config): string {return hash_hmac('sha256','smartdock-mail-v1',$config['rate_key'],true);}
function mail_encrypt(array $config,array $payload): string {
    $nonce=random_bytes(12);$tag='';
    $cipher=openssl_encrypt(json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'aes-256-gcm',mail_key($config),OPENSSL_RAW_DATA,$nonce,$tag,'smartdock-mail-v1');
    if($cipher===false)throw new \RuntimeException('Mail encryption failed.');
    return 'SDO1'.$nonce.$tag.$cipher;
}
function mail_decrypt(array $config,string $record): array {
    if(strlen($record)<33||substr($record,0,4)!=='SDO1')throw new \RuntimeException('Invalid mail record.');
    $plain=openssl_decrypt(substr($record,32),'aes-256-gcm',mail_key($config),OPENSSL_RAW_DATA,substr($record,4,12),substr($record,16,16),'smartdock-mail-v1');
    if($plain===false)throw new \RuntimeException('Mail authentication failed.');
    return json_decode($plain,true,16,JSON_THROW_ON_ERROR);
}
function queue_mail(\PDO $db,array $config,string $type,string $email,string $subject,string $body,string $dedup,?int $userId=null,?int $tokenId=null,array $refs=[],array $attachments=[]): void {
    $email=normalized_email($email);
    $q=$db->prepare('INSERT INTO sd_mail_outbox(message_type,recipient_email,template_version,payload_encrypted,deduplication_key,user_id,user_token_id,subscriber_id,newsletter_token_id,configuration_request_id,contact_request_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $q->execute([$type,$email,'transactional-v1',mail_encrypt($config,['subject'=>$subject,'body'=>$body,'attachments'=>$attachments]),$dedup,$userId,$tokenId,$refs['subscriber_id']??null,$refs['newsletter_token_id']??null,$refs['configuration_request_id']??null,$refs['contact_request_id']??null]);
}
function deliver_mail(array $config,array $mail,array $payload): bool {
    $from=$config['mail_from']??'';
    if(!preg_match('/\A[a-zA-Z0-9._+-]+@smartdock\.de\z/D',$from))throw new \RuntimeException('Mail sender unavailable.');
    if(PHP_SAPI==='cli-server'&&getenv('SD_BACKEND_TEST_RUN')==='1') {
        $file=getenv('SD_TEST_STATE').'/outbox-mails.json';
        $records=is_file($file)?json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR):[];
        $records[]=['to'=>$mail['recipient_email'],'type'=>$mail['message_type'],...$payload];
        file_put_contents($file,json_encode($records,JSON_THROW_ON_ERROR));return true;
    }
    $html=nl2br(htmlspecialchars($payload['body'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));
    $pattern='~'.preg_quote($config['origin'].'/api/index.php?action=','~').'(dealer-confirm|dealer-password|dealer-approve|staff-invitation|newsletter-confirm|newsletter-unsubscribe)&amp;token=[a-f0-9]{64}~';
    $html=preg_replace_callback($pattern,static function(array $match):string {
        $label=match($match[1]){'dealer-approve'=>'Händler für den Händlerbereich freischalten!','dealer-password'=>'Passwort festlegen','newsletter-confirm'=>'Newsletter-Anmeldung bestätigen','newsletter-unsubscribe'=>'Abmelden',default=>'E-Mail bestätigen'};
        return '<a style="display:inline-block;padding:14px 20px;background:#bceb02;color:#1d1d1b;border-radius:8px;text-decoration:none;font-weight:bold" href="'.$match[0].'">'.$label.'</a>';
    },$html);
    $boundary='sd-'.bin2hex(random_bytes(18));
    $body='--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($payload['body'])).'--'.$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode('<!doctype html><html lang="de"><body style="font-family:Arial,sans-serif;line-height:1.6">'.$html.'</body></html>')).'--'.$boundary."--\r\n";
    $contentType='multipart/alternative; boundary="'.$boundary.'"';
    if(!empty($payload['attachments'])) {
        $mixed='sd-mixed-'.bin2hex(random_bytes(18));$body='--'.$mixed."\r\nContent-Type: ".$contentType."\r\n\r\n".$body;
        foreach($payload['attachments'] as $attachment){
            $name=$attachment['name']??'';$bytes=base64_decode($attachment['base64']??'',true);
            if(!preg_match('/\ASMARTDOCK-[a-zA-Z0-9-]+\.pdf\z/D',$name)||!is_string($bytes)||!str_starts_with($bytes,'%PDF-')||strlen($bytes)>2000000)throw new \RuntimeException('PDF attachment invalid.');
            $body.='--'.$mixed."\r\nContent-Type: application/pdf; name=\"".$name."\"\r\nContent-Disposition: attachment; filename=\"".$name."\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($bytes));
        }
        $body.='--'.$mixed."--\r\n";$contentType='multipart/mixed; boundary="'.$mixed.'"';
    }
    return mail($mail['recipient_email'],'=?UTF-8?B?'.base64_encode($payload['subject']).'?=',$body,['From'=>'SMARTDOCK <'.$from.'>','MIME-Version'=>'1.0','Content-Type'=>$contentType],'-f'.$from);
}
// Runs after commit; a failed hand-off remains retryable. SMTP cannot promise exactly once.
function dispatch_mail(\PDO $db,array $config,int $limit=5): void {
    for($i=0;$i<$limit;$i++) {
        $db->beginTransaction();
        try {
            $q=$db->query("SELECT * FROM sd_mail_outbox WHERE message_type IN ('dealer_email_confirmation','dealer_approval_notification','dealer_password','dealer_decision','staff_activation','newsletter_confirmation','configuration_notification','configuration_confirmation','contact_notification') AND payload_encrypted IS NOT NULL AND attempts<5 AND available_at<=UTC_TIMESTAMP(6) AND (status IN ('queued','failed') OR (status='processing' AND locked_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 10 MINUTE))) ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED");
            $mail=$q->fetch();if(!$mail){$db->commit();return;}
            if($mail['user_token_id']) {
                $q=$db->prepare('SELECT id FROM sd_user_tokens WHERE id=? AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP(6)');$q->execute([$mail['user_token_id']]);
                if(!$q->fetch()){$q=$db->prepare("UPDATE sd_mail_outbox SET status='cancelled',payload_encrypted=NULL,payload_json=NULL WHERE id=?");$q->execute([$mail['id']]);$db->commit();continue;}
            }
            if($mail['newsletter_token_id']) {
                $q=$db->prepare("SELECT t.id FROM sd_newsletter_tokens t JOIN sd_newsletter_subscribers s ON s.id=t.subscriber_id WHERE t.id=? AND t.consumed_at IS NULL AND t.expires_at>UTC_TIMESTAMP(6) AND ((t.purpose='confirm' AND s.status='pending') OR (t.purpose='unsubscribe' AND s.status='confirmed'))");$q->execute([$mail['newsletter_token_id']]);
                if(!$q->fetch()){$q=$db->prepare("UPDATE sd_mail_outbox SET status='cancelled',payload_encrypted=NULL,payload_json=NULL WHERE id=?");$q->execute([$mail['id']]);$db->commit();continue;}
            }
            $owner=bin2hex(random_bytes(16));$q=$db->prepare("UPDATE sd_mail_outbox SET status='processing',attempts=attempts+1,locked_at=UTC_TIMESTAMP(6),locked_by=? WHERE id=?");$q->execute([$owner,$mail['id']]);$db->commit();
            try{$ok=deliver_mail($config,$mail,mail_decrypt($config,$mail['payload_encrypted']));}catch(\Throwable){$ok=false;}
            $sql=$ok?"UPDATE sd_mail_outbox SET status='sent',sent_at=UTC_TIMESTAMP(6),payload_encrypted=NULL,payload_json=NULL,last_error=NULL,locked_by=NULL,locked_at=NULL WHERE id=? AND locked_by=?":"UPDATE sd_mail_outbox SET status='failed',last_error='mail_handoff_failed',available_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE),locked_by=NULL,locked_at=NULL WHERE id=? AND locked_by=?";
            $q=$db->prepare($sql);$q->execute([$mail['id'],$owner]);
        }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
    }
}
