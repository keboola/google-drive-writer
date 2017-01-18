<?php

namespace Keboola\GoogleDriveWriter\Tests;

use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleDriveWriter\GoogleDrive\Client;
use Keboola\GoogleDriveWriter\Test\BaseTest;

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 11/08/16
 * Time: 14:33
 */
class ClientTest extends BaseTest
{
    /** @var Client */
    private $client;

    public function setUp()
    {
        parent::setUp();
        $api = new RestApi(getenv('CLIENT_ID'), getenv('CLIENT_SECRET'));
        $api->setCredentials(getenv('ACCESS_TOKEN'), getenv('REFRESH_TOKEN'));
        $api->setBackoffsCount(2); // Speeds up the tests
        $this->client = new Client($api);
    }

    public function testCreateFile()
    {
        $gdFile = $this->client->createFile($this->testFilePath, $this->testFileName);
        $this->assertArrayHasKey('id', $gdFile);
        $this->assertArrayHasKey('name', $gdFile);
        $this->assertArrayHasKey('kind', $gdFile);
        $this->assertEquals($this->testFileName, $gdFile['name']);
        $this->assertEquals('drive#file', $gdFile['kind']);

        $this->client->deleteFile($gdFile['id']);
    }

    public function testCreateFileInFolder()
    {
        $folderId = getenv('GOOGLE_DRIVE_FOLDER');
        $gdFile = $this->client->createFile(
            $this->testFilePath,
            $this->testFileName,
            [
                'parents' => [$folderId]
            ]
        );

        $gdFile = $this->client->getFile($gdFile['id'], ['id', 'name', 'parents']);
        $this->assertArrayHasKey('id', $gdFile);
        $this->assertArrayHasKey('name', $gdFile);
        $this->assertArrayHasKey('parents', $gdFile);
        $this->assertContains($folderId, $gdFile['parents']);
        $this->assertEquals($this->testFileName, $gdFile['name']);

        $this->client->deleteFile($gdFile['id']);
    }

    public function testGetFile()
    {
        $gdFile = $this->client->createFile($this->testFilePath, $this->testFileName);
        $file = $this->client->getFile($gdFile['id']);

        $this->assertArrayHasKey('id', $file);
        $this->assertArrayHasKey('name', $file);
        $this->assertEquals($this->testFileName, $file['name']);

        $this->client->deleteFile($gdFile['id']);
    }

    public function testUpdateFile()
    {
        $gdFile = $this->client->createFile($this->testFilePath, $this->testFileName);
        $res = $this->client->updateFile($gdFile['id'], $this->testFilePath, [
            'name' => $gdFile['name'] . '_changed'
        ]);

        $this->assertArrayHasKey('id', $res);
        $this->assertArrayHasKey('name', $res);
        $this->assertArrayHasKey('kind', $res);
        $this->assertEquals($gdFile['id'], $res['id']);
        $this->assertEquals($gdFile['name'] . '_changed', $res['name']);

        $this->client->deleteFile($gdFile['id']);
    }

    public function testDeleteFile()
    {
        $gdFile = $this->client->createFile($this->testFilePath, $this->testFileName);
        $this->client->deleteFile($gdFile['id']);

        $this->expectException('GuzzleHttp\\Exception\\ClientException');
        $this->client->getFile($gdFile['id']);
    }

    public function testCreateSheet()
    {
        $res = $this->client->createSpreadsheet(
            ['title' => $this->testFileName],
            ['properties' => ['title' => 'my_test_sheet']]
        );

        $this->assertArrayHasKey('spreadsheetId', $res);
        $this->assertArrayHasKey('properties', $res);
        $this->assertArrayHasKey('sheets', $res);
        $this->assertEquals($this->testFileName, $res['properties']['title']);
        $this->assertCount(1, $res['sheets']);
        $sheet = array_shift($res['sheets']);
        $this->assertArrayHasKey('properties', $sheet);
        $this->assertArrayHasKey('sheetId', $sheet['properties']);
        $this->assertArrayHasKey('title', $sheet['properties']);
        $this->assertEquals('my_test_sheet', $sheet['properties']['title']);

        $this->client->deleteFile($res['spreadsheetId']);
    }

    public function testGetSheet()
    {
        $gdFile = $this->client->createFile($this->testFilePath, $this->testFileName);
        $spreadsheet = $this->client->getSpreadsheet($gdFile['id']);

        $this->assertArrayHasKey('spreadsheetId', $spreadsheet);
        $this->assertArrayHasKey('properties', $spreadsheet);
        $this->assertArrayHasKey('sheets', $spreadsheet);

        $this->client->deleteFile($spreadsheet['spreadsheetId']);
    }

    public function testGetSheetValues()
    {
        $gdFile = $this->client->createFile($this->testFilePath, $this->testFileName);
        $gdSheet = $this->client->getSpreadsheet($gdFile['id']);
        $response = $this->client->getSpreadsheetValues(
            $gdFile['id'],
            $gdSheet['sheets'][0]['properties']['title']
        );

        $this->assertArrayHasKey('range', $response);
        $this->assertArrayHasKey('majorDimension', $response);
        $this->assertArrayHasKey('values', $response);
        $header = $response['values'][0];
        $this->assertEquals('Class', $header[1]);
        $this->assertEquals('Sex', $header[2]);
        $this->assertEquals('Age', $header[3]);
        $this->assertEquals('Survived', $header[4]);
        $this->assertEquals('Freq', $header[5]);

        $this->client->deleteFile($gdSheet['spreadsheetId']);
    }

    public function testUpdateSheetValues()
    {
        $gdFile = $this->client->createFile(ROOT_PATH . '/tests/data/in/titanic_1.csv', 'titanic_1');
        $gdSheet = $this->client->getSpreadsheet($gdFile['id']);

        $values = $this->csvToArray(ROOT_PATH . '/tests/data/in/titanic_2.csv');

        $response =$this->client->updateSpreadsheetValues(
            $gdFile['id'],
            $gdSheet['sheets'][0]['properties']['title'],
            $values
        );

        $this->assertArrayHasKey('spreadsheetId', $response);
        $this->assertArrayHasKey('updatedRange', $response);
        $this->assertArrayHasKey('updatedRows', $response);
        $this->assertArrayHasKey('updatedColumns', $response);
        $this->assertArrayHasKey('updatedCells', $response);

        $this->assertEquals($gdFile['id'], $response['spreadsheetId']);

        $gdValues = $this->client->getSpreadsheetValues(
            $response['spreadsheetId'],
            $response['updatedRange']
        );

        $this->assertEquals($values, $gdValues['values']);

        $this->client->deleteFile($gdSheet['spreadsheetId']);
    }

    public function testAppendSheetValues()
    {
        $gdFile = $this->client->createFile(ROOT_PATH . '/tests/data/in/titanic_1.csv', 'titanic');
        $gdSheet = $this->client->getSpreadsheet($gdFile['id']);
        $values = $this->csvToArray(ROOT_PATH . '/tests/data/in/titanic_2.csv');
        array_shift($values); // skip header

        $response =$this->client->appendSpreadsheetValues(
            $gdFile['id'],
            $gdSheet['sheets'][0]['properties']['title'],
            $values
        );

        $expectedValues = $this->csvToArray(ROOT_PATH . '/tests/data/in/titanic.csv');
        $gdValues = $this->client->getSpreadsheetValues(
            $response['spreadsheetId'],
            $gdSheet['sheets'][0]['properties']['title']
        );
        $this->assertEquals($expectedValues, $gdValues['values']);

        $this->client->deleteFile($gdSheet['spreadsheetId']);
    }
}
