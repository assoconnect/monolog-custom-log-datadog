<?php

declare(strict_types=1);

namespace AssoConnect\MonologDatadog;


use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
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

    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warn';
    const LEVEL_ERROR = 'error';

    public function __construct(string $endpoint, string $apiKey, ClientInterface $client)
    {
        $this->client = $client;
        $this->endpoint = $endpoint;
        $this->apiKey = $apiKey;
    }

    /**
     * Query datadog API
     * @param string $path
     * @param string $method
     * @param iterable|null $data
     * @param array $options
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function query(
        string $path,
        string $method,
        iterable $data
    ): ResponseInterface {
        $data = [
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'        => '*/*',
            ],
        ];
        return $this->client->request($method, $this->endpoint . $path, $data);
    }

    protected function write(array $record)
    {
        $path = '/v1/input/' . $this->apiKey;
        $method = 'POST';

        $res = $this->query($path, $method, $record);
    }
}
