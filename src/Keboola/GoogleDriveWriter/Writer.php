<?php
/**
 * Extractor.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 29.7.13
 */

namespace Keboola\GoogleDriveWriter;

use Keboola\GoogleDriveWriter\Configuration\ConfigDefinition;
use Keboola\GoogleDriveWriter\GoogleDrive\Client;
use Keboola\GoogleDriveWriter\Writer\WriterInterface;
use Psr\Http\Message\ResponseInterface;

class Writer
{
    /** @var Client */
    private $driveApi;

    /** @var Input */
    private $input;

    /** @var Logger */
    private $logger;

    public function __construct(Client $driveApi, Input $input, Logger $logger)
    {
        $this->driveApi = $driveApi;
        $this->input = $input;
        $this->logger = $logger;

        $this->driveApi->getApi()->setBackoffsCount(7);
        $this->driveApi->getApi()->setBackoffCallback403($this->getBackoffCallback403());
        $this->driveApi->getApi()->setRefreshTokenCallback(function ($accessToken, $refreshToken) {
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

    public function process(array $files)
    {
        foreach ($files as $file) {
            $actionClassName = sprintf('%s\\%s\\%s', __NAMESPACE__, 'Writer', ucfirst($file['type']));

            $this->logger->info(sprintf(
                "Uploading file '%s'. ID: '%s'. Type: '%s'",
                $file['title'],
                $file['fileId'],
                $file['type']
            ));

            /** @var WriterInterface $writer */
            $writer = (new $actionClassName($this->driveApi, $this->input));
            $writer->process($file);

            $this->logger->info(sprintf("Upload successful"));
        }
    }

    public function createFileMetadata(array $file)
    {
        $params = [
            'parents' => $file['parents']
        ];
        if ($file['type'] == ConfigDefinition::TYPE_SPREADSHEET) {
            $params['mimeType'] = Client::MIME_TYPE_SPREADSHEET;
        }

        return $this->driveApi->createFileMetadata($file['title'], $params);
    }

    public function createSpreadsheet(array $file)
    {
        $gdFile = $this->createFileMetadata($file);

        return $this->driveApi->getSpreadsheet($gdFile['id']);
    }

    public function addSheet($spreadsheet)
    {
        return $this->driveApi->addSheet(
            $spreadsheet['fileId'],
            [
                'properties' => [
                    'title' => $spreadsheet['sheets'][0]['title']
                ]
            ]
        );
    }

    public function deleteSheet($spreadsheet)
    {
        return $this->driveApi->deleteSheet(
            $spreadsheet['fileId'],
            $spreadsheet['sheets'][0]['sheetId']
        );
    }
}
