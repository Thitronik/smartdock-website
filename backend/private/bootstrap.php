<?php
declare(strict_types=1);
namespace Smartdock;

function bootstrap_document(string $title,string $content): string {
    $title=htmlspecialchars($title,ENT_QUOTES,'UTF-8');
    return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.$title.'</title><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/admin-portal.css"><style>body{margin:0;background:hsl(var(--background));color:hsl(var(--foreground));font-family:Outfit,system-ui,sans-serif}.sd-admin-portal{box-sizing:border-box;width:100%;max-width:760px;margin:0 auto;padding:64px 24px;text-align:center}.sd-admin-portal .admin-gate{max-width:640px;margin:0 auto;text-align:center}.admin-gate h1{font-size:clamp(32px,6vw,48px)!important}.admin-gate p,.admin-gate label,.admin-gate input,.admin-gate .admin-notice{text-align:center}.admin-gate form{width:100%;max-width:460px;margin:28px auto 0}.admin-gate input{min-width:0}.setup-secret{margin:20px auto}.setup-qr{margin:24px auto}.setup-qr summary{cursor:pointer;color:hsl(var(--primary));font-weight:600}#setup-qr svg{display:block;width:280px;max-width:100%;height:auto;margin:20px auto;background:#fff;border-radius:8px}.setup-secret summary{cursor:pointer;color:hsl(var(--primary));font-weight:600}.setup-secret code{display:block;overflow-wrap:anywhere;user-select:all;margin:16px auto;font-size:15px;line-height:1.8}.admin-gate [role=status]:empty{display:none}@media(max-width:600px){.sd-admin-portal{padding:36px 20px}}</style><script defer src="/api/qrcodegen.js"></script><script defer src="/api/bootstrap-qr.js"></script></head><body><main class="sd-admin-portal"><section class="admin-gate">'.$content.'</section></main></body></html>';
}

function bootstrap_request(array $config): never {
    header('Content-Type: text/html; charset=utf-8');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    $setup=$config['bootstrap'] ?? [];$method=$_SERVER['REQUEST_METHOD'] ?? '';
    $closed=static function(string $message,int $status=403):never {http_response_code($status);echo bootstrap_document('SMARTDOCK Einrichtung','<p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p>');exit;};
    if(!in_array($method,['GET','POST'],true))$closed('Methode nicht erlaubt.',405);
    if(empty($setup['token_hash'])||empty($setup['email'])||(int)($setup['expires_at'] ?? 0)<time()||!staff_allowed($setup['email'],$config['staff_allowlist'],$config['staff_email_domain'] ?? 'thitronik.de'))$closed('Dieser Einrichtungslink ist nicht verfügbar oder abgelaufen.');
    $hash=$setup['token_hash'];
    if($method==='GET'&&isset($_GET['token'])){
        $token=$_GET['token'];if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)||!hash_equals($hash,hash('sha256',$token)))$closed('Dieser Einrichtungslink ist nicht verfügbar oder abgelaufen.');
        if(!session_regenerate_id(true))throw new \RuntimeException('Session unavailable.');
        // Reopening the same link must not invalidate an already scanned key.
        if(!hash_equals($hash,$_SESSION['bootstrap_hash'] ?? '')||empty($_SESSION['bootstrap_secret'])){
            $_SESSION['bootstrap_hash']=$hash;$_SESSION['bootstrap_secret']=base32_encode(random_bytes(20));
        }
        header('Location: /api/index.php?action=bootstrap',true,303);exit;
    }
    if(!hash_equals($hash,$_SESSION['bootstrap_hash'] ?? '')||empty($_SESSION['bootstrap_secret']))$closed('Bitte den persönlichen Einrichtungslink öffnen.');
    // Native form POSTs need a non-null Origin for the strict CSRF check.
    // Keep no-referrer on the token redirect; the clean form URL may refer
    // only to this same origin, never to an external destination.
    header('Referrer-Policy: same-origin');
    $db=database($config);
    if((int)$db->query('SELECT COUNT(*) FROM sd_staff_roles')->fetchColumn()>0)$closed('Die erste Administration wurde bereits eingerichtet. Weitere Konten werden separat freigeschaltet.',409);
    if(empty($config['mail_from']))$closed('Die Admin-Einrichtung wartet auf den eingerichteten E-Mail-Versand. Ohne Bestätigung des Thitronik-Postfachs wird kein Adminzugang freigeschaltet.',503);
    $q=$db->prepare('SELECT id FROM sd_users WHERE email_normalized=?');$q->execute([$setup['email']]);
    if($q->fetchColumn())$closed('Ihre Einrichtung wartet auf die E-Mail-Bestätigung. Bitte öffnen Sie den Aktivierungslink in Ihrem Postfach. Der Zugang ist bis dahin gesperrt.',200);
    // Read-only native browser check: no credentials and no account creation.
    // Available only inside the authorized, still-open first-admin session.
    if(isset($_GET['check'])){
        if($method==='POST'){
            $originOk=($_SERVER['HTTP_ORIGIN'] ?? '')===$config['origin'];
            $csrfOk=hash_equals($_SESSION['csrf'],is_string($_POST['csrf'] ?? null)?$_POST['csrf']:'');
            $closed($originOk&&$csrfOk?'Verbindungsprüfung erfolgreich. Herkunft und Formularschutz bestätigt. Es wurde kein Konto angelegt.':'Verbindungsprüfung fehlgeschlagen. Herkunft: '.($originOk?'bestätigt':'nicht bestätigt').'; Formularschutz: '.($csrfOk?'bestätigt':'nicht bestätigt').'.',$originOk&&$csrfOk?200:403);
        }
        echo bootstrap_document('SMARTDOCK Verbindungsprüfung','<h1>Verbindung prüfen</h1><p>Diese Prüfung verwendet keine Passwörter und legt kein Konto an.</p><p>Serverzeit (UTC): <time>'.gmdate('Y-m-d H:i:s').'</time></p><form method="post" action="/api/index.php?action=bootstrap&amp;check=1"><input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8').'"><button>Verbindung prüfen</button></form>');exit;
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
                $q=$db->prepare("INSERT INTO sd_users(first_name,last_name,email,email_normalized,password_hash,password_set_at,status) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6),'invited')");
                $q->execute([trim($first),trim($last),$setup['email'],$setup['email'],password_hash($password,PASSWORD_DEFAULT)]);$id=(int)$db->lastInsertId();
                $token=bin2hex(random_bytes(32));
                $q=$db->prepare("INSERT INTO sd_user_tokens(user_id,purpose,token_hash,expires_at) VALUES(?,'verify_email',UNHEX(?),DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 7 DAY))");$q->execute([$id,hash('sha256',$token)]);$tokenId=(int)$db->lastInsertId();
                audit($db,$id,'bootstrap.pending','user',$id,['method'=>'private-operator-setup','token_id'=>$tokenId]);
                require_once __DIR__.'/staff-activation.php';
                if(!staff_activation_mail($config,$setup['email'],$token))throw new \RuntimeException('Activation mail unavailable.');
                $db->commit();
                unset($_SESSION['bootstrap_hash'],$_SESSION['bootstrap_secret']);session_regenerate_id(true);
                $closed('Die Einrichtung wurde gespeichert. Bitte bestätigen Sie Ihre E-Mail-Adresse über den einmaligen Aktivierungslink in Ihrem Thitronik-Postfach. Erst danach ist der Adminzugang aktiv.',200);
            }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();if($secretStored&&is_file(mfa_path($config,$setup['email'])))unlink(mfa_path($config,$setup['email']));throw $e;}
            finally{flock($lock,LOCK_UN);fclose($lock);}
        }
    }
    $issuer='SMARTDOCK Test';
    $provisioning='otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($setup['email']).'?'.http_build_query(['secret'=>$_SESSION['bootstrap_secret'],'issuer'=>$issuer,'algorithm'=>'SHA1','digits'=>6,'period'=>30],'','&',PHP_QUERY_RFC3986);
    $e=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES,'UTF-8');
    echo bootstrap_document('SMARTDOCK · Erstes Administratorkonto','<p class="eyebrow">SMARTDOCK · TESTUMGEBUNG</p><h1>Adminzugang einrichten</h1><p>Dieser einmalige Zugang richtet das erste Administratorkonto für <strong>'.$e($setup['email']).'</strong> ein. Es erhält Verwaltungsrechte für die separate Testdatenbank.</p><div class="admin-notice"><strong>1. Authenticator-App einrichten</strong><p>In Microsoft Authenticator auf + tippen, Anderes Konto wählen und den QR-Code scannen.</p><details class="setup-qr"><summary>QR-Code anzeigen</summary><div id="setup-qr" data-provisioning="'.$e($provisioning).'">QR-Code wird vorbereitet.</div></details><noscript><p>Für den QR-Code bitte JavaScript aktivieren oder den Schlüssel manuell eingeben.</p></noscript><p>Zeitbasiertes Konto (TOTP): 6 Ziffern, 30 Sekunden, SHA-1. Die Uhrzeit am Smartphone bitte automatisch einstellen.</p><details class="setup-secret"><summary>Einrichtungsschlüssel anzeigen</summary><code data-testid="totp-secret">'.$e($_SESSION['bootstrap_secret']).'</code><p>Falls die App den Code weiterhin ablehnt: Den Schlüssel hier mit dem in der App eingerichteten Konto abgleichen. Ein älterer Einrichtungsversuch kann einen anderen Schlüssel verwendet haben.</p></details></div><form method="post" action="/api/index.php?action=bootstrap"><input type="hidden" name="csrf" value="'.$e($_SESSION['csrf']).'"><label>Vorname<input name="first_name" autocomplete="given-name" required maxlength="200"></label><label>Nachname<input name="last_name" autocomplete="family-name" required maxlength="200"></label><label>Neues Passwort<input name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="72"></label><label>Passwort wiederholen<input name="repeat" type="password" autocomplete="new-password" required minlength="12" maxlength="72"></label><label>Aktueller Code aus der Authenticator-App<input name="otp" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" required maxlength="6"></label><p role="status">'.$e($error).'</p><button class="primary-button">Aktivierungslink per E-Mail anfordern</button></form><p class="subtle">Das Konto bleibt bis zur Bestätigung über den E-Mail-Link gesperrt. Der Link ist einmalig und 7 Tage gültig. Andere Konten werden nicht freigeschaltet.</p>');
    exit;
}
