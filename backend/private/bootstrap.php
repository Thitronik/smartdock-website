<?php
declare(strict_types=1);
namespace Smartdock;

function bootstrap_request(array $config): never {
    header('Content-Type: text/html; charset=utf-8');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    $setup=$config['bootstrap'] ?? [];$method=$_SERVER['REQUEST_METHOD'] ?? '';
    $closed=static function(string $message,int $status=403):never {http_response_code($status);echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>SMARTDOCK Einrichtung</title><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p></html>';exit;};
    if(!in_array($method,['GET','POST'],true))$closed('Methode nicht erlaubt.',405);
    if(empty($setup['token_hash'])||empty($setup['email'])||(int)($setup['expires_at'] ?? 0)<time()||!staff_allowed($setup['email'],$config['staff_allowlist']))$closed('Dieser Einrichtungslink ist nicht verfügbar oder abgelaufen.');
    $hash=$setup['token_hash'];
    if($method==='GET'&&isset($_GET['token'])){
        $token=$_GET['token'];if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)||!hash_equals($hash,hash('sha256',$token)))$closed('Dieser Einrichtungslink ist nicht verfügbar oder abgelaufen.');
        if(!session_regenerate_id(true))throw new \RuntimeException('Session unavailable.');
        $_SESSION['bootstrap_hash']=$hash;$_SESSION['bootstrap_secret']=base32_encode(random_bytes(20));
        header('Location: /api/index.php?action=bootstrap',true,303);exit;
    }
    if(!hash_equals($hash,$_SESSION['bootstrap_hash'] ?? '')||empty($_SESSION['bootstrap_secret']))$closed('Bitte den persönlichen Einrichtungslink öffnen.');
    // Native form POSTs need a non-null Origin for the strict CSRF check.
    // Keep no-referrer on the token redirect; the clean form URL may refer
    // only to this same origin, never to an external destination.
    header('Referrer-Policy: same-origin');
    $db=database($config);
    if((int)$db->query('SELECT COUNT(*) FROM sd_staff_roles')->fetchColumn()>0)$closed('Die erste Administration wurde bereits eingerichtet. Weitere Konten werden separat freigeschaltet.',409);
    // Read-only native browser check: no credentials and no account creation.
    // Available only inside the authorized, still-open first-admin session.
    if(isset($_GET['check'])){
        if($method==='POST'){
            $originOk=($_SERVER['HTTP_ORIGIN'] ?? '')===$config['origin'];
            $csrfOk=hash_equals($_SESSION['csrf'],is_string($_POST['csrf'] ?? null)?$_POST['csrf']:'');
            $closed($originOk&&$csrfOk?'Verbindungsprüfung erfolgreich. Herkunft und Formularschutz bestätigt. Es wurde kein Konto angelegt.':'Verbindungsprüfung fehlgeschlagen. Herkunft: '.($originOk?'bestätigt':'nicht bestätigt').'; Formularschutz: '.($csrfOk?'bestätigt':'nicht bestätigt').'.',$originOk&&$csrfOk?200:403);
        }
        echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>SMARTDOCK Verbindungsprüfung</title><h1>Verbindung prüfen</h1><p>Diese Prüfung verwendet keine Passwörter und legt kein Konto an.</p><form method="post" action="/api/index.php?action=bootstrap&amp;check=1"><input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8').'"><button>Verbindung prüfen</button></form></html>';exit;
    }
    $error='';
    if($method==='POST'){
        if(($_SERVER['HTTP_ORIGIN'] ?? '')!==$config['origin']||!hash_equals($_SESSION['csrf'],is_string($_POST['csrf'] ?? null)?$_POST['csrf']:''))$closed('Anfrage nicht erlaubt.');
        if((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>8192)$closed('Anfrage zu groß.',413);
        $first=$_POST['first_name'] ?? '';$last=$_POST['last_name'] ?? '';$password=$_POST['password'] ?? '';$repeat=$_POST['repeat'] ?? '';$otp=$_POST['otp'] ?? '';
        if(!is_string($first)||!is_string($last)||!is_string($password)||!is_string($repeat)||!is_string($otp)||!trim($first)||!trim($last)||strlen($first)>200||strlen($last)>200)$error='Bitte Vorname und Nachname vollständig eingeben.';
        elseif(strlen($password)<12||strlen($password)>72||!hash_equals($password,$repeat))$error='Die Passwörter müssen übereinstimmen und 12 bis 72 Zeichen lang sein.';
        elseif(!rate_limit($config['state_directory'],'bootstrap-'.$hash,time()))$closed('Zu viele Versuche. Bitte später erneut versuchen.',429);
        elseif(totp_step($_SESSION['bootstrap_secret'],$otp,time())===null)$error='Der Einmalcode ist nicht gültig. Bitte den aktuellen Code aus der Authenticator-App verwenden.';
        else{
            // File lock spans the database transaction; parallel first-admin setups
            // cannot both pass the empty-role check. The link is unusable afterwards.
            $lock=fopen($config['state_directory'].'/bootstrap.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Bootstrap unavailable.');
            chmod($config['state_directory'].'/bootstrap.lock',0600);$secretStored=false;
            try{
                $db->beginTransaction();
                if((int)$db->query('SELECT COUNT(*) FROM sd_staff_roles')->fetchColumn()>0){$db->rollBack();$closed('Die Administration wurde bereits eingerichtet.',409);}
                $q=$db->prepare('SELECT id FROM sd_users WHERE email_normalized=? FOR UPDATE');$q->execute([$setup['email']]);
                if($q->fetch()){$db->rollBack();$closed('Für dieses Konto ist eine separate Einrichtung erforderlich.',409);}
                store_staff_secret($config,$setup['email'],$_SESSION['bootstrap_secret']);$secretStored=true;
                $q=$db->prepare("INSERT INTO sd_users(first_name,last_name,email,email_normalized,password_hash,password_set_at,email_verified_at,status) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),'active')");
                $q->execute([trim($first),trim($last),$setup['email'],$setup['email'],password_hash($password,PASSWORD_DEFAULT)]);$id=(int)$db->lastInsertId();
                $q=$db->prepare("INSERT INTO sd_staff_roles(user_id,role,granted_by) VALUES(?,'admin',?)");$q->execute([$id,$id]);
                audit($db,$id,'bootstrap.admin_created','user',$id,['method'=>'private-operator-setup']);$db->commit();
                unset($_SESSION['bootstrap_hash'],$_SESSION['bootstrap_secret']);session_regenerate_id(true);
                header('Location: /admin/',true,303);exit;
            }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();if($secretStored&&is_file(mfa_path($config,$setup['email'])))unlink(mfa_path($config,$setup['email']));throw $e;}
            finally{flock($lock,LOCK_UN);fclose($lock);}
        }
    }
    $e=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES,'UTF-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>SMARTDOCK · Erstes Administratorkonto</title><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/admin-portal.css"></head><body><main class="sd-admin-portal"><section class="admin-gate"><p class="eyebrow">SMARTDOCK · TESTUMGEBUNG</p><h1>Adminzugang einrichten</h1><p>Dieser einmalige Zugang richtet das erste Administratorkonto für <strong>'.$e($setup['email']).'</strong> ein. Es erhält Verwaltungsrechte für die separate Testdatenbank.</p><div class="admin-notice"><strong>1. Authenticator-App einrichten</strong><p>Ein Konto manuell mit dem Namen SMARTDOCK und diesem Schlüssel hinzufügen. Zeitbasierte Einmalcodes auswählen. Den Schlüssel sicher aufbewahren.</p><code data-testid="totp-secret" style="overflow-wrap:anywhere;user-select:all">'.$e($_SESSION['bootstrap_secret']).'</code></div><form method="post" action="/api/index.php?action=bootstrap"><input type="hidden" name="csrf" value="'.$e($_SESSION['csrf']).'"><label>Vorname<input name="first_name" autocomplete="given-name" required maxlength="200"></label><label>Nachname<input name="last_name" autocomplete="family-name" required maxlength="200"></label><label>Neues Passwort<input name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="72"></label><label>Passwort wiederholen<input name="repeat" type="password" autocomplete="new-password" required minlength="12" maxlength="72"></label><label>Aktueller Code aus der Authenticator-App<input name="otp" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" required maxlength="6"></label><p role="status">'.$e($error).'</p><button class="primary-button">Administratorkonto aktivieren</button></form><p class="subtle">Erst dieser letzte Schritt legt das Konto an. Es werden keine anderen Konten aktiviert und keine E-Mails versendet.</p></section></main></body></html>';
    exit;
}
