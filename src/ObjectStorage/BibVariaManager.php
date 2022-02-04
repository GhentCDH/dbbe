<?php

namespace App\ObjectStorage;

use Psr\Cache\InvalidArgumentException;
use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Model\BibVaria;

/**
 * ObjectManager for bib varias
 */
class BibVariaManager extends DocumentManager
{
    /**
     * Get bib varias with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $bibVarias = [];
        if (!empty($ids)) {
            $rawBibVarias = $this->dbs->getMiniInfoByIds($ids);

            foreach ($rawBibVarias as $rawBibVaria) {
                $bibVaria = (new BibVaria(
                    $rawBibVaria['bib_varia_id'],
                    $rawBibVaria['title'],
                    $rawBibVaria['year'],
                    $rawBibVaria['city'],
                    $rawBibVaria['institution']
                ));

                $bibVarias[$rawBibVaria['bib_varia_id']] = $bibVaria;
            }

            $this->setPersonRoles($bibVarias);
        }

        return $bibVarias;
    }

    /**
     * Get bib varias with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        $bibVarias = $this->getMini($ids);

        $this->setIdentifications($bibVarias);

        $this->setComments($bibVarias);

        $this->setManagements($bibVarias);

        return $bibVarias;
    }

    /**
     * Get a single bib varia with all information
     * @param  int        $id
     * @return BibVaria
     */
    public function getFull(int $id): BibVaria
    {
        // Get basic information
        $bibVarias = $this->getShort([$id]);

        if (count($bibVarias) == 0) {
            throw new NotFoundHttpException('Bib varia with id ' . $id .' not found.');
        }

        $this->setCreatedAndModifiedDates($bibVarias);

        $this->setInverseIdentifications($bibVarias);

        $this->setInverseBibliographies($bibVarias);

        $this->setUrls($bibVarias);

        return $bibVarias[$id];
    }

    /**
     * @param  string|null $sortFunction Name of the optional method to call for sorting
     * @return array
     */
    public function getAllMiniShortJson(string $sortFunction = null): array
    {
        return parent::getAllMiniShortJson($sortFunction == null ? 'getDescription' : $sortFunction);
    }

    /**
     * Add a new bib varia
     * @param stdClass $data
     * @return BibVaria
     * @throws Exception|InvalidArgumentException
     */
    public function add(stdClass $data): BibVaria
    {
        if (!property_exists($data, 'title')
            || !is_string($data->title)
            || empty($data->title)
            || (
                property_exists($data, 'year')
                && !is_numeric($data->year)
                && !empty($data->year)
            )
            || (
                property_exists($data, 'city')
                && !is_string($data->city)
                && !empty($data->city)
            )
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new bib varia');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert(
                $data->title,
                property_exists($data, 'year') ? $data->year : null,
                property_exists($data, 'city') ? $data->city : null
            );

            unset($data->title);
            unset($data->year);
            unset($data->city);

            $new = $this->update($id, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update new or existing bib varia
     * @param int $id
     * @param stdClass $data
     * @param bool $isNew Indicate whether this is a new bib varia
     * @return BibVaria
     * @throws InvalidArgumentException
     */
    public function update(int $id, stdClass $data, bool $isNew = false): BibVaria
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('Bib varia with id ' . $id .' not found.');
            }

            $changes = [
                'mini' => $isNew,
            ];
            $roles = $this->container->get(RoleManager::class)->getByType('bibVaria');
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $changes['mini'] = true;
                    $this->updatePersonRole($old, $role, $data->{$role->getSystemName()});
                }
            }
            if (property_exists($data, 'title')) {
                // Title is a required field
                if (!is_string($data->title) || empty($data->title)) {
                    throw new BadRequestHttpException('Incorrect title data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateTitle($id, $data->title);
            }
            // Year is not a required field
            if (property_exists($data, 'year')) {
                if (!empty($data->year) && !is_numeric($data->year)) {
                    throw new BadRequestHttpException('Incorrect year data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateYear($id, $data->year);
            }
            // City is not a required field
            if (property_exists($data, 'city')) {
                if (!empty($data->city) && !is_string($data->city)) {
                    throw new BadRequestHttpException('Incorrect city data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateCity($id, $data->city);
            }
            // Institution is not a required field
            if (property_exists($data, 'institution')) {
                if (!empty($data->institution) && !is_string($data->institution)) {
                    throw new BadRequestHttpException('Incorrect institution data.');
                }
                $changes['full'] = true;
                $this->dbs->updateInstitution($id, $data->institution);
            }
            $this->updateUrlswrapper($old, $data, $changes, 'full');
            if (property_exists($data, 'publicComment')) {
                if (!is_string($data->publicComment)) {
                    throw new BadRequestHttpException('Incorrect public comment data.');
                }
                $changes['short'] = true;
                $this->dbs->updatePublicComment($id, $data->publicComment);
            }
            if (property_exists($data, 'privateComment')) {
                if (!is_string($data->privateComment)) {
                    throw new BadRequestHttpException('Incorrect private comment data.');
                }
                $changes['short'] = true;
                $this->dbs->updatePrivateComment($id, $data->privateComment);
            }
            $this->updateIdentificationwrapper($old, $data, $changes, 'full', 'bibVaria');
            $this->updateManagementwrapper($old, $data, $changes, 'short');

            // Throw error if none of above matched
            if (!in_array(true, $changes)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            $this->cache->invalidateTags([$this->entityType . 's']);

            // (re-)index in elastic search
            $this->ess->add($new);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // Reset elasticsearch
            if ($isNew) {
                $this->updateElasticByIds([$id]);
            } elseif (isset($new) && isset($old)) {
                $this->ess->add($old);
            }
            throw $e;
        }

        return $new;
    }
}
