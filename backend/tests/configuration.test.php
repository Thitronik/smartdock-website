<?php
declare(strict_types=1);
require __DIR__.'/../private/configuration-report.php';
use function Smartdock\{configuration_price,configuration_summary,configuration_pdf};
function expect(bool $value,string $message):void {if(!$value)throw new RuntimeException($message);}
$base=['country'=>'Deutschland','motorCount'=>0,'ankerwinde1'=>true,'street'=>'Musterstraße','houseNumber'=>'12a','postalCode'=>'24340','city'=>'Eckernförde','installationService'=>'Nein'];
$p=configuration_price($base);expect($p['totalPrice']===1250.0,'Anchor total');expect($p['items'][0]['name']==='WCU (1 Ankerwinde)','Anchor mapping');
$installed=configuration_price(array_replace($base,['installationService'=>'Ja']));expect($installed['totalPrice']===3250.0,'Installation total');
$bundle=configuration_price(array_replace($base,['motorCount'=>2,'promoCode'=>' PIBS26 ']));expect($bundle['bundleDiscount']===-500.0,'Bundle');expect($bundle['totalPrice']===4230.0,'Action discount');
$forged=configuration_price($base+['totalPrice'=>0,'configurationPrices'=>[['price'=>0]],'status'=>'completed']);expect($p===$forged,'Client totals ignored');
foreach([['motorCount'=>-1],['country'=>'<script>'],['horn'=>'true'],['promoCode'=>[]]] as $invalid){try{configuration_price(array_replace($base,$invalid));throw new RuntimeException('Invalid selection accepted');}catch(InvalidArgumentException){}}
$contact=['name'=>'Adrian Muster','email'=>'test@example.invalid','company'=>'Muster GmbH','phone'=>'+49 123 456'];
$summary=configuration_summary($contact,$base,$p,'TEST-123456');expect(str_contains($summary,'Musterstraße 12a'),'Street');expect(str_contains($summary,'1.250,00 EUR'),'Formatted price');expect(!str_contains($summary,'configurationPrices'),'No raw JSON');
$pdf=configuration_pdf($contact,$base,$p,'TEST-123456');expect(str_starts_with($pdf,'%PDF-'),'PDF output');expect(strlen($pdf)>1000&&strlen($pdf)<2000000,'PDF size');
if(is_file(__DIR__.'/.configuration-price-cases.json')){
 $cases=json_decode(file_get_contents(__DIR__.'/.configuration-price-cases.json'),true,32,JSON_THROW_ON_ERROR);
 foreach($cases as $i=>$case){$actual=configuration_price($case['selection']);$expected=$case['expected'];foreach(['subtotal','bundleDiscount','totalPrice','vat','currency'] as $key)expect($actual[$key]==$expected[$key],'Price parity '.$i.' '.$key);foreach($expected['items'] as $j=>$line)expect($actual['items'][$j]['name']===$line['name']&&$actual['items'][$j]['price']==$line['price'],'Line parity '.$i.' '.$j);expect(count($actual['items'])===count($expected['items']),'Line count '.$i);}
 echo count($cases)." reference price cases passed.\n";
}
if(in_array('--pdf',$argv,true))file_put_contents(__DIR__.'/.configuration-proof.pdf',$pdf);
echo "Configuration price, validation, summary and PDF checks passed.\n";
