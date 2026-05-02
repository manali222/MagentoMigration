<?php

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Resolver;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Customer\Model\ResourceModel\Address\CollectionFactory as AddressCollectionFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver for magecloneCustomers query.
 *
 * Returns paginated customer data with addresses and custom attributes.
 */
class Customers implements ResolverInterface
{
    /**
     * @var CustomerCollectionFactory
     */
    private CustomerCollectionFactory $customerCollectionFactory;

    /**
     * @var AddressCollectionFactory
     */
    private AddressCollectionFactory $addressCollectionFactory;

    /**
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param AddressCollectionFactory $addressCollectionFactory
     */
    public function __construct(
        CustomerCollectionFactory $customerCollectionFactory,
        AddressCollectionFactory $addressCollectionFactory
    ) {
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->addressCollectionFactory = $addressCollectionFactory;
    }

    /**
     * Resolve customers with addresses.
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

        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect('*');

        if ($updatedSince !== null) {
            $collection->addFieldToFilter('updated_at', ['gteq' => $updatedSince]);
        }

        $totalCount = $collection->getSize();
        $collection->setPageSize($pageSize);
        $collection->setCurPage($currentPage);

        $totalPages = $pageSize > 0 ? (int) ceil($totalCount / $pageSize) : 0;

        $items = [];
        foreach ($collection as $customer) {
            $customerData = [
                'entity_id' => (int) $customer->getId(),
                'email' => $customer->getEmail(),
                'firstname' => $customer->getFirstname(),
                'lastname' => $customer->getLastname(),
                'group_id' => (int) $customer->getGroupId(),
                'store_id' => (int) $customer->getStoreId(),
                'website_id' => (int) $customer->getWebsiteId(),
                'created_at' => $customer->getCreatedAt(),
                'updated_at' => $customer->getUpdatedAt(),
                'dob' => $customer->getDob(),
                'gender' => $customer->getGender() !== null ? (int) $customer->getGender() : null,
                'prefix' => $customer->getPrefix(),
                'suffix' => $customer->getSuffix(),
                'taxvat' => $customer->getTaxvat(),
                'addresses' => $this->getCustomerAddresses((int) $customer->getId()),
                'custom_attributes' => $this->getCustomAttributes($customer),
            ];
            $items[] = $customerData;
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
     * Load addresses for a given customer ID.
     *
     * @param int $customerId
     * @return array
     */
    private function getCustomerAddresses(int $customerId): array
    {
        $addressCollection = $this->addressCollectionFactory->create();
        $addressCollection->addFieldToFilter('parent_id', $customerId);
        $addressCollection->addAttributeToSelect('*');

        $addresses = [];
        foreach ($addressCollection as $address) {
            $street = $address->getStreet();
            if (is_string($street)) {
                $street = explode("\n", $street);
            }

            $addresses[] = [
                'entity_id' => (int) $address->getId(),
                'firstname' => $address->getFirstname(),
                'lastname' => $address->getLastname(),
                'street' => is_array($street) ? $street : [],
                'city' => $address->getCity(),
                'region' => $address->getRegion(),
                'region_id' => $address->getRegionId() !== null ? (int) $address->getRegionId() : null,
                'postcode' => $address->getPostcode(),
                'country_id' => $address->getCountryId(),
                'telephone' => $address->getTelephone(),
                'company' => $address->getCompany(),
                'is_default_billing' => (bool) $address->getData('is_default_billing'),
                'is_default_shipping' => (bool) $address->getData('is_default_shipping'),
            ];
        }

        return $addresses;
    }

    /**
     * Extract custom attributes from customer entity.
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @return array
     */
    private function getCustomAttributes($customer): array
    {
        $customAttributes = [];
        $skipAttributes = [
            'entity_id', 'email', 'firstname', 'lastname', 'group_id',
            'store_id', 'website_id', 'created_at', 'updated_at',
            'dob', 'gender', 'prefix', 'suffix', 'taxvat',
            'password_hash', 'rp_token', 'rp_token_created_at',
            'confirmation', 'created_in', 'default_billing', 'default_shipping',
            'disable_auto_group_change', 'failures_num', 'first_failure',
            'lock_expires', 'increment_id',
        ];

        $data = $customer->getData();
        foreach ($data as $key => $val) {
            if (in_array($key, $skipAttributes, true) || $val === null) {
                continue;
            }
            $customAttributes[] = [
                'attribute_code' => $key,
                'value' => is_string($val) ? $val : (string) $val,
            ];
        }

        return $customAttributes;
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
