<?php
/**
 * MageClone MagentoMigrator GraphQL Client
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model;

use MageClone\MagentoMigrator\Api\GraphQlClientInterface;
use MageClone\MagentoMigrator\Exception\GraphQlClientException;
use Magento\Framework\HTTP\Client\CurlFactory;

/**
 * GraphQL client for communicating with the source Magento instance.
 */
class GraphQlClient implements GraphQlClientInterface
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var CurlFactory
     */
    private CurlFactory $curlFactory;

    /**
     * @param Config $config
     * @param CurlFactory $curlFactory
     */
    public function __construct(
        Config $config,
        CurlFactory $curlFactory
    ) {
        $this->config = $config;
        $this->curlFactory = $curlFactory;
    }

    /**
     * @inheritDoc
     */
    public function query(string $query, array $variables = []): array
    {
        $url = $this->config->getSourceUrl() . '/graphql';
        $body = json_encode([
            'query' => $query,
            'variables' => (object) $variables,
        ]);

        if ($body === false) {
            throw new GraphQlClientException(
                __('Failed to encode GraphQL request body.')
            );
        }

        $curl = $this->curlFactory->create();
        $curl->addHeader('Content-Type', 'application/json');
        $curl->addHeader('Authorization', 'Bearer ' . $this->config->getApiToken());
        $curl->setOption(CURLOPT_TIMEOUT, 120);

        try {
            $curl->post($url, $body);
        } catch (\Exception $e) {
            throw new GraphQlClientException(
                __('GraphQL request failed: %1', $e->getMessage()),
                $e
            );
        }

        $statusCode = $curl->getStatus();
        $responseBody = $curl->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new GraphQlClientException(
                __('GraphQL request returned HTTP status %1: %2', $statusCode, $responseBody)
            );
        }

        $decoded = json_decode($responseBody, true);

        if (!is_array($decoded)) {
            throw new GraphQlClientException(
                __('Failed to decode GraphQL response.')
            );
        }

        if (isset($decoded['errors']) && !empty($decoded['errors'])) {
            $messages = [];
            foreach ($decoded['errors'] as $error) {
                $messages[] = $error['message'] ?? 'Unknown GraphQL error';
            }
            throw new GraphQlClientException(
                __('GraphQL errors: %1', implode('; ', $messages))
            );
        }

        if (!isset($decoded['data'])) {
            throw new GraphQlClientException(
                __('GraphQL response missing "data" key.')
            );
        }

        return $decoded['data'];
    }

    /**
     * @inheritDoc
     */
    public function testConnection(): bool
    {
        try {
            $this->query('{ magecloneMigrationMetadata { customer_count } }');
            return true;
        } catch (GraphQlClientException $e) {
            return false;
        }
    }
}
