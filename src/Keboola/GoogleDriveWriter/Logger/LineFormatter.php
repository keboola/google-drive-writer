<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace Keboola\GoogleDriveWriter\Logger;

use Keboola\Csv\CsvFile;
use Monolog\Formatter\LineFormatter as MonologLineFormatter;

class LineFormatter extends MonologLineFormatter
{
    /**
     * @param mixed $data
     * @param int $depth
     * @return mixed|string
     */
    protected function normalize($data, $depth = 0)
    {
        if ($data instanceof CsvFile) {
            return 'csv file: ' . $data->getFilename();
        }
        return parent::normalize($data);
    }
}
