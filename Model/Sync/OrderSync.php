<?php
/**
 * MageClone MagentoMigrator Order Sync
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Sync;

use MageClone\MagentoMigrator\Api\GraphQlClientInterface;
use MageClone\MagentoMigrator\Api\IdMappingRepositoryInterface;
use MageClone\MagentoMigrator\Api\SyncLogRepositoryInterface;
use MageClone\MagentoMigrator\Api\Data\SyncLogInterfaceFactory;
use MageClone\MagentoMigrator\Api\Data\IdMappingInterfaceFactory;
use MageClone\MagentoMigrator\Model\Mapper\OrderMapper;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Synchronizes order entities from source to destination.
 */
class OrderSync extends AbstractEntitySync
{
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var OrderFactory
     */
    private OrderFactory $orderFactory;

    /**
     * @var OrderMapper
     */
    private OrderMapper $orderMapper;

    /**
     * @param GraphQlClientInterface $graphQlClient
     * @param IdMappingRepositoryInterface $idMappingRepository
     * @param SyncLogRepositoryInterface $syncLogRepository
     * @param SyncLogInterfaceFactory $syncLogFactory
     * @param IdMappingInterfaceFactory $idMappingFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderFactory $orderFactory
     * @param OrderMapper $orderMapper
     */
    public function __construct(
        GraphQlClientInterface $graphQlClient,
        IdMappingRepositoryInterface $idMappingRepository,
        SyncLogRepositoryInterface $syncLogRepository,
        SyncLogInterfaceFactory $syncLogFactory,
        IdMappingInterfaceFactory $idMappingFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        OrderFactory $orderFactory,
        OrderMapper $orderMapper
    ) {
        parent::__construct(
            $graphQlClient,
            $idMappingRepository,
            $syncLogRepository,
            $syncLogFactory,
            $idMappingFactory,
            $searchCriteriaBuilder,
            $logger
        );

        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->orderMapper = $orderMapper;
    }

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
    public function getDependencies(): array
    {
        return ['customer', 'product'];
    }

    /**
     * @inheritDoc
     */
    protected function getGraphQlQuery(): string
    {
        return <<<'GRAPHQL'
query($pageSize: Int!, $currentPage: Int!, $updatedSince: String) {
    magecloneOrders(pageSize: $pageSize, currentPage: $currentPage, updatedSince: $updatedSince) {
        items {
            entity_id
            increment_id
            state
            status
            store_id
            customer_id
            customer_email
            grand_total
            subtotal
            tax_amount
            shipping_amount
            discount_amount
            total_qty_ordered
            currency_code
            order_currency_code
            shipping_method
            shipping_description
            customer_firstname
            customer_lastname
            created_at
            updated_at
            items {
                item_id
                sku
                name
                qty_ordered
                price
                row_total
                tax_amount
                discount_amount
                product_type
                weight
            }
            billing_address {
                entity_id
                firstname
                lastname
                street
                city
                region
                region_id
                postcode
                country_id
                telephone
                company
                address_type
            }
            shipping_address {
                entity_id
                firstname
                lastname
                street
                city
                region
                region_id
                postcode
                country_id
                telephone
                company
                address_type
            }
            payment {
                method
                additional_information
            }
        }
        total_count
        page_info {
            page_size
            current_page
            total_pages
        }
    }
}
GRAPHQL;
    }

    /**
     * @inheritDoc
     */
    protected function getResponseKey(): string
    {
        return 'magecloneOrders';
    }

    /**
     * @inheritDoc
     */
    protected function saveEntity(array $entityData): int
    {
        $idMappings = $this->resolveIdMappings(['customer', 'product']);
        $mapped = $this->orderMapper->mapToDestination($entityData, $idMappings);

        $incrementId = $mapped['increment_id'] ?? '';

        // Check if order already exists by increment_id (natural key)
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId)
            ->create();

        $existingOrders = $this->orderRepository->getList($searchCriteria);

        if ($existingOrders->getTotalCount() > 0) {
            // Orders typically should not be overwritten; skip and return existing ID
            $items = $existingOrders->getItems();
            $existing = reset($items);
            return (int) $existing->getEntityId();
        }

        // Create new order via factory
        $order = $this->orderFactory->create();
        $order->setIncrementId($mapped['increment_id']);
        $order->setState($mapped['state'] ?? '');
        $order->setStatus($mapped['status'] ?? '');
        $order->setStoreId($mapped['store_id'] ?? 1);
        $order->setCustomerId($mapped['customer_id']);
        $order->setCustomerEmail($mapped['customer_email'] ?? '');
        $order->setGrandTotal($mapped['grand_total'] ?? 0.0);
        $order->setSubtotal($mapped['subtotal'] ?? 0.0);
        $order->setTaxAmount($mapped['tax_amount'] ?? 0.0);
        $order->setShippingAmount($mapped['shipping_amount'] ?? 0.0);
        $order->setDiscountAmount($mapped['discount_amount'] ?? 0.0);
        $order->setTotalQtyOrdered($mapped['total_qty_ordered'] ?? 0.0);
        $order->setBaseCurrencyCode($mapped['currency_code'] ?? 'USD');
        $order->setOrderCurrencyCode($mapped['order_currency_code'] ?? 'USD');
        $order->setShippingMethod($mapped['shipping_method'] ?? '');
        $order->setShippingDescription($mapped['shipping_description'] ?? '');
        $order->setCustomerFirstname($mapped['customer_firstname'] ?? '');
        $order->setCustomerLastname($mapped['customer_lastname'] ?? '');

        // Set order items
        foreach ($mapped['items'] as $itemData) {
            $orderItem = $order->addItem(null);
            if ($orderItem === null) {
                continue;
            }
            $orderItem->setSku($itemData['sku'] ?? '');
            $orderItem->setName($itemData['name'] ?? '');
            $orderItem->setQtyOrdered($itemData['qty_ordered'] ?? 0.0);
            $orderItem->setPrice($itemData['price'] ?? 0.0);
            $orderItem->setRowTotal($itemData['row_total'] ?? 0.0);
            $orderItem->setTaxAmount($itemData['tax_amount'] ?? 0.0);
            $orderItem->setDiscountAmount($itemData['discount_amount'] ?? 0.0);
            $orderItem->setProductType($itemData['product_type'] ?? 'simple');
            $orderItem->setWeight($itemData['weight'] ?? null);
        }

        // Set billing address
        if (isset($mapped['billing_address'])) {
            $billingAddress = $order->getBillingAddress();
            if ($billingAddress !== null) {
                $this->applyAddressData($billingAddress, $mapped['billing_address']);
            }
        }

        // Set shipping address
        if (isset($mapped['shipping_address'])) {
            $shippingAddress = $order->getShippingAddress();
            if ($shippingAddress !== null) {
                $this->applyAddressData($shippingAddress, $mapped['shipping_address']);
            }
        }

        // Set payment info
        if (isset($mapped['payment'])) {
            $payment = $order->getPayment();
            if ($payment !== null) {
                $payment->setMethod($mapped['payment']['method'] ?? '');
                if (!empty($mapped['payment']['additional_information'])) {
                    $payment->setAdditionalInformation($mapped['payment']['additional_information']);
                }
            }
        }

        $saved = $this->orderRepository->save($order);

        return (int) $saved->getEntityId();
    }

    /**
     * Apply address data to an order address object
     *
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $address
     * @param array $data
     * @return void
     */
    private function applyAddressData($address, array $data): void
    {
        $address->setFirstname($data['firstname'] ?? '');
        $address->setLastname($data['lastname'] ?? '');
        $address->setStreet($data['street'] ?? '');
        $address->setCity($data['city'] ?? '');
        $address->setRegion($data['region'] ?? null);
        $address->setRegionId($data['region_id'] ?? null);
        $address->setPostcode($data['postcode'] ?? '');
        $address->setCountryId($data['country_id'] ?? '');
        $address->setTelephone($data['telephone'] ?? '');
        $address->setCompany($data['company'] ?? null);
        $address->setAddressType($data['address_type'] ?? '');
    }
}
