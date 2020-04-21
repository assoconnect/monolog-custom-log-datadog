<?php

namespace AssoConnect\Tests;

use AssoConnect\MonologDatadog\DatadogHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Koriym\HttpConstants\Method;
use Koriym\HttpConstants\RequestHeader;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class DatadogHandlerTest extends TestCase
{
    private const ENDPOINT = 'http://foo.com';
    private const API_KEY = 'bar';

    /** @var DatadogHandler */
    private $datadogHandler;

    /** @var Client */
    protected $guzzle;

    /** @var MockHandler */
    protected $guzzleMockHandler;

    /** @var array */
    protected $guzzleHistory = [];

    /** @var Logger */
    protected $logger;

    protected function setUp()
    {
        $this->guzzleHistory = [];
        $history = Middleware::history($this->guzzleHistory);

        $this->guzzleMockHandler = new MockHandler();

        $handler = HandlerStack::create($this->guzzleMockHandler);

        // Add the history middleware to the handler stack.
        $handler->push($history);

        $this->guzzle = new Client(['handler' => $handler]);

        $this->datadogHandler = new DatadogHandler(self::ENDPOINT, self::API_KEY, $this->guzzle);

        $this->logger = new Logger('phpunit');
        $this->logger->pushHandler($this->datadogHandler);
    }

    public function testItCallsTheEndpointWithExpectedMessageAndHeaders()
    {
        $this->guzzleMockHandler->append(new Response(200));

        $this->logger->info($message = 'test');

        $this->assertCount(1, $this->guzzleHistory);
        /** @var Request $request */
        $request = $this->guzzleHistory[0]['request'];
        $this->assertSame(self::ENDPOINT . '/v1/input/' . self::API_KEY, $request->getUri()->__toString());
        $this->assertSame(Method::POST, $request->getMethod());
        $this->assertSame('application/json', $request->getHeaderLine(RequestHeader::CONTENT_TYPE));
        $this->assertSame('*/*', $request->getHeaderLine(RequestHeader::ACCEPT));

        $body = json_decode($request->getBody(), true);
        $this->assertSame($message, $body['message']);
    }

    public function testWithTagsAsString()
    {
        $tags = 'foo:bar,hello:world';
        $this->logger->pushProcessor(function (array $record) use ($tags): array {
            $record['ddtags'] = $tags;
            return $record;
        });

        $this->guzzleMockHandler->append(new Response(200));

        $this->logger->info('test');

        $body = json_decode($this->guzzleHistory[0]['request']->getBody(), true);
        $this->assertSame($tags, $body['ddtags']);
    }

    public function testWithTagsAsArray()
    {
        $this->logger->pushProcessor(function (array $record): array {
            $record['ddtags'] = [
                'foo' => 'bar',
                'hello' => 'world',
            ];
            return $record;
        });

        $this->guzzleMockHandler->append(new Response(200));

        $this->logger->info('test');

        $body = json_decode($this->guzzleHistory[0]['request']->getBody(), true);
        $this->assertSame('foo:bar,hello:world', $body['ddtags']);
    }
}
