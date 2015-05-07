<?php

include dirname(__DIR__) . "/vendor/autoload.php";

use \Sb\Browser\Console;

$options = [
    Console::OPTION_CACHE => new \Sb\Browser\Cache\FileCache(realpath(dirname(__FILE__) . '/../cache')),
    Console::OPTION_USER_AGENT => \Sb\Browser\UserAgent::FIREFOX_V35,
    //Console::OPTION_PROXY => '127.0.0.1:8080'
];

$browser = new Console($options);

$browser->get('http://example.com');
echo $browser->domFindFirst('h1');
