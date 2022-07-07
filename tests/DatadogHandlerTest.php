<?php

namespace AssoConnect\MonologDatadog\Tests;

use AssoConnect\MonologDatadog\DatadogHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Koriym\HttpConstants\Method;
use Koriym\HttpConstants\RequestHeader;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class DatadogHandlerTest extends TestCase
{
    private const ENDPOINT = 'http://foo.com';
    private const API_KEY = 'bar';

    private DatadogHandler $datadogHandler;

    protected Client $guzzle;

    protected MockHandler $guzzleMockHandler;

    /** @var mixed[] */
    protected array $guzzleHistory = [];

    protected Logger $logger;

    protected function setUp(): void
    {
        $this->guzzleHistory = [];
        $history = Middleware::history($this->guzzleHistory);

        $this->guzzleMockHandler = new MockHandler();

        $handler = HandlerStack::create($this->guzzleMockHandler);

        // Add the history middleware to the handler stack.
        $handler->push($history);

        $this->guzzle = new Client(['handler' => $handler]);

        $this->datadogHandler = new DatadogHandler(self::ENDPOINT, self::API_KEY, $this->guzzle, Logger::INFO);

        $this->logger = new Logger('phpunit');
        $this->logger->pushHandler($this->datadogHandler);
    }

    public function testItCallsTheEndpointWithExpectedMessageAndHeaders(): void
    {
        $this->guzzleMockHandler->append(new Response(200));

        $this->logger->info($message = 'test');

        self::assertCount(1, $this->guzzleHistory);
        /** @var Request $request */
        $request = $this->guzzleHistory[0]['request'];
        self::assertSame(self::ENDPOINT . '/v1/input/' . self::API_KEY, $request->getUri()->__toString());
        self::assertSame(Method::POST, $request->getMethod());
        self::assertSame('application/json', $request->getHeaderLine(RequestHeader::CONTENT_TYPE));
        self::assertSame('*/*', $request->getHeaderLine(RequestHeader::ACCEPT));

        $body = json_decode($request->getBody(), true);
        self::assertSame($message, $body[0]['message']);
    }

    public function testSingleModeFiltersLogs(): void
    {
        $this->logger->debug('hello');
        self::assertCount(0, $this->guzzleHistory);
    }

    public function testBatchModeFiltersLogsAndSendsOnlyOneRequest(): void
    {
        $this->guzzleMockHandler->append(new Response(200)); // For the first batch
        $this->guzzleMockHandler->append(new Response(200)); // For the final destruct

        $this->logger = new Logger('phpunit');
        $bufferHandler = new BufferHandler($this->datadogHandler, 3, Logger::DEBUG, true, true);
        $this->logger->pushHandler($bufferHandler);

        // A debug log is filtered out
        $this->logger->debug('hello');
        $bufferHandler->flush();
        self::assertEmpty($this->guzzleHistory);

        // Stacking 3 info logs ...
        $this->logger->info($info1 = 'info 1');
        $this->logger->info($info2 = 'info 2');
        $this->logger->info($info3 = 'info 3');
        // ... they stay buffered
        self::assertEmpty($this->guzzleHistory);

        // The 4th info log triggers the flush
        $this->logger->info('info 4');
        self::assertCount(1, $this->guzzleHistory);

        /** @var Request $request */
        $request = $this->guzzleHistory[0]['request'];
        $body = json_decode($request->getBody(), true);
        self::assertCount(3, $body);
        self::assertSame($info1, $body[0]['message']);
        self::assertSame($info2, $body[1]['message']);
        self::assertSame($info3, $body[2]['message']);
    }

    public function testWithTagsAsString(): void
    {
        $tags = 'foo:bar,hello:world';
        $this->logger->pushProcessor(function (array $record) use ($tags): array {
            $record['ddtags'] = $tags;
            return $record;
        });

        $this->guzzleMockHandler->append(new Response(200));

        $this->logger->info('test');

        $body = json_decode($this->guzzleHistory[0]['request']->getBody(), true);
        self::assertSame($tags, $body[0]['ddtags']);
    }

    public function testWithTagsAsArray(): void
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
        self::assertSame('foo:bar,hello:world', $body[0]['ddtags']);
    }
}
