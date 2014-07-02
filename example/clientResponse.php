<?php
set_time_limit(0);
require dirname(__DIR__) . '/vendor/autoload.php';

use W4Y\Crawler\Client\Client;
use W4Y\Crawler\Response\Response;
use W4Y\Crawler\Filter;

$client = new Client();
$client->setUrl('http://www.google.com');
$client->request();

echo $responseCode = $client->getResponseCode();
// echo $client->getBody();