<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Image;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImageManager extends ObjectManager
{
    /**
     * Get image by url
     * @param  string $url
     * @return Image
     */
    public function getImageByUrl(string $url): Image
    {
        return $this->wrapSingleCache(
            'image_url',
            $url,
            function ($url) {
                $rawImages = $this->dbs->getByUrl($url);
                if (count($rawImages) !== 1) {
                    throw new NotFoundHttpException('Image with url "' . $url . '" not found');
                }

                $rawImage = $rawImages[0];

                $image = new Image($rawImage['image_id'], $rawImage['url'], !$rawImage['is_private']);

                return $image;
            }
        );
    }
}
