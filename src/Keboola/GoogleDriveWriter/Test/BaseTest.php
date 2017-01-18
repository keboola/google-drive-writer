<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/08/16
 * Time: 16:49
 */

namespace Keboola\GoogleDriveWriter\Test;

use Keboola\Csv\CsvFile;

class BaseTest extends \PHPUnit_Framework_TestCase
{
    protected $testFilePath = ROOT_PATH . '/tests/data/in/titanic.csv';

    protected $testFileName = 'titanic';

    protected $config;

    public function setUp()
    {
        $this->config = $this->makeConfig($this->testFilePath, $this->testFileName);
    }

    protected function makeConfig($pathname, $title)
    {
        $config['parameters']['data_dir'] = ROOT_PATH . '/tests/data';
        $config['authorization']['oauth_api']['credentials'] = [
            'appKey' => getenv('CLIENT_ID'),
            '#appSecret' => getenv('CLIENT_SECRET'),
            '#data' => json_encode([
                'access_token' => getenv('ACCESS_TOKEN'),
                'refresh_token' => getenv('REFRESH_TOKEN')
            ])
        ];
        $config['parameters']['sheets'][0] = [
            'id' => 0,
            'fileId' => '',
            'fileTitle' => $title,
            'sheetId' => '',
            'sheetTitle' => '',
            'folder' => '/',
            'type' => 'file',
            'action' => 'create',
            'pathname' => $pathname,
            'enabled' => true
        ];

        return $config;
    }

    protected function csvToArray($pathname)
    {
        $values = [];
        $csvFile = new CsvFile($pathname);
        $csvFile->next();
        while ($csvFile->current()) {
            $values[] = $csvFile->current();
            $csvFile->next();
        }

        return $values;
    }

    public function tearDown()
    {
    }
}
