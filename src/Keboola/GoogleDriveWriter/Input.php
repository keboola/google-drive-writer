<?php

declare(strict_types=1);

namespace Keboola\GoogleDriveWriter;

use Keboola\Csv\CsvFile;
use Symfony\Component\Finder\Finder;

class Input
{
    private string $dataDir;

    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
    }

    public function getInputFiles(): Finder
    {
        $finder = new Finder();
        $finder->files()->notName('*.manifest');
        return $finder->in(sprintf('%s/in/files', $this->dataDir));
    }

    public function getInputFilePath(string $inputFilename): string
    {
        return sprintf('%s/in/files/%s', $this->dataDir, $inputFilename);
    }

    public function getInputTablePath(string $tableId): string
    {
        return sprintf('%s/in/tables/%s.csv', $this->dataDir, $tableId);
    }

    public function getInputCsv(string $tableId): CsvFile
    {
        return new CsvFile($this->getInputTablePath($tableId));
    }
}
