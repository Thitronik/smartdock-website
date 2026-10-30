<?php
require __DIR__.'/../private/newsletter.php';
use function Smartdock\{newsletter_group,newsletter_content,newsletter_html};
function expect($ok){if(!$ok)throw new RuntimeException('Newsletter regression failed');}
$dealers=[['public_email'=>'contact@boat.example','status'=>'active'],['public_email'=>'dealer@gmail.com','status'=>'active'],['public_email'=>'old@old.example','status'=>'archived']];
expect(newsletter_group('NEW@BOAT.EXAMPLE',$dealers)==='dealer');
expect(newsletter_group('new@boat.example.evil',$dealers)==='customer');
expect(newsletter_group('other@gmail.com',$dealers)==='customer');
expect(newsletter_group('dealer@gmail.com',$dealers)==='dealer');
expect(newsletter_group('new@old.example',$dealers)==='customer');
$content=newsletter_content(['subject'=>'Neuigkeiten','title'=>'<img src=x onerror=alert(1)>','text'=>'Hallo & willkommen']);
$html=newsletter_html($content,'https://example.test/unsubscribe',['origin'=>'https://example.test']);
expect(str_contains($html,'&lt;img src=x onerror=alert(1)&gt;'));
expect(str_contains($html,'https://example.test/unsubscribe'));
expect(!str_contains($html,'class="sd-cta-text"')); // no CTA without target
expect(str_contains($html,'color-scheme'));
expect(str_contains($html,'bgcolor="#ffffff"'));
expect(str_contains($html,'border="0" cellpadding="0" cellspacing="0"'));
expect(preg_match('/<h1[^>]*style="[^"]*color:#1d1d1b/', $html)===1);
expect(str_contains($html,'prefers-color-scheme:dark'));
expect(str_contains($html,'https://example.test/media/newsletter-template/dark.png'));
$cta=newsletter_html(newsletter_content(['subject'=>'x','title'=>'x','text'=>'x','button_label'=>'Weiter','button_url'=>'https://example.test']), 'https://example.test/unsubscribe',['origin'=>'https://example.test']);
expect(str_contains($cta,'class="sd-cta-text"'));
expect(str_contains($cta,'https://example.test/media/newsletter-template/green.png'));
foreach(['javascript:alert(1)','http://example.test','https://user:secret@example.test'] as $url){try{newsletter_content(['subject'=>'x','title'=>'x','text'=>'x','image_url'=>$url]);throw new RuntimeException('Unsafe URL accepted');}catch(InvalidArgumentException){}}
try{newsletter_content(['subject'=>"x\r\nBcc: victim@example.test",'title'=>'x','text'=>'x']);throw new RuntimeException('Header injection accepted');}catch(InvalidArgumentException){}
echo "Newsletter grouping and template checks passed.\n";

$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=');
if(\Smartdock\newsletter_image_info($png)[0]!==1)throw new RuntimeException('Valid PNG rejected');
foreach(['<?php echo 1;','<svg xmlns="http://www.w3.org/2000/svg"></svg>',str_repeat('x',5*1024*1024+1)] as $invalid){try{\Smartdock\newsletter_image_info($invalid);throw new RuntimeException('Invalid image accepted');}catch(InvalidArgumentException){}}
$large=substr_replace($png,pack('NN',6001,1),16,8);
try{\Smartdock\newsletter_image_info($large);throw new RuntimeException('Oversized dimensions accepted');}catch(InvalidArgumentException){}
echo "Newsletter upload validation passed.\n";
