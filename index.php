<?php

include_once('googlePlay.php');

date_default_timezone_set("Asia/Taipei");

$config = [
    'pj1' => [
        'packageName' => 'com.xxx', 
        'iss' => 'voidedpurchases@api-project.com', 
        'keyFile' => 'Project.json'],
    'pj2' => [
        'packageName' => 'com.yyy', 
        'iss' => 'voidedpurchases@api-project.com', 
        'keyFile' => 'Project.json']
];

(new googleplay)->getVoidedPurchases($config[$_GET['packageName']]);