<?php
declare(strict_types=1);
// Local CI router emulates TLS termination. This file is never deployable.
if(PHP_SAPI!=='cli-server'||getenv('SD_BACKEND_TEST_RUN')!=='1'){http_response_code(404);exit;}
$_SERVER['HTTPS']='on';$_SERVER['HTTP_HOST']='test.smartdock.de';
ini_set('display_errors','0');header('Content-Type: application/json');
try{require __DIR__.'/../private/app.php';Smartdock\run(require getenv('SD_TEST_STATE').'/config.php');}
catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'Test failure','class'=>get_class($e)]);}
