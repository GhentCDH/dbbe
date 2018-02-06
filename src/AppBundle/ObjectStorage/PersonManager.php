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
            $persons[$rawPerson['person_id']] = $person;
        }

        $this->setCache($persons, 'person');

        return $cached + $persons;
    }
}
