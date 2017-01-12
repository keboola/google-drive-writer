<?php

namespace Keboola\GoogleDriveWriter\Logger;

use Keboola\Csv\CsvFile;

class LineFormatter extends \Monolog\Formatter\LineFormatter
{
    protected function normalize($data)
    {
        if ($data instanceof CsvFile) {
            return "csv file: " . $data->getFilename();
        } else {
            return parent::normalize($data);
        }
    }
}
