<?php
/**
 * Extractor.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 29.7.13
 */

namespace Keboola\GoogleDriveWriter;

use Keboola\GoogleDriveWriter\Configuration\ConfigDefinition;
use Keboola\GoogleDriveWriter\Exception\ApplicationException;
use Keboola\GoogleDriveWriter\GoogleDrive\Client;
use Psr\Http\Message\ResponseInterface;

class Writer
{
    /** @var Client */
    private $client;

    /** @var Input */
    private $input;

    /** @var Logger */
    private $logger;

    public function __construct(Client $client, Input $input, Logger $logger)
    {
        $this->client = $client;
        $this->input = $input;
        $this->logger = $logger;

        $this->client->getApi()->setBackoffsCount(7);
        $this->client->getApi()->setBackoffCallback403($this->getBackoffCallback403());
        $this->client->getApi()->setRefreshTokenCallback(function () {
        });
    }

    public function getBackoffCallback403()
    {
        return function ($response) {
            /** @var ResponseInterface $response */
            $reason = $response->getReasonPhrase();

            if ($reason == 'insufficientPermissions'
                || $reason == 'dailyLimitExceeded'
                || $reason == 'usageLimits.userRateLimitExceededUnreg'
            ) {
                return false;
            }

            return true;
        };
    }

    public function process($file)
    {
        if ($file['action'] == 'update') {
            return $this->update($file);
        } elseif ($file['action'] == 'create') {
            return $this->create($file);
        }
        throw new ApplicationException(sprintf("Action '%s' not allowed", $file['action']));
    }

    private function create($file)
    {
        // create file
        return $this->client->createFile(
            $this->input->getInputTablePath($file['tableId']),
            $file['title'] . ' (' . date('Y-m-d H:i:s') . ')',
            ['parents' => $file['parents']]
        );
    }

    private function update($file)
    {
        if ($this->client->fileExists($file['fileId'])) {
            return $this->client->updateFile(
                $file['fileId'],
                $this->input->getInputTablePath($file['tableId']),
                [
                    'name' => $file['title'],
                    'addParents' => $file['parents']
                ]
            );
        }

        return $this->client->createFile(
            $this->input->getInputTablePath($file['tableId']),
            $file['title'],
            [
                'id' => $file['fileId'],
                'parents' => $file['parents']
            ]
        );
    }

    public function createFileMetadata(array $file)
    {
        $params = [
            'parents' => [$file['folder']]
        ];
        if ($file['type'] == ConfigDefinition::SHEET) {
            $params['mimeType'] = Client::MIME_TYPE_SPREADSHEET;
        }

        return $this->client->createFileMetadata($file['title'], $params);
    }

    public function createSpreadsheet(array $file)
    {
        $gdFile = $this->createFileMetadata($file);

        return $this->client->getSpreadsheet($gdFile['id']);
    }

    public function addSheet($sheet)
    {
        return $this->client->addSheet(
            $sheet['fileId'],
            [
                'properties' => [
                    'title' => $sheet['sheetTitle']
                ]
            ]
        );
    }

    public function deleteSheet($sheet)
    {
        return $this->client->deleteSheet(
            $sheet['fileId'],
            $sheet['sheetId']
        );
    }
}
