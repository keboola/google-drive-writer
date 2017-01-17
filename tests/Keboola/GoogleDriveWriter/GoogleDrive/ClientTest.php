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
        $this->client = new Client($api);
    }

    public function testCreateFile()
    {

    }

    public function testGetFile()
    {
        $file = $this->client->getFile($this->testFile['spreadsheetId']);

        $this->assertArrayHasKey('id', $file);
        $this->assertArrayHasKey('name', $file);
        $this->assertEquals($this->testFile['spreadsheetId'], $file['id']);
    }

    public function testUpdateFile()
    {

    }

    public function testDeleteFile()
    {

    }

    public function testCreateSheet()
    {

    }

    public function testGetSheet()
    {
        $spreadsheet = $this->client->getSpreadsheet($this->testFile['spreadsheetId']);

        $this->assertArrayHasKey('spreadsheetId', $spreadsheet);
        $this->assertArrayHasKey('properties', $spreadsheet);
        $this->assertArrayHasKey('sheets', $spreadsheet);
    }

    public function testGetSheetValues()
    {
        $spreadsheetId = $this->testFile['spreadsheetId'];
        $sheetTitle = $this->testFile['sheets'][0]['properties']['title'];

        $response = $this->client->getSpreadsheetValues($spreadsheetId, $sheetTitle);

        $this->assertArrayHasKey('range', $response);
        $this->assertArrayHasKey('majorDimension', $response);
        $this->assertArrayHasKey('values', $response);
        $header = $response['values'][0];
        $this->assertEquals('Class', $header[1]);
        $this->assertEquals('Sex', $header[2]);
        $this->assertEquals('Age', $header[3]);
        $this->assertEquals('Survived', $header[4]);
        $this->assertEquals('Freq', $header[5]);
    }

    public function testUpdateSheetValues()
    {

    }

    public function testAppendSheetValues()
    {

    }
}
