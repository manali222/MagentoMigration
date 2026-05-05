<?php
/**
 * MageClone MagentoMigrator Config Unit Test
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Test\Unit\Model;

use MageClone\MagentoMigrator\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageClone\MagentoMigrator\Model\Config
 */
class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfigMock;
    private EncryptorInterface&MockObject $encryptorMock;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->encryptorMock = $this->createMock(EncryptorInterface::class);
        $this->config = new Config($this->scopeConfigMock, $this->encryptorMock);
    }

    public function testGetSourceUrlReturnsConfiguredValue(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->with('mageclone/general/source_url', ScopeInterface::SCOPE_STORE)
            ->willReturn('https://source.example.com');

        $this->assertSame('https://source.example.com', $this->config->getSourceUrl());
    }

    public function testGetSourceUrlReturnsNullWhenNotConfigured(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->with('mageclone/general/source_url', ScopeInterface::SCOPE_STORE)
            ->willReturn(null);

        $this->assertNull($this->config->getSourceUrl());
    }

    public function testGetAdminUsernameReturnsConfiguredValue(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->with('mageclone/general/admin_username', ScopeInterface::SCOPE_STORE)
            ->willReturn('mageclone_api');

        $this->assertSame('mageclone_api', $this->config->getAdminUsername());
    }

    public function testGetAdminPasswordDecryptsValue(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->with('mageclone/general/admin_password', ScopeInterface::SCOPE_STORE)
            ->willReturn('encrypted_value');

        $this->encryptorMock->method('decrypt')
            ->with('encrypted_value')
            ->willReturn('plain_password');

        $this->assertSame('plain_password', $this->config->getAdminPassword());
    }

    public function testGetAdminPasswordReturnsNullWhenNotConfigured(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->with('mageclone/general/admin_password', ScopeInterface::SCOPE_STORE)
            ->willReturn(null);

        $this->assertNull($this->config->getAdminPassword());
    }

    public function testGetBatchSizeReturnsConfiguredValue(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->with('mageclone/general/batch_size', ScopeInterface::SCOPE_STORE)
            ->willReturn('100');

        $this->assertSame(100, $this->config->getBatchSize());
    }

    public function testGetBatchSizeReturnsDefaultOf50WhenNotConfigured(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->with('mageclone/general/batch_size', ScopeInterface::SCOPE_STORE)
            ->willReturn(null);

        $this->assertSame(50, $this->config->getBatchSize());
    }

    public function testIsIncrementalEnabledReturnsTrueWhenEnabled(): void
    {
        $this->scopeConfigMock->method('isSetFlag')
            ->with('mageclone/general/incremental_enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $this->assertTrue($this->config->isIncrementalEnabled());
    }

    public function testIsIncrementalEnabledReturnsFalseWhenDisabled(): void
    {
        $this->scopeConfigMock->method('isSetFlag')
            ->with('mageclone/general/incremental_enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(false);

        $this->assertFalse($this->config->isIncrementalEnabled());
    }

    public function testGetCustomTableNamesReturnsCommaSeparatedValues(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->with('mageclone/general/custom_tables', ScopeInterface::SCOPE_STORE)
            ->willReturn('table_one, table_two, table_three');

        $result = $this->config->getCustomTableNames();

        $this->assertSame(['table_one', 'table_two', 'table_three'], $result);
    }

    public function testGetCustomTableNamesReturnsEmptyArrayWhenEmpty(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->with('mageclone/general/custom_tables', ScopeInterface::SCOPE_STORE)
            ->willReturn('');

        $this->assertSame([], $this->config->getCustomTableNames());
    }

    public function testGetEnabledEntityTypesReturnsCommaSeparatedValues(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->with('mageclone/general/enabled_entities', ScopeInterface::SCOPE_STORE)
            ->willReturn('customer, product, order');

        $result = $this->config->getEnabledEntityTypes();

        $this->assertSame(['customer', 'product', 'order'], $result);
    }

    public function testGetSourceMediaUrlReturnsConfiguredValue(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->with('mageclone/media/source_media_url', ScopeInterface::SCOPE_STORE)
            ->willReturn('https://source.example.com/media');

        $this->assertSame('https://source.example.com/media', $this->config->getSourceMediaUrl());
    }

    public function testIsMediaDownloadEnabledReturnsTrueWhenEnabled(): void
    {
        $this->scopeConfigMock->method('isSetFlag')
            ->with('mageclone/media/download_enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $this->assertTrue($this->config->isMediaDownloadEnabled());
    }
}
