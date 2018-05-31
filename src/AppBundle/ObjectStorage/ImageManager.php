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
        $cache = $this->cache->getItem('image_url.' . $url);
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawImages = $this->dbs->getByUrl($url);
        if (count($rawImages) !== 1) {
            throw new NotFoundHttpException('Image with url "' . $url . '" not found');
        }

        $rawImage = $rawImages[0];

        $image = new Image($rawImage['image_id'], $rawImage['url'], !$rawImage['is_private']);

        $this->setCache([$image->getId() => $image], 'image_url');

        return $image;
    }
}
