<?php

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Resolver;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;

/**
 * Resolver for magecloneCustomTableData query.
 *
 * Returns raw data from a custom database table with pagination.
 * Table name is validated to allow only alphanumeric characters and underscores.
 */
class CustomTableData implements ResolverInterface
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var JsonSerializer
     */
    private JsonSerializer $jsonSerializer;

    /**
     * @param ResourceConnection $resourceConnection
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        JsonSerializer $jsonSerializer
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Resolve custom table data.
     *
     * @param Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        $this->authorize($context);

        $tableName = $args['tableName'] ?? '';
        $pageSize = (int) ($args['pageSize'] ?? 50);
        $currentPage = (int) ($args['currentPage'] ?? 1);

        $this->validateTableName($tableName);

        if ($pageSize < 1 || $pageSize > 300) {
            throw new GraphQlInputException(__('pageSize must be between 1 and 300.'));
        }
        if ($currentPage < 1) {
            throw new GraphQlInputException(__('currentPage must be 1 or greater.'));
        }

        $connection = $this->resourceConnection->getConnection();
        $fullTableName = $this->resourceConnection->getTableName($tableName);

        if (!$connection->isTableExists($fullTableName)) {
            throw new GraphQlInputException(__('Table "%1" does not exist.', $tableName));
        }

        // Get total count
        $countSelect = $connection->select()->from($fullTableName, ['COUNT(*)']);
        $totalCount = (int) $connection->fetchOne($countSelect);
        $totalPages = $pageSize > 0 ? (int) ceil($totalCount / $pageSize) : 0;

        // Get column names
        $columns = array_keys($connection->describeTable($fullTableName));

        // Get paginated data
        $offset = ($currentPage - 1) * $pageSize;
        $select = $connection->select()
            ->from($fullTableName)
            ->limit($pageSize, $offset);

        $rows = $connection->fetchAll($select);

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'data' => $this->jsonSerializer->serialize($row),
            ];
        }

        return [
            'items' => $items,
            'total_count' => $totalCount,
            'page_info' => [
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
            ],
            'columns' => $columns,
        ];
    }

    /**
     * Validate table name contains only safe characters.
     *
     * @param string $tableName
     * @return void
     * @throws GraphQlInputException
     */
    private function validateTableName(string $tableName): void
    {
        if (empty($tableName)) {
            throw new GraphQlInputException(__('Table name must not be empty.'));
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            throw new GraphQlInputException(
                __('Table name "%1" is invalid. Only alphanumeric characters and underscores are allowed.', $tableName)
            );
        }
    }

    /**
     * Verify the request is from an admin context, not a customer.
     *
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @return void
     * @throws GraphQlAuthorizationException
     */
    private function authorize($context): void
    {
        if ($context->getExtensionAttributes()->getIsCustomer() === true) {
            throw new GraphQlAuthorizationException(
                __('The current customer is not authorized to access this resource.')
            );
        }
    }
}
