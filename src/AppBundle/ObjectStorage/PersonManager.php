<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Person;
use AppBundle\Model\Origin;

/**
 * ObjectManager for persons
 * Servicename: person_manager
 */
class PersonManager extends EntityManager
{
    /**
     * Get persons with enough information to get an id and a full description (without offices)
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        return $this->wrapLevelCache(
            Person::CACHENAME,
            'mini',
            $ids,
            function ($ids) {
                $persons = [];
                $rawPersons = $this->dbs->getBasicInfoByIds($ids);

                $locationIds = self::getUniqueIds($rawPersons, 'location_id');
                $locations = $this->container->get('location_manager')->get($locationIds);

                foreach ($rawPersons as $rawPerson) {
                     $person = (new Person())
                        ->setId($rawPerson['person_id'])
                        ->setFirstName($rawPerson['first_name'])
                        ->setLastName($rawPerson['last_name'])
                        ->setExtra($rawPerson['extra'])
                        ->setUnprocessed($rawPerson['unprocessed'])
                        ->setBornDate(new FuzzyDate($rawPerson['born_date']))
                        ->setDeathDate(new FuzzyDate($rawPerson['death_date']))
                        ->setHistorical($rawPerson['is_historical'])
                        ->setModern($rawPerson['is_modern']);

                    if ($rawPerson['self_designations'] != null) {
                        $person->setSelfDesignations(explode(',', $rawPerson['self_designations']));
                    }

                    if ($rawPerson['location_id'] != null) {
                        $person->setOrigin(Origin::fromLocation($locations[$rawPerson['location_id']]));
                    }

                    $persons[$rawPerson['person_id']] = $person;
                }

                $this->setIdentifications($persons);

                $this->setPublics($persons);

                return $persons;
            }
        );
    }

    /**
     * Get persons with enough information to get an id and a full description with offices
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        return $this->wrapLevelCache(
            Person::CACHENAME,
            'short',
            $ids,
            function ($ids) {
                // Get basic person information
                $persons = $this->getMini($ids);

                // Remove all ids that did not match above
                $ids = array_keys($persons);

                // Roles
                $rawRoles = $this->dbs->getRoles($ids);

                $roles = [];
                if (count($rawRoles) > 0) {
                    $roleIds = self::getUniqueIds($rawRoles, 'role_id');
                    $roles = $this->container->get('role_manager')->get($roleIds);

                    foreach ($rawRoles as $rawRole) {
                        $persons[$rawRole['person_id']]->addRole($roles[$rawRole['role_id']]);
                    }
                }

                // offices
                $rawOffices = $this->dbs->getOffices($ids);

                $offices = [];
                if (count($rawOffices) > 0) {
                    $officeIds = self::getUniqueIds($rawOffices, 'office_id');
                    $officesWithParents = $this->container->get('office_manager')->getWithParents($officeIds);

                    foreach ($rawOffices as $rawOffice) {
                        $persons[$rawOffice['person_id']]
                            ->addOfficeWithParents($officesWithParents[$rawOffice['office_id']]);
                    }
                }

                $this->setComments($persons);

                return $persons;
            }
        );
    }

    /**
     * Get a single person with all information
     * @param  int    $id
     * @return Person
     */
    public function getFull(int $id): Person
    {
        return $this->wrapSingleLevelCache(
            Person::CACHENAME,
            'full',
            $id,
            function ($id) {
                // Get basic person information
                $persons = $this->getShort([$id]);
                if (count($persons) == 0) {
                    throw new NotFoundHttpException('Person with id ' . $id .' not found.');
                }
                $person = $persons[$id];

                // TODO: Occurrences (scribe, patron, subject)
                // TODO: Types
                // TODO: books, bookchapters, articles

                // Manuscript roles
                $rawManuscripts = $this->dbs->getManuscripts([$id]);
                $manuscriptIds = self::getUniqueIds($rawManuscripts, 'manuscript_id');
                $occurrenceIds = self::getUniqueIds($rawManuscripts, 'occurrence_id');

                $manuscripts = $this->container->get('manuscript_manager')->getMini($manuscriptIds);
                $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);

                $roles = $this->container->get('role_manager')->getWithData($rawManuscripts);

                foreach ($rawManuscripts as $rawManuscript) {
                    if (!isset($rawManuscript['occurrence_id'])) {
                        $person
                            ->addManuscriptRole(
                                $roles[$rawManuscript['role_id']],
                                $manuscripts[$rawManuscript['manuscript_id']]
                            );
                    } else {
                        $person
                            ->addOccurrenceManuscriptRole(
                                $roles[$rawManuscript['role_id']],
                                $manuscripts[$rawManuscript['manuscript_id']],
                                $occurrences[$rawManuscript['occurrence_id']]
                            );
                    }
                }

                return $person;
            }
        );
    }

    public function getAllShort(string $sortFunction = null): array
    {
        return parent::getAllShort($sortFunction == null ? 'getName' : $sortFunction);
    }

    public function getOfficeDependencies(int $officeId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOfficeId($officeId), $short ? 'getShort' : 'getMini');
    }

    public function getOfficeDependenciesWithChildren(int $officeId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOfficeIdWithChildren($officeId), $short ? 'getShort' : 'getMini');
    }

    public function getAllHistoricalPersons(): array
    {
        return $this->wrapArrayCache(
            'historical_persons',
            ['persons'],
            function () {
                $rawIds = $this->dbs->getHistoricalIds();
                $ids = self::getUniqueIds($rawIds, 'person_id');

                $persons = array_values($this->getMini($ids));

                usort($persons, ['AppBundle\Model\Person', 'cmpByFullDescription']);

                return $persons;
            }
        );
    }

    public function getAllModernPersons(): array
    {
        return $this->wrapArrayCache(
            'modern_persons',
            ['persons'],
            function () {
                $rawIds = $this->dbs->getModernIds();
                $ids = self::getUniqueIds($rawIds, 'person_id');

                $persons = array_values($this->getMini($ids));

                usort($persons, ['AppBundle\Model\Person', 'cmpByFullDescription']);

                return $persons;
            }
        );
    }

    public function add(stdClass $data): Person
    {
        $this->dbs->beginTransaction();
        try {
            $personId = $this->dbs->insert();

            $newPerson = $this->update($personId, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newPerson;
    }

    public function update(int $id, stdClass $data, bool $isNew = false): Person
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('Person with id ' . $id .' not found.');
            }

            // update person data
            $cacheReload = [
                'mini' => $isNew,
                'short' => $isNew,
                'full' => $isNew,
            ];
            if (property_exists($data, 'public')) {
                if (!is_bool($data->firstName)) {
                    throw new BadRequestHttpException('Incorrect public data.');
                }
                $cacheReload['mini'] = true;
                $this->updatePublic($old, $data->public);
            }
            if (property_exists($data, 'firstName')) {
                if (!is_string($data->firstName)) {
                    throw new BadRequestHttpException('Incorrect first name data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateFirstName($id, $data->firstName);
            }
            if (property_exists($data, 'lastName')) {
                if (!is_string($data->lastName)) {
                    throw new BadRequestHttpException('Incorrect last name data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateLastName($id, $data->lastName);
            }
            if (property_exists($data, 'selfDesignations')) {
                if (!is_string($data->selfDesignations)) {
                    throw new BadRequestHttpException('Incorrect self designation data.');
                }
                $cacheReload['mini'] = true;
                // Remove spaces before and after commas
                $this->dbs->updateSelfDesignations($id, preg_replace('/\s*,\s*/', ',', $data->selfDesignations));
            }
            if (property_exists($data, 'origin')) {
                if (!is_object($data->origin) && !empty($data->origin)) {
                    throw new BadRequestHttpException('Incorrect origin data.');
                }
                $cacheReload['mini'] = true;
                $this->updateOrigin($old, $data->origin);
            }
            if (property_exists($data, 'extra')) {
                if (!is_string($data->extra)) {
                    throw new BadRequestHttpException('Incorrect extra data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateExtra($id, $data->extra);
            }
            if (property_exists($data, 'historical')) {
                if (!is_bool($data->historical)) {
                    throw new BadRequestHttpException('Incorrect historical data.');
                }
                $cacheReload['mini'] = true;
                $this->updateHistorical($old, $data->historical);
            }
            if (property_exists($data, 'modern')) {
                if (!is_bool($data->modern)) {
                    throw new BadRequestHttpException('Incorrect modern data.');
                }
                $cacheReload['mini'] = true;
                $this->updateModern($old, $data->modern);
            }
            if (property_exists($data, 'bornDate')) {
                if (!is_object($data->bornDate)) {
                    throw new BadRequestHttpException('Incorrect born date data.');
                }
                $cacheReload['mini'] = true;
                $this->updateDate($old, 'born', $old->getBornDate(), $data->bornDate);
            }
            if (property_exists($data, 'deathDate')) {
                if (!is_object($data->deathDate)) {
                    throw new BadRequestHttpException('Incorrect death date data.');
                }
                $cacheReload['mini'] = true;
                $this->updateDate($old, 'died', $old->getDeathDate(), $data->deathDate);
            }
            $identifiers = $this->container->get('identifier_manager')->getIdentifiersByType('person');
            foreach ($identifiers as $identifier) {
                if (property_exists($data, $identifier->getSystemName())) {
                    if (!is_string($data->{$identifier->getSystemName()})) {
                        throw new BadRequestHttpException('Incorrect identification data.');
                    }
                    $cacheReload['mini'] = true;
                    $this->updateIdentification($old, $identifier, $data->{$identifier->getSystemName()});
                }
            }
            if (property_exists($data, 'offices')) {
                if (!is_array($data->offices)) {
                    throw new BadRequestHttpException('Incorrect office data.');
                }
                $cacheReload['short'] = true;
                $this->updateOffices($old, $data->offices);
            }
            if (property_exists($data, 'publicComment')) {
                if (!is_string($data->publicComment)) {
                    throw new BadRequestHttpException('Incorrect public comment data.');
                }
                $cacheReload['short'] = true;
                $this->updatePublicComment($old, $data->publicComment);
            }
            if (property_exists($data, 'privateComment')) {
                if (!is_string($data->privateComment)) {
                    throw new BadRequestHttpException('Incorrect private comment data.');
                }
                $cacheReload['short'] = true;
                $this->updatePrivateComment($old, $data->privateComment);
            }

            // Throw error if none of above matched
            if (!in_array(true, $cacheReload)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new person data
            $this->clearCache($id, $cacheReload);
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            // Reset elasticsearch
            $this->ess->add($new);

            if ($cacheReload['mini']) {
                // update Elastic manuscripts
                $manuscripts = $this->container->get('manuscript_manager')->getPersonDependencies($id, true);
                $this->container->get('manuscript_manager')->elasticIndex($manuscripts);

                // update Elastic occurrences
                $occurrences = $this->container->get('occurrence_manager')->getPersonDependencies($id, true);
                $this->container->get('occurrence_manager')->elasticIndex($occurrences);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            // Reset cache on elasticsearch error
            if (isset($new)) {
                $this->reset([$id]);
            }
            throw $e;
        }

        return $new;
    }

    public function merge(int $primaryId, int $secondaryId): Person
    {
        if ($primaryId == $secondaryId) {
            throw new BadRequestHttpException(
                'Persons with id ' . $primaryId .' and id ' . $secondaryId . ' are identical and cannot be merged.'
            );
        }
        $primary = $this->getFull($primaryId);
        $secondary = $this->getFull($secondaryId);

        $updates = [];
        if (empty($primary->getFirstName()) && !empty($secondary->getFirstName())) {
            $updates['firstName'] = $secondary->getFirstName();
        }
        if (empty($primary->getLastName()) && !empty($secondary->getLastName())) {
            $updates['lastName'] = $secondary->getLastName();
        }
        if (empty($primary->getExtra()) && !empty($secondary->getExtra())) {
            $updates['extra'] = $secondary->getExtra();
        }
        if (empty($primary->getUnprocessed()) && !empty($secondary->getUnprocessed())) {
            $updates['unprocessed'] = $secondary->getUnprocessed();
        }
        if (empty($primary->getBornDate()) && !empty($secondary->getBornDate())) {
            $updates['bornDate'] = $secondary->getBornDate();
        }
        if (empty($primary->getDeathDate()) && !empty($secondary->getDeathDate())) {
            $updates['deathDate'] = $secondary->getDeathDate();
        }
        $identifiers = $this->container->get('identifier_manager')->getIdentifiersByType('person');
        foreach ($identifiers as $identifier) {
            if (empty($primary->getIdentifications()[$identifier->getSystemName()]) && !empty($secondary->getIdentifications()[$identifier->getSystemName()])) {
                $updates[$identifier->getSystemName()] = implode(', ', $secondary->getIdentifications()[$identifier->getSystemName()]->getIdentifications());
            }
        }
        if (empty($primary->getOfficesWithParents()) && !empty($secondary->getOfficesWithParents())) {
            $updates['offices'] = $secondary->getOfficesWithParents();
        }
        if (empty($primary->getPublicComment()) && !empty($secondary->getPublicComment())) {
            $updates['publicComment'] = $secondary->getPublicComment();
        }
        if (empty($primary->getPrivateComment()) && !empty($secondary->getPrivateComment())) {
            $updates['privateComment'] = $secondary->getPrivateComment();
        }

        $manuscripts = $this->container->get('manuscript_manager')->getPersonDependencies($secondaryId, true);
        $occurrences = $this->container->get('occurrence_manager')->getPersonDependencies($secondaryId, true);

        if ((!empty($manuscripts) || !empty($occurrences)) && !$primary->getHistorical()) {
            $updates['historical'] = true;
        }
        // TODO: books, bookchapters, articles
        // if ((!empty($books) || !empty($bookChapters) || !empty($articles)) && !$primary->getModern()) {
        //     $updates['modern'] = true;
        // }

        $this->dbs->beginTransaction();
        try {
            if (!empty($updates)) {
                $primary = $this->updatePerson($primaryId, json_decode(json_encode($updates)));
            }
            if (!empty($manuscripts)) {
                foreach ($manuscripts as $manuscript) {
                    $individualUpdate = [];
                    self::getIndividualUpdatePart($individualUpdate, 'patrons', $manuscript->getPatrons(), $primaryId, $secondaryId);
                    self::getIndividualUpdatePart($individualUpdate, 'scribes', $manuscript->getScribes(), $primaryId, $secondaryId);
                    self::getIndividualUpdatePart($individualUpdate, 'relatedPersons', $manuscript->getRelatedPersons(), $primaryId, $secondaryId);
                    $manuscript = $this->container->get('manuscript_manager')->update(
                        $manuscript->getId(),
                        json_decode(json_encode($individualUpdate))
                    );
                }
            }
            if (!empty($occurrences)) {
                foreach ($occurrences as $occurrence) {
                    $individualUpdate = [];
                    self::getIndividualUpdatePart($individualUpdate, 'patrons', $occurrence->getPatrons(), $primaryId, $secondaryId);
                    self::getIndividualUpdatePart($individualUpdate, 'scribes', $occurrence->getScribes(), $primaryId, $secondaryId);
                    $this->container->get('occurrence_manager')->updateOccurrence(
                        $occurrence->getId(),
                        json_decode(json_encode($individualUpdate))
                    );
                }
            }
            // TODO: books, bookchapters, articles
            $this->delete($secondaryId);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            // Reset caches and elasticsearch
            $this->reset([$primaryId]);
            if (!empty($manuscripts)) {
                $this->container->get('manuscript_manager')->reset(array_map(function ($manuscript) {
                    return $manuscript->getId();
                }, $manuscripts));
            }
            if (!empty($occurrences)) {
                $this->container->get('occurrence_manager')->reset(array_map(function ($occurrence) {
                    return $occurrence->getId();
                }, $occurrences));
            }
            // TODO: books, bookchapters, articles
            throw $e;
        }

        return $primary;
    }

    private function updateOrigin(Person $person, stdClass $origin = null): void
    {
        if (empty($origin)) {
            if (!empty($person->getOrigin())) {
                $this->dbs->deleteOrigin($person->getId());
            }
        } elseif (!property_exists($origin, 'id') || !is_numeric($origin->id)) {
            throw new BadRequestHttpException('Incorrect origin data.');
        } else {
            if (empty($person->getOrigin())) {
                $this->dbs->insertOrigin($person->getId(), $origin->id);
            } else {
                $this->dbs->updateOrigin($person->getId(), $origin->id);
            }
        }
    }

    private function updateHistorical(Person $person, bool $historical): void
    {
        if (!$historical) {
            $manuscripts = $this->container->get('manuscript_manager')->getPersonDependencies($person->getId());
            $occurrences = $this->container->get('occurrence_manager')->getPersonDependencies($person->getId());
            if (!empty($manuscripts) || !empty($occurrences)) {
                throw new BadRequestHttpException('Persons linked to manuscripts or occurrences must be historical');
            }
        }
        $this->dbs->updateHistorical($person->getId(), $historical);
    }

    private function updateModern(Person $person, bool $modern): void
    {
        if (!$modern) {
            // TODO: book, bookchapter, article dependencies
            // if (!empty($books) || !empty($bookChapters) || !empty($articles)) {
            //     throw new BadRequestHttpException('Persons linked to books, book chapters or articles must be modern');
            // }
        }
        $this->dbs->updateModern($person->getId(), $modern);
    }

    private function updateOffices(Person $person, array $offices): void
    {
        foreach ($offices as $office) {
            if (!is_object($office)
                || !property_exists($office, 'id')
                || !is_numeric($office->id)
            ) {
                throw new BadRequestHttpException('Incorrect offices data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($offices, $person->getOfficesWithParents());

        if (count($delIds) > 0) {
            $this->dbs->delOffices($person->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addOffice($person->getId(), $addId);
        }
    }

    public function delete(int $personId): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $person = $this->getFull($personId);

            $this->dbs->delete($personId);

            $this->updateModified($person, null);

            // empty cache
            $this->clearCache('person', $personId);
            $this->cache->invalidateTags(['persons']);

            // delete from elastic search
            $this->ess->delete($person);

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

    private static function getIndividualUpdatePart(array &$individualUpdate, string $personType, array $persons, int $primaryId, int $secondaryId): void
    {
        if (in_array($secondaryId, array_keys($persons))) {
            $individualUpdate[$personType] = [];
            foreach ($persons as $key => $value) {
                if ($key == $secondaryId) {
                    $individualUpdate[$personType][] = ['id' => $primaryId];
                } else {
                    $individualUpdate[$personType][] = ['id' => $key];
                }
            }
        }
    }
}
