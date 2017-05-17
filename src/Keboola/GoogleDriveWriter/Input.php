<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 19/01/17
 * Time: 14:25
 */

namespace Keboola\GoogleDriveWriter;

use Keboola\Csv\CsvFile;
use Symfony\Component\Finder\Finder;

class Input
{
    private $dataDir;

    public function __construct($dataDir)
    {
        $this->dataDir = $dataDir;
    }

    public function getInputFiles()
    {
        $finder = new Finder();
        $finder->files()->notName('*.manifest');
        return $finder->in(sprintf('%s/in/files', $this->dataDir));
    }

    public function getInputFilePath($inputFilename)
    {
        return sprintf('%s/in/files/%s', $this->dataDir, $inputFilename);
    }

    public function getInputTablePath($tableId)
    {
        return sprintf('%s/in/tables/%s.csv', $this->dataDir, $tableId);
    }

    public function getInputCsv($tableId)
    {
        return new CsvFile($this->getInputTablePath($tableId));
    }
}
