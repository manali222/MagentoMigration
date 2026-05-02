<?php
/**
 * MageClone MagentoMigrator Customer Mapper
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Mapper;

/**
 * Maps source customer data to destination format.
 */
class CustomerMapper implements EntityMapperInterface
{
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
    public function getNaturalKeyField(): ?string
    {
        return 'email';
    }

    /**
     * @inheritDoc
     */
    public function mapToDestination(array $sourceData, array $idMappings = []): array
    {
        $mapped = [
            'email' => $sourceData['email'] ?? '',
            'firstname' => $sourceData['firstname'] ?? '',
            'lastname' => $sourceData['lastname'] ?? '',
            'group_id' => $sourceData['group_id'] ?? 1,
            'store_id' => $sourceData['store_id'] ?? 1,
            'website_id' => $sourceData['website_id'] ?? 1,
            'dob' => $sourceData['dob'] ?? null,
            'gender' => $sourceData['gender'] ?? null,
            'prefix' => $sourceData['prefix'] ?? null,
            'suffix' => $sourceData['suffix'] ?? null,
            'taxvat' => $sourceData['taxvat'] ?? null,
            'custom_attributes' => $sourceData['custom_attributes'] ?? [],
        ];

        if (isset($sourceData['addresses']) && is_array($sourceData['addresses'])) {
            $mapped['addresses'] = $this->mapAddresses($sourceData['addresses']);
        } else {
            $mapped['addresses'] = [];
        }

        return $mapped;
    }

    /**
     * Map customer addresses, removing source entity IDs
     *
     * @param array $addresses
     * @return array
     */
    private function mapAddresses(array $addresses): array
    {
        $mappedAddresses = [];

        foreach ($addresses as $address) {
            $mappedAddress = [
                'firstname' => $address['firstname'] ?? '',
                'lastname' => $address['lastname'] ?? '',
                'street' => $address['street'] ?? [],
                'city' => $address['city'] ?? '',
                'region' => $address['region'] ?? null,
                'region_id' => $address['region_id'] ?? null,
                'postcode' => $address['postcode'] ?? '',
                'country_id' => $address['country_id'] ?? '',
                'telephone' => $address['telephone'] ?? '',
                'company' => $address['company'] ?? null,
                'is_default_billing' => $address['is_default_billing'] ?? false,
                'is_default_shipping' => $address['is_default_shipping'] ?? false,
            ];

            $mappedAddresses[] = $mappedAddress;
        }

        return $mappedAddresses;
    }
}
