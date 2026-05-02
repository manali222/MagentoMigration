<?php
/**
 * MageClone MagentoMigrator Media Transfer
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\HTTP\Client\CurlFactory;
use Psr\Log\LoggerInterface;

/**
 * Handles downloading product media files from the source to the destination instance.
 */
class MediaTransfer
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var CurlFactory
     */
    private CurlFactory $curlFactory;

    /**
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    /**
     * @var File
     */
    private File $fileDriver;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Config $config
     * @param CurlFactory $curlFactory
     * @param DirectoryList $directoryList
     * @param File $fileDriver
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        CurlFactory $curlFactory,
        DirectoryList $directoryList,
        File $fileDriver,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->curlFactory = $curlFactory;
        $this->directoryList = $directoryList;
        $this->fileDriver = $fileDriver;
        $this->logger = $logger;
    }

    /**
     * Transfer a single media file from the source to the destination
     *
     * @param string $relativePath The relative path of the media file (e.g., /m/y/myimage.jpg)
     * @return bool True on success, false on failure
     */
    public function transferMediaFile(string $relativePath): bool
    {
        $sourceMediaUrl = $this->config->getSourceMediaUrl();

        if ($sourceMediaUrl === null || $sourceMediaUrl === '') {
            $this->logger->error('MageClone MediaTransfer: Source media URL is not configured.');
            return false;
        }

        $sourceUrl = rtrim($sourceMediaUrl, '/') . '/catalog/product' . $relativePath;

        try {
            // Download the file from source
            $curl = $this->curlFactory->create();
            $curl->setOption(CURLOPT_TIMEOUT, 60);
            $curl->setOption(CURLOPT_FOLLOWLOCATION, true);
            $curl->get($sourceUrl);

            $statusCode = $curl->getStatus();

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->warning(
                    sprintf(
                        'MageClone MediaTransfer: Failed to download %s (HTTP %d)',
                        $sourceUrl,
                        $statusCode
                    )
                );
                return false;
            }

            $fileContent = $curl->getBody();

            if (empty($fileContent)) {
                $this->logger->warning(
                    sprintf('MageClone MediaTransfer: Empty response for %s', $sourceUrl)
                );
                return false;
            }

            // Determine destination path
            $mediaDir = $this->directoryList->getPath(DirectoryList::MEDIA);
            $destinationPath = $mediaDir . '/catalog/product' . $relativePath;
            $destinationDir = dirname($destinationPath);

            // Create directory if it does not exist
            if (!$this->fileDriver->isExists($destinationDir)) {
                $this->fileDriver->createDirectory($destinationDir, 0775);
            }

            // Write file to destination
            $this->fileDriver->filePutContents($destinationPath, $fileContent);

            $this->logger->info(
                sprintf('MageClone MediaTransfer: Successfully transferred %s', $relativePath)
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    'MageClone MediaTransfer: Exception transferring %s: %s',
                    $relativePath,
                    $e->getMessage()
                )
            );
            return false;
        }
    }

    /**
     * Transfer multiple media files from source to destination
     *
     * @param string[] $paths Array of relative file paths
     * @return array Associative array of [relativePath => bool success]
     */
    public function transferBatch(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            $results[$path] = $this->transferMediaFile($path);
        }

        return $results;
    }
}
