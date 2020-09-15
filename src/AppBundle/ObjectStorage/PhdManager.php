<?php

namespace AppBundle\ObjectStorage;

use stdClass;
use Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Model\Phd;
use AppBundle\Utils\ArrayToJson;

/**
 * ObjectManager for PhD theses
 * Servicename: phd_manager
 */
class PhdManager extends DocumentManager
{
    /**
     * Get PhD theses with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $phds = [];
        if (!empty($ids)) {
            $rawPhds = $this->dbs->getMiniInfoByIds($ids);

            foreach ($rawPhds as $rawPhd) {
                $phds[$rawPhd['phd_id']] = new Phd(
                    $rawPhd['phd_id'],
                    $rawPhd['year'],
                    $rawPhd['city'],
                    $rawPhd['title'],
                    $rawPhd['institution'],
                    $rawPhd['volume']
                );
            }

            $this->setPersonRoles($phds);
        }

        return $phds;
    }

    /**
     * Get PhD theses with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        $phds = $this->getMini($ids);

        $this->setIdentifications($phds);

        $this->setComments($phds);

        $this->setManagements($phds);

        return $phds;
    }

    /**
     * Get a single PhD thesis with all information
     * @param  int        $id
     * @return Phd
     */
    public function getFull(int $id): Phd
    {
        // Get basic PhD thesis information
        $phds = $this->getShort([$id]);

        if (count($phds) == 0) {
            throw new NotFoundHttpException('Phd thesis with id ' . $id .' not found.');
        }

        $this->setCreatedAndModifiedDates($phds);

        $this->setInverseIdentifications($phds);

        $this->setInverseBibliographies($phds);

        $this->setUrls($phds);

        return $phds[$id];
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
     * Add a new PhD thesis
     * @param  stdClass $data
     * @return Phd
     */
    public function add(stdClass $data): Phd
    {
        if (!property_exists($data, 'year')
            || !is_string($data->title)
            || empty($data->title)
            || !property_exists($data, 'year')
            || !is_numeric($data->year)
            || empty($data->year)
            || !property_exists($data, 'city')
            || !is_string($data->city)
            || empty($data->city)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new phd');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->title, $data->year, $data->city, $data->institution);

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
     * Update new or existing PhD thesis
     * @param  int      $id
     * @param  stdClass $data
     * @param  bool     $isNew Indicate whether this is a new PhD thesis
     * @return Phd
     */
    public function update(int $id, stdClass $data, bool $isNew = false): Phd
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('PhD thesis with id ' . $id .' not found.');
            }

            $changes = [
                'mini' => $isNew,
                'full' => $isNew,
            ];
            $roles = $this->container->get('role_manager')->getByType('phd');
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $changes['mini'] = true;
                    $this->updatePersonRole($old, $role, $data->{$role->getSystemName()});
                }
            }
            // Title is a required field
            if (property_exists($data, 'title')) {
                if (empty($data->title) || !is_string($data->title)) {
                    throw new BadRequestHttpException('Incorrect title data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateTitle($id, $data->title);
            }
            // Year is a required field
            if (property_exists($data, 'year')) {
                if (!is_numeric($data->year) || empty($data->year)) {
                    throw new BadRequestHttpException('Incorrect year data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateYear($id, $data->year);
            }
            // City is a required field
            if (property_exists($data, 'city')) {
                if (!is_string($data->city) || empty($data->city)) {
                    throw new BadRequestHttpException('Incorrect city data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateCity($id, $data->city);
            }
            if (property_exists($data, 'institution')) {
                if (!empty($data->institution) && !is_string($data->institution)) {
                    throw new BadRequestHttpException('Incorrect institution data.');
                }
                $changes['full'] = true;
                $this->dbs->updateInstitution($id, $data->institution);
            }
            if (property_exists($data, 'volume')) {
                if (!empty($data->volume) && !is_string($data->volume)) {
                    throw new BadRequestHttpException('Incorrect volume data.');
                }
                $changes['full'] = true;
                $this->dbs->updateVolume($id, $data->volume);
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
            $this->updateIdentificationwrapper($old, $data, $changes, 'full', 'phd');
            $this->updateManagementwrapper($old, $data, $changes, 'short');

            // Throw error if none of above matched
            if (!in_array(true, $changes)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            $this->cache->invalidateTags(['phds']);

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
