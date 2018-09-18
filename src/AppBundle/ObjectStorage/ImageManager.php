<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Image;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImageManager extends ObjectManager
{
    /**
     * Get image by id
     * @param  int    $id
     * @return Image
     */
    public function getImageById(int $id): Image
    {
        return $this->wrapSingleCache(
            'image_id',
            $id,
            function ($id) {
                $rawImages = $this->dbs->getById($id);
                if (count($rawImages) !== 1) {
                    throw new NotFoundHttpException('Image with id "' . $id . '" not found');
                }

                $rawImage = $rawImages[0];

                $image = new Image($rawImage['image_id'], $rawImage['url'], !$rawImage['is_private']);

                return $image;
            }
        );
    }
}
