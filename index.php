<?php

chdir(__DIR__);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ————————————————————————————————————————————————————————————————————————————————
// Includes
// ————————————————————————————————————————————————————————————————————————————————
require_once 'inc/DB.class.php';
require_once 'config/db.php';

// require_once 'inc/simple_html_dom.php';
require_once 'inc/functions.php';

// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————
$controller = $argc > 1 ? $argv[1] : false;
if (!$controller) $controller = 'main';
if (!file_exists('controllers/'.$controller.'.php')) exit('Неправильна дія'.PHP_EOL);

// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————
include 'controllers/'.$controller;

// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————
echo 'DONE', PHP_EOL;
