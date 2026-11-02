<?php
declare(strict_types=1);
namespace Smartdock;

// Applies only to new public signups, never to an administrator's historical import.
function newsletter_spam_pattern(string $email): bool {
    [$local,$domain]=explode('@',strtolower($email),2);
    if(!in_array($domain,['gmail.com','googlemail.com'],true))return false;
    $parts=explode('.',$local);$single=0;
    foreach($parts as $part)if(preg_match('/^[a-z]$/D',$part))$single++;
    return count($parts)>=6&&$single>=3;
}

// Caller owns the transaction. Existing opt-outs/blocks always win.
function enroll_newsletter(\PDO $db,string $email,string $language,string $source,string $description,?string $created=null): string {
    $email=normalized_email($email);
    $q=$db->prepare('SELECT * FROM sd_newsletter_subscribers WHERE email_normalized=? FOR UPDATE');$q->execute([$email]);$row=$q->fetch();
    if($row&&in_array($row['status'],['unsubscribed','blocked'],true))return 'suppressed';
    if($row&&$row['status']==='confirmed')return 'existing';
    if($row){$id=(int)$row['id'];$q=$db->prepare("UPDATE sd_newsletter_subscribers SET status='confirmed',consent_version=?,consent_text=?,confirmed_at=NULL WHERE id=?");$q->execute([$source,$description,$id]);}
    else{$q=$db->prepare("INSERT INTO sd_newsletter_subscribers(email,email_normalized,language,status,consent_version,consent_text,created_at) VALUES(?,?,?,'confirmed',?,?,COALESCE(?,UTC_TIMESTAMP(6)))");$q->execute([$email,$email,$language,$source,$description,$created]);$id=(int)$db->lastInsertId();}
    $q=$db->prepare("UPDATE sd_newsletter_tokens SET consumed_at=UTC_TIMESTAMP(6) WHERE subscriber_id=? AND purpose='confirm' AND consumed_at IS NULL");$q->execute([$id]);
    $q=$db->prepare("INSERT INTO sd_newsletter_events(subscriber_id,event_type,consent_version,consent_text) VALUES(?,'signup_requested',?,?)");$q->execute([$id,$source,$description]);
    return $row?'activated':'inserted';
}
