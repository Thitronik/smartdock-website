<?php
declare(strict_types=1);
namespace Smartdock;

// Shared by every outgoing HTML message, including newsletters and admin setup.
function mail_brand_header(array $config): string {
    $e=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $logo=$e($config['origin'].'/media/positionhold/sd-logo_variant_green-white_rgb_png-DJz8V.png');
    $dark=$e($config['origin'].'/media/newsletter-template/dark.png');
    return '<tr data-sd-mail-brand="1"><td class="sd-brand sd-pad" background="'.$dark.'" bgcolor="#191c20" style="border:0;background-color:#191c20;background-image:url('.$dark.');color:#ffffff;padding:28px;text-align:left"><a href="https://www.smartdock.de/" target="_blank" rel="noopener noreferrer" style="display:inline-block;text-decoration:none;border:0"><img alt="SMARTDOCK" src="'.$logo.'" width="240" border="0" style="display:block;width:240px;max-width:100%;height:auto;border:0;color:#ffffff"></a></td></tr>';
}
function transactional_mail_html(array $config,string $subject,string $body): string {
    $e=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $content=nl2br($e($body));
    $pattern='~'.preg_quote($config['origin'].'/api/index.php?action=','~').'(activate-admin|dealer-invitation|dealer-confirm|dealer-password|dealer-approve|staff-invitation|newsletter-confirm|newsletter-unsubscribe)&amp;token=[a-f0-9]{64}~';
    $content=preg_replace_callback($pattern,static function(array $match):string {
        $label=match($match[1]){'dealer-approve'=>'Händlerzugang prüfen und freigeben','dealer-invitation'=>'Händlerzugang einrichten','dealer-password'=>'Händlerpasswort festlegen','staff-invitation'=>'Adminzugang einrichten','activate-admin'=>'Adminzugang bestätigen','newsletter-confirm'=>'Newsletter-Anmeldung bestätigen','newsletter-unsubscribe'=>'Abmelden',default=>'E-Mail bestätigen'};
        return '<a style="display:inline-block;padding:14px 20px;background:#bceb02;color:#1d1d1b;border-radius:8px;text-decoration:none;font-weight:bold" href="'.$match[0].'">'.$label.'</a>';
    },$content);
    $header=mail_brand_header($config);$title=$e($subject);$dark=$e($config['origin'].'/media/newsletter-template/dark.png');
    return <<<HTML
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark"><style>
:root{color-scheme:light dark;supported-color-schemes:light dark}table{border-spacing:0;mso-table-lspace:0pt;mso-table-rspace:0pt}img{border:0;outline:none;text-decoration:none}
@media(prefers-color-scheme:dark){.sd-page,.sd-content{background-color:#191c20!important;background-image:url($dark)!important;color:#f4f5f6!important}.sd-copy{color:#f4f5f6!important}.sd-brand{background-color:#191c20!important;background-image:url($dark)!important}}
[data-ogsc] .sd-copy{color:#f4f5f6!important}[data-ogsb] .sd-page,[data-ogsb] .sd-content,[data-ogsb] .sd-brand{background-color:#191c20!important;background-image:url($dark)!important}
@media(max-width:600px){.sd-pad{padding:24px!important}.sd-title{font-size:25px!important;line-height:32px!important}}
</style></head><body class="sd-page" bgcolor="#ffffff" style="margin:0;padding:0;background-color:#ffffff;color:#1d1d1b;font-family:Arial,Helvetica,sans-serif">
<table class="sd-page" role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:100%;border:0;border-collapse:collapse;background-color:#ffffff"><tr><td align="center" style="padding:20px 12px;border:0">
<!--[if mso]><table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
<table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;border:0;border-collapse:collapse">$header<tr><td class="sd-content sd-pad" bgcolor="#ffffff" style="border:0;padding:32px;background-color:#ffffff;color:#1d1d1b;font:16px/26px Arial,Helvetica,sans-serif;text-align:left"><h1 class="sd-copy sd-title" style="margin:0 0 24px;color:#1d1d1b;font:700 28px/36px Arial,Helvetica,sans-serif">$title</h1><div class="sd-copy" style="color:#1d1d1b;font:16px/26px Arial,Helvetica,sans-serif">$content</div></td></tr></table><!--[if mso]></td></tr></table><![endif]-->
</td></tr></table></body></html>
HTML;
}
