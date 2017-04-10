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

    public function process($files)
    {
        return array_map(function ($file) {
            $action = $file['action'];
            if (!in_array($action, [ConfigDefinition::ACTION_CREATE, ConfigDefinition::ACTION_UPDATE])) {
                throw new ApplicationException(sprintf(
                    "Action '%s' not allowed. Allowed values are 'create' or 'update'",
                    $action
                ));
            }
            if ($action == ConfigDefinition::ACTION_CREATE) {
                return $this->create($file);
            }
            return $this->update($file);
        }, $files);
    }

    private function create($file)
    {
        return $this->client->createFile(
            $this->input->getInputTablePath($file['tableId']),
            $file['title'] . ' (' . date('Y-m-d H:i:s') . ')',
            ['parents' => [$file['folder']]]
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
                    'addParents' => [$file['folder']]
                ]
            );
        }

        return $this->client->createFile(
            $this->input->getInputTablePath($file['tableId']),
            $file['title'],
            [
                'id' => $file['fileId'],
                'parents' => [$file['folder']]
            ]
        );
    }

    public function createFileMetadata(array $file)
    {
        return $this->client->createFileMetadata(
            $file['title'],
            ['parents' => [$file['folder']]]
        );
    }
}
