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
        $config['parameters']['files'][] = [
            'id' => 0,
            'fileId' => '',
            'title' => 'titanic',
            'enabled' => true,
            'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
            'type' => ConfigDefinition::TYPE_FILE,
            'action' => ConfigDefinition::ACTION_CREATE,
            'tableId' => 'titanic',
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
    public function testUpdateSpreadsheet()
    {
        $this->prepareDataFiles();

        // create sheet
        $gdFile = $this->client->createFile(
            $this->dataPath . '/in/tables/titanic_1.csv',
            'titanic_1',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => Client::MIME_TYPE_SPREADSHEET
            ]
        );

        $gdSpreadsheet = $this->client->getSpreadsheet($gdFile['id']);
        $sheetId = $gdSpreadsheet['sheets'][0]['properties']['sheetId'];

        // update sheet
        $config = $this->prepareConfig();
        $config['parameters']['files'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic',
            'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
            'type' => ConfigDefinition::TYPE_SPREADSHEET,
            'sheets' => [[
                'sheetId' => $sheetId,
                'title' => 'casualties',
                'tableId' => 'titanic_2',
                'action' => ConfigDefinition::ACTION_UPDATE,
                'enabled' => true
            ]]
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());

        $response = $this->client->getSpreadsheet($gdFile['id']);
        $values = $this->client->getSpreadsheetValues($gdFile['id'], 'casualties');

        $this->assertEquals($gdFile['id'], $response['spreadsheetId']);
        $this->assertEquals('titanic', $response['properties']['title']);
        $this->assertEquals('casualties', $response['sheets'][0]['properties']['title']);
        $this->assertEquals($this->csvToArray($this->dataPath . '/in/tables/titanic_2.csv'), $values['values']);
    }

    /**
     * Update large Spreadsheet
     */
    public function testUpdateSpreadsheetLarge()
    {
        $this->prepareDataFiles();

        // create sheet
        $gdFile = $this->client->createFile(
            $this->dataPath . '/in/tables/titanic_1.csv',
            'titanic_1',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => Client::MIME_TYPE_SPREADSHEET
            ]
        );

        $gdSpreadsheet = $this->client->getSpreadsheet($gdFile['id']);
        $sheetId = $gdSpreadsheet['sheets'][0]['properties']['sheetId'];

        // create large file
        $inputCsvPath = $this->tmpDataPath . '/in/tables/large.csv';
        touch($inputCsvPath);
        $inputCsv = new CsvFile($inputCsvPath);
        $inputCsv->writeRow(['id', 'random_string']);
        for ($i = 0; $i < 2000; $i++) {
            $inputCsv->writeRow([$i, uniqid()]);
        }

        // update sheet
        $newSheetTitle = 'Long John Silver';
        $config = $this->prepareConfig();
        $config['parameters']['files'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'pirates',
            'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
            'type' => ConfigDefinition::TYPE_SPREADSHEET,
            'sheets' => [[
                'sheetId' => $sheetId,
                'title' => $newSheetTitle,
                'tableId' => 'large',
                'action' => ConfigDefinition::ACTION_UPDATE,
                'enabled' => true
            ]]
        ];

        $process = $this->runProcess($config);

        $this->assertEquals(0, $process->getExitCode(), $process->getOutput());

        $response = $this->client->getSpreadsheet($gdFile['id']);
        $values = $this->client->getSpreadsheetValues(
            $gdFile['id'],
            urlencode($newSheetTitle),
            [
                'valueRenderOption' => 'UNFORMATTED_VALUE'
            ]
        );

        $this->assertEquals($gdFile['id'], $response['spreadsheetId']);
        $this->assertEquals('pirates', $response['properties']['title']);
        $this->assertEquals($newSheetTitle, $response['sheets'][0]['properties']['title']);
        $this->assertEquals($this->csvToArray($inputCsvPath), $values['values']);
    }

    /**
     * Add sheet missing in Google Drive
     */
    public function testUpdateSpreadsheetAddSheet()
    {
        $this->prepareDataFiles();

        // create sheet
        $gdFile = $this->client->createFile(
            $this->dataPath . '/in/tables/titanic_1.csv',
            'titanic_1',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => Client::MIME_TYPE_SPREADSHEET
            ]
        );

        $gdSpreadsheet = $this->client->getSpreadsheet($gdFile['id']);
        $sheetId = $gdSpreadsheet['sheets'][0]['properties']['sheetId'];

        // create and delete sheet from GD
        $response = $this->client->addSheet($gdFile['id'], [
            'properties' => [
                'title' => 'titanic_2',
            ]
        ]);
        $newSheet = $response['replies'][0]['addSheet'];

        $this->client->deleteSheet($gdFile['id'], $newSheet['properties']['sheetId']);

        // add new sheet to config
        $config = $this->prepareConfig();
        $config['parameters']['files'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic',
            'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
            'type' => ConfigDefinition::TYPE_SPREADSHEET,
            'sheets' => [
                [
                    'sheetId' => $sheetId,
                    'title' => 'titanic_1',
                    'tableId' => 'titanic_1',
                    'action' => ConfigDefinition::ACTION_UPDATE,
                    'enabled' => true
                ],
                [
                    'sheetId' => $newSheet['properties']['sheetId'],
                    'title' => 'titanic_2',
                    'tableId' => 'titanic_2',
                    'action' => ConfigDefinition::ACTION_UPDATE,
                    'enabled' => true
                ]
            ]
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());

        $gdSpreadsheet = $this->client->getSpreadsheet($gdFile['id']);
        $this->assertEquals($newSheet['properties']['sheetId'], $gdSpreadsheet['sheets'][1]['properties']['sheetId']);
    }

    /**
     * Delete sheet from Google Drive that is not in config
     */
    public function testUpdateSpreadsheetRemoveSheet()
    {
        $this->prepareDataFiles();

        // create sheet
        $gdFile = $this->client->createFile(
            $this->dataPath . '/in/tables/titanic_1.csv',
            'titanic_1',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => Client::MIME_TYPE_SPREADSHEET
            ]
        );

        $gdSpreadsheet = $this->client->getSpreadsheet($gdFile['id']);
        $sheetId = $gdSpreadsheet['sheets'][0]['properties']['sheetId'];

        // create another sheet
        $this->client->addSheet($gdFile['id'], [
            'properties' => [
                'title' => 'titanic_2',
            ]
        ]);

        $gdSpreadsheet = $this->client->getSpreadsheet($gdFile['id']);
        $this->assertCount(2, $gdSpreadsheet['sheets']);

        //  new sheet is not in the config
        $config = $this->prepareConfig();
        $config['parameters']['files'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic',
            'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
            'type' => ConfigDefinition::TYPE_SPREADSHEET,
            'sheets' => [
                [
                    'sheetId' => $sheetId,
                    'title' => 'titanic_1',
                    'tableId' => 'titanic_1',
                    'action' => ConfigDefinition::ACTION_UPDATE,
                    'enabled' => true
                ]
            ]
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());
        $gdSpreadsheet = $this->client->getSpreadsheet($gdFile['id']);
        $this->assertCount(1, $gdSpreadsheet['sheets']);
        $this->assertEquals($sheetId, $gdSpreadsheet['sheets'][0]['properties']['sheetId']);
    }

    /**
     * Append content to a sheet
     */
    public function testAppendSheet()
    {
        $this->prepareDataFiles();

        // create spreadsheet
        $gdFile = $this->client->createFile(
            $this->dataPath . '/in/tables/titanic_1.csv',
            'titanic',
            [
                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
                'mimeType' => Client::MIME_TYPE_SPREADSHEET
            ]
        );

        $gdSpreadsheet = $this->client->getSpreadsheet($gdFile['id']);
        $sheetId = $gdSpreadsheet['sheets'][0]['properties']['sheetId'];

        // append other data do the sheet
        $config = $this->prepareConfig();
        $config['parameters']['files'][] = [
            'id' => 0,
            'fileId' => $gdFile['id'],
            'title' => 'titanic',
            'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
            'type' => ConfigDefinition::TYPE_SPREADSHEET,

            'sheets' => [
                [
                    'sheetId' => $sheetId,
                    'title' => 'casualties',
                    'tableId' => 'titanic_2_headerless',
                    'action' => ConfigDefinition::ACTION_APPEND,
                    'enabled' => true
                ]
            ]
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());

        $response = $this->client->getSpreadsheetValues($gdFile['id'], 'casualties');
        $this->assertEquals($this->csvToArray($this->dataPath . '/in/tables/titanic.csv'), $response['values']);
    }

    /**
     * Create New File using sync action
     */
    public function testSyncActionCreateFile()
    {
        $this->prepareDataFiles();

        $config = $this->prepareConfig();
        $config['action'] = 'createFile';
        $config['parameters']['files'][] = [
            'id' => 0,
            'title' => 'titanic',
            'enabled' => true,
            'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
            'type' => ConfigDefinition::TYPE_FILE,
            'action' => ConfigDefinition::ACTION_UPDATE
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());
        $response = json_decode($process->getOutput(), true);
        $gdFile = $this->client->getFile($response['file']['id']);
        $this->assertArrayHasKey('id', $gdFile);
        $this->assertEquals('titanic', $gdFile['name']);
    }

    /**
     * Create New Spreadsheet using sync action
     */
    public function testSyncActionCreateSpreadsheet()
    {
        $this->prepareDataFiles();

        $config = $this->prepareConfig();
        $config['action'] = 'createSpreadsheet';
        $config['parameters']['files'][] = [
            'id' => 0,
            'title' => 'titanic',
            'enabled' => true,
            'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
            'type' => ConfigDefinition::TYPE_SPREADSHEET,
            'action' => ConfigDefinition::ACTION_UPDATE
        ];

        $process = $this->runProcess($config);
        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());
        $response = json_decode($process->getOutput(), true);
        $gdFile = $this->client->getSpreadsheet($response['spreadsheet']['spreadsheetId']);
        $this->assertArrayHasKey('spreadsheetId', $gdFile);
        $this->assertEquals('titanic', $gdFile['properties']['title']);
    }

    /**
     * Add Sheet to a Spreadsheet using sync action
     */
    public function testSyncActionAddSheet()
    {
//        $this->prepareDataFiles();
//
//        // create spreadsheet
//        $gdFile = $this->client->createFile(
//            $this->dataPath . '/in/tables/titanic_1.csv',
//            'titanic',
//            [
//                'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
//                'mimeType' => Client::MIME_TYPE_SPREADSHEET
//            ]
//        );
//
//        $config = $this->prepareConfig();
//        $config['action'] = 'addSheet';
//        $config['parameters']['files'][] = [
//            'id' => 0,
//            'title' => 'titanic',
//            'enabled' => true,
//            'parents' => [getenv('GOOGLE_DRIVE_FOLDER')],
//            'type' => ConfigDefinition::TYPE_SPREADSHEET,
//            'action' => ConfigDefinition::ACTION_UPDATE
//        ];
//
//        $process = $this->runProcess($config);
//
//        var_dump($process->getOutput());
//        var_dump($process->getErrorOutput());
//
//        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());
//
//        $response = json_decode($process->getOutput(), true);
//        $gdFile = $this->client->getSpreadsheet($response['fileId']);
//
//        var_dump($gdFile);
//
//        $this->assertArrayHasKey('spreadsheetId', $gdFile);
//        $this->assertEquals('titanic', $gdFile['properties']['title']);
    }

    public function testSyncActionDeleteSheet()
    {

    }

    /**
     * @param $config
     * @return Process
     */
    private function runProcess($config)
    {
        file_put_contents($this->tmpDataPath . '/config.json', json_encode($config));

        $process = new Process(sprintf('php run.php --data=%s', $this->tmpDataPath));
        $process->setTimeout(180);
        $process->run();

        return $process;
    }

    private function prepareDataFiles() {
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
