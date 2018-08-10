<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Psr\Cache\CacheItemPoolInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Person;
use AppBundle\Model\Role;
use AppBundle\Service\DatabaseService\DatabaseServiceInterface;
use AppBundle\Service\ElasticSearchService\ElasticSearchServiceInterface;

class PersonManager extends EntityManager
{
    public function __construct(
        DatabaseServiceInterface $databaseService,
        CacheItemPoolInterface $cacheItemPool,
        ContainerInterface $container,
        ElasticSearchServiceInterface $elasticSearchService = null,
        TokenStorageInterface $tokenStorage = null
    ) {
        parent::__construct($databaseService, $cacheItemPool, $container, $elasticSearchService, $tokenStorage);
        $this->en = 'person';
    }

    /**
     * Get persons with enough information to get an id and a full description (without offices)
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'person_mini');
        if (empty($ids)) {
            return $cached;
        }

        $persons = [];
        $rawPersons = $this->dbs->getBasicInfoByIds($ids);

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

            $persons[$rawPerson['person_id']] = $person;
        }

        $this->setIdentifications($persons);

        $this->setPublics($persons);

        $this->setCache($persons, 'person_mini');

        return $cached + $persons;
    }

    /**
     * Get persons with enough information to get an id and a full description with offices
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'person_short');
        if (empty($ids)) {
            return $cached;
        }

        // Get basic person information
        $persons = $this->getMini($ids);

        // Remove all ids that did not match above
        $ids = array_keys($persons);

        // Roles
        $rawRoles = $this->dbs->getRoles($ids);

        $roles = [];
        if (count($rawRoles) > 0) {
            $roleIds = self::getUniqueIds($rawRoles, 'role_id');
            $roles = $this->container->get('role_manager')->getRolesByIds($roleIds);

            foreach ($rawRoles as $rawRole) {
                $persons[$rawRole['person_id']]
                    ->addRole($roles[$rawRole['role_id']])
                    ->addCacheDependency('role.' . $rawRole['role_id'])
                    ->addCacheDependency($rawRole['document_key'] . '.' . $rawRole['document_id']);
            }
        }

        // offices
        $rawOffices = $this->dbs->getOffices($ids);

        $offices = [];
        if (count($rawOffices) > 0) {
            $officeIds = self::getUniqueIds($rawOffices, 'office_id');
            $offices = $this->container->get('office_manager')->getOfficesByIds($officeIds);

            foreach ($rawOffices as $rawOffice) {
                $persons[$rawOffice['person_id']]
                    ->addOffice($offices[$rawOffice['office_id']])
                    ->addCacheDependency('office.' . $rawOffice['office_id']);
            }
        }

        $this->setComments($persons);

        $this->setCache($persons, 'person_short');

        return $cached + $persons;
    }

    /**
     * Get a single person with all information
     * @param  int    $id
     * @return Person
     */
    public function getFull(int $id): Person
    {
        $cache = $this->cache->getItem('person_full.' . $id);
        if ($cache->isHit()) {
            return $cache->get();
        }

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

        foreach ($rawManuscripts as $rawManuscript) {
            if (!isset($rawManuscript['occurrence_id'])) {
                $person
                    ->addManuscriptRole(
                        new Role(
                            $rawManuscript['role_id'],
                            json_decode($rawManuscript['role_usage']),
                            $rawManuscript['role_system_name'],
                            $rawManuscript['role_name']
                        ),
                        $manuscripts[$rawManuscript['manuscript_id']]
                    )
                    // manuscript roles are defined in the short section
                    ->addCacheDependency('manuscript_short.' . $rawManuscript['manuscript_id']);
                foreach ($manuscripts[$rawManuscript['manuscript_id']]->getCacheDependencies() as $cacheDependency) {
                    $person->addCacheDependency($cacheDependency);
                }
            } else {
                $person
                    ->addOccurrenceManuscriptRole(
                        new Role(
                            $rawManuscript['role_id'],
                            json_decode($rawManuscript['role_usage']),
                            $rawManuscript['role_system_name'],
                            $rawManuscript['role_name']
                        ),
                        $manuscripts[$rawManuscript['manuscript_id']],
                        $occurrences[$rawManuscript['occurrence_id']]
                    )
                    // manuscript and occurrence roles are defined in the short section
                    ->addCacheDependency('manuscript_short.' . $rawManuscript['manuscript_id'])
                    ->addCacheDependency('occurrence_short.' . $rawManuscript['occurrence_id']);
                foreach ($manuscripts[$rawManuscript['manuscript_id']]->getCacheDependencies() as $cacheDependency) {
                    $person->addCacheDependency($cacheDependency);
                }
                foreach ($occurrences[$rawManuscript['occurrence_id']]->getCacheDependencies() as $cacheDependency) {
                    $person->addCacheDependency($cacheDependency);
                }
            }
        }

        $this->setCache([$person->getId() => $person], 'person_full');

        return $person;
    }

    public function getAllShort(): array
    {
        $cache = $this->cache->getItem('persons');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $persons = parent::getAllShort();

        // Sort by name
        usort($persons, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $cache->tag(['persons']);
        $this->cache->save($cache->set($persons));
        return $persons;
    }

    public function getOfficeDependencies(int $officeId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOfficeId($officeId));
    }

    public function getAllHistoricalPersons(): array
    {
        $cache = $this->cache->getItem('historical_persons');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawIds = $this->dbs->getHistoricalIds();
        $ids = self::getUniqueIds($rawIds, 'person_id');

        $persons = array_values($this->getMini($ids));

        usort($persons, ['AppBundle\Model\Person', 'sortByFullDescription']);

        $cache->tag(['persons']);
        $this->cache->save($cache->set($persons));
        return $persons;
    }

    public function getAllModernPersons(): array
    {
        $cache = $this->cache->getItem('modern_persons');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawIds = $this->dbs->getModernIds();
        $ids = self::getUniqueIds($rawIds, 'person_id');

        $persons = array_values($this->getMini($ids));

        usort($persons, ['AppBundle\Model\Person', 'sortByFullDescription']);

        $cache->tag(['persons']);
        $this->cache->save($cache->set($persons));
        return $persons;
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

    public function update(int $id, stdClass $data, bool $new = false): Person
    {
        $this->dbs->beginTransaction();
        try {
            $person = $this->getFull($id);
            if ($person == null) {
                throw new NotFoundHttpException('Person with id ' . $id .' not found.');
            }

            // update person data
            $cacheReload = [
                'mini' => $new,
                'short' => $new,
                'full' => $new,
            ];
            if (property_exists($data, 'public')) {
                $cacheReload['mini'] = true;
                $this->updatePublic($person, $data->public);
            }
            if (property_exists($data, 'firstName')) {
                if (!is_string($data->firstName)) {
                    throw new BadRequestHttpException('Incorrect first name data.');
                }
                $cacheReload['mini'] = true;
                $this->updateFirstName($person, $data->firstName);
            }
            if (property_exists($data, 'lastName')) {
                if (!is_string($data->lastName)) {
                    throw new BadRequestHttpException('Incorrect last name data.');
                }
                $cacheReload['mini'] = true;
                $this->updateLastName($person, $data->lastName);
            }
            if (property_exists($data, 'extra')) {
                if (!is_string($data->extra)) {
                    throw new BadRequestHttpException('Incorrect extra data.');
                }
                $cacheReload['mini'] = true;
                $this->updateExtra($person, $data->extra);
            }
            if (property_exists($data, 'historical')) {
                if (!is_bool($data->historical)) {
                    throw new BadRequestHttpException('Incorrect historical data.');
                }
                $cacheReload['mini'] = true;
                $this->updateHistorical($person, $data->historical);
            }
            if (property_exists($data, 'modern')) {
                if (!is_bool($data->modern)) {
                    throw new BadRequestHttpException('Incorrect modern data.');
                }
                $cacheReload['mini'] = true;
                $this->updateModern($person, $data->modern);
            }
            if (property_exists($data, 'bornDate')) {
                $cacheReload['mini'] = true;
                $this->updateDate($person, 'born', $person->getBornDate(), $data->bornDate);
            }
            if (property_exists($data, 'deathDate')) {
                $cacheReload['mini'] = true;
                $this->updateDate($person, 'died', $person->getDeathDate(), $data->deathDate);
            }
            $identifiers = $this->container->get('identifier_manager')->getIdentifiersByType('person');
            foreach ($identifiers as $identifier) {
                if (property_exists($data, $identifier->getSystemName())) {
                    $cacheReload['mini'] = true;
                    $this->updateIdentification($person, $identifier, $data->{$identifier->getSystemName()});
                }
            }
            if (property_exists($data, 'offices')) {
                $cacheReload['short'] = true;
                $this->updateOffices($person, $data->offices);
            }
            if (property_exists($data, 'publicComment')) {
                if (!is_string($data->publicComment)) {
                    throw new BadRequestHttpException('Incorrect public comment data.');
                }
                $cacheReload['short'] = true;
                $this->updatePublicComment($person, $data->publicComment);
            }
            if (property_exists($data, 'privateComment')) {
                if (!is_string($data->privateComment)) {
                    throw new BadRequestHttpException('Incorrect private comment data.');
                }
                $cacheReload['short'] = true;
                $this->updatePrivateComment($person, $data->privateComment);
            }

            // Throw error if none of above matched
            if (!in_array(true, $cacheReload)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new person data
            $this->clearCache($id, $cacheReload);
            $newPerson = $this->getFull($id);

            $this->updateModified($new ? null : $person, $newPerson);

            // Reset cache and elasticsearch
            $this->ess->addPerson($newPerson);

            if ($cacheReload['mini']) {
                // update Elastic manuscripts
                $manuscripts = $this->container->get('manuscript_manager')->getPersonDependencies($id);
                $this->container->get('manuscript_manager')->elasticIndex($manuscripts);

                // update Elastic occurrences
                $occurrences = $this->container->get('occurrence_manager')->getPersonDependencies($id);
                $this->container->get('occurrence_manager')->elasticIndex($occurrences);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            // Reset cache on elasticsearch error
            if (isset($newPerson)) {
                $this->reset([$id]);
            }
            throw $e;
        }

        return $newPerson;
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
        var_dump($updates);
        if (empty($primary->getOffices()) && !empty($secondary->getOffices())) {
            $updates['offices'] = $secondary->getOffices();
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

            // Make sure indirect properties (manuscripts, occurrences) are reloaded correctly
            $this->cache->invalidateTags(['person.' . $primaryId]);
            $this->cache->deleteItem('person.' . $primaryId);
            $person = $this->getFull($primaryId);
            $this->cache->invalidateTags(['persons']);

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

    private function updateFirstName(Person $person, string $firstName = null): void
    {
        $this->dbs->updateFirstName($person->getId(), $firstName);
    }

    private function updateLastName(Person $person, string $lastName = null): void
    {
        $this->dbs->updateLastName($person->getId(), $lastName);
    }

    private function updateExtra(Person $person, string $extra = null): void
    {
        $this->dbs->updateExtra($person->getId(), $extra);
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
        list($delIds, $addIds) = self::calcDiff($offices, $person->getOffices());

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
