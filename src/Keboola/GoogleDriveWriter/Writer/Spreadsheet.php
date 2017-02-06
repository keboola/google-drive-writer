<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/01/17
 * Time: 14:23
 */

namespace Keboola\GoogleDriveWriter\Writer;

use GuzzleHttp\Exception\ClientException;
use Keboola\GoogleDriveWriter\Application;
use Keboola\GoogleDriveWriter\Configuration\ConfigDefinition;
use Keboola\GoogleDriveWriter\Exception\ApplicationException;
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

    public function process($spreadsheet)
    {
        // get metadata from Google Drive (file and spreadsheet)
        $gdSpreadsheet = $this->getCreateSpreadsheet($spreadsheet);
        $gdFile = $this->client->getFile($spreadsheet['fileId'], ['id', 'name', 'parents']);

        // sync metadata
        $this->syncFileMetadata($spreadsheet, $gdFile);

        try {
            foreach ($spreadsheet['sheets'] as $sheet) {
                $this->uploadSheetValues($spreadsheet['fileId'], $sheet);
            }
        } catch (ClientException $e) {
            //@todo handle API exception
            throw new UserException($e->getMessage(), 0, $e, [
                'response' => $e->getResponse()->getBody()->getContents(),
                'reasonPhrase' => $e->getResponse()->getReasonPhrase()
            ]);
        }
    }

    /**
     * @param $sheetTitle
     * @param $columnCount
     * @param int $rowOffset
     * @param int $rowLimit
     * @return string
     */
    private function getRange($sheetTitle, $columnCount, $rowOffset = 1, $rowLimit = 1000)
    {
        $lastColumn = $this->getColumnA1($columnCount-1);

        $start = 'A' . $rowOffset;
        $end = $lastColumn . ($rowOffset + $rowLimit - 1);

        return urlencode($sheetTitle) . '!' . $start . ':' . $end;
    }

    private function getCreateSpreadsheet($spreadsheet)
    {
        try {
            return $this->client->getSpreadsheet($spreadsheet['fileId']);
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
            return $this->client->getSpreadsheet($gdFile['id']);
        }
    }

    private function uploadSheetValues($spreadsheetId, $sheet)
    {
        $csvFile = $this->input->getInputCsv($sheet['tableId']);
        $columnCount = $csvFile->getColumnsCount();
        $rowCount = $this->input->countLines($csvFile);

        // update sheets metadata (title, rows and cols count) first
        $this->updateSheetMetadata($spreadsheetId, $sheet, [
            'rowCount' => $rowCount,
            'columnCount' => $columnCount
        ]);

        // clear values
        if ($sheet['action'] === ConfigDefinition::ACTION_UPDATE) {
            $this->client->clearSpreadsheetValues($spreadsheetId, urlencode($sheet['title']));
        }

        // insert new values
        $offset = 1;
        $limit = 1000;
        $responses = [];
        while ($csvFile->current()) {
            $i = 0;
            $values = [];
            while ($i < $limit && $csvFile->current()) {
                $values[] = $csvFile->current();
                $csvFile->next();
                $i++;
            }

            switch ($sheet['action']) {
                case ConfigDefinition::ACTION_UPDATE:
                    $responses[] = $this->client->updateSpreadsheetValues(
                        $spreadsheetId,
                        $this->getRange($sheet['title'], $columnCount, $offset, $limit),
                        $values
                    );
                    break;
                case ConfigDefinition::ACTION_APPEND:
                    $responses[] = $this->client->appendSpreadsheetValues(
                        $spreadsheetId,
                        urlencode($sheet['title']),
                        $values
                    );
                    break;
                default:
                    throw new ApplicationException(sprintf("Action '%s' not allowed", $sheet['action']));
                    break;
            }

            $offset = $offset + $i;
        }

        return $responses;
    }

    /**
     * @param $columnNumber
     * @return string
     */
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

        if (!empty($requests)) {
            $this->client->batchUpdateSpreadsheet($spreadsheetId, [
                'requests' => $requests
            ]);
        }
    }
}
