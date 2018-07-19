<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/08/16
 * Time: 15:45
 */

namespace Keboola\GoogleDriveWriter;

use GuzzleHttp\Exception\RequestException;
use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleDriveWriter\Configuration\ConfigDefinition;
use Keboola\GoogleDriveWriter\Exception\ApplicationException;
use Keboola\GoogleDriveWriter\Exception\UserException;
use Keboola\GoogleSheetsClient\Client;
use Monolog\Handler\NullHandler;
use Pimple\Container;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class Application
{
    private $container;

    public function __construct($config)
    {
        $container = new Container();
        $container['action'] = isset($config['action'])?$config['action']:'run';
        $container['logger'] = function ($c) {
            $logger = new Logger(APP_NAME);
            if ($c['action'] !== 'run') {
                $logger->setHandlers([new NullHandler(Logger::INFO)]);
            }
            return $logger;
        };
        $container['parameters'] = $this->validateParameters($config['parameters']);
        if (empty($config['authorization'])) {
            throw new UserException('Missing authorization data');
        }
        $tokenData = json_decode($config['authorization']['oauth_api']['credentials']['#data'], true);
        $container['google_client'] = function () use ($config, $tokenData) {
            return new RestApi(
                $config['authorization']['oauth_api']['credentials']['appKey'],
                $config['authorization']['oauth_api']['credentials']['#appSecret'],
                $tokenData['access_token'],
                $tokenData['refresh_token']
            );
        };
        $container['google_drive_client'] = function ($c) {
            $client = new Client($c['google_client']);
            $client->setTeamDriveSupport(true);
            return $client;
        };
        $container['input'] = function ($c) {
            return new Input($c['parameters']['data_dir']);
        };
        $container['writer'] = function ($c) {
            return new Writer(
                $c['google_drive_client'],
                $c['input'],
                $c['logger']
            );
        };

        $this->container = $container;
    }

    public function run()
    {
        $actionMethod = $this->container['action'] . 'Action';
        if (!method_exists($this, $actionMethod)) {
            throw new UserException(sprintf("Action '%s' does not exist.", $this->container['action']));
        }

        try {
            return $this->$actionMethod();
        } catch (RequestException $e) {
            if ($e->getCode() == 401) {
                throw new UserException('Expired or wrong credentials, please reauthorize.', $e->getCode(), $e);
            }
            if ($e->getCode() == 403) {
                if (strtolower($e->getResponse()->getReasonPhrase()) == 'forbidden') {
                    $this->container['logger']->warning(
                        sprintf(
                            'You don\'t have access to Google Drive resource "%s"',
                            $e->getRequest()->getUri()->__toString()
                        ),
                        [
                            'uri' => $e->getRequest()->getUri(),
                            'statusCode' => $e->getResponse()->getStatusCode(),
                            'message' => $e->getMessage(),
                            'exception' => $e
                        ]
                    );

                    return ['status' => 'warning'];
                }
                throw new UserException('Reason: ' . $e->getResponse()->getReasonPhrase(), $e->getCode(), $e);
            }
            if ($e->getCode() == 400) {
                throw new UserException($e->getMessage());
            }
            if ($e->getCode() >= 500 && $e->getCode() < 600) {
                throw new UserException('Google API error: ' . $e->getMessage(), $e->getCode(), $e);
            }
            throw new ApplicationException($e->getMessage(), 500, $e, [
                'response' => $e->getResponse()->getBody()->getContents()
            ]);
        }
    }

    protected function runAction()
    {
        /** @var Writer $writer */
        $writer = $this->container['writer'];

        if (!empty($this->container['parameters']['tables'])) {
            $writer->processTables($this->container['parameters']['tables']);
        }

        if (!empty($this->container['parameters']['files'])) {
            $writer->processFiles($this->container['parameters']['files']);
        }

        return [
            'status' => 'ok'
        ];
    }

    protected function createFileAction()
    {
        /** @var Writer $writer */
        $writer = $this->container['writer'];
        $writer->setNumberOfRetries(2);
        $response = $writer->createFileMetadata($this->container['parameters']['tables'][0]);

        return [
            'status' => 'ok',
            'file' => $response
        ];
    }

    protected function getFolderAction()
    {
        /** @var Writer $writer */
        $writer = $this->container['writer'];
        $writer->setNumberOfRetries(2);

        $folderId = 'root';
        if (!empty($this->container['parameters']['tables'][0]['folder']['id'])) {
            $folderId = $this->container['parameters']['tables'][0]['folder']['id'];
        }
        $response = $writer->getFile($folderId);

        return [
            'status' => 'ok',
            'file' => $response
        ];
    }

    private function validateParameters($parameters)
    {
        try {
            $processor = new Processor();
            return $processor->processConfiguration(
                new ConfigDefinition(),
                [$parameters]
            );
        } catch (InvalidConfigurationException $e) {
            throw new UserException($e->getMessage(), 400, $e);
        }
    }
}
