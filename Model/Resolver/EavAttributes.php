<?php

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Resolver;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver for magecloneEavAttributes query.
 *
 * Returns EAV attributes and their options for a given entity type.
 */
class EavAttributes implements ResolverInterface
{
    /**
     * @var AttributeCollectionFactory
     */
    private AttributeCollectionFactory $attributeCollectionFactory;

    /**
     * @var EavConfig
     */
    private EavConfig $eavConfig;

    /**
     * @param AttributeCollectionFactory $attributeCollectionFactory
     * @param EavConfig $eavConfig
     */
    public function __construct(
        AttributeCollectionFactory $attributeCollectionFactory,
        EavConfig $eavConfig
    ) {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->eavConfig = $eavConfig;
    }

    /**
     * Resolve EAV attributes for a given entity type.
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

        $entityTypeCode = $args['entityTypeCode'] ?? '';
        if (empty($entityTypeCode)) {
            throw new GraphQlInputException(__('The "entityTypeCode" argument is required.'));
        }

        try {
            $entityType = $this->eavConfig->getEntityType($entityTypeCode);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new GraphQlInputException(
                __('Invalid entity type code "%1".', $entityTypeCode)
            );
        }

        $collection = $this->attributeCollectionFactory->create();
        $collection->setEntityTypeFilter($entityType->getEntityTypeId());
        $collection->addSetInfo();

        $result = [];
        foreach ($collection as $attribute) {
            $options = [];
            $frontendInput = $attribute->getFrontendInput();

            if (in_array($frontendInput, ['select', 'multiselect'], true)) {
                try {
                    $attributeModel = $this->eavConfig->getAttribute(
                        $entityTypeCode,
                        $attribute->getAttributeCode()
                    );
                    $source = $attributeModel->getSource();
                    if ($source !== null) {
                        $allOptions = $source->getAllOptions(false);
                        foreach ($allOptions as $option) {
                            if ($option['value'] === '' || $option['value'] === null) {
                                continue;
                            }
                            $options[] = [
                                'value' => (string) $option['value'],
                                'label' => (string) $option['label'],
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    // Skip options if source model fails
                }
            }

            $result[] = [
                'attribute_id' => (int) $attribute->getAttributeId(),
                'attribute_code' => $attribute->getAttributeCode(),
                'frontend_input' => $frontendInput,
                'frontend_label' => $attribute->getFrontendLabel(),
                'is_required' => (bool) $attribute->getIsRequired(),
                'is_user_defined' => (bool) $attribute->getIsUserDefined(),
                'default_value' => $attribute->getDefaultValue(),
                'entity_type_code' => $entityTypeCode,
                'options' => $options,
            ];
        }

        return $result;
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
