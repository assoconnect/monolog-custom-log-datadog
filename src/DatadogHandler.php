<?php

declare(strict_types=1);

namespace AssoConnect\MonologDatadog;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Koriym\HttpConstants\Method;
use Koriym\HttpConstants\RequestHeader;
use Monolog\Handler\AbstractProcessingHandler;

class DatadogHandler extends AbstractProcessingHandler
{
    /**
     * Guzzle Client
     *
     * @var ClientInterface
     */
    protected $client;

    /**
     * Datadog endpoint
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Datadog API KEY
     *
     * @var string
     */
    protected $apiKey;

    public function __construct(string $endpoint, string $apiKey, ClientInterface $client)
    {
        $this->endpoint = $endpoint;
        $this->apiKey = $apiKey;
        $this->client = $client;
    }

    /**
     * @param array $record
     *
     * @throws GuzzleException
     */
    protected function write(array $record): void
    {
        $record = $this->formatRecord($record);

        $data = [
            'json' => $record,
            'headers' => [
                RequestHeader::ACCEPT => '*/*',
            ],
        ];

        $this->client->request(
            Method::POST,
            $this->endpoint . '/v1/input/' . $this->apiKey,
            $data
        );
    }

    protected function formatRecord(array $record): array
    {
        if (isset($record['ddtags']) && !is_string($record['ddtags'])) {
            $tags = [];
            foreach ($record['ddtags'] as $key => $value) {
                $tags[] = $key . ':' . $value;
            }
            $record['ddtags'] = implode(',', $tags);
        }

        if (isset($record['context']['service']) && !isset($record['service'])) {
            $record['service'] = $record['context']['service'];
        }

        if (isset($record['context']['hostname']) && !isset($record['hostname'])) {
            $record['hostname'] = $record['context']['hostname'];
        }

        return $record;
    }
}
