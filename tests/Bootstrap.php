<?php
ini_set('short_open_tag', '1');

date_default_timezone_set('UTC');
#ini_set('short_open_tag',true);
if(file_exists(__DIR__.'/../vendor/autoload.php')) {
    $loader = require __DIR__.'/../vendor/autoload.php';
} else {
    $loader = require __DIR__.'/init_autoloader.php';
}
