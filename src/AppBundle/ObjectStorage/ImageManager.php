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
        $rawImages = $this->dbs->getImagesByIds($ids);
        return $this->getWithData($rawImages);
    }

    /**
     * Get images with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
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

    /**
     * Get an image object by uploaded file
     * Upload to the server and create database entry if necessary
     * @param  UploadedFile $file
     * @return Image
     */
    public function getImageByFile(UploadedFile $file): Image
    {
        $filename = $file->getClientOriginalName();
        $imageDirectory = $this->container->getParameter('kernel.project_dir') . '/'
            . $this->container->getParameter('image_directory') . '/';

        // Make sure file exists
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists($imageDirectory . $filename)) {
            $file->move($imageDirectory, $filename);
        } else {
            $fileSystem->remove($file->getPathname());
        }

        // Make sure image exists in database
        $this->dbs->beginTransaction();
        try {
            $rawImages = $this->dbs->getImagesByFileName($filename);
            if (count($rawImages) > 1) {
                throw new NotFoundHttpException('Multiple images already exsist with filename "' . $filename . '"');
            } elseif (count($rawImages) == 1) {
                $new = $this->getWithData($rawImages)[$rawImages[0]['image_id']];
            } else {
                $id = $this->dbs->insert($filename, null, false);
                $new = $this->get([$id])[$id];

                $this->updateModified(null, $new);
            }
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }
        return $new;
    }

    /**
     * Add a new image to the database or return an (updated if necessary) existing image
     * Only imagelinks (url) are allowed; images (filename) should be added using getImageByFile
     * @param  stdClass $data
     * @return Image
     */
    public function add(stdClass $data): Image
    {
        if (!property_exists($data, 'url')
            || !is_string($data->url)
            || empty($data->url)
            || !property_exists($data, 'public')
            || !is_bool($data->public)
        ) {
            throw new BadRequestHttpException('Incorrect image link data.');
        }
        $this->dbs->beginTransaction();
        try {
            $rawImages = $this->dbs->getImagesByUrl($data->url);
            if (count($rawImages) > 1) {
                throw new NotFoundHttpException('Multiple images with url ' . $data->url .' found.');
            } elseif (count($rawImages) == 1) {
                $new = $this->getWithData($rawImages)[$rawImages[0]['image_id']];
                if ($data->public != $new->getPublic()) {
                    $this->update(
                        $new->getId(),
                        json_decode(json_encode(['public' => $data->public]))
                    );
                }
            } else {
                $id = $this->dbs->insert(null, $data->url, $data->public);
                $new = $this->get([$id])[$id];

                $this->updateModified(null, $new);
            }

            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }
        return $new;
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
                throw new NotFoundHttpException('Image with id ' . $id .' not found.');
            }
            $old = $images[$id];
            $correct = false;

            if (property_exists($data, 'public')) {
                if (!is_bool($data->public)) {
                    throw new BadRequestHttpException('Incorrect public data.');
                }
                $this->dbs->updatePublic($id, $data->public);
                $correct = true;
            }

            if (property_exists($data, 'url')) {
                if (!is_string($data->url)) {
                    throw new BadRequestHttpException('Incorrect url data.');
                }
                $this->dbs->updateUrl($id, $data->url);
                $this->dbs->mergeByUrl($data->url);

                $correct = true;
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
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

    // TODO: cleanup unused images in database and on filesystem (after delimages in occurrence?)
}
