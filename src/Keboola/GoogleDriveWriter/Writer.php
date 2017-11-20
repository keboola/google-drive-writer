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
use Keboola\GoogleDriveWriter\Exception\UserException;
use Keboola\GoogleSheetsClient\Client;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

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

    public function setNumberOfRetries($cnt)
    {
        $this->client->getApi()->setBackoffsCount($cnt);
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

    private function tryParseNameFromManifest($filePath)
    {
        $name = basename($filePath);
        $manifestFile = $filePath . '.manifest';
        if (file_exists($manifestFile)) {
            $manifest = json_decode(file_get_contents($manifestFile), true);
            if (!empty($manifest['name'])) {
                $name = $manifest['name'];
            }
        }
        return $name;
    }

    public function processFiles($filesConfig)
    {
        /** @var Finder $finder */
        $finder = $this->input->getInputFiles();
        $results = [];

        foreach ($finder as $fileInfo) {
            $file = $filesConfig;
            /** @var SplFileInfo $fileInfo */
            $file['inputFile'] = $fileInfo->getFilename();
            $file['title'] = $this->tryParseNameFromManifest($fileInfo->getRealPath());
            $gdFiles = $this->client->listFiles(sprintf("trashed=false and name='%s'", $file['inputFile']));

            if (!empty($gdFiles['files'])) {
                $lastGdFile = array_shift($gdFiles['files']);
                $file['fileId'] = $lastGdFile['id'];
                $results[] = $this->update($file);
                continue;
            }

            $results[] = $this->createFile($file);
        }
    }

    public function processTables($tables)
    {
        return array_map(function ($table) {
            $action = $table['action'];
            if (!in_array($action, [ConfigDefinition::ACTION_CREATE, ConfigDefinition::ACTION_UPDATE])) {
                throw new ApplicationException(sprintf(
                    "Action '%s' not allowed. Allowed values are 'create' or 'update'",
                    $action
                ));
            }
            if ($action == ConfigDefinition::ACTION_CREATE) {
                return $this->create($table);
            }
            return $this->update($table);
        }, $tables);
    }

    private function create($file)
    {
        $file['title'] = $file['title'] . ' (' . date('Y-m-d H:i:s') . ')';
        return $this->createFile($file);
    }

    private function update($file)
    {
        if ($this->client->fileExists($file['fileId'])) {
            $params = [
                'name' => $file['title']
            ];
            if (!empty($file['folder']['id'])) {
                $params['addParents'] = [$file['folder']['id']];
            }

            return $this->client->updateFile(
                $file['fileId'],
                $this->getInputFile($file),
                $params
            );
        }

        return $this->createFile($file);
    }

    private function createFile($file)
    {
        $pathname = $this->getInputFile($file);
        $params = [
            'mimeType' => \GuzzleHttp\Psr7\mimetype_from_filename($pathname)
        ];
        if (!empty($file['fileId'])) {
            $params['id'] = $file['fileId'];
        }
        if (isset($file['folder']['id'])) {
            $params['parents'] = [$file['folder']['id']];
        }
        if (isset($file['convert']) && $file['convert']) {
            $params['mimeType'] = Client::MIME_TYPE_SPREADSHEET;
        }
        return $this->client->createFile($pathname, $file['title'], $params);
    }

    public function createFileMetadata(array $file)
    {
        // writer can now only work with tables, so this is the only mimeType
        $params = [
            'mimeType' => $file['convert'] ? Client::MIME_TYPE_SPREADSHEET : 'text/csv'
        ];
        $folder = [];
        if (isset($file['folder']['id'])) {
            $params['parents'] = [$file['folder']['id']];
            $folder = $file['folder'];
        }
        $fileRes = $this->client->createFileMetadata($file['title'], $params);

        if (empty($folder)) {
            $folderRes = $this->getFile($fileRes['parents'][0]);
            $folder = [
                'id' => $folderRes['id'],
                'title' => $folderRes['name']
            ];
        }
        $fileRes['folder'] = $folder;

        return $fileRes;
    }

    public function getFile($fileId, $fields = [])
    {
        $defaultFields = ['kind', 'id', 'name', 'mimeType', 'parents'];
        if (empty($fields)) {
            $fields = $defaultFields;
        }
        return $this->client->getFile($fileId, $fields);
    }

    private function getInputFile($file)
    {
        if (!empty($file['inputFile'])) {
            return $this->input->getInputFilePath($file['inputFile']);
        }
        if (!empty($file['tableId'])) {
            return $this->input->getInputTablePath($file['tableId']);
        }
        throw new ApplicationException("No input file or table specified", 0, null, [
            'file' => $file
        ]);
    }
}
