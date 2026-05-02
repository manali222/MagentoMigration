<?php
/**
 * MageClone MagentoMigrator CMS Mapper
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Mapper;

/**
 * Maps source CMS page and block data to destination format.
 */
class CmsMapper implements EntityMapperInterface
{
    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return 'cms';
    }

    /**
     * @inheritDoc
     */
    public function getNaturalKeyField(): ?string
    {
        return 'identifier';
    }

    /**
     * @inheritDoc
     */
    public function mapToDestination(array $sourceData, array $idMappings = []): array
    {
        $mapped = [
            'identifier' => $sourceData['identifier'] ?? '',
            'title' => $sourceData['title'] ?? '',
            'content' => $sourceData['content'] ?? '',
            'is_active' => $sourceData['is_active'] ?? true,
            'store_ids' => $sourceData['store_ids'] ?? [0],
        ];

        // CMS page-specific fields
        if (isset($sourceData['page_id']) || isset($sourceData['content_heading'])) {
            $mapped['content_heading'] = $sourceData['content_heading'] ?? null;
            $mapped['page_layout'] = $sourceData['page_layout'] ?? null;
            $mapped['meta_title'] = $sourceData['meta_title'] ?? null;
            $mapped['meta_description'] = $sourceData['meta_description'] ?? null;
            $mapped['meta_keywords'] = $sourceData['meta_keywords'] ?? null;
            $mapped['sort_order'] = $sourceData['sort_order'] ?? 0;
        }

        return $mapped;
    }
}
