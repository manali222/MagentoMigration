<?php
/**
 * MageClone MagentoMigrator GraphQL Client Unit Test
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Test\Unit\Model;

use MageClone\MagentoMigrator\Exception\GraphQlClientException;
use MageClone\MagentoMigrator\Model\Config;
use MageClone\MagentoMigrator\Model\GraphQlClient;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \MageClone\MagentoMigrator\Model\GraphQlClient
 */
class GraphQlClientTest extends TestCase
{
    private Config&MockObject $configMock;
    private CurlFactory&MockObject $curlFactoryMock;
    private Curl&MockObject $curlMock;
    private LoggerInterface&MockObject $loggerMock;
    private GraphQlClient $graphQlClient;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->curlFactoryMock = $this->createMock(CurlFactory::class);
        $this->curlMock = $this->createMock(Curl::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->curlFactoryMock->method('create')->willReturn($this->curlMock);

        // Default config for JWT token fetch
        $this->configMock->method('getSourceUrl')->willReturn('https://source.example.com');
        $this->configMock->method('getAdminUsername')->willReturn('api_user');
        $this->configMock->method('getAdminPassword')->willReturn('api_pass');

        $this->graphQlClient = new GraphQlClient(
            $this->configMock,
            $this->curlFactoryMock,
            $this->loggerMock
        );
    }

    public function testQuerySuccessfullyFetchesData(): void
    {
        $query = '{ customers { items { email } } }';

        // JWT token fetch returns 200
        $this->curlMock->method('getStatus')->willReturn(200);
        $this->curlMock->method('getBody')->willReturnOnConsecutiveCalls(
            json_encode('test-jwt-token'),
            json_encode(['data' => ['customers' => ['items' => []]]])
        );

        $result = $this->graphQlClient->query($query);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('customers', $result);
    }

    public function testQueryThrowsExceptionOnCurlFailure(): void
    {
        // JWT token fetch succeeds
        $this->curlMock->method('getStatus')->willReturn(200);
        $this->curlMock->method('getBody')->willReturn(json_encode('test-jwt-token'));

        $this->curlMock->method('post')
            ->willReturnCallback(function ($url) {
                if (str_contains($url, 'graphql')) {
                    throw new \Exception('Connection refused');
                }
            });

        $this->expectException(GraphQlClientException::class);

        $this->graphQlClient->query('{ test }');
    }

    public function testQueryThrowsExceptionOnGraphQlErrors(): void
    {
        $this->curlMock->method('getStatus')->willReturn(200);
        $this->curlMock->method('getBody')->willReturnOnConsecutiveCalls(
            json_encode('test-jwt-token'),
            json_encode([
                'errors' => [
                    ['message' => 'Field not found'],
                ],
            ])
        );

        $this->expectException(GraphQlClientException::class);

        $this->graphQlClient->query('{ invalidField }');
    }

    public function testTestConnectionReturnsTrueOnSuccess(): void
    {
        $this->curlMock->method('getStatus')->willReturn(200);
        $this->curlMock->method('getBody')->willReturnOnConsecutiveCalls(
            json_encode('test-jwt-token'),
            json_encode(['data' => ['magecloneMigrationMetadata' => ['customer_count' => 100]]])
        );

        $this->assertTrue($this->graphQlClient->testConnection());
    }

    public function testTestConnectionReturnsFalseOnException(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->configMock->method('getSourceUrl')->willReturn('https://source.example.com');
        $this->configMock->method('getAdminUsername')->willReturn(null);
        $this->configMock->method('getAdminPassword')->willReturn(null);

        $client = new GraphQlClient(
            $this->configMock,
            $this->curlFactoryMock,
            $this->loggerMock
        );

        $this->assertFalse($client->testConnection());
    }

    public function testThrowsExceptionWhenCredentialsMissing(): void
    {
        $configMock = $this->createMock(Config::class);
        $configMock->method('getSourceUrl')->willReturn('https://source.example.com');
        $configMock->method('getAdminUsername')->willReturn(null);
        $configMock->method('getAdminPassword')->willReturn(null);

        $client = new GraphQlClient(
            $configMock,
            $this->curlFactoryMock,
            $this->loggerMock
        );

        $this->expectException(GraphQlClientException::class);

        $client->query('{ test }');
    }
}
