<?php

declare(strict_types=1);

namespace Keboola\GoogleDriveWriter;

use Keboola\GoogleDriveWriter\Logger\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;

class Logger extends MonologLogger
{
    private bool $debug = false;

    public function __construct(string $name = '')
    {
        $options = getopt('', ['debug']);
        if (isset($options['debug'])) {
            $this->debug = true;
        }

        $formatter = $this->getFormatter();
        $errHandler = new StreamHandler('php://stderr', MonologLogger::NOTICE, false);
        $level = $this->debug ? MonologLogger::DEBUG : MonologLogger::INFO;
        $handler = new StreamHandler('php://stdout', $level);
        $handler->setFormatter($formatter);

        parent::__construct($name, [$errHandler, $handler]);
    }

    public function setDebug(bool $bool): void
    {
        $this->debug = $bool;
    }

    private function getFormatter(): LineFormatter
    {
        if ($this->debug) {
            return new LineFormatter();
        }

        return new LineFormatter("%message%\n");
    }
}
