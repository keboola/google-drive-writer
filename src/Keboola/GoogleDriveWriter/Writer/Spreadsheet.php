<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/01/17
 * Time: 14:23
 */

namespace Keboola\GoogleDriveWriter\Writer;

use GuzzleHttp\Exception\ClientException;
use Keboola\GoogleDriveWriter\Exception\UserException;
use Keboola\GoogleDriveWriter\GoogleDrive\Client;
use Keboola\GoogleDriveWriter\Input;

class Spreadsheet
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

    public function update($spreadsheet)
    {
        try {
            $gdSpreadsheet = $this->client->getSpreadsheet($spreadsheet['fileId']);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }

            // file doesn't exist
            $gdFile = $this->client->createFile(
                $this->input->getInputTablePath($spreadsheet['tableId']),
                $spreadsheet['title'],
                [
                    'parents' => $spreadsheet['parents'],
                    'mimeType' => Client::MIME_TYPE_SPREADSHEET
                ]
            );

            $gdSpreadsheet = $this->client->getSpreadsheet($gdFile['id']);
        }

        $gdFile = $this->client->getFile($spreadsheet['fileId'], ['id', 'name', 'parents']);

        // sync metadata
        $this->syncFileMetadata($spreadsheet, $gdFile);
//        @todo: uncomment
//        $this->syncSpreadsheetMetadata($spreadsheet, $gdSpreadsheet);

        // upload values for each sheet in spreadsheet
        try {
            $responses = [];
            foreach ($spreadsheet['sheets'] as $sheet) {
                $csvFile = $this->input->getInputCsv($sheet['tableId']);
                $columnCount = $csvFile->getColumnsCount();
                $rowCount = $this->input->countLines($csvFile);

                // update sheets metadata (title, rows and cols count) first
                $this->updateSheetMetadata($spreadsheet['fileId'], $sheet, [
                    'rowCount' => $rowCount,
                    'columnCount' => $columnCount
                ]);

                // clear values
                $this->client->clearSpreadsheetValues($spreadsheet['fileId'], urlencode($sheet['title']));

                // insert new values
                $offset = 1;
                $limit = 1000;
                while ($csvFile->current()) {
                    $i = 0;
                    $values = [];
                    while ($i < $limit && $csvFile->current()) {
                        $values[] = $csvFile->current();
                        $csvFile->next();
                        $i++;
                    }

                    $responses[] = $this->client->updateSpreadsheetValues(
                        $spreadsheet['fileId'],
                        $this->getRange($sheet['title'], $columnCount, $offset, $limit),
                        $values
                    );

                    $offset = $offset + $i;
                }
            }

            return $responses;
        } catch (ClientException $e) {
            //@todo handle API exception
            throw new UserException($e->getMessage(), 0, $e, [
                'response' => $e->getResponse()->getBody()->getContents(),
                'reasonPhrase' => $e->getResponse()->getReasonPhrase()
            ]);
        }
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

    /**
     * Sync title and parent folder
     *
     * @param $sheet
     * @param $gdFile
     */
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

    /**
     * Add or Remove sheets
     *
     * @param $spreadsheet
     * @param $gdSpreadsheet
     */
    private function syncSpreadsheetMetadata($spreadsheet, $gdSpreadsheet)
    {
        //@todo sheets - addSheet / removeSheet
        $requests = [];
        foreach ($spreadsheet['sheets'] as $sheet) {
            $requests[] = [

            ];
        }

        // rename sheet in spreadsheet
        $this->client->batchUpdateSpreadsheet($gdSpreadsheet['spreadsheetId'], [
            'requests' => $requests
        ]);
    }

    /**
     * Update sheets metadata - title, columnCount, rowCount
     *
     * @param $spreadsheetId
     * @param $sheet
     * @param $gridProperties
     *      [
     *          'rowCount' => NUMBER OF ROWS
     *          'columnCount' => NUMBER OF COLUMNS
     *      ]
     */
    private function updateSheetMetadata($spreadsheetId, $sheet, $gridProperties)
    {
        // update sheets properties - title and gridProperties
        $requests[] = [
            'updateSheetProperties' => [
                'properties' => [
                    'sheetId' => $sheet['sheetId'],
                    'title' => $sheet['title'],
                    'gridProperties' => $gridProperties
                ],
                'fields' => 'title,gridProperties'
            ]
        ];

        // rename sheet in spreadsheet
        $this->client->batchUpdateSpreadsheet($spreadsheetId, [
            'requests' => $requests
        ]);
    }
}
