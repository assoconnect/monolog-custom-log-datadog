<?php

namespace AssoConnect\Tests;

use AssoConnect\MonologDatadog\CustomLogHandler;
use GuzzleHttp\ClientInterface;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class CustomLogHandlerTest extends TestCase
{
    private const ENDPOINT = 'foo';
    private const API_KEY = 'bar';

    private $clientMock;

    private $customLogHandler;

    protected function setUp()
    {
        $this->clientMock = $this->getMockBuilder(ClientInterface::class)->getMock();
        $this->customLogHandler = new CustomLogHandler(self::ENDPOINT, self::API_KEY, $this->clientMock);
    }

    public function testItCallTheEndpointWithExpectedMessageAndHeaders()
    {
        $this->clientMock->expects($this->once())->method('request')->with(
            'POST',
            self::ENDPOINT . '/v1/input/' . self::API_KEY,
            $this->callback(function ($options) {
                $body = json_decode($options['body'], true);

                return $body['message'] === 'test'
                    && $options['headers']['Content-Type'] == 'application/json'
                    && $options['headers']['Accept'] == '*/*'
                ;
            })
        )->willReturn($this->getMockBuilder(ResponseInterface::class)->getMock());

        $logger = new Logger('phpunit');
        $logger->pushHandler($this->customLogHandler);

        $logger->info('test');
    }
}
