<?php
/**
 * MageClone MagentoMigrator Configuration Model
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Config
 *
 * Provides access to module system configuration values.
 */
class Config
{
    /**
     * Configuration path constants
     */
    private const XML_PATH_SOURCE_URL = 'mageclone/general/source_url';
    private const XML_PATH_API_TOKEN = 'mageclone/general/api_token';
    private const XML_PATH_BATCH_SIZE = 'mageclone/general/batch_size';
    private const XML_PATH_INCREMENTAL_ENABLED = 'mageclone/general/incremental_enabled';
    private const XML_PATH_CUSTOM_TABLES = 'mageclone/general/custom_tables';
    private const XML_PATH_ENABLED_ENTITIES = 'mageclone/general/enabled_entities';
    private const XML_PATH_MEDIA_DOWNLOAD_ENABLED = 'mageclone/media/download_enabled';
    private const XML_PATH_SOURCE_MEDIA_URL = 'mageclone/media/source_media_url';

    /**
     * Default batch size
     */
    private const DEFAULT_BATCH_SIZE = 50;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get source Magento instance URL
     *
     * @return string|null
     */
    public function getSourceUrl(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOURCE_URL,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get API authentication token
     *
     * @return string|null
     */
    public function getApiToken(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_API_TOKEN,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get batch size for sync operations
     *
     * @return int
     */
    public function getBatchSize(): int
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_BATCH_SIZE,
            ScopeInterface::SCOPE_STORE
        );

        return $value !== null ? (int) $value : self::DEFAULT_BATCH_SIZE;
    }

    /**
     * Check if incremental sync is enabled
     *
     * @return bool
     */
    public function isIncrementalEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_INCREMENTAL_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get custom table names to sync
     *
     * @return array
     */
    public function getCustomTableNames(): array
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_CUSTOM_TABLES,
            ScopeInterface::SCOPE_STORE
        );

        if (empty($value)) {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }

    /**
     * Get enabled entity types for sync
     *
     * @return array
     */
    public function getEnabledEntityTypes(): array
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_ENABLED_ENTITIES,
            ScopeInterface::SCOPE_STORE
        );

        if (empty($value)) {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }

    /**
     * Check if media download is enabled
     *
     * @return bool
     */
    public function isMediaDownloadEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_MEDIA_DOWNLOAD_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get source media URL
     *
     * @return string|null
     */
    public function getSourceMediaUrl(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOURCE_MEDIA_URL,
            ScopeInterface::SCOPE_STORE
        );
    }
}
