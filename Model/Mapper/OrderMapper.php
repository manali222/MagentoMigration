<?php
/**
 * MageClone MagentoMigrator Order Mapper
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Mapper;

/**
 * Maps source order data to destination format.
 */
class OrderMapper implements EntityMapperInterface
{
    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return 'order';
    }

    /**
     * @inheritDoc
     */
    public function getNaturalKeyField(): ?string
    {
        return 'increment_id';
    }

    /**
     * @inheritDoc
     */
    public function mapToDestination(array $sourceData, array $idMappings = []): array
    {
        $customerId = null;
        if (isset($sourceData['customer_id']) && isset($idMappings['customer'][$sourceData['customer_id']])) {
            $customerId = $idMappings['customer'][$sourceData['customer_id']];
        }

        $mapped = [
            'increment_id' => $sourceData['increment_id'] ?? '',
            'state' => $sourceData['state'] ?? '',
            'status' => $sourceData['status'] ?? '',
            'store_id' => $sourceData['store_id'] ?? 1,
            'customer_id' => $customerId,
            'customer_email' => $sourceData['customer_email'] ?? '',
            'grand_total' => $sourceData['grand_total'] ?? 0.0,
            'subtotal' => $sourceData['subtotal'] ?? 0.0,
            'tax_amount' => $sourceData['tax_amount'] ?? 0.0,
            'shipping_amount' => $sourceData['shipping_amount'] ?? 0.0,
            'discount_amount' => $sourceData['discount_amount'] ?? 0.0,
            'total_qty_ordered' => $sourceData['total_qty_ordered'] ?? 0.0,
            'currency_code' => $sourceData['currency_code'] ?? 'USD',
            'order_currency_code' => $sourceData['order_currency_code'] ?? 'USD',
            'shipping_method' => $sourceData['shipping_method'] ?? '',
            'shipping_description' => $sourceData['shipping_description'] ?? '',
            'customer_firstname' => $sourceData['customer_firstname'] ?? '',
            'customer_lastname' => $sourceData['customer_lastname'] ?? '',
        ];

        if (isset($sourceData['items']) && is_array($sourceData['items'])) {
            $mapped['items'] = $this->mapItems($sourceData['items']);
        } else {
            $mapped['items'] = [];
        }

        if (isset($sourceData['billing_address']) && is_array($sourceData['billing_address'])) {
            $mapped['billing_address'] = $this->mapAddress($sourceData['billing_address']);
        }

        if (isset($sourceData['shipping_address']) && is_array($sourceData['shipping_address'])) {
            $mapped['shipping_address'] = $this->mapAddress($sourceData['shipping_address']);
        }

        if (isset($sourceData['payment']) && is_array($sourceData['payment'])) {
            $mapped['payment'] = $this->mapPayment($sourceData['payment']);
        }

        return $mapped;
    }

    /**
     * Map order items, resolving product references by SKU
     *
     * @param array $items
     * @return array
     */
    private function mapItems(array $items): array
    {
        $mappedItems = [];

        foreach ($items as $item) {
            $mappedItems[] = [
                'sku' => $item['sku'] ?? '',
                'name' => $item['name'] ?? '',
                'qty_ordered' => $item['qty_ordered'] ?? 0.0,
                'price' => $item['price'] ?? 0.0,
                'row_total' => $item['row_total'] ?? 0.0,
                'tax_amount' => $item['tax_amount'] ?? 0.0,
                'discount_amount' => $item['discount_amount'] ?? 0.0,
                'product_type' => $item['product_type'] ?? 'simple',
                'weight' => $item['weight'] ?? null,
            ];
        }

        return $mappedItems;
    }

    /**
     * Map order address, removing source entity ID
     *
     * @param array $address
     * @return array
     */
    private function mapAddress(array $address): array
    {
        return [
            'firstname' => $address['firstname'] ?? '',
            'lastname' => $address['lastname'] ?? '',
            'street' => $address['street'] ?? '',
            'city' => $address['city'] ?? '',
            'region' => $address['region'] ?? null,
            'region_id' => $address['region_id'] ?? null,
            'postcode' => $address['postcode'] ?? '',
            'country_id' => $address['country_id'] ?? '',
            'telephone' => $address['telephone'] ?? '',
            'company' => $address['company'] ?? null,
            'address_type' => $address['address_type'] ?? '',
        ];
    }

    /**
     * Map order payment information
     *
     * @param array $payment
     * @return array
     */
    private function mapPayment(array $payment): array
    {
        return [
            'method' => $payment['method'] ?? '',
            'additional_information' => $payment['additional_information'] ?? [],
        ];
    }
}
