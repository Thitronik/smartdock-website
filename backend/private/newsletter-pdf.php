<?php
declare(strict_types=1);
namespace Smartdock;
require_once __DIR__.'/configuration-pdf-layout.php';

function newsletter_pdf(array $content,string $unsubscribe,array $config): string {
    $pdf=new ConfigurationPdf();$pdf->SetMargins(20,20,20);$pdf->SetAutoPageBreak(true,22);$pdf->AddPage();
    $pdf->SetTitle($content['subject'],true);$pdf->Image(__DIR__.'/configuration-logo.png',20,20,85);$pdf->SetY(40);
    $pdf->SetFont('Helvetica','B',22);$pdf->SetTextColor(29,29,27);$pdf->MultiCell(170,10,$pdf->encoded($content['title']));$pdf->Ln(6);
    // Only read our re-encoded uploads from disk. Never fetch a supplied URL.
    $prefix=rtrim($config['origin'],'/').'/media/newsletter/';$url=$content['image_url']??'';
    if(str_starts_with($url,$prefix)&&preg_match('/\A[a-f0-9]{48}\.png\z/D',substr($url,strlen($prefix)))){
        $path=dirname($_SERVER['SCRIPT_FILENAME'],2).'/media/newsletter/'.substr($url,strlen($prefix));
        if(is_file($path)&&($info=getimagesize($path))){$w=min(170,100*$info[0]/$info[1]);$h=$w*$info[1]/$info[0];if($pdf->GetY()+$h>270)$pdf->AddPage();$y=$pdf->GetY();$pdf->Image($path,20,$y,$w,$h);$pdf->SetY($y+$h+8);}
    }
    $pdf->SetFont('Helvetica','',11);$pdf->MultiCell(170,6,$pdf->encoded($content['text']));$pdf->Ln(8);
    if($content['button_url']){$pdf->SetDrawColor(188,235,2);$pdf->SetFont('Helvetica','B',11);$pdf->Cell(170,12,$pdf->encoded($content['button_label']),1,1,'C',false,$content['button_url']);$pdf->Ln(8);}
    $pdf->SetFont('Helvetica','',9);$pdf->MultiCell(170,5,$pdf->encoded($config['newsletter_footer']??'SMARTDOCK · Thitronik GmbH'));$pdf->Ln(4);
    $pdf->Cell(170,6,$pdf->encoded($unsubscribe!==''?'Newsletter abmelden':'Testnewsletter – kein Abonnement.'),0,1,'L',false,$unsubscribe);
    return $pdf->Output('S');
}

function newsletter_attachment(array $content,string $unsubscribe,array $config): array {
    return [['name'=>'SMARTDOCK-Newsletter.pdf','base64'=>base64_encode(newsletter_pdf($content,$unsubscribe,$config))]];
}
