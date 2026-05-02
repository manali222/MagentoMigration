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

/**
 * @covers \MageClone\MagentoMigrator\Model\GraphQlClient
 */
class GraphQlClientTest extends TestCase
{
    /**
     * @var Config&MockObject
     */
    private Config $configMock;

    /**
     * @var CurlFactory&MockObject
     */
    private CurlFactory $curlFactoryMock;

    /**
     * @var Curl&MockObject
     */
    private Curl $curlMock;

    /**
     * @var GraphQlClient
     */
    private GraphQlClient $graphQlClient;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->curlFactoryMock = $this->createMock(CurlFactory::class);
        $this->curlMock = $this->createMock(Curl::class);

        $this->curlFactoryMock->method('create')->willReturn($this->curlMock);

        $this->graphQlClient = new GraphQlClient(
            $this->configMock,
            $this->curlFactoryMock
        );
    }

    public function testQueryBuildsCorrectRequestWithHeadersAndUrl(): void
    {
        $sourceUrl = 'https://source.example.com';
        $token = 'test-bearer-token';
        $query = '{ customers { items { email } } }';

        $this->configMock->method('getSourceUrl')->willReturn($sourceUrl);
        $this->configMock->method('getApiToken')->willReturn($token);

        $this->curlMock->expects($this->exactly(2))
            ->method('addHeader')
            ->willReturnCallback(function (string $name, string $value) use ($token): void {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertSame('Content-Type', $name);
                    $this->assertSame('application/json', $value);
                } elseif ($callCount === 2) {
                    $this->assertSame('Authorization', $name);
                    $this->assertSame('Bearer ' . $token, $value);
                }
            });

        $this->curlMock->expects($this->once())
            ->method('post')
            ->with(
                $sourceUrl . '/graphql',
                $this->callback(function (string $body) use ($query): bool {
                    $decoded = json_decode($body, true);
                    return $decoded['query'] === $query;
                })
            );

        $this->curlMock->method('getStatus')->willReturn(200);
        $this->curlMock->method('getBody')->willReturn(
            json_encode(['data' => ['customers' => ['items' => []]]])
        );

        $result = $this->graphQlClient->query($query);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('customers', $result);
    }

    public function testQueryThrowsGraphQlClientExceptionOnHttpError(): void
    {
        $this->configMock->method('getSourceUrl')->willReturn('https://source.example.com');
        $this->configMock->method('getApiToken')->willReturn('token');

        $this->curlMock->method('getStatus')->willReturn(500);
        $this->curlMock->method('getBody')->willReturn('Internal Server Error');

        $this->expectException(GraphQlClientException::class);

        $this->graphQlClient->query('{ test }');
    }

    public function testQueryThrowsExceptionOnCurlFailure(): void
    {
        $this->configMock->method('getSourceUrl')->willReturn('https://source.example.com');
        $this->configMock->method('getApiToken')->willReturn('token');

        $this->curlMock->method('post')
            ->willThrowException(new \Exception('Connection refused'));

        $this->expectException(GraphQlClientException::class);

        $this->graphQlClient->query('{ test }');
    }

    public function testQueryThrowsExceptionOnGraphQlErrors(): void
    {
        $this->configMock->method('getSourceUrl')->willReturn('https://source.example.com');
        $this->configMock->method('getApiToken')->willReturn('token');

        $this->curlMock->method('getStatus')->willReturn(200);
        $this->curlMock->method('getBody')->willReturn(
            json_encode([
                'errors' => [
                    ['message' => 'Field not found'],
                ],
            ])
        );

        $this->expectException(GraphQlClientException::class);

        $this->graphQlClient->query('{ invalidField }');
    }

    public function testQueryThrowsExceptionWhenResponseMissingDataKey(): void
    {
        $this->configMock->method('getSourceUrl')->willReturn('https://source.example.com');
        $this->configMock->method('getApiToken')->willReturn('token');

        $this->curlMock->method('getStatus')->willReturn(200);
        $this->curlMock->method('getBody')->willReturn(json_encode(['something' => 'else']));

        $this->expectException(GraphQlClientException::class);

        $this->graphQlClient->query('{ test }');
    }

    public function testQueryThrowsExceptionOnInvalidJsonResponse(): void
    {
        $this->configMock->method('getSourceUrl')->willReturn('https://source.example.com');
        $this->configMock->method('getApiToken')->willReturn('token');

        $this->curlMock->method('getStatus')->willReturn(200);
        $this->curlMock->method('getBody')->willReturn('not valid json');

        $this->expectException(GraphQlClientException::class);

        $this->graphQlClient->query('{ test }');
    }

    public function testTestConnectionReturnsTrueOnSuccess(): void
    {
        $this->configMock->method('getSourceUrl')->willReturn('https://source.example.com');
        $this->configMock->method('getApiToken')->willReturn('token');

        $this->curlMock->method('getStatus')->willReturn(200);
        $this->curlMock->method('getBody')->willReturn(
            json_encode(['data' => ['magecloneMigrationMetadata' => ['customer_count' => 100]]])
        );

        $this->assertTrue($this->graphQlClient->testConnection());
    }

    public function testTestConnectionReturnsFalseOnException(): void
    {
        $this->configMock->method('getSourceUrl')->willReturn('https://source.example.com');
        $this->configMock->method('getApiToken')->willReturn('token');

        $this->curlMock->method('post')
            ->willThrowException(new \Exception('Connection refused'));

        $this->assertFalse($this->graphQlClient->testConnection());
    }
}
