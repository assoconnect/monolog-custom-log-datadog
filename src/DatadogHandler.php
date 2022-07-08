<?php

declare(strict_types=1);

namespace AssoConnect\MonologDatadog;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Koriym\HttpConstants\Method;
use Koriym\HttpConstants\RequestHeader;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * @phpstan-import-type Record from \Monolog\Logger
 */
class DatadogHandler extends AbstractProcessingHandler
{
    /**
     * Guzzle Client
     */
    protected ClientInterface $client;

    /**
     * Datadog endpoint
     */
    protected string $endpoint;

    /**
     * Datadog API KEY
     */
    protected string $apiKey;

    public function __construct(
        string $endpoint,
        string $apiKey,
        ClientInterface $client,
        $level = Logger::DEBUG,
        $bubble = true
    ) {
        $this->endpoint = $endpoint;
        $this->apiKey = $apiKey;
        $this->client = $client;

        parent::__construct($level, $bubble);
    }

    /**
     * @param Record $record
     *
     * @throws GuzzleException
     */
    protected function write(array $record): void
    {
        $this->doWrite([$record]);
    }

    /**
     * @param Record[] $records
     */
    public function handleBatch(array $records): void
    {
        $records = array_filter($records, [$this, 'isHandling']);

        if ([] !== $this->processors) {
            $records = array_map([$this, 'processRecord'], $records);
        }

        array_walk($records, function (array $record): array {
            /** @var Record $record */
            $record['formatted'] = $this->getFormatter()->format($record);
            return $record;
        });

        /** @var Record[] $records */
        $this->doWrite($records);
    }

    /**
     * @param Record[] $records
     */
    private function doWrite(array $records): void
    {
        if ([] === $records) {
            return;
        }

        $records = array_map([$this, 'formatRecord'], $records);

        $data = [
            'json' => $records,
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

    /**
     * @param Record $record
     * @return Record
     */
    private function formatRecord(array $record): array
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
