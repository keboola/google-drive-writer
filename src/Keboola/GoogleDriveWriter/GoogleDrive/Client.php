<?php
/**
 * RestApi.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.7.13
 */

namespace Keboola\GoogleDriveWriter\GoogleDrive;

use GuzzleHttp\Exception\ClientException;
use Keboola\Google\ClientBundle\Google\RestApi as GoogleApi;
use Keboola\GoogleDriveWriter\Exception\ApplicationException;

class Client
{
    /** @var GoogleApi */
    protected $api;

    const DRIVE_FILES = 'https://www.googleapis.com/drive/v3/files';

    const DRIVE_UPLOAD = 'https://www.googleapis.com/upload/drive/v3/files';

    const SPREADSHEETS = 'https://sheets.googleapis.com/v4/spreadsheets/';

    const MIME_TYPE_SPREADSHEET = 'application/vnd.google-apps.spreadsheet';

    public function __construct(GoogleApi $api)
    {
        $this->api = $api;
    }

    /**
     * @return GoogleApi
     */
    public function getApi()
    {
        return $this->api;
    }

    /**
     * @param $id
     * @param array $fields
     * @return mixed
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function getFile($id, $fields = [])
    {
        $uri = self::DRIVE_FILES . '/' . $id;
        if (!empty($fields)) {
            $uri .= sprintf('?fields=%s', implode(',', $fields));
        }
        $response = $this->api->request($uri);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $query
     * @return mixed
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function listFiles($query = '')
    {
        $uri = self::DRIVE_FILES;
        if (!empty($query)) {
            $uri .= sprintf('?q=%s', $query);
        }
        $response = $this->api->request($uri);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param $pathname
     * @param $title
     * @param array $params
     * @return array
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function createFile($pathname, $title, $params = [])
    {
        $body = [
            'name' => $title
        ];

        $response = $this->api->request(
            self::DRIVE_FILES,
            'POST',
            [
                'Content-Type' => 'application/json',
            ],
            [
                'json' => array_merge($body, $params)
            ]
        );

        $responseJson = json_decode($response->getBody(), true);

        $mediaUrl = sprintf('%s/%s?uploadType=media', self::DRIVE_UPLOAD, $responseJson['id']);

        $response = $this->api->request(
            $mediaUrl,
            'PATCH',
            [
                'Content-Type' => 'text/csv',
                'Content-Length' => filesize($pathname)
            ],
            [
                'body' => \GuzzleHttp\Psr7\stream_for(fopen($pathname, 'r'))
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param $id
     * @param $pathname
     * @param $params
     * @return mixed
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function updateFile($id, $pathname, $params)
    {
        // update metadata
        $responseJson = $this->updateFileMetadata($id, $params);

        $response = $this->api->request(
            sprintf('%s/%s?uploadType=media', self::DRIVE_UPLOAD, $responseJson['id']),
            'PATCH',
            [
                'Content-Type' => 'text/csv',
                'Content-Length' => filesize($pathname)
            ],
            [
                'body' => \GuzzleHttp\Psr7\stream_for(fopen($pathname, 'r'))
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param $id
     * @param array $body
     * @param array $params
     * @return mixed
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function updateFileMetadata($id, $body = [], $params = [])
    {
        $uri = sprintf('%s/%s', self::DRIVE_FILES, $id);
        if (!empty($params)) {
            $uri .= '?' . \GuzzleHttp\Psr7\build_query($params);
        }

        $response = $this->api->request(
            $uri,
            'PATCH',
            [
                'Content-Type' => 'application/json',
            ],
            [
                'json' => $body
            ]
        );

        return json_decode($response->getBody(), true);
    }

    /**
     * @param $id
     * @return \GuzzleHttp\Psr7\Response
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function deleteFile($id)
    {
        return $this->api->request(
            sprintf('%s/%s', self::DRIVE_FILES, $id),
            'DELETE'
        );
    }

    /**
     * @param $id
     * @param string $mimeType
     * @return \GuzzleHttp\Psr7\Response
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function exportFile($id, $mimeType = 'text/csv')
    {
        return $this->api->request(
            sprintf(
                '%s/%s/export?mimeType=%s',
                self::DRIVE_FILES,
                $id,
                $mimeType
            )
        );
    }

    /**
     * Returns list of sheet for given document
     *
     * @param $fileId
     * @return array|bool
     * @throws ApplicationException
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function getSpreadsheet($fileId)
    {
        $response = $this->api->request(
            sprintf('%s%s', self::SPREADSHEETS, $fileId),
            'GET',
            [
                'Accept' => 'application/json',
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param $spreadsheetId
     * @param $range
     * @return array
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function getSpreadsheetValues($spreadsheetId, $range)
    {
        $response = $this->api->request(
            sprintf(
                '%s%s/values/%s',
                self::SPREADSHEETS,
                $spreadsheetId,
                $range
            ),
            'GET',
            [
                'Accept' => 'application/json',
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param $fileProperties
     * @param $sheets
     * @param null $fileId
     * @return mixed
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function createSpreadsheet($fileProperties, $sheets, $fileId = null)
    {
        $body = [
            'properties' => $fileProperties,
            'sheets' => $sheets
        ];

        if ($fileId !== null) {
            $body['spreadsheetId'] = $fileId;
        }

        $response = $this->api->request(
            self::SPREADSHEETS,
            'POST',
            [
                'Accept' => 'application/json',
            ],
            [
                'json' => $body
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param $spreadsheetId
     * @param $sheet
     * @return mixed
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function addSheet($spreadsheetId, $sheet)
    {
        return $this->batchUpdateSpreadsheet($spreadsheetId, [
            'requests' => [
                [
                    'addSheet' => $sheet
                ]
            ]
        ]);
    }

    public function updateSheet($spreadsheetId, $properties)
    {
        return $this->batchUpdateSpreadsheet($spreadsheetId, [
            'requests' => [
                [
                    'updateSheetProperties' => [
                        'properties' => $properties,
                        'fields' => 'title'
                    ]
                ]
            ]
        ]);
    }

    /**
     * Batch Update Spreadsheet Metadata
     *
     * @param $spreadsheetId
     * @param $body
     * @return mixed
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function batchUpdateSpreadsheet($spreadsheetId, $body)
    {
        $response = $this->api->request(
            sprintf(
                '%s%s:batchUpdate',
                self::SPREADSHEETS,
                $spreadsheetId
            ),
            'POST',
            [
                'Accept' => 'application/json',
            ],
            [
                'json' => $body
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param $spreadsheetId
     * @param $range
     * @param $values
     * @return mixed
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function updateSpreadsheetValues($spreadsheetId, $range, $values)
    {
        $response = $this->api->request(
            sprintf(
                '%s%s/values/%s?valueInputOption=USER_ENTERED',
                self::SPREADSHEETS,
                $spreadsheetId,
                $range
            ),
            'PUT',
            [
                'Accept' => 'application/json',
            ],
            [
                'json' => [
                    'range' => $range,
                    'majorDimension' => 'ROWS',
                    'values' => $values
                ]
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param $spreadsheetId
     * @param $range
     * @param $values
     * @return mixed
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function appendSpreadsheetValues($spreadsheetId, $range, $values)
    {
        $response = $this->api->request(
            sprintf(
                '%s%s/values/%s:append?valueInputOption=USER_ENTERED',
                self::SPREADSHEETS,
                $spreadsheetId,
                $range
            ),
            'POST',
            [
                'Accept' => 'application/json',
            ],
            [
                'json' => [
                    'range' => $range,
                    'majorDimension' => 'ROWS',
                    'values' => $values
                ]
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param int $count
     * @return mixed
     * @throws \Keboola\Google\ClientBundle\Exception\RestApiException
     */
    public function generateIds($count = 10)
    {
        $response = $this->api->request(
            sprintf('%s/generateIds?count=%s', self::DRIVE_FILES, $count)
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param $fileId
     * @return bool
     */
    public function fileExists($fileId)
    {
        try {
            $this->getFile($fileId);
            return true;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }
        return false;
    }
}
