<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Person;
use AppBundle\Model\FuzzyDate;

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
                $person->setRGK('I', $rawPerson['rgki']);
            }
            if (isset($rawPerson['rgkii'])) {
                $person->setRGK('II', $rawPerson['rgkii']);
            }
            if (isset($rawPerson['rgkiii'])) {
                $person->setRGK('III', $rawPerson['rgkiii']);
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
}
