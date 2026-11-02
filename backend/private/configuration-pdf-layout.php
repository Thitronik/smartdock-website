<?php
declare(strict_types=1);
namespace Smartdock;
require_once __DIR__.'/lib/fpdf/fpdf.php';

// Server rendering of the approved download layout: 800px canvas, 60px margins,
// 2:1 module/configuration columns, #f5f5f5 cards and a white grand-total row.
final class ConfigurationPdf extends \FPDF {
    public function rounded(float $x,float $y,float $w,float $h,float $r=2.1):void {
        $k=$this->k;$page=$this->h;$c=.5522847498;$r=min($r,$h/2,$w/2);
        $point=fn($a,$b)=>sprintf('%.3F %.3F',$a*$k,($page-$b)*$k);
        $this->_out($point($x+$r,$y).' m '.$point($x+$w-$r,$y).' l');
        $this->_out($point($x+$w-$r+$r*$c,$y).' '.$point($x+$w,$y+$r-$r*$c).' '.$point($x+$w,$y+$r).' c');
        $this->_out($point($x+$w,$y+$h-$r).' l '.$point($x+$w,$y+$h-$r+$r*$c).' '.$point($x+$w-$r+$r*$c,$y+$h).' '.$point($x+$w-$r,$y+$h).' c');
        $this->_out($point($x+$r,$y+$h).' l '.$point($x+$r-$r*$c,$y+$h).' '.$point($x,$y+$h-$r+$r*$c).' '.$point($x,$y+$h-$r).' c');
        $this->_out($point($x,$y+$r).' l '.$point($x,$y+$r-$r*$c).' '.$point($x+$r-$r*$c,$y).' '.$point($x+$r,$y).' c f');
    }
    public function encoded(string $text):string {return iconv('UTF-8','windows-1252//TRANSLIT',$text)?:$text;}
    public function lines(string $text,float $width,float $size=11.9,string $style='B'):array {
        $this->SetFont('Helvetica',$style,$size);$result=[];
        foreach(explode("\n",$text) as $paragraph){$line='';foreach(preg_split('//u',$paragraph,-1,PREG_SPLIT_NO_EMPTY)?:[] as $char){if($line!==''&&$this->GetStringWidth($this->encoded($line.$char))>$width){$result[]=rtrim($line);$line='';}$line.=$char;}$result[]=trim($line);}
        return $result;
    }
    public function textAt(float $x,float $y,string $text,float $size=11.9,string $style='',array $color=[23,23,23],float $width=0,string $align='L'):void {
        $this->SetFont('Helvetica',$style,$size);$this->SetTextColor(...$color);$this->SetXY($x,$y);$this->Cell($width,5.5,$this->encoded($text),0,0,$align);
    }
    public function cardHeight(array $fields,float $width):float {
        $h=8.4;foreach($fields as [$label,$value])$h+=4.2+count($this->lines($value,$width-8.4))*5.5+1.1;return $h;
    }
    public function card(float $x,float $y,float $width,array $fields,?float $height=null):float {
        $h=$height??$this->cardHeight($fields,$width);$this->SetFillColor(245,245,245);$this->rounded($x,$y,$width,$h);$at=$y+4.2;
        foreach($fields as [$label,$value]){$this->textAt($x+4.2,$at,$label,8.9,'',[115,115,115]);$at+=4.2;foreach($this->lines($value,$width-8.4) as $line){$this->textAt($x+4.2,$at,$line,11.9,'B');$at+=5.5;}$at+=1.1;}
        return $y+$h;
    }
    public function room(float $y,float $height):float {if($y+$height>278){$this->AddPage();return 16;}return $y;}
}

function render_configuration_pdf(array $contact,array $s,array $price,string $reference):string {
    $pdf=new ConfigurationPdf();$pdf->SetMargins(15.75,15.75,15.75);$pdf->SetAutoPageBreak(false);$pdf->SetTitle('SMARTDOCK - '.$reference,true);$pdf->AddPage();
    $left=15.75;$right=194.25;$width=178.5;
    $money=fn($n)=>configuration_money((float)$n,$price['currency']==='EUR'?'€':$price['currency']);
    $pdf->Image(__DIR__.'/configuration-logo.png',$left,15.75,68.25);
    $pdf->textAt($left,29,'Zusammenfassung & Anfrage',11.9,'',[115,115,115]);
    $pdf->textAt(105,21,'Datum: '.(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Berlin')))->format('d.m.Y'),10.4,'',[115,115,115],89.25,'R');
    $pdf->textAt(100,28,'Referenz: '.$reference,10.4,'B',[23,23,23],94.25,'R');
    $pdf->SetDrawColor(23,23,23);$pdf->SetLineWidth(.5);$pdf->Line($left,40,$right,40);
    $y=51;$pdf->textAt($left,$y,'Ausgewählte Module',13.4,'B');$pdf->textAt(138.25,$y,'Deine Konfiguration',13.4,'B');
    $moduleY=$y+11;
    foreach($price['items'] as $item){$lines=$pdf->lines($item['name'],78,11.9,'');$height=max(13,count($lines)*5.5+6);$moduleY=$pdf->room($moduleY,$height);foreach($lines as $i=>$line)$pdf->textAt($left,$moduleY+$i*5.5,$line,11.9,'',[64,64,64]);$pdf->textAt(94,$moduleY,$money($item['price']),11.9,'B',[23,23,23],33.75,'R');$pdf->SetDrawColor(229,229,229);$pdf->SetLineWidth(.25);$pdf->Line($left,$moduleY+$height-3,127.75,$moduleY+$height-3);$moduleY+=$height;}
    $cards=[];
    foreach(['motorType'=>'Motor','joystickType'=>'Steuerung','thrusterManufacturer'=>'Bug-/Heckstrahlruder','thrusterType'=>'Strahlrudertyp'] as $key=>$label)if(!empty($s[$key])&&$s[$key]!=='N/A')$cards[]=[$label,$s[$key]];
    if(!empty($s['motorCount']))$cards[]=['Anzahl Motoren',$s['motorCount']===1?'1 Motor':'2+ Motoren'];
    $cards[]=['Land',$s['country']??''];$cards[]=['Einbauservice',($s['installationService']??'Nein')==='Ja'?'Ja':'Nein'];
    if(!empty($s['dealer']))$cards[]=['Ausgewählter Händler',$s['dealer']['company_name']];
    $cardY=$y+9;
    foreach($cards as $card){$h=$pdf->cardHeight([$card],56);$cardY=$pdf->room($cardY,$h);$cardY=$pdf->card(138.25,$cardY,56,[$card])+3.15;}
    $y=max($cardY,$moduleY)+10.5;
    $contacts=[];foreach(['name'=>'Name','company'=>'Firma','email'=>'E-Mail','phone'=>'Telefon'] as $key=>$label)if(!empty($contact[$key]))$contacts[]=[[$label,$contact[$key]]];
    $contacts[]=[['Adresse / Straße',trim(($s['street']??'').' '.($s['houseNumber']??''))],['Stadt',trim(($s['postalCode']??'').' '.($s['city']??''))],['Land',$s['country']??'']];
    $y=$pdf->room($y,45);$pdf->textAt($left,$y,'Kontaktdaten',13.4,'B');$y+=9;
    foreach(array_chunk($contacts,2) as $row){$h=max(array_map(fn($fields)=>$pdf->cardHeight($fields,87.675),$row));$y=$pdf->room($y,$h);foreach($row as $i=>$fields)$pdf->card($left+$i*90.825,$y,87.675,$fields,$h);$y+=$h+3.15;}
    $y+=10.5;$discounts=[];foreach(['bundleDiscount'=>'Rabatt','actionDiscount'=>'Aktionsrabatt'] as $key=>$label)if(!empty($price[$key]))$discounts[]=[$label,$money($price[$key])];
    $boxH=29.5+count($discounts)*7;$y=$pdf->room($y,$boxH+49);
    $pdf->SetFillColor(245,245,245);$pdf->rounded($left,$y,$width,$boxH,3.15);$at=$y+8.4;
    $pdf->textAt($left+8.4,$at,'Zwischensumme',10.4,'',[115,115,115]);$pdf->textAt(110,$at,$money($price['subtotal']),10.4,'B',[23,23,23],75.85,'R');$at+=7;
    foreach($discounts as [$label,$amount]){$pdf->textAt($left+8.4,$at,$label,10.4,'',[188,235,2]);$pdf->textAt(110,$at,$amount,10.4,'B',[188,235,2],75.85,'R');$at+=7;}
    $pdf->textAt($left+8.4,$at,'MwSt. ('.$price['vat'].' %)',10.4,'',[115,115,115]);$pdf->textAt(110,$at,'inklusive',10.4,'',[115,115,115],75.85,'R');
    $y+=$boxH+8;$pdf->textAt($left+8.4,$y+2,'Gesamtpreis',13.4,'B');$pdf->textAt(85,$y,$money($price['totalPrice']),20.8,'B',[188,235,2],100.85,'R');
    $y+=20;$pdf->SetDrawColor(229,229,229);$pdf->SetLineWidth(.25);$pdf->Line($left,$y,$right,$y);$y+=7;
    foreach($pdf->lines('Die angezeigten Preise sind unverbindliche Preisempfehlungen inkl. der gesetzlichen Mehrwertsteuer.',$width,8.9,'') as $line){$pdf->textAt($left,$y,$line,8.9,'',[163,163,163],$width,'C');$y+=4.2;}
    $y+=3; $noticeLines=[];$noticeLine='';$pdf->SetFont('Helvetica','',8.9);
    foreach(explode(' ',configuration_nonbinding_notice()) as $word){$candidate=trim($noticeLine.' '.$word);if($noticeLine!==''&&$pdf->GetStringWidth($pdf->encoded($candidate))>$width){$noticeLines[]=$noticeLine;$noticeLine=$word;}else{$noticeLine=$candidate;}}
    if($noticeLine!=='')$noticeLines[]=$noticeLine; $y=$pdf->room($y,count($noticeLines)*4.2+10);
    foreach($noticeLines as $line){$pdf->textAt($left,$y,$line,8.9,'',[115,115,115],$width,'C');$y+=4.2;}
    $y+=3;
    $pdf->textAt($left,$y,'www.thitronik.de • smartdock@thitronik.de',8.9,'',[163,163,163],$width,'C');
    return $pdf->Output('S');
}
