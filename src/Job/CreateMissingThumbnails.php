<?php

namespace CreateMissingThumbnails\Job;

use Omeka\Api\Adapter\MediaAdapter;
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
        $api = $services->get('Omeka\ApiManager');
        $sharedEventManager = $this->getServiceLocator()->get('SharedEventManager');

        $sharedEventManager->attach(MediaAdapter::class, 'api.search.query', function ($event) {
            $qb = $event->getParam('queryBuilder');

            $qb->andWhere('omeka_root.hasOriginal = true');
            $qb->andWhere('omeka_root.hasThumbnails = false');

            // Prevent using too much memory on large databases
            $qb->setMaxResults(100000);
        });

        $query = $this->getArg('query', []);
        $media_ids = $api->search('media', $query, ['returnScalar' => 'id'])->getContent();

        $total = count($media_ids);
        $logger->info(sprintf('Start processing %d media', $total));

        $i = 0;
        foreach ($media_ids as $media_id) {
            if ($this->shouldStop()) {
                $logger->info('Job stopped');
                $em->flush();
                return;
            }

            $i++;

            $media = $em->find('Omeka\Entity\Media', $media_id);

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
