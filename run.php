<?php

declare(strict_types=1);

use Keboola\GoogleDriveWriter\Application;
use Keboola\GoogleDriveWriter\Exception\ApplicationException;
use Keboola\GoogleDriveWriter\Exception\UserException;
use Monolog\Logger;

require_once(dirname(__FILE__) . '/bootstrap.php');

$logger = new Logger(APP_NAME);

try {
    $arguments = getopt('d::', ['data::']);
    if (!isset($arguments['data'])) {
        throw new UserException('Data folder not set.');
    }
    $config = json_decode(file_get_contents($arguments['data'] . '/config.json'), true);
    $config['parameters']['data_dir'] = $arguments['data'];

    $isSyncAction = isset($config['action']) && $config['action'] !== 'run';
    $app = new Application($config);
    $result = $app->run();

    if ($isSyncAction) {
        echo json_encode($result);
        exit(0);
    }

    $status = isset($result['status']) ? $result['status'] : 'ok';
    echo sprintf('Writer finished with status: %s', $result['status']);
    exit(0);
} catch (UserException $e) {
    if (isset($config['action']) && $config['action'] !== 'run') {
        echo json_encode([
            'status' => 'error',
            'error' => 'User Error',
            'message' => $e->getMessage(),
        ]);
    } else {
        $logger->log('error', $e->getMessage(), (array) $e->getData());
    }
    exit(1);
} catch (ApplicationException $e) {
    $logger->log('error', $e->getMessage(), [
        'data' => $e->getData(),
        'errFile' => $e->getFile(),
        'errLine' => $e->getLine(),
    ]);
    exit(2);
} catch (Throwable $e) {
    $logger->log('error', $e->getMessage(), [
        'errFile' => $e->getFile(),
        'errLine' => $e->getLine(),
        'trace' => $e->getTrace(),
    ]);
    exit(2);
}
