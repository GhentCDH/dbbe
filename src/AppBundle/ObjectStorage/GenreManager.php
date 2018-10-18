<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use AppBundle\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Genre;

/**
 * ObjectManager for genres
 * Servicename: genre_manager
 */
class GenreManager extends ObjectManager
{
    /**
     * Get single genres with all information
     * @param  array $ids
     * @return array
     */
    public function get(array $ids): array
    {
        $rawGenres = $this->dbs->getGenresByIds($ids);
        return $this->getWithData($rawGenres);
    }

    /**
     * Get single genres with all information from existing data
     * @param  array $data
     * @return array
     */
    public function getWithData(array $data): array
    {
        $genres = [];
        foreach ($data as $rawGenre) {
            if (isset($rawGenre['genre_id']) && !isset($genres[$rawGenre['genre_id']])) {
                $genres[$rawGenre['genre_id']] = new Genre(
                    $rawGenre['genre_id'],
                    $rawGenre['name']
                );
            }
        }

        return $genres;
    }
    public function getAll(): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'genre_id');
        $genres = $this->get($ids);

        // Sort by name
        usort($genres, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        return $genres;
    }

    /**
     * Get all genres with minimal information
     * @return array
     */
    public function getAllShortJson(): array
    {
        return $this->wrapArrayCache(
            'genres',
            ['genres'],
            function () {
                return ArrayToJson::arrayToShortJson($this->getAll());
            }
        );
    }

    /**
     * Get all genres with all information
     * @return array
     */
    public function getAllJson(): array
    {
        return ArrayToJson::arrayToJson($genres);
    }

    /**
     * Add a new genre
     * @param  stdClass $data
     * @return Genre
     */
    public function add(stdClass $data): Genre
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $id = $this->dbs->insert($data->name);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->get([$id])[$id];

            $this->updateModified(null, $new);

            $this->cache->invalidateTags(['genres']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update an existing genre
     * @param  int      $id
     * @param  stdClass $data
     * @return Genre
     */
    public function update(int $id, stdClass $data): Genre
    {
        $this->dbs->beginTransaction();
        try {
            $genres = $this->get([$id]);
            if (count($genres) == 0) {
                $this->dbs->rollBack();
                throw new NotFoundHttpException('Genre with id ' . $id .' not found.');
            }
            $old = $genres[$id];

            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $this->dbs->updateName($id, $data->name);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->get([$id])[$id];

            $this->updateModified($old, $new);

            $this->cache->invalidateTags(['genres']);

            // update Elastic occurrences
            $occurrenceIds = $this->container->get('occurrence_manager')->getGenreDependencies($id, 'getId');
            $this->container->get('occurrence_manager')->updateElasticByIds($occurrenceIds);

            // update Elastic types
            $typeIds = $this->container->get('type_manager')->getGenreDependencies($id, 'getId');
            $this->container->get('type_manager')->updateElasticByIds($typeIds);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Delete a genre
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            $genres = $this->get([$id]);
            if (count($genres) == 0) {
                throw new NotFoundHttpException('Genre with id ' . $id .' not found.');
            }
            $old = $genres[$id];

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            $this->cache->invalidateTags(['genres']);

            // commit transaction
            $this->dbs->commit();
        } catch (DependencyException $e) {
            $this->dbs->rollBack();
            throw new BadRequestHttpException($e->getMessage());
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return;
    }
}
