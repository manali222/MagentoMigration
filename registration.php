<?php
/**
 * MageClone MagentoMigrator Module Registration
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'MageClone_MagentoMigrator',
    __DIR__
);
