<?php

namespace AppBundle\ObjectStorage;

use stdClass;

use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Person;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            // identification
            if (isset($rawPerson['rgki'])) {
                $person->addRGK('I', $rawPerson['rgki']);
            }
            if (isset($rawPerson['rgkii'])) {
                $person->addRGK('II', $rawPerson['rgkii']);
            }
            if (isset($rawPerson['rgkiii'])) {
                $person->addRGK('III', $rawPerson['rgkiii']);
            }
            if (isset($rawPerson['vgh'])) {
                $person->setVGH($rawPerson['vgh']);
            }
            if (isset($rawPerson['pbw'])) {
                $person->setPBW($rawPerson['pbw']);
            }
            $persons[$rawPerson['person_id']] = $person;
        }

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
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, 'person_id');
        return $this->getShortPersonsByIds($ids);
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

        // Occurrences (scribe, patron, subject)
        // Types

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

    public function addPerson(stdClass $data): Person
    {
        $this->dbs->beginTransaction();
        try {
            $personId = $this->dbs->insert();

            $newPerson = $this->updatePerson($personId, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
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
            if (property_exists($data, 'rgk')) {
                $cacheReload['mini'] = true;
                $this->updateRGK($person, $data->rgk);
            }
            if (property_exists($data, 'vgh')) {
                $cacheReload['mini'] = true;
                $this->updateVGH($person, $data->vgh);
            }
            if (property_exists($data, 'pbw')) {
                $cacheReload['mini'] = true;
                $this->updatePBW($person, $data->pbw);
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
            $this->resetCache($cacheReload, 'person', $id);
            $newPerson = $this->getPersonById($id);

            $this->updateModified($new ? null : $person, $newPerson);

            // (re-)index in elastic search
            $this->ess->addPerson($newPerson);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            // Reset cache on elasticsearch error
            if (isset($newPerson)) {
                $this->resetCache($cacheReload, 'person', $id);
                $this->getPersonById($id);
            }
            throw $e;
        }

        return $newPerson;
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
        $this->dbs->updateHistorical($person->getId(), $historical);
    }

    private function updateRGK(Person $person, string $rgk): void
    {
        if (!empty($rgk) && !preg_match(
            '/^I{1,3}[.][\d]+(?:, I{1,3}[.][\d]+)*/',
            $rgk
        )) {
            throw new BadRequestHttpException('Incorrect rgk data.');
        }

        $rgkArray = empty($rgk) ? [] : explode(', ', $rgk);
        $volumeArray = [];
        foreach ($rgkArray as $rgkID) {
            $volume = explode('.', $rgkID);
            if (in_array($volume, $volumeArray)) {
                throw new BadRequestHttpException('Incorrect rgk data (duplicate).');
            } else {
                $volumeArray[] = $volume;
            }
        }

        $delRgks = array_diff($person->getRGK(), $rgkArray);
        $addRgks = array_diff($rgkArray, $person->getRGK());

        foreach ($delRgks as $delRgk) {
            $this->dbs->delRGKs(
                $person->getId(),
                array_map(
                    function ($delRgk) {
                        return explode('.', $delRgk)[0];
                    },
                    $delRgks
                )
            );
        }
        foreach ($addRgks as $addRgk) {
            list($rgkVolume, $rgkNumber) = explode('.', $addRgk);
            $this->dbs->addRGK($person->getId(), $rgkVolume, $rgkNumber);
        }
    }

    private function updateVGH(Person $person, string $vgh = null): void
    {
        if (!empty($vgh) && !preg_match(
            '/^[\d]+[.][A-Z](?:, [\d]+[.][A-Z])*$/',
            $vgh
        )) {
            throw new BadRequestHttpException('Incorrect vgh data.');
        }

        if (empty($vgh)) {
            $this->dbs->delVGH($person->getId());
        } else {
            $this->dbs->upsertVGH($person->getId(), $vgh);
        }
    }

    private function updatePBW(Person $person, string $pbw = null): void
    {
        if (!empty($pbw) && !is_numeric($pbw)) {
            throw new BadRequestHttpException('Incorrect pbw data.');
        }

        if (empty($pbw)) {
            $this->dbs->delPBW($person->getId());
        } else {
            $this->dbs->upsertPBW($person->getId(), $pbw);
        }
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
}
