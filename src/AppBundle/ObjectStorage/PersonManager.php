<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Person;

class PersonManager extends EntityManager
{
    /**
     * Get persons with enough information to get an id and a full description (without occupations)
     * @param  array $ids
     * @return array
     */
    public function getMiniPersonsByIds(array $ids): array
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
                ->setHistorical($rawPerson['is_historical']);

            $persons[$rawPerson['person_id']] = $person;
        }

        $this->setIdentifications($persons);

        $this->setPublics($persons);

        $this->setCache($persons, 'person_mini');

        return $cached + $persons;
    }

    /**
     * Get persons with enough information to get an id and a full description with occupations
     * @param  array $ids
     * @return array
     */
    public function getShortPersonsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'person_short');
        if (empty($ids)) {
            return $cached;
        }

        // Get basic person information
        $persons = $this->getMiniPersonsByIds($ids);

        // Remove all ids that did not match above
        $ids = array_keys($persons);

        // Occupations
        $rawOccupations = $this->dbs->getOccupations($ids);

        $occupations = [];
        if (count($rawOccupations) > 0) {
            $occupationIds = self::getUniqueIds($rawOccupations, 'occupation_id');
            $occupations = $this->container->get('occupation_manager')->getOccupationsByIds($occupationIds);

            foreach ($rawOccupations as $rawOccupation) {
                $persons[$rawOccupation['person_id']]
                    ->addOccupation($occupations[$rawOccupation['occupation_id']])
                    ->addCacheDependency('occupation.' . $rawOccupation['occupation_id']);
            }
        }

        $this->setComments($persons);

        $this->setCache($persons, 'person_short');

        return $cached + $persons;
    }

    public function getAllPersons(): array
    {
        $cache = $this->cache->getItem('persons');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'person_id');
        $persons = $this->getShortPersonsByIds($ids);

        // Sort by name
        usort($persons, function ($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $cache->tag(['persons']);
        $this->cache->save($cache->set($persons));
        return $persons;
    }

    public function getPersonById(int $id): Person
    {
        $cache = $this->cache->getItem('person.' . $id);
        if ($cache->isHit()) {
            return $cache->get();
        }

        // Get basic person information
        $persons = $this->getShortPersonsByIds([$id]);
        if (count($persons) == 0) {
            throw new NotFoundHttpException('Person with id ' . $id .' not found.');
        }
        $person = $persons[$id];

        // TODO: Occurrences (scribe, patron, subject)
        // TODO: Types
        // TODO: books, bookchapters, articles

        $rawManuscripts = $this->dbs->getManuscripts([$id]);
        $patronManuscriptIds = self::getUniqueIds($rawManuscripts, 'manuscript_id', 'type', 'patron');
        $scribeManuscriptIds = self::getUniqueIds($rawManuscripts, 'manuscript_id', 'type', 'scribe');
        $relatedManuscriptIds = self::getUniqueIds($rawManuscripts, 'manuscript_id', 'type', 'related');
        $manuscriptIds = array_unique(array_merge($patronManuscriptIds, $scribeManuscriptIds, $relatedManuscriptIds));

        $manuscripts = $this->container->get('manuscript_manager')->getMiniManuscriptsByIds($manuscriptIds);

        foreach ($rawManuscripts as $rawManuscript) {
            $person
                ->addManuscript($manuscripts[$rawManuscript['manuscript_id']], $rawManuscript['type'])
                // manuscript patrons, scribes and related persons are defined in the short section
                ->addCacheDependency('manuscript_short.' . $rawManuscript['manuscript_id']);
            if (!empty($rawManuscript['occurrence_id'])) {
                // occurrence patrons and scribes are defined in the short section
                $person->addCacheDependency('occurrence_short.' . $rawManuscript['occurrence_id']);
            }
        }

        $this->setCache([$person->getId() => $person], 'person');

        return $person;
    }

    public function getPersonsDependenciesByOccupation(int $occupationId): array
    {
        $rawIds = $this->dbs->getDepIdsByOccupationId($occupationId);
        return $this->getShortPersonsByIds(self::getUniqueIds($rawIds, 'person_id'));
    }

    private function getBibroles(array $occupations): array
    {
        $persons = [];
        $personIds = [];
        $personsByOccupations = array_merge($this->getPersonsByOccupations($occupations));
        foreach ($personsByOccupations as $personsByOccupation) {
            foreach ($personsByOccupation as $person) {
                if (!in_array($person->getId(), $personIds)) {
                    $personIds[] = $person->getId();
                    $persons[] = $person;
                }
            }
        }

        usort($persons, ['AppBundle\Model\Person', 'sortByFullDescription']);

        return $persons;
    }

    public function getAllPatrons(): array
    {
        $cache = $this->cache->getItem('patrons');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $patrons = $this->getBibroles(['Sponsor', 'Owner']);

        $cache->tag(['persons']);
        $this->cache->save($cache->set($patrons));
        return $patrons;
    }

    public function getAllScribes(): array
    {
        $cache = $this->cache->getItem('scribes');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $scribes = $this->getBibroles(['Scribe']);

        $cache->tag(['persons']);
        $this->cache->save($cache->set($scribes));
        return $scribes;
    }

    public function getAllHistoricalPersons(): array
    {
        $cache = $this->cache->getItem('historical_persons');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $rawIds = $this->dbs->getHistoricalIds();
        $ids = self::getUniqueIds($rawIds, 'person_id');

        $persons = array_values($this->getMiniPersonsByIds($ids));

        usort($persons, ['AppBundle\Model\Person', 'sortByFullDescription']);

        $cache->tag(['persons']);
        $this->cache->save($cache->set($persons));
        return $persons;
    }

    private function getPersonsByOccupations(array $occupations): array
    {
        $rawOccupationPersons = $this->dbs->getIdsByOccupations($occupations);
        $personIds = self::getUniqueIds($rawOccupationPersons, 'person_id');
        $persons = $this->getMiniPersonsByIds($personIds);

        $occupationPersons = [];
        foreach ($rawOccupationPersons as $rawOccupationPerson) {
            $occupationPersons[$rawOccupationPerson['occupation']][] = $persons[$rawOccupationPerson['person_id']];
        }

        return $occupationPersons;
    }

    /**
     * Clear cache and update elasticsearch
     * @param array $ids person ids
     */
    public function resetPersons(array $ids): void
    {
        foreach ($ids as $id) {
            $this->cache->deleteItem('person_mini.' . $id);
            $this->cache->deleteItem('person_short.' . $id);
            $this->cache->deleteItem('person.' . $id);
            $person = $this->getPersonById($id);
            $this->ess->addPerson($person);
        }

        $this->cache->invalidateTags(['persons']);
    }

    public function addPerson(stdClass $data): Person
    {
        $this->dbs->beginTransaction();
        try {
            $personId = $this->dbs->insert();

            $newPerson = $this->updatePerson($personId, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newPerson;
    }

    public function updatePerson(int $id, stdClass $data, bool $new = false): Person
    {
        $this->dbs->beginTransaction();
        try {
            $person = $this->getPersonById($id);
            if ($person == null) {
                throw new NotFoundHttpException('Person with id ' . $id .' not found.');
            }

            // update person data
            $cacheReload = [
                'mini' => $new,
                'short' => $new,
                'extended' => $new,
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
            if (property_exists($data, 'types')) {
                $cacheReload['short'] = true;
                $this->updateOccupations($person, $data->types, 'types');
            }
            if (property_exists($data, 'functions')) {
                $cacheReload['short'] = true;
                $this->updateOccupations($person, $data->functions, 'functions');
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
            $this->clearCache('person', $id, $cacheReload);
            $this->cache->invalidateTags(['persons']);
            $newPerson = $this->getPersonById($id);

            $this->updateModified($new ? null : $person, $newPerson);

            // Reset cache and elasticsearch
            $this->ess->addPerson($newPerson);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            // Reset cache on elasticsearch error
            if (isset($newPerson)) {
                $this->resetPersons([$id]);
                $this->cache->invalidateTags(['persons']);
            }
            throw $e;
        }

        return $newPerson;
    }

    public function mergePersons(int $primaryId, int $secondaryId): Person
    {
        if ($primaryId == $secondaryId) {
            throw new BadRequestHttpException(
                'Persons with id ' . $primaryId .' and id ' . $secondaryId . ' are identical and cannot be merged.'
            );
        }
        $primary = $this->getPersonById($primaryId);
        $secondary = $this->getPersonById($secondaryId);

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
        if (empty($primary->getTypes()) && !empty($secondary->getTypes())) {
            $updates['types'] = $secondary->getTypes();
        }
        if (empty($primary->getFunctions()) && !empty($secondary->getFunctions())) {
            $updates['functions'] = $secondary->getFunctions();
        }
        if (empty($primary->getPublicComment()) && !empty($secondary->getPublicComment())) {
            $updates['publicComment'] = $secondary->getPublicComment();
        }
        if (empty($primary->getPrivateComment()) && !empty($secondary->getPrivateComment())) {
            $updates['privateComment'] = $secondary->getPrivateComment();
        }

        $manuscripts = $this->container->get('manuscript_manager')->getManuscriptsDependenciesByPerson($secondaryId, true);
        $occurrences = $this->container->get('occurrence_manager')->getOccurrencesDependenciesByPerson($secondaryId, true);

        if ((!empty($manuscripts) || !empty($occurrences)) && !$primary->getHistorical()) {
            $updates['historical'] = true;
        }
        // TODO: books, bookchapters, articles

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
                    $manuscript = $this->container->get('manuscript_manager')->updateManuscript(
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
            $this->delPerson($secondaryId);

            // Make sure indirect properties (manuscripts, occurrences) are reloaded correctly
            $this->cache->invalidateTags(['person.' . $primaryId]);
            $this->cache->deleteItem('person.' . $primaryId);
            $person = $this->getPersonById($primaryId);
            $this->cache->invalidateTags(['persons']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            // Reset caches and elasticsearch
            $this->resetPersons([$primaryId]);
            if (!empty($manuscripts)) {
                $this->container->get('manuscript_manager')->resetManuscripts(array_map(function ($manuscript) {
                    return $manuscript->getId();
                }, $manuscripts));
            }
            if (!empty($occurrences)) {
                $this->container->get('occurrence_manager')->resetOccurrences(array_map(function ($occurrence) {
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
            $manuscripts = $this->container->get('manuscript_manager')->getManuscriptsDependenciesByPerson($person->getId());
            $occurrences = $this->container->get('occurrence_manager')->getOccurrencesDependenciesByPerson($person->getId());
            if (!empty($manuscripts) || !empty($occurrences)) {
                throw new BadRequestHttpException('Persons linked to manuscripts or occurrences must be historical');
            }
        }
        $this->dbs->updateHistorical($person->getId(), $historical);
    }

    private function updateOccupations(Person $person, array $occupations, string $occupationType): void
    {
        foreach ($occupations as $occupation) {
            if (!is_object($occupation)
                || !property_exists($occupation, 'id')
                || !is_numeric($occupation->id)
            ) {
                throw new BadRequestHttpException('Incorrect occupations data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($occupations, $occupationType === 'types' ? $person->getTypes() : $person->getFunctions());

        if (count($delIds) > 0) {
            $this->dbs->delOccupations($person->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addOccupation($person->getId(), $addId);
        }
    }

    public function delPerson(int $personId): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $person = $this->getPersonById($personId);

            $this->dbs->delete($personId);

            $this->updateModified($person, null);

            // empty cache
            $this->clearCache('person', $personId);
            $this->cache->invalidateTags(['persons']);

            // delete from elastic search
            $this->ess->delPerson($person);

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
