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
use Keboola\GoogleDriveWriter\Extractor\Extractor;
use Keboola\GoogleDriveWriter\Extractor\Output;
use Keboola\GoogleDriveWriter\GoogleDrive\Client;
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
        $container['parameters'] = $this->validateParamteters($config['parameters']);
        $container['logger'] = function ($c) {
            $logger = new Logger(APP_NAME);
            if ($c['action'] !== 'run') {
                $logger->setHandlers([new NullHandler(Logger::INFO)]);
            }
            return $logger;
        };
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
            return new Client($c['google_client']);
        };
        $container['output'] = function ($c) {
            return new Output($c['parameters']['data_dir'], $c['parameters']['outputBucket']);
        };
        $container['extractor'] = function ($c) {
            return new Extractor(
                $c['google_drive_client'],
                $c['output'],
                $c['logger']
            );
        };

        $this->container = $container;
    }

    public function run()
    {
        $actionMethod = $this->container['action'] . 'Action';
        if (!method_exists($this, $actionMethod)) {
            throw new UserException(sprintf("Action '%s' does not exist.", $this['action']));
        }

        try {
            return $this->$actionMethod();
        } catch (RequestException $e) {
            if ($e->getCode() == 401) {
                throw new UserException("Expired or wrong credentials, please reauthorize.", $e->getCode(), $e);
            }
            if ($e->getCode() == 403) {
                if (strtolower($e->getResponse()->getReasonPhrase()) == 'forbidden') {
                    $this->container['logger']->warning("You don't have access to Google Drive resource.");
                    return [];
                }
                throw new UserException("Reason: " . $e->getResponse()->getReasonPhrase(), $e->getCode(), $e);
            }
            if ($e->getCode() == 400) {
                throw new UserException($e->getMessage());
            }
            if ($e->getCode() == 503) {
                throw new UserException("Google API error: " . $e->getMessage(), $e->getCode(), $e);
            }
            throw new ApplicationException($e->getMessage(), 500, $e, [
                'response' => $e->getResponse()->getBody()->getContents()
            ]);
        }
    }

    private function runAction()
    {
        /** @var Extractor $extractor */
        $extractor = $this->container['extractor'];
        $extracted = $extractor->run($this->container['parameters']['sheets']);

        return [
            'status' => 'ok',
            'extracted' => $extracted
        ];
    }

    private function validateParamteters($parameters)
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
