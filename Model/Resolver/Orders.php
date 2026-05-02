<?php

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Resolver;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver for magecloneOrders query.
 *
 * Returns paginated order data with items, addresses, and payment information.
 */
class Orders implements ResolverInterface
{
    /**
     * @var OrderCollectionFactory
     */
    private OrderCollectionFactory $orderCollectionFactory;

    /**
     * @param OrderCollectionFactory $orderCollectionFactory
     */
    public function __construct(
        OrderCollectionFactory $orderCollectionFactory
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * Resolve orders with items, addresses, and payment.
     *
     * @param Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        $this->authorize($context);

        $pageSize = (int) ($args['pageSize'] ?? 50);
        $currentPage = (int) ($args['currentPage'] ?? 1);
        $updatedSince = $args['updatedSince'] ?? null;

        if ($pageSize < 1 || $pageSize > 300) {
            throw new GraphQlInputException(__('pageSize must be between 1 and 300.'));
        }
        if ($currentPage < 1) {
            throw new GraphQlInputException(__('currentPage must be 1 or greater.'));
        }

        $collection = $this->orderCollectionFactory->create();

        if ($updatedSince !== null) {
            $collection->addFieldToFilter('updated_at', ['gteq' => $updatedSince]);
        }

        $totalCount = $collection->getSize();
        $collection->setPageSize($pageSize);
        $collection->setCurPage($currentPage);

        $totalPages = $pageSize > 0 ? (int) ceil($totalCount / $pageSize) : 0;

        $items = [];
        foreach ($collection as $order) {
            $items[] = [
                'entity_id' => (int) $order->getEntityId(),
                'increment_id' => $order->getIncrementId(),
                'state' => $order->getState(),
                'status' => $order->getStatus(),
                'store_id' => (int) $order->getStoreId(),
                'customer_id' => $order->getCustomerId() !== null ? (int) $order->getCustomerId() : null,
                'customer_email' => $order->getCustomerEmail(),
                'grand_total' => (float) $order->getGrandTotal(),
                'subtotal' => (float) $order->getSubtotal(),
                'tax_amount' => (float) $order->getTaxAmount(),
                'shipping_amount' => (float) $order->getShippingAmount(),
                'discount_amount' => (float) $order->getDiscountAmount(),
                'total_qty_ordered' => (float) $order->getTotalQtyOrdered(),
                'currency_code' => $order->getBaseCurrencyCode(),
                'order_currency_code' => $order->getOrderCurrencyCode(),
                'shipping_method' => $order->getShippingMethod(),
                'shipping_description' => $order->getShippingDescription(),
                'customer_firstname' => $order->getCustomerFirstname(),
                'customer_lastname' => $order->getCustomerLastname(),
                'created_at' => $order->getCreatedAt(),
                'updated_at' => $order->getUpdatedAt(),
                'items' => $this->getOrderItems($order),
                'billing_address' => $this->mapAddress($order->getBillingAddress()),
                'shipping_address' => $this->mapAddress($order->getShippingAddress()),
                'payment' => $this->getPaymentInfo($order),
            ];
        }

        return [
            'items' => $items,
            'total_count' => $totalCount,
            'page_info' => [
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Get order line items.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    private function getOrderItems($order): array
    {
        $items = [];
        foreach ($order->getItemsCollection() as $item) {
            $items[] = [
                'item_id' => (int) $item->getItemId(),
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'qty_ordered' => (float) $item->getQtyOrdered(),
                'price' => (float) $item->getPrice(),
                'row_total' => (float) $item->getRowTotal(),
                'tax_amount' => (float) $item->getTaxAmount(),
                'discount_amount' => (float) $item->getDiscountAmount(),
                'product_type' => $item->getProductType(),
                'weight' => $item->getWeight() !== null ? (float) $item->getWeight() : null,
            ];
        }

        return $items;
    }

    /**
     * Map order address to array.
     *
     * @param \Magento\Sales\Model\Order\Address|null $address
     * @return array|null
     */
    private function mapAddress($address): ?array
    {
        if ($address === null || $address === false) {
            return null;
        }

        $street = $address->getStreet();
        $streetString = is_array($street) ? implode("\n", $street) : (string) $street;

        return [
            'entity_id' => (int) $address->getEntityId(),
            'firstname' => $address->getFirstname(),
            'lastname' => $address->getLastname(),
            'street' => $streetString,
            'city' => $address->getCity(),
            'region' => $address->getRegion(),
            'region_id' => $address->getRegionId() !== null ? (int) $address->getRegionId() : null,
            'postcode' => $address->getPostcode(),
            'country_id' => $address->getCountryId(),
            'telephone' => $address->getTelephone(),
            'company' => $address->getCompany(),
            'address_type' => $address->getAddressType(),
        ];
    }

    /**
     * Get order payment information.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array|null
     */
    private function getPaymentInfo($order): ?array
    {
        $payment = $order->getPayment();
        if ($payment === null) {
            return null;
        }

        $additionalInfo = $payment->getAdditionalInformation();
        $additionalInfoArray = [];
        if (is_array($additionalInfo)) {
            foreach ($additionalInfo as $key => $val) {
                $additionalInfoArray[] = $key . ': ' . (is_string($val) ? $val : json_encode($val));
            }
        }

        return [
            'method' => $payment->getMethod(),
            'additional_information' => $additionalInfoArray,
        ];
    }

    /**
     * Verify the request is from an admin context, not a customer.
     *
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @return void
     * @throws GraphQlAuthorizationException
     */
    private function authorize($context): void
    {
        if ($context->getExtensionAttributes()->getIsCustomer() === true) {
            throw new GraphQlAuthorizationException(
                __('The current customer is not authorized to access this resource.')
            );
        }
    }
}
