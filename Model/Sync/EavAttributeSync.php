<?php
/**
 * MageClone MagentoMigrator EAV Attribute Sync
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Sync;

use MageClone\MagentoMigrator\Api\GraphQlClientInterface;
use MageClone\MagentoMigrator\Api\IdMappingRepositoryInterface;
use MageClone\MagentoMigrator\Api\SyncLogRepositoryInterface;
use MageClone\MagentoMigrator\Api\Data\SyncLogInterfaceFactory;
use MageClone\MagentoMigrator\Api\Data\IdMappingInterfaceFactory;
use MageClone\MagentoMigrator\Model\Mapper\AttributeMapper;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Synchronizes EAV attribute definitions from source to destination.
 */
class EavAttributeSync extends AbstractEntitySync
{
    /**
     * @var AttributeRepositoryInterface
     */
    private AttributeRepositoryInterface $attributeRepository;

    /**
     * @var AttributeFactory
     */
    private AttributeFactory $attributeFactory;

    /**
     * @var AttributeOptionInterfaceFactory
     */
    private AttributeOptionInterfaceFactory $optionFactory;

    /**
     * @var AttributeOptionLabelInterfaceFactory
     */
    private AttributeOptionLabelInterfaceFactory $optionLabelFactory;

    /**
     * @var AttributeMapper
     */
    private AttributeMapper $attributeMapper;

    /**
     * @param GraphQlClientInterface $graphQlClient
     * @param IdMappingRepositoryInterface $idMappingRepository
     * @param SyncLogRepositoryInterface $syncLogRepository
     * @param SyncLogInterfaceFactory $syncLogFactory
     * @param IdMappingInterfaceFactory $idMappingFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     * @param AttributeRepositoryInterface $attributeRepository
     * @param AttributeFactory $attributeFactory
     * @param AttributeOptionInterfaceFactory $optionFactory
     * @param AttributeOptionLabelInterfaceFactory $optionLabelFactory
     * @param AttributeMapper $attributeMapper
     */
    public function __construct(
        GraphQlClientInterface $graphQlClient,
        IdMappingRepositoryInterface $idMappingRepository,
        SyncLogRepositoryInterface $syncLogRepository,
        SyncLogInterfaceFactory $syncLogFactory,
        IdMappingInterfaceFactory $idMappingFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
        AttributeRepositoryInterface $attributeRepository,
        AttributeFactory $attributeFactory,
        AttributeOptionInterfaceFactory $optionFactory,
        AttributeOptionLabelInterfaceFactory $optionLabelFactory,
        AttributeMapper $attributeMapper
    ) {
        parent::__construct(
            $graphQlClient,
            $idMappingRepository,
            $syncLogRepository,
            $syncLogFactory,
            $idMappingFactory,
            $searchCriteriaBuilder,
            $logger
        );

        $this->attributeRepository = $attributeRepository;
        $this->attributeFactory = $attributeFactory;
        $this->optionFactory = $optionFactory;
        $this->optionLabelFactory = $optionLabelFactory;
        $this->attributeMapper = $attributeMapper;
    }

    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return 'eav_attribute';
    }

    /**
     * @inheritDoc
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     *
     * EAV attributes use a non-paginated query filtered by entity type code.
     */
    public function fetchPage(int $page, int $pageSize, ?string $updatedSince = null): array
    {
        $query = $this->getGraphQlQuery();
        $data = $this->graphQlClient->query($query, [
            'entityTypeCode' => 'catalog_product',
        ]);

        $responseKey = $this->getResponseKey();

        if (!isset($data[$responseKey]) || !is_array($data[$responseKey])) {
            return [];
        }

        // Apply manual pagination since the query returns all attributes
        $allItems = $data[$responseKey];
        $offset = ($page - 1) * $pageSize;

        return array_slice($allItems, $offset, $pageSize);
    }

    /**
     * @inheritDoc
     */
    public function getSourceCount(?string $updatedSince = null): int
    {
        $query = $this->getGraphQlQuery();
        $data = $this->graphQlClient->query($query, [
            'entityTypeCode' => 'catalog_product',
        ]);

        $responseKey = $this->getResponseKey();

        if (!isset($data[$responseKey]) || !is_array($data[$responseKey])) {
            return 0;
        }

        return count($data[$responseKey]);
    }

    /**
     * @inheritDoc
     */
    protected function getGraphQlQuery(): string
    {
        return <<<'GRAPHQL'
query($entityTypeCode: String!) {
    magecloneEavAttributes(entityTypeCode: $entityTypeCode) {
        attribute_id
        attribute_code
        frontend_input
        frontend_label
        is_required
        is_user_defined
        default_value
        entity_type_code
        options {
            value
            label
        }
    }
}
GRAPHQL;
    }

    /**
     * @inheritDoc
     */
    protected function getResponseKey(): string
    {
        return 'magecloneEavAttributes';
    }

    /**
     * @inheritDoc
     */
    protected function saveEntity(array $entityData): int
    {
        $mapped = $this->attributeMapper->mapToDestination($entityData);
        $attributeCode = $mapped['attribute_code'] ?? '';
        $entityTypeCode = $mapped['entity_type_code'] ?? 'catalog_product';

        // Check if attribute exists by code
        $existingAttribute = null;
        try {
            $existingAttribute = $this->attributeRepository->get($entityTypeCode, $attributeCode);
        } catch (NoSuchEntityException $e) {
            // Attribute does not exist, will create new
        }

        if ($existingAttribute !== null) {
            $attribute = $existingAttribute;
        } else {
            $attribute = $this->attributeFactory->create();
            $attribute->setEntityTypeId(
                $this->getEntityTypeId($entityTypeCode)
            );
            $attribute->setAttributeCode($attributeCode);
        }

        $attribute->setFrontendInput($mapped['frontend_input'] ?? 'text');
        $attribute->setDefaultFrontendLabel($mapped['frontend_label'] ?? $attributeCode);
        $attribute->setIsRequired($mapped['is_required'] ?? false);
        $attribute->setIsUserDefined($mapped['is_user_defined'] ?? true);

        if ($mapped['default_value'] !== null) {
            $attribute->setDefaultValue($mapped['default_value']);
        }

        // Set attribute options
        if (!empty($mapped['options'])) {
            $options = [];
            foreach ($mapped['options'] as $optionData) {
                $option = $this->optionFactory->create();
                $label = $this->optionLabelFactory->create();
                $label->setStoreId(0);
                $label->setLabel($optionData['label'] ?? '');
                $option->setLabel($optionData['label'] ?? '');
                $option->setStoreLabels([$label]);
                $option->setValue($optionData['value'] ?? '');
                $options[] = $option;
            }
            $attribute->setOptions($options);
        }

        $saved = $this->attributeRepository->save($attribute);

        return (int) $saved->getAttributeId();
    }

    /**
     * Get the entity type ID for a given entity type code
     *
     * @param string $entityTypeCode
     * @return int
     */
    private function getEntityTypeId(string $entityTypeCode): int
    {
        $entityTypeMap = [
            'catalog_product' => 4,
            'catalog_category' => 3,
            'customer' => 1,
            'customer_address' => 2,
        ];

        return $entityTypeMap[$entityTypeCode] ?? 4;
    }
}
