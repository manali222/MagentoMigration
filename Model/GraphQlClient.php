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
use Psr\Log\LoggerInterface;

class GraphQlClient implements GraphQlClientInterface
{
    private Config $config;
    private CurlFactory $curlFactory;
    private LoggerInterface $logger;
    private ?string $jwtToken = null;
    private ?int $tokenExpiresAt = null;

    public function __construct(
        Config $config,
        CurlFactory $curlFactory,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->curlFactory = $curlFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function query(string $query, array $variables = []): array
    {
        $token = $this->getJwtToken();
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
        $curl->addHeader('Authorization', 'Bearer ' . $token);
        $curl->setOption(CURLOPT_TIMEOUT, 120);
        $curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $curl->setOption(CURLOPT_SSL_VERIFYHOST, false);

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

        // If 401, token may have expired - retry once with fresh token
        if ($statusCode === 401) {
            $this->jwtToken = null;
            $this->tokenExpiresAt = null;
            $token = $this->getJwtToken();

            $curl = $this->curlFactory->create();
            $curl->addHeader('Content-Type', 'application/json');
            $curl->addHeader('Authorization', 'Bearer ' . $token);
            $curl->setOption(CURLOPT_TIMEOUT, 120);
            $curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $curl->setOption(CURLOPT_SSL_VERIFYHOST, false);
            $curl->post($url, $body);

            $statusCode = $curl->getStatus();
            $responseBody = $curl->getBody();
        }

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
            $this->logger->debug('MageClone connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a valid JWT token, fetching a new one if needed
     */
    private function getJwtToken(): string
    {
        // Return cached token if still valid (with 5-minute buffer)
        if ($this->jwtToken !== null && $this->tokenExpiresAt !== null
            && time() < ($this->tokenExpiresAt - 300)) {
            return $this->jwtToken;
        }

        $sourceUrl = $this->config->getSourceUrl();
        $username = $this->config->getAdminUsername();
        $password = $this->config->getAdminPassword();

        if (empty($sourceUrl) || empty($username) || empty($password)) {
            throw new GraphQlClientException(
                __('Source URL, admin username, and admin password must be configured.')
            );
        }

        $tokenUrl = rtrim($sourceUrl, '/') . '/rest/V1/integration/admin/token';

        $curl = $this->curlFactory->create();
        $curl->addHeader('Content-Type', 'application/json');
        $curl->setOption(CURLOPT_TIMEOUT, 30);
        $curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $curl->setOption(CURLOPT_SSL_VERIFYHOST, false);

        $body = json_encode([
            'username' => $username,
            'password' => $password,
        ]);

        try {
            $curl->post($tokenUrl, $body);
        } catch (\Exception $e) {
            throw new GraphQlClientException(
                __('Failed to obtain JWT token: %1', $e->getMessage()),
                $e
            );
        }

        $statusCode = $curl->getStatus();
        $responseBody = $curl->getBody();

        if ($statusCode !== 200) {
            throw new GraphQlClientException(
                __('Failed to obtain JWT token (HTTP %1): %2', $statusCode, $responseBody)
            );
        }

        $token = json_decode($responseBody, true);
        if (!is_string($token)) {
            throw new GraphQlClientException(
                __('Invalid JWT token response from source.')
            );
        }

        $this->jwtToken = $token;

        // Parse JWT expiry from payload
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode($parts[1]), true);
            $this->tokenExpiresAt = $payload['exp'] ?? (time() + 3600);
        } else {
            $this->tokenExpiresAt = time() + 3600;
        }

        return $this->jwtToken;
    }
}
