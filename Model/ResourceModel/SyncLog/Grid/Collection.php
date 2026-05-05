<?php
/**
 * MageClone MagentoMigrator Sync Log Grid Collection
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\ResourceModel\SyncLog\Grid;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use MageClone\MagentoMigrator\Model\ResourceModel\SyncLog\Collection as SyncLogCollection;
use Psr\Log\LoggerInterface;

class Collection extends SyncLogCollection implements SearchResultInterface
{
    /**
     * @var AggregationInterface
     */
    protected $aggregations;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        string $mainTable = 'mageclone_sync_log',
        string $resourceModel = \MageClone\MagentoMigrator\Model\ResourceModel\SyncLog::class,
        string $model = Document::class,
        AdapterInterface $connection = null,
        AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->_init($model, $resourceModel);
        $this->setMainTable($mainTable);
    }

    /**
     * @return AggregationInterface
     */
    public function getAggregations()
    {
        return $this->aggregations;
    }

    /**
     * @param AggregationInterface $aggregations
     * @return $this
     */
    public function setAggregations($aggregations)
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    /**
     * @return SearchCriteriaInterface|null
     */
    public function getSearchCriteria()
    {
        return null;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return $this
     */
    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria = null)
    {
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalCount()
    {
        return $this->getSize();
    }

    /**
     * @param int $totalCount
     * @return $this
     */
    public function setTotalCount($totalCount)
    {
        return $this;
    }

    /**
     * @param array|null $items
     * @return $this
     */
    public function setItems(array $items = null)
    {
        return $this;
    }
}
