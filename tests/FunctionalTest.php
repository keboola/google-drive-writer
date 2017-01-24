<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/08/16
 * Time: 16:45
 */
namespace Keboola\GoogleDriveWriter\Tests;

use GuzzleHttp\Exception\ClientException;
use Keboola\GoogleDriveWriter\Configuration\ConfigDefinition;
use Keboola\GoogleDriveWriter\GoogleDrive\Client;
use Keboola\GoogleDriveWriter\Test\BaseTest;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class FunctionalTest extends BaseTest
{
    private $tmpDataPath = '/tmp/data-test';

    public function setUp()
    {
        parent::setUp();
        $testFiles = $this->client->listFiles("name contains 'titanic' and trashed != true");
        foreach ($testFiles['files'] as $file) {
            $this->client->deleteFile($file['id']);
        }
    }

    /**
     * Create each time a new file - append date to filename
     */
    public function testCreateFile()
    {
        $config = $this->prepareConfig();
        $config['parameters']['files'][] = [
            'id' => 0,
            'fileId' => '',
            'title' => 'titanic',
            'enabled' => true,
            'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
            'type' => ConfigDefinition::TYPE_FILE,
            'action' => ConfigDefinition::ACTION_CREATE,
            'tableId' => 'titanic',
            'sheets' => [[
                'title' => 'sheet1'
            ]]
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());

        $gdFiles = $this->client->listFiles("name contains 'titanic (" . date('Y-m-d') . "' and trashed != true");

        $this->assertArrayHasKey('files', $gdFiles);
        $this->assertNotEmpty($gdFiles['files']);
        $this->assertCount(1, $gdFiles['files']);
    }

    /**
     * Create or replace a file
     */
    public function testUpdateFile()
    {
        // create file
        $gdFile = $this->client->createFile(
            $this->tmpDataPath . '/in/tables/titanic_1.csv',
            'titanic_1',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')]
            ]
        );

        // update file
        $config = $this->prepareConfig();
        $config['parameters']['files'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic_2',
            'enabled' => true,
            'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
            'type' => ConfigDefinition::TYPE_FILE,
            'action' => ConfigDefinition::ACTION_UPDATE,
            'tableId' => 'titanic_2'
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());

        $response = $this->client->getFile($gdFile['id']);

        $this->assertEquals($gdFile['id'], $response['id']);
        $this->assertEquals('titanic_2', $response['name']);
    }

    /**
     * Create or update a sheet
     */
    public function testUpdateSheet()
    {
        // create sheet
        $gdFile = $this->client->createFile(
            $this->tmpDataPath . '/in/tables/titanic_1.csv',
            'titanic_1',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => Client::MIME_TYPE_SPREADSHEET
            ]
        );

        $gdSpreadsheet = $this->client->getSpreadsheet($gdFile['id']);

        // rename sheet in spreadsheet
        $this->client->updateSheet($gdFile['id'], [
            'sheetId' => $gdSpreadsheet['sheets'][0]['properties']['sheetId'],
            'title' => 'sheet1'
        ]);

        // update sheet
        $config = $this->prepareConfig();
        $config['parameters']['files'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic',
            'enabled' => true,
            'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
            'type' => ConfigDefinition::TYPE_SHEET,
            'action' => ConfigDefinition::ACTION_UPDATE,
            'tableId' => 'titanic_2',
            'sheets' => [[
                'title' => 'casualties'
            ]]
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());

        $response = $this->client->getSpreadsheet($gdSheet['id']);

        var_dump($response);

        var_dump($response['sheets']);
    }

    /**
     * Append content to a sheet
     */
    public function testAppendSheet()
    {

    }

    /**
     * Create New File using sync action
     */
    public function testSyncActionCreateFile()
    {

    }

    /**
     * Create New Spreadsheet using sync action
     */
    public function testSyncActionCreateSheet()
    {

    }

    /**
     * @param $config
     * @return Process
     */
    private function runProcess($config)
    {
        $fs = new Filesystem();
        $fs->remove($this->tmpDataPath);
        $fs->mkdir($this->tmpDataPath);
        $fs->mkdir($this->tmpDataPath . '/in/tables/');
        $fs->copy($this->dataPath . '/in/tables/titanic.csv', $this->tmpDataPath . '/in/tables/titanic.csv');
        $fs->copy($this->dataPath . '/in/tables/titanic_1.csv', $this->tmpDataPath . '/in/tables/titanic_1.csv');
        $fs->copy($this->dataPath . '/in/tables/titanic_2.csv', $this->tmpDataPath . '/in/tables/titanic_2.csv');
        file_put_contents($this->tmpDataPath . '/config.json', json_encode($config));

        $process = new Process(sprintf('php run.php --data=%s', $this->tmpDataPath));
        $process->setTimeout(180);
        $process->run();

        return $process;
    }
}
