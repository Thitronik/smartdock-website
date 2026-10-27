<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SD_BACKEND_TEST_RUN') !== '1') exit(1);
$dsn=getenv('SD_TEST_DSN');
if (!$dsn || !str_contains($dsn,'dbname=smartdock_test;')) throw new RuntimeException('Isolated test database required.');
$db=new PDO($dsn,getenv('SD_TEST_DB_USER'),getenv('SD_TEST_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$root=getenv('SD_TEST_STATE');if(!$root||!is_dir($root))throw new RuntimeException('Isolated state required.');
require __DIR__.'/../private/security.php';
if(count($argv)===1){
 if($db->query('SHOW TABLES')->fetch())throw new RuntimeException('Empty isolated database required.');
 foreach(['001_initial.sql','002_dealers_and_users.sql','003_access_requests_and_mail.sql'] as $file)$db->exec(file_get_contents(__DIR__.'/../../database/migrations/'.$file));
 foreach(['state','sessions','mfa'] as $folder)mkdir($root.'/'.$folder,0700);
 $token=bin2hex(random_bytes(32));
 $config=['origin'=>'https://test.smartdock.de','dsn'=>$dsn,'database_user'=>getenv('SD_TEST_DB_USER'),'database_password'=>getenv('SD_TEST_DB_PASSWORD'),'state_directory'=>$root.'/state','session_directory'=>$root.'/sessions','mfa_directory'=>$root.'/mfa','rate_key'=>bin2hex(random_bytes(32)),'dummy_password_hash'=>password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),'staff_email_domain'=>'example.invalid','mail_from'=>'activation-ci-fixture@smartdock.de','staff_allowlist'=>['admin@example.invalid'],'staff_totp_secrets'=>[],'bootstrap'=>['token_hash'=>hash('sha256',$token),'email'=>'admin@example.invalid','expires_at'=>time()+3600]];
 $config['dealer_approval_email']='management@example.invalid';
 file_put_contents($root.'/config.php','<?php return '.var_export($config,true).';');chmod($root.'/config.php',0600);
 echo json_encode(['bootstrapToken'=>$token],JSON_THROW_ON_ERROR);exit;
}
$config=require $root.'/config.php';
if(in_array('--pending-status',$argv,true)){
 echo json_encode(['roles'=>(int)$db->query('SELECT COUNT(*) FROM sd_staff_roles WHERE revoked_at IS NULL')->fetchColumn(),'user'=>$db->query('SELECT status,email_verified_at FROM sd_users WHERE id=1')->fetch(PDO::FETCH_ASSOC)]);exit;
}
if(in_array('--expire-mail',$argv,true)){$db->exec("UPDATE sd_user_tokens SET created_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 2 DAY),expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 DAY) WHERE purpose='verify_email' AND consumed_at IS NULL");echo '{}';exit;}
if(in_array('--restore-mail',$argv,true)){$db->exec("UPDATE sd_user_tokens SET created_at=UTC_TIMESTAMP(6),expires_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 7 DAY) WHERE purpose='verify_email' AND consumed_at IS NULL");echo '{}';exit;}
if(in_array('--remove-proof',$argv,true)){$db->exec("DELETE FROM sd_admin_audit_events WHERE action='staff.email_verified' AND target_id=1");echo '{}';exit;}

if((int)$db->query('SELECT COUNT(*) FROM sd_users')->fetchColumn()!==1 || $db->query('SELECT email_normalized FROM sd_users WHERE id=1')->fetchColumn()!=='admin@example.invalid')throw new RuntimeException('Bootstrap fixture required.');
$q=$db->prepare("INSERT INTO sd_users(first_name,last_name,email,email_normalized,password_hash,password_set_at,email_verified_at,status) VALUES('Test','Person',?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),'active')");
foreach(['outsider@example.invalid','dealer@example.invalid'] as $email)$q->execute([$email,$email,password_hash('FictionalPassword-OnlyForCI!',PASSWORD_DEFAULT)]);
$db->exec("INSERT INTO sd_staff_roles(user_id,role) VALUES(2,'admin')");
$db->exec("INSERT INTO sd_dealer(company_name,country_code) VALUES('Example Company','DE')");
$db->exec("INSERT INTO sd_dealer_memberships(dealer_id,user_id,status) VALUES(1,3,'approved')");
echo json_encode(['otp'=>Smartdock\totp_code(Smartdock\staff_secret($config,'admin@example.invalid'),intdiv(time(),30))],JSON_THROW_ON_ERROR);
