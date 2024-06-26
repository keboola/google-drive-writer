<?php

declare(strict_types=1);

define('APP_NAME', 'wr-google-drive');
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

date_default_timezone_set('Europe/Prague');

ini_set('display_errors', '1');
error_reporting(E_ALL);

set_error_handler(
    function ($errno, $errstr, $errfile, $errline, array $errcontext) {
        // error was suppressed with the @-operator
        if (error_reporting() === 0) {
            return false;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    },
);

require_once ROOT_PATH . '/vendor/autoload.php';
