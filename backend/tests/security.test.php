<?php
declare(strict_types=1);
require __DIR__ . '/../private/security.php';
use function Smartdock\{normalized_email,staff_allowed,active_user,dealer_allowed,totp_code,totp_step,rate_limit,consume_totp,base32_encode,base32_decode,staff_secret,store_staff_secret,mfa_path};
$count=0;
function check(bool $ok,string $label):void {global $count;if(!$ok)throw new RuntimeException($label);$count++;echo "PASS $label\n";}
$allow=['admin@example.invalid'];
check(staff_allowed(' Admin@Example.invalid ',$allow,'example.invalid'),'Exact normalized staff identity');
check(!staff_allowed('other@example.invalid',$allow,'example.invalid'),'Same domain is insufficient');
check(!staff_allowed('admin@example.invalid.evil.test',$allow,'example.invalid'),'Suffix is insufficient');
check(!staff_allowed('not-an-address',$allow,'example.invalid'),'Malformed address denied');
check(!staff_allowed('user@foreign.invalid',['user@foreign.invalid']),'Approved foreign domain denied');
check(!staff_allowed('user@thitronik.de.foreign.invalid',['user@thitronik.de.foreign.invalid']),'Corporate suffix spoof denied');
check(!staff_allowed('user@sub.thitronik.de',['user@sub.thitronik.de']),'Corporate subdomain denied');
check(!staff_allowed('fixture@thitronik.de',[]),'Corporate domain alone grants no access');
check(staff_allowed('fixture@thitronik.de',['fixture@thitronik.de']),'Corporate domain and individual approval required');
$user=['id'=>10,'status'=>'active','email_verified_at'=>'2026-10-27','password_set_at'=>'2026-10-27','password_hash'=>'hash'];
$company=['id'=>20,'status'=>'active','finder_status'=>'unlisted'];
$member=['user_id'=>10,'dealer_id'=>20,'status'=>'approved'];
check(dealer_allowed($user,$member,$company),'Private dealer access independent of map');
check(!dealer_allowed($user,array_replace($member,['status'=>'pending']),array_replace($company,['finder_status'=>'approved'])),'Public map approval grants no login');
check(!dealer_allowed($user,array_replace($member,['user_id'=>11]),$company),'Another employee cannot reuse membership');
check(!dealer_allowed($user,array_replace($member,['dealer_id'=>21]),$company),'Another company cannot reuse membership');
foreach(['invited','suspended'] as $status)check(!active_user(array_replace($user,['status'=>$status])),'Inactive user denied '.$status);
foreach(['email_verified_at','password_set_at','password_hash'] as $field)check(!active_user(array_replace($user,[$field=>null])),'Missing '.$field.' denied');
check(!dealer_allowed($user,$member,array_replace($company,['status'=>'archived'])),'Archived company denied');
// RFC 6238 SHA-1 test vectors, not real credentials.
$secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
foreach([59=>'94287082',1111111109=>'07081804',1111111111=>'14050471',1234567890=>'89005924',2000000000=>'69279037',20000000000=>'65353130'] as $time=>$code){check(totp_code($secret,intdiv($time,30),8)===$code,'RFC6238 '.$time);}
$now=1234567890;$step=intdiv($now,30);
check(totp_step($secret,totp_code($secret,$step),$now)===$step,'Current OTP accepted');
check(totp_step($secret,totp_code($secret,$step-1),$now)===$step-1,'One-step clock window');
check(totp_step($secret,totp_code($secret,$step-3),$now)===null,'Expired OTP denied');
check(totp_step($secret,'123',$now)===null,'Malformed OTP denied');
$directory=__DIR__.'/.sd-security-'.bin2hex(random_bytes(8));mkdir($directory,0700);
try{
 for($i=0;$i<10;$i++)check(rate_limit($directory,'rate',1000), 'Rate limit permitted attempt '.$i);
 check(!rate_limit($directory,'rate',1000),'Limit blocks attempt 11');
 check(!rate_limit($directory,'rate',1899),'Limit persists to window boundary');
 check(rate_limit($directory,'rate',1900),'Window resets after 15 minutes');
 check(consume_totp($directory,'replay',100),'First OTP consumption succeeds');
 check(!consume_totp($directory,'replay',100),'OTP replay denied');
 check(!consume_totp($directory,'replay',99),'Older OTP denied');
 check(consume_totp($directory,'replay',101),'New OTP allowed');
 mkdir($directory.'/mfa',0700);$config=['mfa_directory'=>$directory.'/mfa','rate_key'=>str_repeat('a',64),'staff_totp_secrets'=>[]];
 check(base32_decode(base32_encode(str_repeat('x',20)))===str_repeat('x',20),'Base32 enrollment roundtrip');
 store_staff_secret($config,'admin@example.invalid',$secret);
 check(staff_secret($config,'admin@example.invalid')===$secret,'Encrypted MFA enrollment roundtrip');
 check(!str_contains(file_get_contents(mfa_path($config,'admin@example.invalid')),$secret),'MFA key not stored in plaintext');
 check(staff_secret($config,'other@example.invalid')==='','Other identity has no MFA key');
 $rejected=false;try{store_staff_secret($config,'admin@example.invalid',$secret);}catch(RuntimeException){$rejected=true;}check($rejected,'Existing enrollment cannot be overwritten');
 $rejected=false;try{staff_secret(array_replace($config,['rate_key'=>str_repeat('b',64)]),'admin@example.invalid');}catch(RuntimeException){$rejected=true;}
 // Different master key also changes the lookup name, so it safely returns empty.
 check($rejected||staff_secret(array_replace($config,['rate_key'=>str_repeat('b',64)]),'admin@example.invalid')==='','Wrong master key grants no enrollment');
}finally{foreach(glob($directory.'/*.json') as $file)unlink($file);if(is_dir($directory.'/mfa')){foreach(glob($directory.'/mfa/*.key') as $file)unlink($file);rmdir($directory.'/mfa');}rmdir($directory);}
echo "$count security checks passed.\n";
