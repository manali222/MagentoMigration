<?php
/**
 * MageClone MagentoMigrator GraphQL Client Interface
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Api;

use MageClone\MagentoMigrator\Exception\GraphQlClientException;

/**
 * Interface GraphQlClientInterface
 *
 * Client for communicating with the source Magento instance via GraphQL.
 */
interface GraphQlClientInterface
{
    /**
     * Execute a GraphQL query against the source Magento instance
     *
     * @param string $query The GraphQL query or mutation string
     * @param array $variables Optional variables for the query
     * @return array The decoded response data
     * @throws GraphQlClientException
     */
    public function query(string $query, array $variables = []): array;

    /**
     * Test the connection to the source Magento instance
     *
     * @return bool True if connection is successful
     * @throws GraphQlClientException
     */
    public function testConnection(): bool;
}
