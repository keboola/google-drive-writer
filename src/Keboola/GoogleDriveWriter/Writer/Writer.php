<?php
/**
 * Extractor.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 29.7.13
 */

namespace Keboola\GoogleDriveWriter\Writer;

use Keboola\GoogleDriveWriter\GoogleDrive\Client;
use Psr\Http\Message\ResponseInterface;

class Writer
{
    /** @var Client */
    private $driveApi;

    public function __construct(Client $driveApi)
    {
        $this->driveApi = $driveApi;

        $this->driveApi->getApi()->setBackoffsCount(7);
        $this->driveApi->getApi()->setBackoffCallback403($this->getBackoffCallback403());
        $this->driveApi->getApi()->setRefreshTokenCallback(function($accessToken, $refreshToken) {});
    }

    public function getBackoffCallback403()
    {
        return function ($response) {
            /** @var ResponseInterface $response */
            $reason = $response->getReasonPhrase();

            if ($reason == 'insufficientPermissions'
                || $reason == 'dailyLimitExceeded'
                || $reason == 'usageLimits.userRateLimitExceededUnreg'
            ) {
                return false;
            }

            return true;
        };
    }

    public function process($sheets)
    {
        $results = [];

        foreach ($sheets as $sheet) {
            $actionName = $sheet['action'];
            $actionClassName = ucfirst($sheet['type']);
            (new $actionClassName($this->driveApi))->$actionName($sheet);
        }

        return $results;
    }
}
