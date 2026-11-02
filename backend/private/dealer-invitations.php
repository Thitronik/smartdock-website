<?php
declare(strict_types=1);
namespace Smartdock;
require_once __DIR__ . '/newsletter-enrollment.php';

// Private access container: never a public dealer or installation business.
function independent_access_container(\PDO $db): int {
    $q=$db->prepare("INSERT INTO sd_dealer(source_system,source_id,company_name,country_code) VALUES('smartdock-internal','extra-access','SMARTDOCK · weitere Zugänge','ZZ') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");$q->execute();
    $q=$db->prepare("SELECT id FROM sd_dealer WHERE source_system='smartdock-internal' AND source_id='extra-access' AND status='active' FOR UPDATE");$q->execute();$id=$q->fetchColumn();
    if(!$id)throw new \InvalidArgumentException('Weitere Zugänge sind derzeit gesperrt.');return (int)$id;
}

function queue_dealer_invitation(\PDO $db,array $config,int $dealerId,string $email,int $actor): int {
    $email=normalized_email($email);
    $q=$db->prepare('SELECT source_system FROM sd_dealer WHERE id=?');$q->execute([$dealerId]);$independent=$q->fetchColumn()==='smartdock-internal';
    $q=$db->prepare('SELECT * FROM sd_users WHERE email_normalized=?');$q->execute([$email]);$account=$q->fetch();
    if($account){
        if(!active_user($account))throw new \InvalidArgumentException('Das vorhandene Konto muss zuerst eingerichtet oder reaktiviert werden. Keine Einladung versendet.');
        $q=$db->prepare('SELECT id FROM sd_dealer_memberships WHERE dealer_id=? AND user_id=?');$q->execute([$dealerId,$account['id']]);
        if($q->fetch())throw new \InvalidArgumentException('Dieser Händlerzugang besteht bereits. Bitte den vorhandenen Eintrag verwalten. Es wurde keine neue Einladung versendet.');
    }
    $q=$db->prepare('UPDATE sd_dealer_invitations SET revoked_at=UTC_TIMESTAMP(6) WHERE email=? AND consumed_at IS NULL AND revoked_at IS NULL');$q->execute([$email]);
    $raw=bin2hex(random_bytes(32));
    $q=$db->prepare('INSERT INTO sd_dealer_invitations(dealer_id,email,token_hash,invited_by,expires_at) VALUES(?,?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 7 DAY))');
    $q->execute([$dealerId,$email,hash('sha256',$raw,true),$actor]);$id=(int)$db->lastInsertId();
    enroll_newsletter($db,$email,'de','dealer-enrollment-2026-11-02','Automatische Aufnahme beim Anlegen eines Händlerzugangs durch SMARTDOCK. Bestehende Abmeldungen bleiben erhalten.');
    queue_mail($db,$config,'dealer_invitation',$email,'SMARTDOCK: Einladung zum Händlerbereich',"Du bist zum SMARTDOCK Händlerbereich eingeladen. ".($account?"Bestätige die Einladung mit deinem aktuellen SMARTDOCK Passwort. Dein Passwort bleibt unverändert.":"Vervollständige dein persönliches Profil und lege dein Passwort fest.")."\n\n".access_link($config,'dealer-invitation',$raw)."\n\n".($independent?"Dein persönlicher Zugang wird ohne Firmenzuordnung eingerichtet.":"Dein Zugang wird automatisch der eingeladenen Firma zugeordnet.")." Der Link ist 7 Tage gültig und kann einmal verwendet werden.",'dealer-invitation-'.$id);
    return $id;
}

function dealer_invitation_page(array $config): never {
    $db=database($config);access_limit($config,'dealer-invitation');$method=$_SERVER['REQUEST_METHOD']??'GET';
    if(!in_array($method,['GET','POST'],true))access_html(405,'Nicht erlaubt','<p>Methode nicht erlaubt.</p>');
    if($method==='GET'&&isset($_GET['token'])){
        $raw=$_GET['token'];if(!is_string($raw)||!preg_match('/\A[a-f0-9]{64}\z/D',$raw))access_html(403,'Link nicht gültig','<p>Bitte wende dich an SMARTDOCK.</p>');
        $_SESSION['dealer-invitation']=hash('sha256',$raw,true);header('Referrer-Policy: no-referrer');header('Location: /api/index.php?action=dealer-invitation',true,303);exit;
    }
    $query="SELECT i.*,d.company_name,d.source_system FROM sd_dealer_invitations i JOIN sd_dealer d ON d.id=i.dealer_id WHERE i.token_hash=? AND i.consumed_at IS NULL AND i.revoked_at IS NULL AND i.expires_at>UTC_TIMESTAMP(6) AND d.status='active'";
    $q=$db->prepare($query);$q->execute([$_SESSION['dealer-invitation']??'']);$invitation=$q->fetch();
    if(!$invitation)access_html(403,'Link nicht gültig','<p>Die Einladung ist abgelaufen oder bereits verwendet. Bitte SMARTDOCK um eine neue Einladung bitten.</p>');
    $q=$db->prepare('SELECT * FROM sd_users WHERE email_normalized=?');$q->execute([$invitation['email']]);$account=$q->fetch();$existing=(bool)$account;
    $error='';
    if($method==='POST'){
        if(($_SERVER['HTTP_ORIGIN']??'')!==$config['origin']||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))access_html(403,'Anfrage nicht erlaubt','<p>Bitte lade die Seite neu.</p>');
        try{
            $values=[];foreach(['first_name'=>200,'last_name'=>200,'position'=>200,'phone'=>100] as $field=>$max)$values[$field]=$existing&&in_array($field,['first_name','last_name'],true)?$account[$field]:access_field($_POST,$field,$max,$invitation['source_system']!=='smartdock-internal'||in_array($field,['first_name','last_name'],true));
            if(normalized_email(access_field($_POST,'email',254))!==$invitation['email'])throw new \InvalidArgumentException('Bitte verwende die eingeladene E-Mail-Adresse.');
            $password=$_POST['password']??'';
            if(!is_string($password)||($existing?(strlen($password)<1||strlen($password)>1024):(strlen($password)<12||strlen($password)>72||$password!==($_POST['repeat']??null))))throw new \InvalidArgumentException($existing?'Bitte dein aktuelles SMARTDOCK Passwort eingeben.':'Bitte zweimal dasselbe Passwort mit 12 bis 72 Zeichen eingeben.');
            $db->beginTransaction();$q=$db->prepare($query.' FOR UPDATE');$q->execute([$_SESSION['dealer-invitation']]);$fresh=$q->fetch();
            if(!$fresh)throw new \InvalidArgumentException('Diese Einladung ist nicht mehr gültig.');
            if($existing){
                $q=$db->prepare('SELECT * FROM sd_users WHERE email_normalized=? FOR UPDATE');$q->execute([$fresh['email']]);$current=$q->fetch();
                if(!$current||!active_user($current)||!password_verify($password,$current['password_hash']))throw new \InvalidArgumentException('Das aktuelle Passwort stimmt nicht oder das Konto ist gesperrt.');
                $userId=(int)$current['id'];
            }else{
                $q=$db->prepare("INSERT INTO sd_users(first_name,last_name,email,email_normalized,password_hash,password_set_at,email_verified_at,status) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),'active')");
                $q->execute([$values['first_name'],$values['last_name'],$fresh['email'],$fresh['email'],password_hash($password,PASSWORD_DEFAULT)]);$userId=(int)$db->lastInsertId();
            }
            $q=$db->prepare("INSERT INTO sd_dealer_memberships(dealer_id,user_id,status,decided_at,decided_by,position,phone) VALUES(?,?,'approved',UTC_TIMESTAMP(6),?,?,?)");$q->execute([$fresh['dealer_id'],$userId,$fresh['invited_by'],$values['position'],$values['phone']]);
            $q=$db->prepare('UPDATE sd_dealer_invitations SET consumed_at=UTC_TIMESTAMP(6) WHERE id=?');$q->execute([$fresh['id']]);
            audit($db,$userId,'dealer.invitation_accepted','dealer',(int)$fresh['dealer_id'],[]);$db->commit();unset($_SESSION['dealer-invitation']);
            access_html(200,'Willkommen im Händlerbereich','<p>Dein Händlerzugang ist eingerichtet. Schön, dass du dabei bist!</p>');
        }catch(\InvalidArgumentException $e){if($db->inTransaction())$db->rollBack();$error='<p role="alert">'.access_escape($e->getMessage()).'</p>';}
        catch(\PDOException $e){if($db->inTransaction())$db->rollBack();if($e->getCode()!=='23000')throw $e;$error='<p role="alert">Für diese E-Mail besteht bereits ein Konto. Bitte melde dich an oder wende dich an SMARTDOCK.</p>';}
        catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    $fields=$existing?'<p>Du hast bereits ein SMARTDOCK Konto. Bestätige die Einladung mit deinem aktuellen Passwort.</p>':'';foreach(['first_name'=>'Vorname','last_name'=>'Nachname','position'=>'Position','phone'=>'Telefonnummer'] as $name=>$label)$fields.='<label>'.$label.'<input name="'.$name.'" maxlength="'.($name==='phone'?100:200).'"'.($existing&&in_array($name,['first_name','last_name'],true)?' value="'.access_escape($account[$name]).'" readonly':'').($invitation['source_system']==='smartdock-internal'&&in_array($name,['position','phone'],true)?'':' required').'></label>';
    $fields.='<label>E-Mail-Adresse<input type="email" name="email" value="'.access_escape($invitation['email']).'" readonly required></label>';
    $fields.=$existing?'<label>Aktuelles SMARTDOCK Passwort<input type="password" name="password" autocomplete="current-password" maxlength="1024" required></label>':'<label>Passwort<input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required></label><label>Passwort wiederholen<input type="password" name="repeat" autocomplete="new-password" minlength="12" maxlength="72" required></label>';
    access_html(200,'Deinen Händlerzugang einrichten','<p>'.access_escape($invitation['source_system']==='smartdock-internal'?'Persönlicher Händlerzugang ohne Firmenzuordnung':$invitation['company_name']).'</p>'.$error.access_form('Zugang einrichten',$fields));
}
