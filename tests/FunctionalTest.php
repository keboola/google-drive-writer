<?php

declare(strict_types=1);

namespace Keboola\GoogleDriveWriter\Tests;

use Keboola\GoogleDriveWriter\Configuration\ConfigDefinition;
use Keboola\GoogleDriveWriter\Test\BaseTest;
use Keboola\GoogleSheetsClient\Client;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Throwable;

class FunctionalTest extends BaseTest
{
    private string $tmpDataPath = '/tmp/data-test';

    public function setUp(): void
    {
        parent::setUp();
        $testFiles = $this->client->listFiles("name contains 'titanic' and trashed != true");
        foreach ($testFiles['files'] as $file) {
            try {
                $this->client->deleteFile($file['id']);
            } catch (Throwable $e) {
                // ignore
            }
        }
    }

    /**
     * Create each time a new file - append date to filename
     */
    public function testCreateFile(): void
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

        $this->client->setTeamDriveSupport(true);
        $gdFiles = $this->client->listFiles("name contains 'titanic' and trashed != true");

        $this->assertArrayHasKey('files', $gdFiles);
        $this->assertNotEmpty($gdFiles['files']);
        $this->assertCount(1, $gdFiles['files']);
    }

    public function testCreateFileNoFolder(): void
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

    public function testCreateFileConvert(): void
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
            'convert' => true,
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
    public function testUpdateFile(): void
    {
        $this->prepareDataTables();

        // create file
        $gdFile = $this->client->createFile(
            $this->tmpDataPath . '/in/tables/titanic_1.csv',
            'titanic_1',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => 'text/csv',
            ],
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
            'tableId' => 'titanic_2',
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

        $response = $this->client->getFile($gdFile['id']);

        $this->assertEquals($gdFile['id'], $response['id']);
        $this->assertEquals('titanic_2', $response['name']);
        $this->assertEquals('text/csv', $response['mimeType']);
    }

    public function testUpdateTeamFile(): void
    {
        $this->prepareDataTables();

        // create file
        $gdFile = $this->client->createFile(
            $this->tmpDataPath . '/in/tables/titanic_1.csv',
            'titanic_1',
            [
                'parents' => [getenv('GOOGLE_DRIVE_TEAM_FOLDER')],
                'mimeType' => 'text/csv',
            ],
        );

        // update file
        $config = $this->prepareConfig();
        $config['parameters']['tables'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic_2',
            'enabled' => true,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_TEAM_FOLDER')],
            'action' => ConfigDefinition::ACTION_UPDATE,
            'tableId' => 'titanic_2',
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

        $response = $this->client->getFile($gdFile['id']);

        $this->assertEquals($gdFile['id'], $response['id']);
        $this->assertEquals('titanic_2', $response['name']);
        $this->assertEquals('text/csv', $response['mimeType']);
    }

    public function testUpdateDisabledFile(): void
    {
        $this->prepareDataTables();

        // create file
        $gdFile = $this->client->createFile(
            $this->tmpDataPath . '/in/tables/titanic_1.csv',
            'titanic_1',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => 'text/csv',
            ],
        );

        $modified = $this->client->getFile($gdFile['id'], ['modifiedTime']);

        // update file
        $config = $this->prepareConfig();
        $config['parameters']['tables'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic_2',
            'enabled' => false,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
            'action' => ConfigDefinition::ACTION_UPDATE,
            'tableId' => 'titanic_2',
        ];

        sleep(5);

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

        $response = $this->client->getFile($gdFile['id'], ['id', 'name', 'mimeType', 'modifiedTime']);

        $this->assertEquals($gdFile['id'], $response['id']);
        $this->assertEquals('titanic_1', $response['name']);
        $this->assertEquals('text/csv', $response['mimeType']);
        $this->assertEquals($modified['modifiedTime'], $response['modifiedTime']);
    }

    public function testUpdateFileConvert(): void
    {
        $this->prepareDataTables();

        // create file
        $gdFile = $this->client->createFile(
            $this->tmpDataPath . '/in/tables/titanic.csv',
            'titanic',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => Client::MIME_TYPE_SPREADSHEET,
            ],
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
            'convert' => true,
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

        $response = $this->client->getFile($gdFile['id']);

        $this->assertEquals($gdFile['id'], $response['id']);
        $this->assertEquals('titanic', $response['name']);
        $this->assertEquals(Client::MIME_TYPE_SPREADSHEET, $response['mimeType']);
    }

    public function testUpdateFileNoFolder(): void
    {
        $this->prepareDataTables();

        // create file
        $gdFile = $this->client->createFile(
            $this->tmpDataPath . '/in/tables/titanic_1.csv',
            'titanic_3',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => 'text/csv',
            ],
        );

        // update file
        $config = $this->prepareConfig();
        $config['parameters']['tables'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic_4',
            'enabled' => true,
            'action' => ConfigDefinition::ACTION_UPDATE,
            'tableId' => 'titanic_2',
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
    public function testSyncActionCreateFile(): void
    {
        $config = $this->prepareConfig();
        $config['action'] = 'createFile';
        $config['parameters']['tables'][] = [
            'id' => 0,
            'title' => 'titanic',
            'enabled' => true,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
            'action' => ConfigDefinition::ACTION_UPDATE,
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());
        $response = json_decode($process->getOutput(), true);
        $gdFile = $this->client->getFile($response['file']['id']);
        $this->assertArrayHasKey('id', $gdFile);
        $this->assertEquals('titanic', $gdFile['name']);
        $this->assertEquals('text/csv', $gdFile['mimeType']);
    }

    public function testSyncActionCreateFileNoFolder(): void
    {
        $config = $this->prepareConfig();
        $config['action'] = 'createFile';
        $config['parameters']['tables'][] = [
            'id' => 0,
            'title' => 'titanic',
            'enabled' => true,
            'action' => ConfigDefinition::ACTION_UPDATE,
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

    public function testSyncActionCreateFileConvert(): void
    {
        $config = $this->prepareConfig();
        $config['action'] = 'createFile';
        $config['parameters']['tables'][] = [
            'id' => 0,
            'title' => 'titanic',
            'enabled' => true,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
            'action' => ConfigDefinition::ACTION_UPDATE,
            'convert' => true,
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());
        $response = json_decode($process->getOutput(), true);
        $gdFile = $this->client->getFile($response['file']['id']);
        $this->assertArrayHasKey('id', $gdFile);
        $this->assertEquals('titanic', $gdFile['name']);
        $this->assertEquals(Client::MIME_TYPE_SPREADSHEET, $gdFile['mimeType']);
    }

    /**
     * Test processing files from FileUpload
     */
    public function testFileUpload(): void
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

        // test update
        $gdFile = reset($gdFiles['files']);
        $gdFile = $this->client->getFile($gdFile['id'], ['id', 'name', 'version']);
        $this->assertArrayHasKey('version', $gdFile);
        $this->assertArrayHasKey('id', $gdFile);

        $oldVersion = $gdFile['version'];
        $oldId = $gdFile['id'];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

        $gdFiles = $this->client->listFiles("name contains 'titanic-named-file.png' and trashed != true");
        $this->assertArrayHasKey('files', $gdFiles);
        $this->assertNotEmpty($gdFiles['files']);
        $this->assertCount(1, $gdFiles['files']);

        $gdFile = reset($gdFiles['files']);
        $gdFile = $this->client->getFile($gdFile['id'], ['id', 'name', 'version']);
        $this->assertArrayHasKey('version', $gdFile);
        $this->assertArrayHasKey('id', $gdFile);

        $this->assertEquals($oldId, $gdFile['id']);
        $this->assertGreaterThan($oldVersion, $gdFile['version']);
    }

    public function testCreateDuplicateFile(): void
    {
        $this->prepareDataTables();

        // Create file
        $config1 = $this->prepareConfig();
        $config1['parameters']['tables'][] = [
            'id' => 0,
            'title' => 'titanic',
            'enabled' => true,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
            'action' => ConfigDefinition::ACTION_CREATE,
            'tableId' => 'titanic',
        ];
        $process1 = $this->runProcess($config1);
        $this->assertEquals(0, $process1->getExitCode(), $process1->getOutput());
        $gdFiles = $this->client->listFiles("name contains 'titanic (" . date('Y-m-d') . "' and trashed != true");
        $this->assertCount(1, $gdFiles['files']);

        // Create duplicate file -> UserException excepted
        $fileId = $gdFiles['files'][0]['id'];
        $config2 = $this->prepareConfig();
        $config2['parameters']['tables'][] = [
            'id' => 0,
            'fileId' => $fileId,
            'title' => 'titanic',
            'enabled' => true,
            'folder' => ['id' => getenv('GOOGLE_DRIVE_FOLDER')],
            'action' => ConfigDefinition::ACTION_CREATE,
            'tableId' => 'titanic',
        ];
        $process2 = $this->runProcess($config2);
        $this->assertEquals(1, $process2->getExitCode(), $process2->getOutput());
        $this->assertContains('A file already exists with the provided ID', $process2->getOutput());
    }

    private function runProcess(array $config): Process
    {
        file_put_contents($this->tmpDataPath . '/config.json', json_encode($config));

        $process = new Process(['php', 'run.php', '--data=' . $this->tmpDataPath, '2>&1']);
        $process->setTimeout(180);
        $process->run();

        return $process;
    }

    private function prepareDataTables(): void
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
            $this->tmpDataPath . '/in/tables/titanic_2_headerless.csv',
        );
    }

    private function prepareDataFiles(): void
    {
        $fs = new Filesystem();
        $fs->remove($this->tmpDataPath);
        $fs->mkdir($this->tmpDataPath);
        $fs->mkdir($this->tmpDataPath . '/in/files/');
        $fs->copy(
            $this->dataPath . '/in/files/wr-google-drive-64.png',
            $this->tmpDataPath . '/in/files/wr-google-drive-64.png',
        );
        $fs->copy(
            $this->dataPath . '/in/files/wr-google-drive-64.png.manifest',
            $this->tmpDataPath . '/in/files/wr-google-drive-64.png.manifest',
        );
    }
}
