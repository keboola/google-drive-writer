<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/01/17
 * Time: 14:23
 */

namespace Keboola\GoogleDriveWriter\Writer;

use Keboola\GoogleDriveWriter\GoogleDrive\Client;
use Keboola\GoogleDriveWriter\Input;

class File
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

    public function create($file)
    {
        // generate new filename
        $newTitle = $file['title'] . ' (' . date('Y-m-d H:i:s') . ')';

        // create file
        return $this->client->createFile(
            $this->input->getInputTablePath($file['tableId']),
            $newTitle,
            ['parents' => $file['parents']]
        );
    }

    public function update($file)
    {
        // check if file exists
        if ($this->client->fileExists($file['fileId'])) {
            // file exists
            return $this->client->updateFile(
                $file['fileId'],
                $this->input->getInputTablePath($file['tableId']),
                [
                    'name' => $file['title'],
                    'addParents' => $file['parents']
                ]
            );
        }

        // file don't exist
        return $this->client->createFile(
            $this->input->getInputTablePath($file['tableId']),
            $file['title'],
            [
                'id' => $file['fileId'],
                'parents' => $file['parents']
            ]
        );
    }
}
