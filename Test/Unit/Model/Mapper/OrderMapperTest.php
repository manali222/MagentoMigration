<?php
/**
 * MageClone MagentoMigrator OrderMapper Unit Test
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Test\Unit\Model\Mapper;

use MageClone\MagentoMigrator\Model\Mapper\OrderMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageClone\MagentoMigrator\Model\Mapper\OrderMapper
 */
class OrderMapperTest extends TestCase
{
    /**
     * @var OrderMapper
     */
    private OrderMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new OrderMapper();
    }

    public function testMapToDestinationResolvesCustomerIdFromIdMappings(): void
    {
        $sourceData = [
            'increment_id' => '100000001',
            'customer_id' => 5,
            'customer_email' => 'john@example.com',
            'grand_total' => 99.99,
        ];

        $idMappings = [
            'customer' => [5 => 42],
        ];

        $result = $this->mapper->mapToDestination($sourceData, $idMappings);

        $this->assertSame(42, $result['customer_id']);
    }

    public function testMapToDestinationSetsCustomerIdNullWhenNoMapping(): void
    {
        $sourceData = [
            'increment_id' => '100000001',
            'customer_id' => 5,
            'customer_email' => 'john@example.com',
            'grand_total' => 99.99,
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertNull($result['customer_id']);
    }

    public function testMapToDestinationPreservesIncrementId(): void
    {
        $sourceData = [
            'increment_id' => '100000042',
            'grand_total' => 150.00,
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertSame('100000042', $result['increment_id']);
    }

    public function testMapToDestinationMapsOrderFields(): void
    {
        $sourceData = [
            'increment_id' => '100000001',
            'state' => 'complete',
            'status' => 'complete',
            'store_id' => 1,
            'customer_email' => 'test@example.com',
            'grand_total' => 100.00,
            'subtotal' => 90.00,
            'tax_amount' => 7.20,
            'shipping_amount' => 10.00,
            'discount_amount' => 5.00,
            'total_qty_ordered' => 3.0,
            'currency_code' => 'EUR',
            'order_currency_code' => 'EUR',
            'shipping_method' => 'flatrate_flatrate',
            'shipping_description' => 'Flat Rate',
            'customer_firstname' => 'John',
            'customer_lastname' => 'Doe',
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertSame('complete', $result['state']);
        $this->assertSame('complete', $result['status']);
        $this->assertSame(1, $result['store_id']);
        $this->assertSame('test@example.com', $result['customer_email']);
        $this->assertSame(100.00, $result['grand_total']);
        $this->assertSame(90.00, $result['subtotal']);
        $this->assertSame(7.20, $result['tax_amount']);
        $this->assertSame(10.00, $result['shipping_amount']);
        $this->assertSame(5.00, $result['discount_amount']);
        $this->assertSame(3.0, $result['total_qty_ordered']);
        $this->assertSame('EUR', $result['currency_code']);
        $this->assertSame('flatrate_flatrate', $result['shipping_method']);
        $this->assertSame('John', $result['customer_firstname']);
    }

    public function testMapToDestinationMapsItemsCorrectly(): void
    {
        $sourceData = [
            'increment_id' => '100000001',
            'items' => [
                [
                    'item_id' => 10,
                    'sku' => 'PROD-001',
                    'name' => 'Widget',
                    'qty_ordered' => 2.0,
                    'price' => 25.00,
                    'row_total' => 50.00,
                    'tax_amount' => 4.00,
                    'discount_amount' => 0.00,
                    'product_type' => 'simple',
                    'weight' => 0.5,
                ],
                [
                    'item_id' => 11,
                    'sku' => 'PROD-002',
                    'name' => 'Gadget',
                    'qty_ordered' => 1.0,
                    'price' => 50.00,
                    'row_total' => 50.00,
                    'tax_amount' => 4.00,
                    'discount_amount' => 5.00,
                    'product_type' => 'simple',
                    'weight' => 1.0,
                ],
            ],
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(2, $result['items']);

        $firstItem = $result['items'][0];
        $this->assertSame('PROD-001', $firstItem['sku']);
        $this->assertSame('Widget', $firstItem['name']);
        $this->assertSame(2.0, $firstItem['qty_ordered']);
        $this->assertSame(25.00, $firstItem['price']);
        $this->assertSame(50.00, $firstItem['row_total']);
        $this->assertSame('simple', $firstItem['product_type']);
        // item_id should not be carried over
        $this->assertArrayNotHasKey('item_id', $firstItem);

        $secondItem = $result['items'][1];
        $this->assertSame('PROD-002', $secondItem['sku']);
    }

    public function testMapToDestinationMapsEmptyItemsArray(): void
    {
        $sourceData = [
            'increment_id' => '100000001',
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertSame([], $result['items']);
    }

    public function testMapToDestinationMapsBillingAddress(): void
    {
        $sourceData = [
            'increment_id' => '100000001',
            'billing_address' => [
                'entity_id' => 200,
                'firstname' => 'John',
                'lastname' => 'Doe',
                'street' => '123 Main St',
                'city' => 'Springfield',
                'region' => 'Illinois',
                'region_id' => 23,
                'postcode' => '62701',
                'country_id' => 'US',
                'telephone' => '555-1234',
                'address_type' => 'billing',
            ],
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertArrayHasKey('billing_address', $result);
        $this->assertArrayNotHasKey('entity_id', $result['billing_address']);
        $this->assertSame('John', $result['billing_address']['firstname']);
        $this->assertSame('billing', $result['billing_address']['address_type']);
    }

    public function testMapToDestinationMapsPayment(): void
    {
        $sourceData = [
            'increment_id' => '100000001',
            'payment' => [
                'entity_id' => 300,
                'method' => 'checkmo',
                'additional_information' => ['order_id' => '12345'],
            ],
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertArrayHasKey('payment', $result);
        $this->assertSame('checkmo', $result['payment']['method']);
        $this->assertArrayNotHasKey('entity_id', $result['payment']);
    }

    public function testGetNaturalKeyFieldReturnsIncrementId(): void
    {
        $this->assertSame('increment_id', $this->mapper->getNaturalKeyField());
    }

    public function testGetEntityTypeReturnsOrder(): void
    {
        $this->assertSame('order', $this->mapper->getEntityType());
    }

    public function testMapToDestinationUsesDefaultValuesForMissingFields(): void
    {
        $sourceData = [
            'increment_id' => '100000001',
        ];

        $result = $this->mapper->mapToDestination($sourceData);

        $this->assertSame('', $result['state']);
        $this->assertSame('', $result['status']);
        $this->assertSame(1, $result['store_id']);
        $this->assertSame('', $result['customer_email']);
        $this->assertSame(0.0, $result['grand_total']);
        $this->assertSame('USD', $result['currency_code']);
    }
}
