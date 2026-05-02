<?php
/**
 * MageClone MagentoMigrator CustomerMapper Unit Test
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Test\Unit\Model\Mapper;

use MageClone\MagentoMigrator\Model\Mapper\CustomerMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageClone\MagentoMigrator\Model\Mapper\CustomerMapper
 */
class CustomerMapperTest extends TestCase
{
    /**
     * @var CustomerMapper
     */
    private CustomerMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new CustomerMapper();
    }

    public function testMapToDestinationRemovesEntityId(): void
    {
        $sourceData = [
            'entity_id' => 42,
            'email' => 'test@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertArrayNotHasKey('entity_id', $result);
    }

    public function testMapToDestinationPreservesEmailAndNameFields(): void
    {
        $sourceData = [
            'entity_id' => 1,
            'email' => 'alice@example.com',
            'firstname' => 'Alice',
            'lastname' => 'Wonderland',
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertSame('alice@example.com', $result['email']);
        $this->assertSame('Alice', $result['firstname']);
        $this->assertSame('Wonderland', $result['lastname']);
    }

    public function testMapToDestinationPreservesOptionalFields(): void
    {
        $sourceData = [
            'entity_id' => 1,
            'email' => 'test@example.com',
            'firstname' => 'Test',
            'lastname' => 'User',
            'group_id' => 2,
            'store_id' => 3,
            'website_id' => 1,
            'dob' => '1990-01-15',
            'gender' => 1,
            'prefix' => 'Mr.',
            'suffix' => 'Jr.',
            'taxvat' => 'VAT123',
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertSame(2, $result['group_id']);
        $this->assertSame(3, $result['store_id']);
        $this->assertSame(1, $result['website_id']);
        $this->assertSame('1990-01-15', $result['dob']);
        $this->assertSame(1, $result['gender']);
        $this->assertSame('Mr.', $result['prefix']);
        $this->assertSame('Jr.', $result['suffix']);
        $this->assertSame('VAT123', $result['taxvat']);
    }

    public function testMapToDestinationMapsAddressesCorrectly(): void
    {
        $sourceData = [
            'entity_id' => 1,
            'email' => 'test@example.com',
            'firstname' => 'Test',
            'lastname' => 'User',
            'addresses' => [
                [
                    'entity_id' => 100,
                    'firstname' => 'Test',
                    'lastname' => 'User',
                    'street' => ['123 Main St'],
                    'city' => 'Springfield',
                    'region' => 'IL',
                    'region_id' => 23,
                    'postcode' => '62701',
                    'country_id' => 'US',
                    'telephone' => '555-1234',
                    'company' => 'Acme Inc',
                    'is_default_billing' => true,
                    'is_default_shipping' => false,
                ],
            ],
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertArrayHasKey('addresses', $result);
        $this->assertCount(1, $result['addresses']);

        $address = $result['addresses'][0];
        $this->assertArrayNotHasKey('entity_id', $address);
        $this->assertSame('Test', $address['firstname']);
        $this->assertSame('User', $address['lastname']);
        $this->assertSame(['123 Main St'], $address['street']);
        $this->assertSame('Springfield', $address['city']);
        $this->assertSame('62701', $address['postcode']);
        $this->assertSame('US', $address['country_id']);
        $this->assertSame('555-1234', $address['telephone']);
        $this->assertTrue($address['is_default_billing']);
        $this->assertFalse($address['is_default_shipping']);
    }

    public function testMapToDestinationHandlesMultipleAddresses(): void
    {
        $sourceData = [
            'entity_id' => 1,
            'email' => 'test@example.com',
            'firstname' => 'Test',
            'lastname' => 'User',
            'addresses' => [
                [
                    'entity_id' => 100,
                    'firstname' => 'Test',
                    'lastname' => 'User',
                    'street' => ['123 Main St'],
                    'city' => 'Springfield',
                    'postcode' => '62701',
                    'country_id' => 'US',
                    'telephone' => '555-1234',
                ],
                [
                    'entity_id' => 101,
                    'firstname' => 'Test',
                    'lastname' => 'User',
                    'street' => ['456 Oak Ave'],
                    'city' => 'Shelbyville',
                    'postcode' => '62702',
                    'country_id' => 'US',
                    'telephone' => '555-5678',
                ],
            ],
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertCount(2, $result['addresses']);
        $this->assertArrayNotHasKey('entity_id', $result['addresses'][0]);
        $this->assertArrayNotHasKey('entity_id', $result['addresses'][1]);
    }

    public function testMapToDestinationReturnsEmptyAddressesWhenNone(): void
    {
        $sourceData = [
            'entity_id' => 1,
            'email' => 'test@example.com',
            'firstname' => 'Test',
            'lastname' => 'User',
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertSame([], $result['addresses']);
    }

    public function testGetNaturalKeyFieldReturnsEmail(): void
    {
        $this->assertSame('email', $this->mapper->getNaturalKeyField());
    }

    public function testGetEntityTypeReturnsCustomer(): void
    {
        $this->assertSame('customer', $this->mapper->getEntityType());
    }

    public function testMapToDestinationUsesDefaultValuesForMissingFields(): void
    {
        $sourceData = [
            'email' => 'minimal@example.com',
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertSame('minimal@example.com', $result['email']);
        $this->assertSame('', $result['firstname']);
        $this->assertSame('', $result['lastname']);
        $this->assertSame(1, $result['group_id']);
        $this->assertSame(1, $result['store_id']);
        $this->assertSame(1, $result['website_id']);
    }
}
