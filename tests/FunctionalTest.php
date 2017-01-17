<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/08/16
 * Time: 16:45
 */
namespace Keboola\GoogleDriveWriter\Tests;

use Keboola\GoogleDriveWriter\Test\BaseTest;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class FunctionalTest extends BaseTest
{
    private $dataPath = '/tmp/data-test';

    public function testRun()
    {
        $process = $this->runProcess();
        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());

        $fileId = $this->config['parameters']['sheets'][0]['fileId'];
        $sheetId = $this->config['parameters']['sheets'][0]['sheetId'];

        $this->assertFileEquals(
            $this->testFilePath,
            $this->dataPath . '/out/tables/' . $this->getOutputFileName($fileId, $sheetId),
            "",
            true
        );
    }

    public function testRunEmptyTable()
    {
        $emptyFilePath = ROOT_PATH . '/tests/data/in/empty.csv';
        touch($emptyFilePath);

        $this->testFile = $this->prepareTestFile($emptyFilePath, 'empty');
        $this->config = $this->makeConfig($this->testFile);

        $process = $this->runProcess();
        $this->assertEquals(0, $process->getExitCode(), $process->getErrorOutput());

        $fileId = $this->config['parameters']['sheets'][0]['fileId'];
        $sheetId = $this->config['parameters']['sheets'][0]['sheetId'];

        $outputFilepath = $this->dataPath . '/out/tables/' . $this->getOutputFileName($fileId, $sheetId);
        $this->assertFileNotExists($outputFilepath);
        $this->assertFileNotExists($outputFilepath . '.manifest');

        unlink($emptyFilePath);
    }

    /**
     * Create each time a new file - append date to filename
     */
    public function testCreateFile()
    {

    }

    /**
     * Create or replace a file
     */
    public function testUpdateFile()
    {

    }

    /**
     * Create or update a sheet
     */
    public function testUpdateSheet()
    {

    }

    /**
     * Append content to a sheet
     */
    public function testAppendSheet()
    {

    }

    /**
     * @return Process
     */
    private function runProcess()
    {
        $fs = new Filesystem();
        $fs->remove($this->dataPath);
        $fs->mkdir($this->dataPath);
        $fs->mkdir($this->dataPath . '/out/tables');

        $yaml = new Yaml();
        file_put_contents($this->dataPath . '/config.yml', $yaml->dump($this->config));

        $process = new Process(sprintf('php run.php --data=%s', $this->dataPath));
        $process->run();

        return $process;
    }
}
