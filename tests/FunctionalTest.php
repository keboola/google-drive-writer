<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/08/16
 * Time: 16:45
 */
namespace Keboola\GoogleDriveWriter\Tests;

use GuzzleHttp\Exception\ClientException;
use Keboola\Csv\CsvFile;
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
        $this->prepareDataFiles();

        $config = $this->prepareConfig();
        $config['parameters']['tables'][] = [
            'id' => 0,
            'fileId' => '',
            'title' => 'titanic',
            'enabled' => true,
            'folder' => getenv('GOOGLE_DRIVE_FOLDER'),
            'action' => ConfigDefinition::ACTION_CREATE,
            'tableId' => 'titanic',
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

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
        $this->prepareDataFiles();

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
        $config['parameters']['tables'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic_2',
            'enabled' => true,
            'folder' => getenv('GOOGLE_DRIVE_FOLDER'),
            'action' => ConfigDefinition::ACTION_UPDATE,
            'tableId' => 'titanic_2'
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

        $response = $this->client->getFile($gdFile['id']);

        $this->assertEquals($gdFile['id'], $response['id']);
        $this->assertEquals('titanic_2', $response['name']);
    }

    /**
     * Create New File using sync action
     */
    public function testSyncActionCreateFile()
    {
        $this->prepareDataFiles();

        $config = $this->prepareConfig();
        $config['action'] = 'createFile';
        $config['parameters']['tables'][] = [
            'id' => 0,
            'title' => 'titanic',
            'enabled' => true,
            'folder' => getenv('GOOGLE_DRIVE_FOLDER'),
            'action' => ConfigDefinition::ACTION_UPDATE
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());
        $response = json_decode($process->getOutput(), true);
        $gdFile = $this->client->getFile($response['file']['id']);
        $this->assertArrayHasKey('id', $gdFile);
        $this->assertEquals('titanic', $gdFile['name']);
    }

    /**
     * @param $config
     * @return Process
     */
    private function runProcess($config)
    {
        file_put_contents($this->tmpDataPath . '/config.json', json_encode($config));

        $process = new Process(sprintf('php run.php --data=%s 2>&1', $this->tmpDataPath));
        $process->setTimeout(180);
        $process->run();

        return $process;
    }

    private function prepareDataFiles()
    {
        $fs = new Filesystem();
        $fs->remove($this->tmpDataPath);
        $fs->mkdir($this->tmpDataPath);
        $fs->mkdir($this->tmpDataPath . '/in/tables/');
        $fs->copy($this->dataPath . '/in/tables/titanic.csv', $this->tmpDataPath . '/in/tables/titanic.csv');
        $fs->copy($this->dataPath . '/in/tables/titanic_1.csv', $this->tmpDataPath . '/in/tables/titanic_1.csv');
        $fs->copy($this->dataPath . '/in/tables/titanic_2.csv', $this->tmpDataPath . '/in/tables/titanic_2.csv');
        $fs->copy(
            $this->dataPath . '/in/tables/titanic_2_headerless.csv',
            $this->tmpDataPath . '/in/tables/titanic_2_headerless.csv'
        );
    }
}
