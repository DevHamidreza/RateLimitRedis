<?php

use RateLimit\RateLimit;
use RateLimit\SpamDetector;

require_once 'vendor/autoload.php';

$rateLimit = new RateLimit();
$spamDetector = new SpamDetector();

$isLimited = $rateLimit->isLimited('index');

if($isLimited){
    die('You are blocked');
}

$id = '12';
$isSpam = $spamDetector->isSpam($id);

if ($isSpam) {
    die('Please do not spam' );
}

echo "welcome";