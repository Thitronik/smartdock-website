<?php
declare(strict_types=1);
namespace Smartdock;

function configuration_reference(callable $reserve): string {
    // Only return a number after its atomic reservation in the database succeeds.
    for($attempt=0;$attempt<32;$attempt++) {
        $digits=sprintf('%08d',random_int(0,99999999));
        $reference='SD-'.substr($digits,0,4).'-'.substr($digits,4);
        if($reserve($reference))return $reference;
    }
    throw new \RuntimeException('Unable to reserve a unique configuration reference.');
}

// Price rules mirror the existing configurator. No submitted amount, recipient,
// module name or HTML is used as an authoritative value.
function configuration_price(array $s): array {
    $countries=json_decode(file_get_contents(__DIR__.'/configuration-countries.json'),true,16,JSON_THROW_ON_ERROR);
    $country=$s['country']??'Deutschland';
    if(!is_string($country)||!isset($countries[$country]))throw new \InvalidArgumentException('Country invalid.');
    $c=$countries[$country];$swiss=$country==='Schweiz';$lines=[];
    $add=static function(string $type,string $name)use(&$lines,$c,$swiss):void {
        $net=['ECU'=>3319.33,'TCU'=>1218.49,'ACU'=>1218.49,'WCU'=>1050.42,'INSTALLATION'=>1680.67];
        $ch=['ECU'=>4481.1,'TCU'=>2437,'ACU'=>2437,'WCU'=>2100,'INSTALLATION'=>1500];
        $lines[]=['type'=>$type,'name'=>$name,'price'=>$swiss?$ch[$type]:round($net[$type]*(1+$c['vat']/100)*$c['rate'],2)];
    };
    $motor=$s['motorCount']??0;if(!is_int($motor)||$motor<0||$motor>8)throw new \InvalidArgumentException('Motor count invalid.');
    if($motor)$add('ECU',$motor===1?'ECU (1 Motor)':'ECU (2+ Motoren)');
    $extras=[];foreach(['ankerwinde1'=>'Ankerwinde','ankerwinde2'=>'Ankerwinde','ankerwinch1'=>'Winch','ankerwinch2'=>'Winch','horn'=>'Horn'] as $k=>$label){if(isset($s[$k])&&!is_bool($s[$k]))throw new \InvalidArgumentException('Selection invalid.');if($s[$k]??false)$extras[]=$label;}
    $thrusters=[];foreach(['bugstrahlruder'=>'Bugstrahlruder','heckstrahlruder'=>'Heckstrahlruder'] as $k=>$label){if(isset($s[$k])&&!is_bool($s[$k]))throw new \InvalidArgumentException('Selection invalid.');if($s[$k]??false)$thrusters[]=$label;}
    $manufacturer=$s['thrusterManufacturer']??'';if(!is_string($manufacturer))throw new \InvalidArgumentException('Manufacturer invalid.');
    if(count($thrusters)===1&&$extras&&!str_contains(strtolower($manufacturer),'proportional'))$add('ACU','ACU ('.$thrusters[0].', '.array_shift($extras).')');
    elseif($thrusters)$add('TCU','TCU ('.implode(', ',$thrusters).')');
    while($extras){$pair=array_splice($extras,0,2);$labels=[];foreach(array_count_values($pair) as $label=>$count)$labels[]=$count.' '.($count===2?['Ankerwinde'=>'Ankerwinden','Winch'=>'Winches','Horn'=>'Horn'][$label]:$label);$add('WCU','WCU ('.implode(', ',$labels).')');}
    if(($s['installationService']??'Nein')==='Ja')$add('INSTALLATION','Einbauservice');
    if(!$lines)throw new \InvalidArgumentException('Empty configuration.');
    $subtotal=round(array_sum(array_column($lines,'price')),2);
    $types=array_column($lines,'type');$bundle=in_array('ECU',$types,true)&&array_intersect(['TCU','ACU','WCU'],$types)&&!$swiss?round(500*$c['rate'],2):0;
    $promo=$s['promoCode']??'';if(!is_string($promo)||strlen($promo)>40)throw new \InvalidArgumentException('Promo invalid.');
    $discount=strtoupper(trim($promo))==='PIBS26'?round(($subtotal-$bundle)*.1,2):0;
    return ['version'=>'website-2026-10','items'=>$lines,'subtotal'=>$subtotal,'bundleDiscount'=>-$bundle,'actionDiscount'=>-$discount,'totalPrice'=>round($subtotal-$bundle-$discount,2),'currency'=>$c['currency'],'vat'=>$c['vat']];
}
function configuration_money(float $amount,string $currency): string {return number_format($amount,2,',','.').' '.$currency;}
function configuration_summary(array $contact,array $s,array $price,string $reference): string {
    $out=['Referenz: '.$reference,'Name: '.$contact['name'],'E-Mail: '.$contact['email']];
    foreach(['company'=>'Unternehmen','phone'=>'Telefon'] as $key=>$label)if(!empty($contact[$key]))$out[]=$label.': '.$contact[$key];
    $out[]='Anschrift: '.trim(($s['street']??'').' '.($s['houseNumber']??''));$out[]=trim(($s['postalCode']??'').' '.($s['city']??'')).', '.($s['country']??'');
    $out[]='';$out[]='Ausgewählte Komponenten';foreach($price['items'] as $line)$out[]=$line['name'].' – '.configuration_money((float)$line['price'],$price['currency']);
    foreach(['motorType'=>'Motorhersteller','joystickType'=>'Steuerung','thrusterManufacturer'=>'Strahlruderhersteller','thrusterType'=>'Strahlrudertyp'] as $k=>$label)if(!empty($s[$k])&&$s[$k]!=='N/A')$out[]=$label.': '.$s[$k];
    $out[]='';$out[]='Zwischensumme: '.configuration_money($price['subtotal'],$price['currency']);
    foreach(['bundleDiscount'=>'Paketrabatt','actionDiscount'=>'Aktionsrabatt'] as $k=>$label)if($price[$k])$out[]=$label.': '.configuration_money($price[$k],$price['currency']);
    $out[]='MwSt.: '.$price['vat'].' % inklusive';$out[]='Gesamtpreis: '.configuration_money($price['totalPrice'],$price['currency']);
    $out[]='';$out[]='Einbauhändler: '.($s['dealer']['company_name']??'Kein Einbauservice gewählt');
    if(!empty($s['dealer']))$out[]=trim(implode(' ',array_filter([$s['dealer']['street'],$s['dealer']['house_number'],$s['dealer']['postal_code'],$s['dealer']['city']])));
    $out[]='';$out[]='Unverbindliche Konfigurationsanfrage. Auswahl, Einbau und Preise werden vor einem Angebot geprüft. Dies ist keine Bestell- oder Terminbestätigung.';
    return implode("\n",$out);
}
function configuration_pdf(array $contact,array $s,array $price,string $reference): string {
    require_once __DIR__.'/configuration-pdf-layout.php';
    return render_configuration_pdf($contact,$s,$price,$reference);
}
