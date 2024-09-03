<?php

require_once 'vendor/autoload.php';

$rateLimit = new Src\RateLimit();
$isLimited = $rateLimit->isLimited('index');

if($isLimited){
    die('You are blocked');
}

echo "welcome";