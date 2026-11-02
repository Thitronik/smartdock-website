<?php
declare(strict_types=1);
// Private CLI entry; never deploy below public_html.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/app.php';
require_once __DIR__.'/mail.php';
try {
    $config=require __DIR__.'/config.php';
    $db=Smartdock\database($config);
    Smartdock\dispatch_mail($db,$config,20);
    echo "SMARTDOCK transactional mail queue checked.\n";
}catch(Throwable){fwrite(STDERR,"SMARTDOCK mail worker failed. Check private configuration and queue status.\n");exit(1);}
