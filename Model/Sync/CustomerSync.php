<?php
/**
 * MageClone MagentoMigrator Customer Sync
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
use MageClone\MagentoMigrator\Model\Mapper\CustomerMapper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Synchronizes customer entities from source to destination.
 */
class CustomerSync extends AbstractEntitySync
{
    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @var CustomerInterfaceFactory
     */
    private CustomerInterfaceFactory $customerFactory;

    /**
     * @var AddressInterfaceFactory
     */
    private AddressInterfaceFactory $addressFactory;

    /**
     * @var CustomerMapper
     */
    private CustomerMapper $customerMapper;

    /**
     * @param GraphQlClientInterface $graphQlClient
     * @param IdMappingRepositoryInterface $idMappingRepository
     * @param SyncLogRepositoryInterface $syncLogRepository
     * @param SyncLogInterfaceFactory $syncLogFactory
     * @param IdMappingInterfaceFactory $idMappingFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerInterfaceFactory $customerFactory
     * @param AddressInterfaceFactory $addressFactory
     * @param CustomerMapper $customerMapper
     */
    public function __construct(
        GraphQlClientInterface $graphQlClient,
        IdMappingRepositoryInterface $idMappingRepository,
        SyncLogRepositoryInterface $syncLogRepository,
        SyncLogInterfaceFactory $syncLogFactory,
        IdMappingInterfaceFactory $idMappingFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
        CustomerRepositoryInterface $customerRepository,
        CustomerInterfaceFactory $customerFactory,
        AddressInterfaceFactory $addressFactory,
        CustomerMapper $customerMapper
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

        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->addressFactory = $addressFactory;
        $this->customerMapper = $customerMapper;
    }

    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return 'customer';
    }

    /**
     * @inheritDoc
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    protected function getGraphQlQuery(): string
    {
        return <<<'GRAPHQL'
query($pageSize: Int!, $currentPage: Int!, $updatedSince: String) {
    magecloneCustomers(pageSize: $pageSize, currentPage: $currentPage, updatedSince: $updatedSince) {
        items {
            entity_id
            email
            firstname
            lastname
            group_id
            store_id
            website_id
            created_at
            updated_at
            dob
            gender
            prefix
            suffix
            taxvat
            addresses {
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
                is_default_billing
                is_default_shipping
            }
            custom_attributes {
                attribute_code
                value
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
        return 'magecloneCustomers';
    }

    /**
     * @inheritDoc
     */
    protected function saveEntity(array $entityData): int
    {
        $mapped = $this->customerMapper->mapToDestination($entityData);
        $email = $mapped['email'] ?? '';
        $websiteId = (int) ($mapped['website_id'] ?? 1);

        $existingCustomer = null;
        try {
            $existingCustomer = $this->customerRepository->get($email, $websiteId);
        } catch (NoSuchEntityException $e) {
            // Customer does not exist, will create new
        }

        if ($existingCustomer !== null) {
            $customer = $existingCustomer;
        } else {
            $customer = $this->customerFactory->create();
        }

        $customer->setEmail($email);
        $customer->setFirstname($mapped['firstname'] ?? '');
        $customer->setLastname($mapped['lastname'] ?? '');
        $customer->setGroupId($mapped['group_id'] ?? 1);
        $customer->setStoreId($mapped['store_id'] ?? 1);
        $customer->setWebsiteId($websiteId);
        $customer->setDob($mapped['dob'] ?? null);
        $customer->setGender($mapped['gender'] ?? null);
        $customer->setPrefix($mapped['prefix'] ?? null);
        $customer->setSuffix($mapped['suffix'] ?? null);
        $customer->setTaxvat($mapped['taxvat'] ?? null);

        // Map addresses
        $addresses = [];
        foreach ($mapped['addresses'] as $addressData) {
            $address = $this->addressFactory->create();
            $address->setFirstname($addressData['firstname'] ?? '');
            $address->setLastname($addressData['lastname'] ?? '');
            $address->setStreet($addressData['street'] ?? []);
            $address->setCity($addressData['city'] ?? '');
            $address->setRegion($addressData['region'] ?? null);
            $address->setRegionId($addressData['region_id'] ?? null);
            $address->setPostcode($addressData['postcode'] ?? '');
            $address->setCountryId($addressData['country_id'] ?? '');
            $address->setTelephone($addressData['telephone'] ?? '');
            $address->setCompany($addressData['company'] ?? null);
            $address->setIsDefaultBilling($addressData['is_default_billing'] ?? false);
            $address->setIsDefaultShipping($addressData['is_default_shipping'] ?? false);
            $addresses[] = $address;
        }

        $customer->setAddresses($addresses);

        // Set custom attributes
        foreach ($mapped['custom_attributes'] as $attr) {
            $customer->setCustomAttribute($attr['attribute_code'], $attr['value'] ?? null);
        }

        $saved = $this->customerRepository->save($customer);

        return (int) $saved->getId();
    }
}
