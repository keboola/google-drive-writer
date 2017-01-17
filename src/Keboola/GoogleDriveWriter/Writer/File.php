<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 17/01/17
 * Time: 14:23
 */

namespace Keboola\GoogleDriveWriter\Writer;

use Keboola\GoogleDriveWriter\GoogleDrive\Client;

class File
{
    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function create($sheet)
    {
        // generate new filename
        $newTitle = $sheet['title'] . ' (' . date('Y-m-d H:i:s') . ')';

        // create file
        return $this->client->createFile($sheet['pathname'], $newTitle);
    }

    public function update($sheet)
    {
        // check if file exists
        $driveFile = null;
        if ($sheet['fileId'] !== null) {
            try {
                $driveFile = $this->client->getFile($sheet['fileId']);
            } catch (\Exception $e) {
            }
        }

        // file don't exist
        if ($driveFile === null) {
            return $this->client->createFile($sheet['pathname'], $sheet['fileTitle']);
        }

        // file exists
        return $this->client->updateFile($sheet);
    }
}
