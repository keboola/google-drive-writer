<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 19/01/17
 * Time: 14:25
 */

namespace Keboola\GoogleDriveWriter;

class Input
{
    private $dataDir;

    public function __construct($dataDir)
    {
        $this->dataDir = $dataDir;
    }

    public function getInputTablePath($tableId)
    {
        return sprintf('%s/in/tables/%s.csv', $this->dataDir, $tableId);
    }
}
