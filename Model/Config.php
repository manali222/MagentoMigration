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
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_SOURCE_URL = 'mageclone/general/source_url';
    private const XML_PATH_ADMIN_USERNAME = 'mageclone/general/admin_username';
    private const XML_PATH_ADMIN_PASSWORD = 'mageclone/general/admin_password';
    private const XML_PATH_BATCH_SIZE = 'mageclone/general/batch_size';
    private const XML_PATH_INCREMENTAL_ENABLED = 'mageclone/general/incremental_enabled';
    private const XML_PATH_CUSTOM_TABLES = 'mageclone/general/custom_tables';
    private const XML_PATH_ENABLED_ENTITIES = 'mageclone/general/enabled_entities';
    private const XML_PATH_MEDIA_DOWNLOAD_ENABLED = 'mageclone/media/download_enabled';
    private const XML_PATH_SOURCE_MEDIA_URL = 'mageclone/media/source_media_url';

    private const DEFAULT_BATCH_SIZE = 50;

    private ScopeConfigInterface $scopeConfig;
    private EncryptorInterface $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    public function getSourceUrl(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOURCE_URL,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getAdminUsername(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_ADMIN_USERNAME,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getAdminPassword(): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_ADMIN_PASSWORD,
            ScopeInterface::SCOPE_STORE
        );

        if ($value !== null) {
            return $this->encryptor->decrypt($value);
        }

        return null;
    }

    public function getBatchSize(): int
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_BATCH_SIZE,
            ScopeInterface::SCOPE_STORE
        );

        return $value !== null ? (int) $value : self::DEFAULT_BATCH_SIZE;
    }

    public function isIncrementalEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_INCREMENTAL_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

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

    public function isMediaDownloadEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_MEDIA_DOWNLOAD_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getSourceMediaUrl(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOURCE_MEDIA_URL,
            ScopeInterface::SCOPE_STORE
        );
    }
}
