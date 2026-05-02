<?php
/**
 * MageClone MagentoMigrator GraphQL Client Exception
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * Exception thrown when GraphQL client operations fail.
 */
class GraphQlClientException extends LocalizedException
{
}
