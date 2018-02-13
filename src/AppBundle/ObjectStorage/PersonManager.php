<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Person;
use AppBundle\Model\FuzzyDate;

class PersonManager extends ObjectManager
{
    public function getPersonsByIds(array $ids): array
    {
        list($cached, $ids) = $this->getCache($ids, 'person');
        if (empty($ids)) {
            return $cached;
        }

        $persons = [];
        $rawPersons = $this->dbs->getPersonsByIds($ids);

        foreach ($rawPersons as $rawPerson) {
             $person = (new Person())
                ->setId($rawPerson['person_id'])
                ->setFirstName($rawPerson['first_name'])
                ->setLastName($rawPerson['last_name'])
                ->setExtra($rawPerson['extra'])
                ->setUnprocessed($rawPerson['unprocessed'])
                ->setBornDate(new FuzzyDate($rawPerson['born_date']))
                ->setDeathDate(new FuzzyDate($rawPerson['death_date']));
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
            if (isset($rawPerson['vgk'])) {
                $person->setVGK($rawPerson['vgk']);
            }
            if (isset($rawPerson['pbw'])) {
                $person->setPBW($rawPerson['pbw']);
            }
            if (isset($rawPerson['occupations'])) {
                $person->setOccupations(json_decode($rawPerson['occupations']));
            }
            $persons[$rawPerson['person_id']] = $person;
        }

        $this->setCache($persons, 'person');

        return $cached + $persons;
    }

    private function getBibroles(string $cacheKey, array $occupations): array
    {
        $cache = $this->cache->getItem($cacheKey);
        if ($cache->isHit()) {
            return $cache->get();
        }

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

        $cache->tag('persons');
        $cache->tag('occupations');
        $this->cache->save($cache->set($persons));
        return $persons;
    }

    public function getPatrons(): array
    {
        return $this->getBibroles('patrons', ['Sponsor', 'Owner']);
    }

    public function getScribes(): array
    {
        return $this->getBibroles('scribes', ['Scribe']);
    }

    private function getPersonsByOccupations(array $occupations): array
    {
        list($cached, $occupations) = $this->getCache($occupations, 'persons_by_occupation');
        if (empty($occupations)) {
            return $cached;
        }

        $rawOccupationPersons = $this->dbs->getIdsByOccupations($occupations);
        $personIds = self::getUniqueIds($rawOccupationPersons, 'person_id');
        $persons = $this->getPersonsByIds($personIds);

        $occupationPersons = [];
        foreach ($rawOccupationPersons as $rawOccupationPerson) {
            $occupationPersons[$rawOccupationPerson['occupation']][] = $persons[$rawOccupationPerson['person_id']];
        }

        $this->setCache($occupationPersons, 'persons_by_occupation');

        return $cached + $occupationPersons;
    }
}
