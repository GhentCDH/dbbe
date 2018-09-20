<?php

namespace AppBundle\ObjectStorage;

use stdClass;

use Symfony\Component\Filesystem\Filesystem;

use AppBundle\Model\Image;

use Symfony\Component\HttpFoundation\File\UploadedFile;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ImageManager extends ObjectManager
{
    /**
     * Get images with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        return $this->wrapCache(
            Image::CACHENAME,
            $ids,
            function ($ids) {
                $rawImages = $this->dbs->getImagesByIds($ids);
                $images = $this->getWithData($rawImages);

                return $images;
            }
        );
    }

    /**
     * Get images with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
        return $this->wrapDataCache(
            Image::CACHENAME,
            $data,
            'image_id',
            function ($data) {
                $images = [];
                foreach ($data as $rawImage) {
                    if (isset($rawImage['image_id']) && !isset($images[$rawImage['image_id']])) {
                        $images[$rawImage['image_id']] = new Image(
                            $rawImage['image_id'],
                            $rawImage['filename'],
                            $rawImage['url'],
                            !($rawImage['is_private'])
                        );
                    }
                }

                return $images;
            }
        );
    }

    /**
     * Get an image object by uploaded file
     * Upload to the server and create database entry if necessary
     * @param  UploadedFile $file
     * @return Image
     */
    public function getImageByFile(UploadedFile $file): Image
    {
        $filename = $file->getClientOriginalName();
        $imageDirectory = $this->container->getParameter('image_directory');

        // Make sure file exists
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists($imageDirectory . $filename)) {
            $file->move($imageDirectory, $filename);
        } else {
            $fileSystem->remove($file->getPathname());
        }

        // Make sure image exists in database
        $rawImages = $this->dbs->getIdByFileName($filename);
        if (count($rawImages) > 1) {
            throw new NotFoundHttpException('Multiple images already exsist with filename "' . $filename . '"');
        } elseif (count($rawImages) == 1) {
            return $this->getWithData($rawImages)[$rawImages[0]['image_id']];
        } else {
            $id = $this->dbs->insert($filename, null, false);
            return $this->get([$id])[$id];
        }
    }

    /**
     * Update an existing image
     * @param  int      $id
     * @param  stdClass $data
     * @return Image
     */
    public function update(int $id, stdClass $data): Image
    {
        $this->dbs->beginTransaction();
        try {
            $images = $this->get([$id]);
            if (count($images) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Image with id ' . $id .' not found.');
            }
            $old = $images[$id];

            if (property_exists($data, 'public')
                && is_bool($data->public)
            ) {
                $this->dbs->updatePublic($id, $data->public);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $this->deleteCache(Image::CACHENAME, $id);
            $new = $this->get([$id])[$id];

            $this->updateModified($old, $new);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }
}
