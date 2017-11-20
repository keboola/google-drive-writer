<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/08/16
 * Time: 16:45
 */
namespace Keboola\GoogleDriveWriter\Tests;

use Keboola\GoogleDriveWriter\Configuration\ConfigDefinition;
use Keboola\GoogleDriveWriter\Test\BaseTest;
use Keboola\GoogleSheetsClient\Client;
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
        $this->prepareDataTables();

        $config = $this->prepareConfig();
        $config['parameters']['tables'][] = [
            'id' => 0,
            'title' => 'titanic',
            'enabled' => true,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
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

    public function testCreateFileNoFolder()
    {
        $this->prepareDataTables();

        $config = $this->prepareConfig();
        $config['parameters']['tables'][] = [
            'id' => 0,
            'fileId' => '',
            'title' => 'titanic',
            'enabled' => true,
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

    public function testCreateFileConvert()
    {
        $this->prepareDataTables();

        $config = $this->prepareConfig();
        $config['parameters']['tables'][] = [
            'id' => 0,
            'title' => 'titanic',
            'enabled' => true,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
            'action' => ConfigDefinition::ACTION_CREATE,
            'tableId' => 'titanic',
            'convert' => true
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

        $gdFiles = $this->client->listFiles("name contains 'titanic (" . date('Y-m-d') . "' and trashed != true");
        $this->assertArrayHasKey('files', $gdFiles);
        $this->assertNotEmpty($gdFiles['files']);
        $this->assertCount(1, $gdFiles['files']);
        $gdFile = $gdFiles['files'][0];
        $this->assertEquals(Client::MIME_TYPE_SPREADSHEET, $gdFile['mimeType']);
    }

    /**
     * Create or replace a file
     */
    public function testUpdateFile()
    {
        $this->prepareDataTables();

        // create file
        $gdFile = $this->client->createFile(
            $this->tmpDataPath . '/in/tables/titanic_1.csv',
            'titanic_1',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => 'text/csv'
            ]
        );

        // update file
        $config = $this->prepareConfig();
        $config['parameters']['tables'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic_2',
            'enabled' => true,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
            'action' => ConfigDefinition::ACTION_UPDATE,
            'tableId' => 'titanic_2'
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

        $response = $this->client->getFile($gdFile['id']);

        $this->assertEquals($gdFile['id'], $response['id']);
        $this->assertEquals('titanic_2', $response['name']);
        $this->assertEquals('text/csv', $response['mimeType']);
    }

    public function testUpdateFileConvert()
    {
        $this->prepareDataTables();

        // create file
        $gdFile = $this->client->createFile(
            $this->tmpDataPath . '/in/tables/titanic.csv',
            'titanic',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => Client::MIME_TYPE_SPREADSHEET
            ]
        );

        // update file
        $config = $this->prepareConfig();
        $config['parameters']['tables'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic',
            'enabled' => true,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
            'action' => ConfigDefinition::ACTION_UPDATE,
            'tableId' => 'titanic',
            'convert' => true
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

        $response = $this->client->getFile($gdFile['id']);

        $this->assertEquals($gdFile['id'], $response['id']);
        $this->assertEquals('titanic', $response['name']);
        $this->assertEquals(Client::MIME_TYPE_SPREADSHEET, $response['mimeType']);
    }

    public function testUpdateFileNoFolder()
    {
        $this->prepareDataTables();

        // create file
        $gdFile = $this->client->createFile(
            $this->tmpDataPath . '/in/tables/titanic_1.csv',
            'titanic_3',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => 'text/csv'
            ]
        );

        // update file
        $config = $this->prepareConfig();
        $config['parameters']['tables'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic_4',
            'enabled' => true,
            'action' => ConfigDefinition::ACTION_UPDATE,
            'tableId' => 'titanic_2'
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

        $response = $this->client->getFile($gdFile['id']);

        $this->assertEquals($gdFile['id'], $response['id']);
        $this->assertEquals('titanic_4', $response['name']);
        $this->assertEquals('text/csv', $response['mimeType']);
    }

    /**
     * Create New File using sync action
     */
    public function testSyncActionCreateFile()
    {
        $config = $this->prepareConfig();
        $config['action'] = 'createFile';
        $config['parameters']['tables'][] = [
            'id' => 0,
            'title' => 'titanic',
            'enabled' => true,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
            'action' => ConfigDefinition::ACTION_UPDATE
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());
        $response = json_decode($process->getOutput(), true);
        $gdFile = $this->client->getFile($response['file']['id']);
        $this->assertArrayHasKey('id', $gdFile);
        $this->assertEquals('titanic', $gdFile['name']);
        $this->assertEquals('text/csv', $gdFile['mimeType']);
    }

    public function testSyncActionCreateFileNoFolder()
    {
        $config = $this->prepareConfig();
        $config['action'] = 'createFile';
        $config['parameters']['tables'][] = [
            'id' => 0,
            'title' => 'titanic',
            'enabled' => true,
            'action' => ConfigDefinition::ACTION_UPDATE
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());
        $response = json_decode($process->getOutput(), true);

        $expectedFields = ['kind', 'id', 'name', 'mimeType', 'folder'];
        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $response['file']);
        }
        $gdFile = $this->client->getFile($response['file']['id']);
        $this->assertArrayHasKey('id', $gdFile);
        $this->assertEquals('titanic', $gdFile['name']);
        $this->assertNotEquals(getenv('GOOGLE_DRIVE_FOLDER'), $gdFile['parents'][0]);
        $this->assertEquals($response['file']['parents'][0], $gdFile['parents'][0]);
        $this->assertEquals('text/csv', $gdFile['mimeType']);
    }

    public function testSyncActionCreateFileConvert()
    {
        $config = $this->prepareConfig();
        $config['action'] = 'createFile';
        $config['parameters']['tables'][] = [
            'id' => 0,
            'title' => 'titanic',
            'enabled' => true,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
            'action' => ConfigDefinition::ACTION_UPDATE,
            'convert' => true
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());
        $response = json_decode($process->getOutput(), true);
        $gdFile = $this->client->getFile($response['file']['id']);
        $this->assertArrayHasKey('id', $gdFile);
        $this->assertEquals('titanic', $gdFile['name']);
        $this->assertEquals(Client::MIME_TYPE_SPREADSHEET, $gdFile['mimeType']);
    }

    public function testSyncActionGetFolder()
    {
        $config = $this->prepareConfig();
        $config['action'] = 'getFolder';
        $config['parameters']['tables'][] = [
            'id' => 0,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());
        $response = json_decode($process->getOutput(), true);

        $this->assertEquals('application/vnd.google-apps.folder', $response['file']['mimeType']);
        $this->assertEquals(getenv('GOOGLE_DRIVE_FOLDER'), $response['file']['id']);
    }

    public function testSyncActionGetFolderDefault()
    {
        $config = $this->prepareConfig();
        $config['action'] = 'getFolder';
        $config['parameters']['tables'][] = [
            'id' => 0
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());
        $response = json_decode($process->getOutput(), true);

        $this->assertEquals('application/vnd.google-apps.folder', $response['file']['mimeType']);
        $this->assertNotEquals(getenv('GOOGLE_DRIVE_FOLDER'), $response['file']['id']);
    }

    /**
     * Test processing files from FileUpload
     */
    public function testFileUpload()
    {
        $this->prepareDataFiles();

        $config = $this->prepareConfig();
        $config['parameters']['files'] = [
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

        $gdFiles = $this->client->listFiles("name contains 'titanic-named-file.png' and trashed != true");
        $this->assertArrayHasKey('files', $gdFiles);
        $this->assertNotEmpty($gdFiles['files']);
        $this->assertCount(1, $gdFiles['files']);
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

    private function prepareDataTables()
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

    private function prepareDataFiles()
    {
        $fs = new Filesystem();
        $fs->remove($this->tmpDataPath);
        $fs->mkdir($this->tmpDataPath);
        $fs->mkdir($this->tmpDataPath . '/in/files/');
        $fs->copy(
            $this->dataPath . '/in/files/wr-google-drive-64.png',
            $this->tmpDataPath . '/in/files/wr-google-drive-64.png'
        );
        $fs->copy(
            $this->dataPath . '/in/files/wr-google-drive-64.png.manifest',
            $this->tmpDataPath . '/in/files/wr-google-drive-64.png.manifest'
        );
    }
}
