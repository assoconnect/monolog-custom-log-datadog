<?php

declare(strict_types=1);

namespace AssoConnect\MonologDatadog;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\AbstractProcessingHandler;

class CustomLogHandler extends AbstractProcessingHandler
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
        $data = [
            'body' => json_encode($record),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => '*/*',
            ],
        ];

        $this->client->request(
            'POST',
            $this->endpoint . '/v1/input/' . $this->apiKey,
            $data
        );
    }
}
