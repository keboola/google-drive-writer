<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/01/17
 * Time: 14:23
 */

namespace Keboola\GoogleDriveWriter\Writer;

use GuzzleHttp\Exception\ClientException;
use Keboola\Csv\CsvFile;
use Keboola\GoogleDriveWriter\GoogleDrive\Client;
use Keboola\GoogleDriveWriter\Input;

class Sheet
{
    /** @var Client */
    private $client;

    /** @var Input */
    private $input;

    public function __construct(Client $client, Input $input)
    {
        $this->client = $client;
        $this->input = $input;
    }

    public function update($sheet)
    {
        try {
            $gdSpreadsheet = $this->client->getSpreadsheet($sheet['fileId']);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }

            // file doesn't exist
            $gdFile = $this->client->createFile(
                $this->input->getInputTablePath($sheet['tableId']),
                $sheet['title'],
                [
                    'parents' => $sheet['parents'],
                    'mimeType' => Client::MIME_TYPE_SPREADSHEET
                ]
            );

            $gdSpreadsheet = $this->client->getSpreadsheet($gdFile['id']);
        }

        $gdFile = $this->client->getFile($sheet['fileId'], ['id', 'name', 'parents']);

        // sync metadata
        $this->syncFileMetadata($sheet, $gdFile);
        $this->syncSpreadsheetMetadata($sheet, $gdSpreadsheet);

        // upload values
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

    private function syncFileMetadata($sheet, $gdFile)
    {
        $parentsToAdd = [];
        foreach ($sheet['parents'] as $parent) {
            if (false === array_search($parent, $gdFile['parents'])) {
                $parentsToAdd[] = $parent;
            }
        }
        $body = [];
        if ($sheet['title'] !== $gdFile['name']) {
            $body['name'] = $sheet['title'];
        }
        $params = [];
        if (!empty($parentsToAdd)) {
            $params['addParents'] = $parentsToAdd;
        }

        if (!empty($body) || !empty($params)) {
            $this->client->updateFileMetadata($gdFile['id'], $body, $params);
        }
    }

    private function syncSpreadsheetMetadata($sheet, $gdSpreadsheet)
    {
        //@todo spreadsheet title, sheets, sheet titles

        // rename sheet in spreadsheet
        $this->client->updateSheet($gdSpreadsheet['spreadsheetId'], [
            'sheetId' => $gdSpreadsheet['sheets'][0]['properties']['sheetId'],
            'title' => 'sheet1'
        ]);
    }
}
