<?php

namespace AssoConnect\Tests;

use AssoConnect\MonologDatadog\CustomLogHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    protected function createCustomLogHandler(): CustomLogHandler
    {
        $endpoint = getenv('DATADOG_ENDPOINT');
        $apiKey = getenv('DATADOG_API_KEY');

        $guzzleClient = new \GuzzleHttp\Client();

        $client = new CustomLogHandler($endpoint, $apiKey, $guzzleClient);

        return $client;
    }

    public function testSuccess() :void
    {
        $customLogHandler = $this->createCustomLogHandler();
        $logger = new Logger('phpunit');

        $logger->pushHandler($customLogHandler);

        $logger->pushProcessor(function ($record) {
            $record['hostname'] = 'phpunit';
            $record['ddsource'] = 'php';
            $record['service'] = 'phpunit';
            return $record;
        });

        $this->assertTrue($logger->info('test'));
    }
}
