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
            ->select('media')
            ->from('Omeka\Entity\Media', 'media')
            ->where('media.hasOriginal = true')
            ->andWhere('media.hasThumbnails = false')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('media.mediaType', $qb->expr()->literal('application/pdf')),
                $qb->expr()->like('media.mediaType', $qb->expr()->literal('image/%')),
                $qb->expr()->like('media.mediaType', $qb->expr()->literal('video/%'))
            ));

        $query = $qb->getQuery();
        foreach ($query->iterate() as $row) {
            if ($this->shouldStop()) {
                $logger->info('Job stopped');
                $em->flush();
                return;
            }

            $media = $row[0];

            try {
                $hasThumbnails = $this->createThumbnails($media);
                if ($hasThumbnails) {
                    $logger->info(sprintf('Thumbnails created for media %d', $media->getId()));
                } else {
                    $logger->err(sprintf('Thumbnails creation failed for media %d: unknown reason', $media->getId()));
                }
            } catch (\Exception $e) {
                $logger->err(sprintf('Thumbnails creation failed for media %d: %s', $media->getId(), $e->getMessage()));
            }

            $em->flush();
            $em->detach($media);
        }
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

        return $hasThumbnails;
    }
}
