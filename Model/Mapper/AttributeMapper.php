<?php
/**
 * MageClone MagentoMigrator Attribute Mapper
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Mapper;

/**
 * Maps source EAV attribute data to destination format.
 */
class AttributeMapper implements EntityMapperInterface
{
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
    public function getNaturalKeyField(): ?string
    {
        return 'attribute_code';
    }

    /**
     * @inheritDoc
     */
    public function mapToDestination(array $sourceData, array $idMappings = []): array
    {
        $mapped = [
            'attribute_code' => $sourceData['attribute_code'] ?? '',
            'frontend_input' => $sourceData['frontend_input'] ?? 'text',
            'frontend_label' => $sourceData['frontend_label'] ?? '',
            'is_required' => $sourceData['is_required'] ?? false,
            'is_user_defined' => $sourceData['is_user_defined'] ?? true,
            'default_value' => $sourceData['default_value'] ?? null,
            'entity_type_code' => $sourceData['entity_type_code'] ?? 'catalog_product',
        ];

        if (isset($sourceData['options']) && is_array($sourceData['options'])) {
            $mapped['options'] = $this->mapOptions($sourceData['options']);
        } else {
            $mapped['options'] = [];
        }

        return $mapped;
    }

    /**
     * Map attribute options
     *
     * @param array $options
     * @return array
     */
    private function mapOptions(array $options): array
    {
        $mappedOptions = [];

        foreach ($options as $option) {
            if (($option['value'] ?? '') === '') {
                continue;
            }
            $mappedOptions[] = [
                'value' => $option['value'] ?? '',
                'label' => $option['label'] ?? '',
            ];
        }

        return $mappedOptions;
    }
}
