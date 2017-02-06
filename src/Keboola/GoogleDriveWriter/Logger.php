<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/12/15
 * Time: 12:45
 */

namespace Keboola\GoogleDriveWriter;

use Keboola\GoogleDriveWriter\Logger\LineFormatter;
use Monolog\Handler\StreamHandler;

class Logger extends \Monolog\Logger
{
    private $debug = false;

    public function __construct($name = '')
    {
        $options = getopt("", ['debug']);
        if (isset($options['debug'])) {
            // Default format with all the info for dev debug
            $formatter = new LineFormatter();
            $this->debug = true;
        } elseif ($this->debug) {
            // Set user debug mode
            $formatter = new LineFormatter("%level_name%: %message% %context% %extra%\n");
        } else {
            // Simple message
            $formatter = new LineFormatter("%message%\n");
        }

        $errHandler = new StreamHandler('php://stderr', \Monolog\Logger::NOTICE, false);
        $level = $this->debug ? \Monolog\Logger::DEBUG : \Monolog\Logger::INFO;
        $handler = new StreamHandler('php://stdout', $level);
        $handler->setFormatter($formatter);

        parent::__construct($name, [$errHandler, $handler]);
    }

    public function setDebug($bool)
    {
        $this->debug = $bool;
    }
}
