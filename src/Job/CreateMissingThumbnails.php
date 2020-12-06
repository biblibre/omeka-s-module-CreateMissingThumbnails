<?php

namespace CreateMissingThumbnails\Job;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\Media;
use Omeka\File\Store\Local as LocalStore;
use Omeka\Job\AbstractJob;

class CreateMissingThumbnails extends AbstractJob
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $em = $services->get('Omeka\EntityManager');
        $logger = $services->get('Omeka\Logger');

        $qb = $em->createQueryBuilder();
        $qb
            ->select('media.id')
            ->from('Omeka\Entity\Media', 'media')
            ->where('media.hasOriginal = true')
            ->andWhere('media.hasThumbnails = false')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('media.mediaType', $qb->expr()->literal('application/pdf')),
                $qb->expr()->like('media.mediaType', $qb->expr()->literal('image/%')),
                $qb->expr()->like('media.mediaType', $qb->expr()->literal('video/%'))
            ));

        // Prevent using too much memory on large databases
        $qb->setMaxResults(100000);

        $query = $qb->getQuery();
        $result = $query->getResult();
        $total = count($result);
        $logger->info(sprintf('Start processing %d media', $total));

        $i = 0;
        foreach ($result as $row) {
            if ($this->shouldStop()) {
                $logger->info('Job stopped');
                $em->flush();
                return;
            }

            $i++;

            $media = $em->find('Omeka\Entity\Media', $row['id']);

            $logPrefix = sprintf('[%d%%] ', ($i * 100 / $total));

            try {
                $hasThumbnails = $this->createThumbnails($media);
                if ($hasThumbnails) {
                    $logger->info($logPrefix . sprintf('Thumbnails created for media %d', $media->getId()));
                } else {
                    $logger->err($logPrefix . sprintf('Thumbnails creation failed for media %d: unknown reason', $media->getId()));
                }
            } catch (\Exception $e) {
                $logger->err($logPrefix . sprintf('Thumbnails creation failed for media %d: %s', $media->getId(), $e->getMessage()));
            }

            $em->flush();
            $em->detach($media);
        }

        $logger->info('Job completed');
    }

    protected function createThumbnails(Media $media)
    {
        $services = $this->getServiceLocator();
        $apiAdapters = $services->get('Omeka\ApiAdapterManager');
        $downloader = $services->get('Omeka\File\Downloader');
        $fileStore = $services->get('Omeka\File\Store');
        $logger = $services->get('Omeka\Logger');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');

        if (get_class($fileStore) === LocalStore::class) {
            $storagePath = sprintf('original/%s', $media->getFilename());
            $localPath = $fileStore->getLocalPath($storagePath);
            $tempFile = $tempFileFactory->build();
            if (false === copy($localPath, $tempFile->getTempPath())) {
                throw new \Exception(sprintf('Failed to copy %s to %s', $localPath, $tempFile->getTempPath()));
            }
        } else {
            $mediaRepresentation = new MediaRepresentation($media, $apiAdapters->get('media'));
            $originalUrl = $mediaRepresentation->originalUrl();
            $tempFile = $downloader->download($originalUrl);
        }

        $tempFile->setStorageId($media->getStorageId());
        $hasThumbnails = $tempFile->storeThumbnails();
        $media->setHasThumbnails($hasThumbnails);
        $tempFile->delete();

        return $hasThumbnails;
    }
}
