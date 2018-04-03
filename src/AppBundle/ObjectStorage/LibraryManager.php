<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\Library;

class LibraryManager extends ObjectManager
{
    public function getLibrariesByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'library');
        if (empty($ids)) {
            return $cached;
        }

        $libraries = [];
        $rawLibraries = $this->dbs->getLibrariesByIds($ids);

        foreach ($rawLibraries as $rawLibrary) {
            $libraries[$rawLibrary['library_id']] = new Library($rawLibrary['library_id'], $rawLibrary['name']);
        }

        $this->setCache($libraries, 'library');

        return $cached + $libraries;
    }

    public function addLibrary(stdClass $data): Library
    {
        $this->dbs->beginTransaction();
        try {
            if (property_exists($data, 'name')
                && is_string($data->name)
                && property_exists($data, 'city')
                && property_exists($data->city, 'id')
                && is_numeric($data->city->id)
            ) {
                $libraryId = $this->dbs->insert($data->name, $data->city->id);
            } else {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new library data
            $this->cache->invalidateTags(['libraries']);
            $newLibrary = $this->getLibrariesByIds([$libraryId])[$libraryId];

            $this->updateModified(null, $newLibrary);

            // update cache
            $this->setCache([$newLibrary->getId() => $newLibrary], 'library');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newLibrary;
    }

    public function updateLibrary(int $libraryId, stdClass $data): Library
    {
        $this->dbs->beginTransaction();
        try {
            $libraries = $this->getLibrariesByIds([$libraryId]);
            if (count($libraries) == 0) {
                throw new NotFoundHttpException('Library with id ' . $libraryId .' not found.');
            }
            $library = $libraries[$libraryId];

            // update library data
            $correct = false;
            if (property_exists($data, 'name')
                && is_string($data->name)
            ) {
                $correct = true;
                $this->dbs->updateName($libraryId, $data->name);
            }
            if (property_exists($data, 'city')
                && property_exists($data->city, 'id')
                && is_numeric($data->city->id)
            ) {
                $correct = true;
                $this->dbs->updateRegion($libraryId, $data->city->id);
            }

            if (!$correct) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new library data
            $this->cache->invalidateTags(['libraries']);
            $this->cache->deleteItem('library.' . $libraryId);
            $newLibrary = $this->getLibrariesByIds([$libraryId])[$libraryId];

            $this->updateModified($library, $newLibrary);

            // update cache
            $this->setCache([$newLibrary->getId() => $newLibrary], 'library');

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newLibrary;
    }

    public function delLibrary(int $libraryId): void
    {
        $this->dbs->beginTransaction();
        try {
            $libraries = $this->getLibrariesByIds([$libraryId]);
            if (count($libraries) == 0) {
                throw new NotFoundHttpException('Library with id ' . $libraryId .' not found.');
            }
            $library = $libraries[$libraryId];

            $this->dbs->delete($libraryId);

            // load new library data
            $this->cache->invalidateTags(['libraries']);
            $this->cache->deleteItem('library.' . $libraryId);

            $this->updateModified($library, null);

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
