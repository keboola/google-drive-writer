<?php

declare(strict_types=1);

namespace Keboola\GoogleDriveWriter\Tests;

use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleDriveWriter\Configuration\ConfigDefinition;
use Keboola\GoogleDriveWriter\Exception\UserException;
use Keboola\GoogleDriveWriter\Input;
use Keboola\GoogleDriveWriter\Logger;
use Keboola\GoogleDriveWriter\Writer;
use Keboola\GoogleSheetsClient\Client;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;

/**
 * Hermetic unit tests for Writer - no Google API calls, no credentials needed.
 */
class WriterTest extends PHPUnit_Framework_TestCase
{
    private const DATA_DIR = '/tmp/wr-gd-test';

    /**
     * A table configured with action "update" but without "fileId" used to hit
     * "Undefined index: fileId" in Writer::update(), which the global error handler
     * turned into an ErrorException and the app reported as an internal error (exit 2).
     * It must now fail as a user error (exit 1) with an actionable message.
     */
    public function testUpdateWithoutFileIdThrowsUserException(): void
    {
        $client = $this->createClientMock();
        $client->expects($this->never())->method('fileExists');
        $client->expects($this->never())->method('updateFile');
        $client->expects($this->never())->method('createFile');

        $writer = $this->createWriter($client);

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Missing "fileId" in the configuration of table "in.c-test.titanic"');

        $writer->processTables([
            [
                'id' => 0,
                'tableId' => 'in.c-test.titanic',
                'title' => 'titanic',
                'enabled' => true,
                'action' => ConfigDefinition::ACTION_UPDATE,
            ],
        ]);
    }

    /**
     * Guard for the happy path: when "fileId" is present the update flow is untouched.
     */
    public function testUpdateWithFileIdUpdatesTheFile(): void
    {
        $client = $this->createClientMock();
        $client->expects($this->once())
            ->method('fileExists')
            ->with('file-123')
            ->willReturn(true);
        $client->expects($this->once())
            ->method('updateFile')
            ->with('file-123', self::DATA_DIR . '/in/tables/in.c-test.titanic.csv', ['name' => 'titanic'])
            ->willReturn(['id' => 'file-123', 'name' => 'titanic']);

        $writer = $this->createWriter($client);

        $responses = $writer->processTables([
            [
                'id' => 0,
                'tableId' => 'in.c-test.titanic',
                'title' => 'titanic',
                'fileId' => 'file-123',
                'enabled' => true,
                'action' => ConfigDefinition::ACTION_UPDATE,
            ],
        ]);

        $this->assertSame([['id' => 'file-123', 'name' => 'titanic']], $responses);
    }

    /**
     * Guard for the happy path: an empty "fileId" is still passed through to the client,
     * exactly as before - only a completely missing key is treated as a user error.
     */
    public function testUpdateWithEmptyFileIdIsPassedToClient(): void
    {
        $client = $this->createClientMock();
        $client->expects($this->once())
            ->method('fileExists')
            ->with('')
            ->willReturn(false);
        $client->expects($this->once())
            ->method('createFile')
            ->willReturn(['id' => 'new-file', 'name' => 'titanic']);

        $writer = $this->createWriter($client);

        $responses = $writer->processTables([
            [
                'id' => 0,
                'tableId' => 'in.c-test.titanic',
                'title' => 'titanic',
                'fileId' => '',
                'enabled' => true,
                'action' => ConfigDefinition::ACTION_UPDATE,
            ],
        ]);

        $this->assertSame([['id' => 'new-file', 'name' => 'titanic']], $responses);
    }

    private function createClientMock(): PHPUnit_Framework_MockObject_MockObject
    {
        // PHPUnit 5.7 generates mocks via ReflectionType::__toString(), which is deprecated on
        // PHP 7.4. bootstrap.php turns every PHP error into an ErrorException, so the deprecation
        // has to be silenced while the mock classes are being generated.
        set_error_handler(
            function (int $errno, string $errstr, string $errfile = '', int $errline = 0): bool {
                return true;
            },
            E_DEPRECATED,
        );
        try {
            $api = $this->createMock(RestApi::class);
            $client = $this->createMock(Client::class);
        } finally {
            restore_error_handler();
        }

        $client->method('getApi')->willReturn($api);

        return $client;
    }

    private function createWriter(PHPUnit_Framework_MockObject_MockObject $client): Writer
    {
        /** @var Client $clientStub */
        $clientStub = $client;

        return new Writer($clientStub, new Input(self::DATA_DIR), new Logger('wr-google-drive'));
    }
}
