<?php
/**
 * MageClone MagentoMigrator Sync Queue Message
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Queue;

use JsonSerializable;

/**
 * Class SyncMessage
 *
 * Data transfer object for sync queue messages.
 */
class SyncMessage implements JsonSerializable
{
    /**
     * @var string
     */
    private string $entityType = '';

    /**
     * @var int
     */
    private int $page = 1;

    /**
     * @var int
     */
    private int $pageSize = 50;

    /**
     * @var string
     */
    private string $batchId = '';

    /**
     * @var string|null
     */
    private ?string $updatedSince = null;

    /**
     * Get entity type
     *
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * Set entity type
     *
     * @param string $entityType
     * @return $this
     */
    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }

    /**
     * Get page number
     *
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Set page number
     *
     * @param int $page
     * @return $this
     */
    public function setPage(int $page): self
    {
        $this->page = $page;
        return $this;
    }

    /**
     * Get page size
     *
     * @return int
     */
    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Set page size
     *
     * @param int $pageSize
     * @return $this
     */
    public function setPageSize(int $pageSize): self
    {
        $this->pageSize = $pageSize;
        return $this;
    }

    /**
     * Get batch ID
     *
     * @return string
     */
    public function getBatchId(): string
    {
        return $this->batchId;
    }

    /**
     * Set batch ID
     *
     * @param string $batchId
     * @return $this
     */
    public function setBatchId(string $batchId): self
    {
        $this->batchId = $batchId;
        return $this;
    }

    /**
     * Get updated since filter
     *
     * @return string|null
     */
    public function getUpdatedSince(): ?string
    {
        return $this->updatedSince;
    }

    /**
     * Set updated since filter
     *
     * @param string|null $updatedSince
     * @return $this
     */
    public function setUpdatedSince(?string $updatedSince): self
    {
        $this->updatedSince = $updatedSince;
        return $this;
    }

    /**
     * Serialize to JSON
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'entity_type' => $this->entityType,
            'page' => $this->page,
            'page_size' => $this->pageSize,
            'batch_id' => $this->batchId,
            'updated_since' => $this->updatedSince,
        ];
    }

    /**
     * Create a SyncMessage instance from a JSON string
     *
     * @param string $json
     * @return self
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON provided for SyncMessage deserialization.');
        }

        $message = new self();
        $message->setEntityType((string) ($data['entity_type'] ?? ''));
        $message->setPage((int) ($data['page'] ?? 1));
        $message->setPageSize((int) ($data['page_size'] ?? 50));
        $message->setBatchId((string) ($data['batch_id'] ?? ''));
        $message->setUpdatedSince($data['updated_since'] ?? null);

        return $message;
    }
}
