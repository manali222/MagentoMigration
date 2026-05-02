<?php
/**
 * MageClone MagentoMigrator Category Mapper
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Mapper;

/**
 * Maps source category data to destination format.
 *
 * Uses path-based matching rather than a single natural key field.
 */
class CategoryMapper implements EntityMapperInterface
{
    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return 'category';
    }

    /**
     * @inheritDoc
     */
    public function getNaturalKeyField(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function mapToDestination(array $sourceData, array $idMappings = []): array
    {
        $parentId = $sourceData['parent_id'] ?? null;
        if ($parentId !== null && isset($idMappings['category'][$parentId])) {
            $parentId = $idMappings['category'][$parentId];
        }

        $mapped = [
            'name' => $sourceData['name'] ?? '',
            'parent_id' => $parentId,
            'is_active' => $sourceData['is_active'] ?? true,
            'position' => $sourceData['position'] ?? 0,
            'level' => $sourceData['level'] ?? null,
            'url_key' => $sourceData['url_key'] ?? null,
            'description' => $sourceData['description'] ?? null,
            'meta_title' => $sourceData['meta_title'] ?? null,
            'meta_description' => $sourceData['meta_description'] ?? null,
            'include_in_menu' => $sourceData['include_in_menu'] ?? true,
            'custom_attributes' => $sourceData['custom_attributes'] ?? [],
        ];

        return $mapped;
    }
}
