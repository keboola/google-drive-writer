<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/01/17
 * Time: 14:23
 */

namespace Keboola\GoogleDriveWriter\Writer;

use Keboola\Csv\CsvFile;
use Keboola\GoogleDriveWriter\GoogleDrive\Client;

class Sheet
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function update($sheet)
    {
        $driveFile = null;
        if ($sheet['fileId'] !== null) {
            try {
                $driveFile = $this->client->getSpreadsheet($sheet['fileId']);
            } catch (\Exception $e) {
            }
        }

        // file do not exist
        if ($driveFile == null) {
            // upload file
            $this->client->createFile($sheet['pathname'], $sheet['title']);
        }

        $csvFile = new CsvFile($sheet['pathname']);
        $offset = 0;
        $limit = 1000;
        $csvFile->next();
        $responses = [];
        $columns = count($csvFile->current());
        while ($csvFile->current()) {
            $i = 0;
            $values = [];
            while ($i < $limit) {
                $values[] = $csvFile->current();
                $csvFile->next();
                $i++;
            }
            $responses[] = $this->client->updateSpreadsheetValues(
                $sheet['fileId'],
                $this->getRange($sheet['sheetTitle'], $columns, $offset, $limit),
                $values
            );
            $offset = $i;
        }

        return $responses;

        // file exists, check if the sheet exists
//        $sheetExists = false;
//        foreach ($driveFile['sheets'] as $driveSheet) {
//            if ($driveSheet['properties']['title'] == $sheet['sheetTitle']) {
//                $sheetExists = true;
//            }
//        }
//
//        if ($sheetExists) {
//
//        }
    }

    public function append($sheet)
    {

    }

    public function getRange($sheetTitle, $columnCount, $rowOffset = 1, $rowLimit = 1000)
    {
        $lastColumn = $this->getColumnA1($columnCount-1);

        $start = 'A' . $rowOffset;
        $end = $lastColumn . ($rowOffset + $rowLimit - 1);

        return urlencode($sheetTitle) . '!' . $start . ':' . $end;
    }

    private function getColumnA1($columnNumber)
    {
        $alphas = range('A', 'Z');

        $prefix = '';
        if ($columnNumber > 25) {
            $quotient = intval(floor($columnNumber/26));
            $prefix = $alphas[$quotient-1];
        }

        $remainder = $columnNumber%26;

        return $prefix . $alphas[$remainder];
    }
}
