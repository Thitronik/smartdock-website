<?php
declare(strict_types=1);
// CI-only isolated database fixture. Never part of a website artifact.
if (PHP_SAPI !== 'cli' || getenv('SD_BACKEND_TEST_RUN') !== '1') exit(1);
$dsn=getenv('SD_TEST_DSN');
if (!$dsn || !str_contains($dsn,'dbname=smartdock_test;')) throw new RuntimeException('Isolated test database required.');
$db=new PDO($dsn,getenv('SD_TEST_DB_USER'),getenv('SD_TEST_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if($db->query('SHOW TABLES')->fetch())throw new RuntimeException('Empty isolated database required.');
foreach(['001_initial.sql','002_dealers_and_users.sql'] as $file)$db->exec(file_get_contents(__DIR__.'/../../database/migrations/'.$file));
$root=getenv('SD_TEST_STATE');if(!$root||!is_dir($root))throw new RuntimeException('Isolated state required.');
mkdir($root.'/state',0700);mkdir($root.'/sessions',0700);
$config=['origin'=>'https://test.smartdock.de','dsn'=>$dsn,'database_user'=>getenv('SD_TEST_DB_USER'),'database_password'=>getenv('SD_TEST_DB_PASSWORD'),'state_directory'=>$root.'/state','session_directory'=>$root.'/sessions','rate_key'=>bin2hex(random_bytes(32)),'dummy_password_hash'=>password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),'staff_allowlist'=>['admin@example.invalid'],'staff_totp_secrets'=>['admin@example.invalid'=>'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ']];
file_put_contents($root.'/config.php','<?php return '.var_export($config,true).';');chmod($root.'/config.php',0600);
$q=$db->prepare("INSERT INTO sd_users(first_name,last_name,email,email_normalized,password_hash,password_set_at,email_verified_at,status) VALUES('Test','Person',?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),'active')");
foreach(['admin@example.invalid','outsider@example.invalid','dealer@example.invalid'] as $email)$q->execute([$email,$email,password_hash('FictionalPassword-OnlyForCI!',PASSWORD_DEFAULT)]);
$db->exec("INSERT INTO sd_staff_roles(user_id,role) VALUES(1,'admin'),(2,'admin')");
$db->exec("INSERT INTO sd_dealer(company_name,country_code) VALUES('Example Company','DE')");
$db->exec("INSERT INTO sd_dealer_memberships(dealer_id,user_id,status) VALUES(1,3,'approved')");
require __DIR__.'/../private/security.php';
echo json_encode(['otp'=>Smartdock\totp_code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',intdiv(time(),30))],JSON_THROW_ON_ERROR);
