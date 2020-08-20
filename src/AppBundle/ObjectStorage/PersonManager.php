<?php

namespace AppBundle\ObjectStorage;

use DateTime;
use Exception;
use stdClass;

use AppBundle\Model\FuzzyInterval;

use AppBundle\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Exceptions\DependencyException;
use AppBundle\Model\FuzzyDate;
use AppBundle\Model\Origin;
use AppBundle\Model\Person;
use AppBundle\Model\Poem;

/**
 * ObjectManager for persons
 * Servicename: person_manager
 */
class PersonManager extends ObjectEntityManager
{
    /**
     * Get persons with enough information to get an id and a full description (without offices)
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $persons = [];
        $rawPersons = $this->dbs->getBasicInfoByIds($ids);

        // filter out empty values, so no requests are done unnecessarily
        $locationIds = array_filter(self::getUniqueIds($rawPersons, 'location_id'));
        $locations = $this->container->get('location_manager')->get($locationIds);

        foreach ($rawPersons as $rawPerson) {
             $person = (new Person())
                 ->setId($rawPerson['person_id'])
                 ->setFirstName($rawPerson['first_name'])
                 ->setLastName($rawPerson['last_name'])
                 ->setExtra($rawPerson['extra'])
                 ->setUnprocessed($rawPerson['unprocessed'])
                 ->setHistorical($rawPerson['is_historical'])
                 ->setModern($rawPerson['is_modern'])
                 ->setDBBE($rawPerson['is_dbbe']);

            if ($rawPerson['location_id'] != null) {
                $person->setOrigin(Origin::fromLocation($locations[$rawPerson['location_id']]));
            }

            if (!empty($rawPerson['born_date'])) {
                $person->setBornDate(new FuzzyDate($rawPerson['born_date']));
            }

            if (!empty($rawPerson['death_date'])) {
                $person->setDeathDate(new FuzzyDate($rawPerson['death_date']));
            }

            if (!empty($rawPerson['attested_dates'])) {
                foreach (json_decode($rawPerson['attested_dates']) as $attestedDate) {
                    if ($attestedDate != null) {
                        $person->addAttestedDateOrInterval(FuzzyDate::fromDB($attestedDate));
                    }
                }
            }
            if (!empty($rawPerson['attested_intervals'])) {
                foreach (json_decode($rawPerson['attested_intervals']) as $attestedInterval) {
                    if ($attestedInterval != null) {
                        $person->addAttestedDateOrInterval(FuzzyInterval::fromDB($attestedInterval));
                    }
                }
            }
            $person->sortAttestedDatesAndIntervals();

            $persons[$rawPerson['person_id']] = $person;
        }

        $rawSelfDesignations = $this->dbs->getSelfDesignations($ids);
        $selfDesignations = $this->container->get('self_designation_manager')->getWithData($rawSelfDesignations);
        foreach ($rawSelfDesignations as $rawSelfDesignation) {
            $persons[$rawSelfDesignation['person_id']]
                ->addSelfDesignation($selfDesignations[$rawSelfDesignation['self_designation_id']]);
        }

        foreach ($persons as $person) {
            $person->sortSelfDesignations();
        }

        $this->setIdentifications($persons);

        $this->setPublics($persons);

        return $persons;
    }

    /**
     * Get persons with enough information to get an id and a full description with offices
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
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

        foreach ($persons as $person) {
            $person->sortOfficesWithParents();
        }

        $this->setComments($persons);

        $this->setManagements($persons);

        $this->setCreatedAndModifiedDates($persons);

        return $persons;
    }

    /**
     * Get a single person with all information
     * @param  int    $id
     * @return Person
     */
    public function getFull(int $id): Person
    {
        // Get basic person information
        $persons = $this->getShort([$id]);
        if (count($persons) == 0) {
            throw new NotFoundHttpException('Person with id ' . $id .' not found.');
        }

        $this->setBibliographies($persons);

        $person = $persons[$id];

        // Manuscript roles
        $rawManuscripts = $this->dbs->getManuscriptsAsRoles([$id]);
        $manuscriptIds = self::getUniqueIds($rawManuscripts, 'manuscript_id');
        $occurrenceIds = self::getUniqueIds($rawManuscripts, 'occurrence_id');
        $roleIds = self::getUniqueIds($rawManuscripts, 'role_id');

        $manuscripts = $this->container->get('manuscript_manager')->getMini($manuscriptIds);
        $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);
        $roles = $this->container->get('role_manager')->get($roleIds);

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

        // Occurrence roles
        $rawOccurrences = $this->dbs->getOccurrencesAsRoles([$id]);
        $occurrenceIds = self::getUniqueIds($rawOccurrences, 'occurrence_id');
        $roleIds = self::getUniqueIds($rawOccurrences, 'role_id');

        $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);
        $roles = $this->container->get('role_manager')->get($roleIds);

        foreach ($rawOccurrences as $rawOccurrence) {
            $person
                ->addDocumentRole(
                    'occurrence',
                    $roles[$rawOccurrence['role_id']],
                    $occurrences[$rawOccurrence['occurrence_id']]
                );
        }

        // occurrence subjects
        // Add 'subject' as pseudo role
        $rawOccurrences = $this->dbs->getOccurrencesAsSubjects([$id]);
        $occurrenceIds = self::getUniqueIds($rawOccurrences, 'occurrence_id');

        $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);
        $role = $this->container->get('role_manager')->getWithData([[
            'role_id' => 0,
            'role_usage' => json_encode(['occurrence']),
            'role_system_name' => 'subject',
            'role_name' => 'Subject',
            'role_is_contributor_role' => false,
            'role_has_rank' => false,
            'role_order' => null,
        ]])[0];

        foreach ($rawOccurrences as $rawOccurrence) {
            $person
                ->addDocumentRole(
                    'occurrence',
                    $role,
                    $occurrences[$rawOccurrence['occurrence_id']]
                );
        }

        // Type roles
        $rawTypes = $this->dbs->getTypesAsRoles([$id]);
        $typeIds = self::getUniqueIds($rawTypes, 'type_id');
        $roleIds = self::getUniqueIds($rawTypes, 'role_id');

        $types = $this->container->get('type_manager')->getMini($typeIds);
        $roles = $this->container->get('role_manager')->get($roleIds);

        foreach ($rawTypes as $rawType) {
            $person
                ->addDocumentRole(
                    'type',
                    $roles[$rawType['role_id']],
                    $types[$rawType['type_id']]
                );
        }

        // type subjects
        // Add 'subject' as pseudo role
        $rawTypes = $this->dbs->getTypesAsSubjects([$id]);
        $typeIds = self::getUniqueIds($rawTypes, 'type_id');

        $types = $this->container->get('type_manager')->getMini($typeIds);
        $role = $this->container->get('role_manager')->getWithData([[
            'role_id' => 0,
            'role_usage' => json_encode(['type']),
            'role_system_name' => 'subject',
            'role_name' => 'Subject',
            'role_is_contributor_role' => false,
            'role_has_rank' => false,
            'role_order' => null,
        ]])[0];

        foreach ($rawTypes as $rawType) {
            $person
                ->addDocumentRole(
                    'type',
                    $role,
                    $types[$rawType['type_id']]
                );
        }

        // Article roles
        $raws = $this->dbs->getArticles([$id]);
        $ids = self::getUniqueIds($raws, 'article_id');
        $roleIds = self::getUniqueIds($raws, 'role_id');

        $articles = $this->container->get('article_manager')->getMini($ids);
        $roles = $this->container->get('role_manager')->get($roleIds);

        foreach ($raws as $raw) {
            $person
                ->addDocumentRole(
                    'article',
                    $roles[$raw['role_id']],
                    $articles[$raw['article_id']]
                );
        }

        // Book roles
        $raws = $this->dbs->getBooks([$id]);
        $ids = self::getUniqueIds($raws, 'book_id');
        $roleIds = self::getUniqueIds($raws, 'role_id');

        $books = $this->container->get('book_manager')->getMini($ids);
        $roles = $this->container->get('role_manager')->get($roleIds);

        foreach ($raws as $raw) {
            $person
                ->addDocumentRole(
                    'book',
                    $roles[$raw['role_id']],
                    $books[$raw['book_id']]
                );
        }

        // Book chapter roles
        $raws = $this->dbs->getBookChapters([$id]);
        $ids = self::getUniqueIds($raws, 'book_chapter_id');
        $roleIds = self::getUniqueIds($raws, 'role_id');

        $bookChapters = $this->container->get('book_chapter_manager')->getMini($ids);
        $roles = $this->container->get('role_manager')->get($roleIds);

        foreach ($raws as $raw) {
            $person
                ->addDocumentRole(
                    'bookChapter',
                    $roles[$raw['role_id']],
                    $bookChapters[$raw['book_chapter_id']]
                );
        }

        // Manuscript contents
        $rawManuscripts = $this->dbs->getManuscriptsAsContents($id);
        $manuscriptIds = self::getUniqueIds($rawManuscripts, 'manuscript_id');
        $manuscripts = $this->container->get('manuscript_manager')->getMini($manuscriptIds);
        foreach ($rawManuscripts as $rawManuscript) {
            $person->addManuscriptContent($manuscripts[$rawManuscript['manuscript_id']]);
        }

        return $person;
    }

    /**
     * Get all persons that are dependent on a specific office
     * @param  int    $officeId
     * @param  string $method
     * @return array
     */
    public function getOfficeDependencies(int $officeId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOfficeId($officeId), $method);
    }

    /**
     * Get all persons that are dependent on a specific office or one of its children
     * @param  int    $officeId
     * @param  string $method
     * @return array
     */
    public function getOfficeDependenciesWithChildren(int $officeId, string $method): array
    {
        return $this->getDependencies(
            $this->dbs->getDepIdsByOfficeIdWithChildren($officeId),
            $method
        );
    }

    /**
     * Get all persons that are dependent on a specific region
     * @param  int    $regionId
     * @param  string $method
     * @return array
     */
    public function getRegionDependencies(int $regionId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByRegionId($regionId), $method);
    }

    /**
     * Get all persons that are dependent on a specific region or one of its children
     * @param  int    $regionId
     * @param  string $method
     * @return array
     */
    public function getRegionDependenciesWithChildren(int $regionId, string $method): array
    {
        return $this->getDependencies(
            $this->dbs->getDepIdsByRegionIdWithChildren($regionId),
            $method
        );
    }

    /**
     * Get all persons that are dependent on a specific self designation
     * @param  int    $selfDesignationId
     * @param  string $method
     * @return array
     */
    public function getSelfDesignationDependencies(int $selfDesignationId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsBySelfDesignationId($selfDesignationId), $method);
    }

    /**
     * Get all persons that are dependent on a specific manuscript
     * @param  int    $manuscriptId
     * @param  string $method
     * @return array
     */
    public function getManuscriptDependencies(int $manuscriptId, string $method): array
    {
        return $this->getDependencies(
            $this->dbs->getDepIdsByManuscriptId($manuscriptId),
            $method
        );
    }

    /**
     * Get all persons that are dependent on a specific occurrence
     * @param  int    $occurrenceId
     * @param  string $method
     * @return array
     */
    public function getOccurrenceDependencies(int $occurrenceId, string $method): array
    {
        return $this->getDependencies(
            $this->dbs->getDepIdsByOccurrenceId($occurrenceId),
            $method
        );
    }

    /**
     * Get all persons that are dependent on a specific type
     * @param  int    $typeId
     * @param  string $method
     * @return array
     */
    public function getTypeDependencies(int $typeId, string $method): array
    {
        return $this->getDependencies(
            $this->dbs->getDepIdsByTypeId($typeId),
            $method
        );
    }

    private function getByType(string $type): array
    {
        $rawIds = [];
        switch ($type) {
            case 'historical':
                $rawIds = $this->dbs->getHistoricalIds();
                break;
            case 'modern':
                $rawIds = $this->dbs->getModernIds();
                break;
            case 'dbbe':
                $rawIds = $this->dbs->getDBBEIds();
                break;
        }
        $ids = self::getUniqueIds($rawIds, 'person_id');

        $persons = array_values($this->getMini($ids));

        usort($persons, function ($a, $b) {
            return strcmp($a->getFullDescription(), $b->getFullDescription());
        });

        return $persons;
    }

    /**
     * @return array
     */
    public function getAllHistoricalShortJson(): array
    {
        return $this->wrapArrayCache(
            'historical_persons',
            ['persons'],
            function () {
                return ArrayToJson::arrayToShortJson($this->getByType('historical'));
            }
        );
    }

    /**
     * @return array
     */
    public function getAllModernShortJson(): array
    {
        return $this->wrapArrayCache(
            'modern_persons',
            ['persons'],
            function () {
                return ArrayToJson::arrayToShortJson($this->getByType('modern'));
            }
        );
    }

    /**
     * @return array
     */
    public function getAllDBBEShortJson(): array
    {
        return $this->wrapArrayCache(
            'dbbe_persons',
            ['persons'],
            function () {
                return ArrayToJson::arrayToShortJson($this->getByType('dbbe'));
            }
        );
    }

    /**
     * Add a new Person
     * @param  stdClass $data
     * @return Person
     */
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

    /**
     * Update a new or existing person
     * @param  int      $id
     * @param  stdClass $data
     * @param  bool     $isNew Indicate whether this is a new person
     * @return Person
     */
    public function update(int $id, stdClass $data, bool $isNew = false): Person
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $old = $this->getFull($id);

            // update person data
            $changes = [
                'mini' => $isNew,
                'short' => $isNew,
                'full' => $isNew,
            ];
            if (property_exists($data, 'public')) {
                if (!is_bool($data->public)) {
                    throw new BadRequestHttpException('Incorrect public data.');
                }
                $changes['mini'] = true;
                $this->updatePublic($old, $data->public);
            }
            if (property_exists($data, 'firstName')) {
                if (!is_string($data->firstName)) {
                    throw new BadRequestHttpException('Incorrect first name data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateFirstName($id, $data->firstName);
            }
            if (property_exists($data, 'lastName')) {
                if (!is_string($data->lastName)) {
                    throw new BadRequestHttpException('Incorrect last name data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateLastName($id, $data->lastName);
            }
            if (property_exists($data, 'selfDesignations')) {
                if (!is_array($data->selfDesignations)) {
                    throw new BadRequestHttpException('Incorrect self designation data.');
                }
                $changes['mini'] = true;
                $this->updateSelfDesignations($old, $data->selfDesignations);
            }
            if (property_exists($data, 'origin')) {
                if (!is_object($data->origin) && !empty($data->origin)) {
                    throw new BadRequestHttpException('Incorrect origin data.');
                }
                $changes['mini'] = true;
                $this->updateOrigin($old, $data->origin);
            }
            // Helper for regionmanager -> merge
            if (property_exists($data, 'region')) {
                if (empty($data->region) || !is_object($data->region)) {
                    throw new BadRequestHttpException('Incorrect region data.');
                }
                $changes['mini'] = true;
                $this->updateRegion($old, $data->region);
            }
            if (property_exists($data, 'extra')) {
                if (!is_string($data->extra)) {
                    throw new BadRequestHttpException('Incorrect extra data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateExtra($id, $data->extra);
            }
            if (property_exists($data, 'unprocessed')) {
                if (!is_string($data->unprocessed)) {
                    throw new BadRequestHttpException('Incorrect unprocessed data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateUnprocessed($id, $data->unprocessed);
            }
            if (property_exists($data, 'historical')) {
                if (!is_bool($data->historical)) {
                    throw new BadRequestHttpException('Incorrect historical data.');
                }
                $changes['mini'] = true;
                $this->updateHistorical($old, $data->historical);
            }
            if (property_exists($data, 'modern')) {
                if (!is_bool($data->modern)) {
                    throw new BadRequestHttpException('Incorrect modern data.');
                }
                $changes['mini'] = true;
                $this->updateModern($old, $data->modern);
            }
            if (property_exists($data, 'dbbe')) {
                if (!is_bool($data->dbbe)) {
                    throw new BadRequestHttpException('Incorrect dbbe data.');
                }
                $changes['mini'] = true;
                $this->updateDBBE($old, $data->dbbe);
            }
            if (property_exists($data, 'dates')) {
                $this->validateDates($data->dates);
                $changes['mini'] = true;
                $this->updateDates($old, $data->dates);
            }
            $this->updateIdentificationwrapper($old, $data, $changes, 'mini', 'person');
            if (property_exists($data, 'offices')) {
                if (!is_array($data->offices)) {
                    throw new BadRequestHttpException('Incorrect office data.');
                }
                $changes['short'] = true;
                $this->updateOffices($old, $data->offices);
            }
            if (property_exists($data, 'bibliography')) {
                $changes['full'] = true;
                $this->updateBibliography($old, $data->bibliography);
            }
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
            $this->updateManagementwrapper($old, $data, $changes, 'short');

            // Throw error if none of above matched
            if (!in_array(true, $changes)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            $this->cache->invalidateTags([$this->entityType . 's']);

            // Reset elasticsearch
            $this->ess->add($new);

            // update Elastic dependencies
            if ($changes['mini']) {
                foreach ([
                        'manuscript',
                        'occurrence',
                        'type',
                        'article',
                        'book',
                        'book_chapter',
                    ] as $entity) {
                    $this->container->get($entity .'_manager')->updateElasticByIds(
                        $this->container->get($entity .'_manager')->getPersonDependencies($id, 'getId')
                    );
                }
                $this->container->get('manuscript_manager')->updateElasticByIds(
                    $this->container->get('manuscript_manager')->getPersonContentDependenciesWithChildren($id, 'getId')
                );
            }

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

            // Reset Elastic dependencies
            if (isset($changes) && $changes['mini']) {
                foreach ([
                        'manuscript',
                        'occurrence',
                        'type',
                        'article',
                        'book',
                        'book_chapter',
                    ] as $entity) {
                    $this->container->get($entity .'_manager')->updateElasticByIds(
                        $this->container->get($entity .'_manager')->getPersonDependencies($id, 'getId')
                    );
                }
            }
            throw $e;
        }

        return $new;
    }

    /**
     * Merge two persons
     * @param  int $primaryId
     * @param  int $secondaryId
     * @return Person
     */
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
        if (empty($primary->getSelfDesignations()) && !empty($secondary->getSelfDesignations())) {
            $updates['selfDesignations'] = ArrayToJson::arrayToShortJson($secondary->getSelfDesignations());
        }
        $identifiers = $this->container->get('identifier_manager')->getByType('person');
        foreach ($identifiers as $identifier) {
            if (empty($primary->getIdentifications()[$identifier->getSystemName()])
                && !empty($secondary->getIdentifications()[$identifier->getSystemName()])
            ) {
                $updates[$identifier->getSystemName()] =
                    implode(', ', $secondary->getIdentifications()[$identifier->getSystemName()][1]);
            }
        }
        if (empty($primary->getOfficesWithParents()) && !empty($secondary->getOfficesWithParents())) {
            $updates['offices'] = ArrayToJson::arrayToShortJson($secondary->getOfficesWithParents());
        }
        if (empty($primary->getPublicComment()) && !empty($secondary->getPublicComment())) {
            $updates['publicComment'] = $secondary->getPublicComment();
        }
        if (empty($primary->getPrivateComment()) && !empty($secondary->getPrivateComment())) {
            $updates['privateComment'] = $secondary->getPrivateComment();
        }

        $manuscripts = $this->container->get('manuscript_manager')->getPersonDependencies($secondaryId, 'getShort');
        $occurrences = $this->container->get('occurrence_manager')->getPersonDependencies($secondaryId, 'getShort');
        $types = $this->container->get('type_manager')->getPersonDependencies($secondaryId, 'getShort');
        $poems = $occurrences + $types;

        if ((!empty($manuscripts) || !empty($occurrences) || !empty($types)) && !$primary->getHistorical()) {
            $updates['historical'] = true;
        }

        $articles = $this->container->get('article_manager')->getPersonDependencies($secondaryId, 'getShort');
        $books = $this->container->get('book_manager')->getPersonDependencies($secondaryId, 'getShort');
        $bookChapters = $this->container->get('book_chapter_manager')->getPersonDependencies($secondaryId, 'getShort');
        if (!empty($articles) || (!empty($books) || !empty($bookChapters)) && !$primary->getModern()) {
            $updates['modern'] = true;
        }

        $contentIds = $this->container->get('content_manager')->getPersonDependencies($secondaryId);

        $this->dbs->beginTransaction();
        try {
            if (!empty($updates)) {
                $primary = $this->update($primaryId, json_decode(json_encode($updates)));
            }

            if (!empty($manuscripts)) {
                foreach ($manuscripts as $manuscript) {
                    $personRoles = $manuscript->getPersonRoles();
                    $update = $this->getMergeUpdate($personRoles, $primaryId, $secondaryId);

                    $contributorRoles = $manuscript->getContributorRoles();
                    $update += $this->getMergeUpdate($contributorRoles, $primaryId, $secondaryId);

                    if (!empty($update)) {
                        $this->container->get('manuscript_manager')->update(
                            $manuscript->getId(),
                            json_decode(json_encode($update))
                        );
                    }
                }
            }
            if (!empty($poems)) {
                $this->dbs->mergePoemBibroles($primaryId, $secondaryId);
                $this->dbs->mergePoemSubjects($primaryId, $secondaryId);
                $esData = [];
                $manuscriptIds = [];
                foreach ($poems as $poem) {
                    $old = (new Poem())
                        ->setId($poem->getId());
                    $new = (new Poem())
                        ->setId($poem->getId());

                    $personRoles = $poem->getPersonRoles();
                    $newPersonRoles = $personRoles;
                    $found = false;
                    foreach ($personRoles as $roleName => $roleAndPersons) {
                        foreach ($roleAndPersons[1] as $id => $person) {
                            if ($id == $secondaryId) {
                                unset($newPersonRoles[$roleName][1][$secondaryId]);
                                $newPersonRoles[$roleName][1][$primaryId] = $primary;
                                $found = true;
                                break;
                            }
                        }
                        if ($found) {
                            break;
                        }
                    }
                    if ($found) {
                        $old->setPersonRoles($personRoles);
                        $new->setPersonRoles($newPersonRoles);
                        if (get_class($poem == 'AppBundle\Model\Occurrences')) {
                            $manuscriptIds[] = $poem->getManuscript()->getId();
                        }
                    }

                    $contributorRoles = $poem->getContributorRoles();
                    $newContributorRoles = $contributorRoles;
                    $found = false;
                    foreach ($contributorRoles as $roleName => $roleAndPersons) {
                        foreach ($roleAndPersons[1] as $id => $person) {
                            if ($id == $secondaryId) {
                                unset($newContributorRoles[$roleName][1][$secondaryId]);
                                $newContributorRoles[$roleName][1][$primaryId] = $primary;
                                $found = true;
                                break;
                            }
                        }
                        if ($found) {
                            break;
                        }
                    }
                    if ($found) {
                        $old->setContributorRoles($contributorRoles);
                        $new->setContributorRoles($newContributorRoles);
                    }

                    $subjects = $poem->getSubjects();
                    $newSubjects = $subjects;
                    $new->delSubjectById($secondaryId);
                    if (count($subjects) != count($newSubjects)) {
                        $newSubjects->addSubject($primary)->sortSubjects();
                        $old->setSubjects($subjects);
                        $new->setSubjects($newSubjects);
                    }

                    $this->updateModified($old, $new);

                    $esData[$new->getId()] = [
                        'id' => $new->getId(),
                    ];
                    foreach ($new->getPersonRoles() as $roleName => $personRole) {
                        $esData[$roleName] = ArrayToJson::arrayToShortJson($personRole[1]);
                    }
                    foreach ($new->getPublicPersonRoles() as $roleName => $personRole) {
                        $esData[$roleName . '_public'] = ArrayToJson::arrayToShortJson($personRole[1]);
                    }
                    foreach ($new->getContributorRoles() as $roleName => $contributorRole) {
                        $esData[$roleName] = ArrayToJson::arrayToShortJson($contributorRole[1]);
                    }
                    foreach ($new->getPublicContributorRoles() as $roleName => $contributorRole) {
                        $esData[$roleName . '_public'] = ArrayToJson::arrayToShortJson($contributorRole[1]);
                    }
                    if (!empty($new->subjects)) {
                        $esData['subject'] = ArrayToJson::arrayToShortJson($new->subjects);
                    }
                }
                $this->container->get('occurrence_elastic_service')->updateMultiple(
                    array_filter(
                        $esData,
                        function ($key) use ($occurrences) {
                            return in_array($key, array_keys($occurrences));
                        },
                        ARRAY_FILTER_USE_KEY
                    )
                );
                $this->container->get('type_elastic_service')->updateMultiple(
                    array_filter(
                        $esData,
                        function ($key) use ($types) {
                            return in_array($key, array_keys($types));
                        },
                        ARRAY_FILTER_USE_KEY
                    )
                );

                // Reindex manuscripts (occurrencePersonRoles)
                $this->container->get('manuscript_manager')->updateElasticByIds($manuscriptIds);
            }
            if (!empty($articles)) {
                foreach ($articles as $article) {
                    $personRoles = $article->getPersonRoles();
                    $update = $this->getMergeUpdate($personRoles, $primaryId, $secondaryId);
                    if (!empty($update)) {
                        $this->container->get('article_manager')->update(
                            $article->getId(),
                            json_decode(json_encode($update))
                        );
                    }
                }
            }
            if (!empty($books)) {
                foreach ($books as $book) {
                    $personRoles = $book->getPersonRoles();
                    $update = $this->getMergeUpdate($personRoles, $primaryId, $secondaryId);
                    if (!empty($update)) {
                        $this->container->get('book_manager')->update(
                            $book->getId(),
                            json_decode(json_encode($update))
                        );
                    }
                }
            }
            if (!empty($bookChapters)) {
                foreach ($bookChapters as $bookChapter) {
                    $personRoles = $bookChapter->getPersonRoles();
                    $update = $this->getMergeUpdate($personRoles, $primaryId, $secondaryId);
                    if (!empty($update)) {
                        $this->container->get('book_chapter_manager')->update(
                            $bookChapter->getId(),
                            json_decode(json_encode($update))
                        );
                    }
                }
            }

            if (!empty($contentIds)) {
                foreach ($contentIds as $contentId) {
                    $this->container->get('content_manager')->update(
                        $contentId,
                        json_decode(json_encode(['individualPerson' => ['id' => $primaryId]]))
                    );
                }
            }

            $this->delete($secondaryId);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // Reset elasticsearch
            $this->updateElasticByIds([$primaryId]);

            $this->container->get('manuscript_manager')->updateElasticByIds(array_keys($manuscripts));
            $this->container->get('occurrence_manager')->updateElasticByIds(array_keys($occurrences));
            $this->container->get('type_manager')->updateElasticByIds(array_keys($types));
            $this->container->get('article_manager')->updateElasticByIds(array_keys($articles));
            $this->container->get('book_manager')->updateElasticByIds(array_keys($books));
            $this->container->get('book_chapter_manager')->updateElasticByIds(array_keys($bookChapters));

            throw $e;
        }

        return $primary;
    }

    /**
     * @param Person $person
     * @param array  $selfDesignations
     */
    protected function updateSelfDesignations(Person $person, array $selfDesignations): void
    {
        foreach ($selfDesignations as $selfDesignation) {
            if (!is_object($selfDesignation)
                || !property_exists($selfDesignation, 'id')
                || !is_numeric($selfDesignation->id)
            ) {
                throw new BadRequestHttpException('Incorrect self designation data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($selfDesignations, $person->getSelfDesignations());

        if (count($delIds) > 0) {
            $this->dbs->delSelfDesignations($person->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addSelfDesignation($person->getId(), $addId);
        }
    }

    /**
     * @param Person        $person
     * @param stdClass|null $origin
     */
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

    /**
     * Helper for regionmanager -> merge
     * @param Person   $person
     * @param stdClass $region
     */
    private function updateRegion(Person $person, stdClass $region): void
    {
        if (!property_exists($region, 'id') || !is_numeric($region->id)) {
            throw new BadRequestHttpException('Incorrect region data.');
        }
        $this->dbs->updateRegion($person->getId(), $region->id);
    }

    /**
     * @param Person $person
     * @param bool   $historical
     */
    private function updateHistorical(Person $person, bool $historical): void
    {
        if (!$historical) {
            $manuscripts = $this->container->get('manuscript_manager')->getPersonDependencies($person->getId());
            $occurrences = $this->container->get('occurrence_manager')->getPersonDependencies($person->getId());
            $types = $this->container->get('type_manager')->getPersonDependencies($person->getId());
            if (!empty($manuscripts) || !empty($occurrences) || !empty($types)) {
                throw new BadRequestHttpException(
                    'This person is linked linked to a manuscript, occurrence or type, so must be historical.'
                );
            }
        }
        $this->dbs->updateHistorical($person->getId(), $historical);
    }

    /**
     * @param Person $person
     * @param bool   $modern
     */
    private function updateModern(Person $person, bool $modern): void
    {
        if (!$modern) {
            $articles = $this->container->get('article_manager')->getPersonDependencies($person->getId());
            $books = $this->container->get('book_manager')->getPersonDependencies($person->getId());
            $bookChapters = $this->container->get('book_chapter_manager')->getPersonDependencies($person->getId());
            if (!empty($articles) || !empty($books) || !empty($bookChapters)) {
                throw new BadRequestHttpException(
                    'This person is linked to a book, book chapter or article, so must be modern.'
                );
            }
        }
        $this->dbs->updateModern($person->getId(), $modern);
    }

    /**
     * @param Person $person
     * @param bool   $dbbe
     */
    private function updateDBBE(Person $person, bool $dbbe): void
    {
        $this->dbs->updateDBBE($person->getId(), $dbbe);
    }

    protected function validateDates($dates): void
    {
        parent::validateDates($dates);

        $bornItems = array_filter($dates, function ($item) {return $item->type == 'born';});
        if (count($bornItems) > 1) {
            throw new BadRequestHttpException('Too many born dates (only one allowed).');
        }
        foreach ($bornItems as $bornItem) {
            if ($bornItem->isInterval) {
                throw new BadRequestHttpException('Only dates are allowed for born dates.');
            }
        }
        $diedItems = array_filter($dates, function ($item) {return $item->type == 'died';});
        if (count($diedItems) > 1) {
            throw new BadRequestHttpException('Too many died dates (only one allowed).');
        }
        foreach ($diedItems as $diedItem) {
            if ($diedItem->isInterval) {
                throw new BadRequestHttpException('Only dates are allowed for died dates.');
            }
        }
        $attestedItems = array_filter($dates, function ($item) {return $item->type == 'attested';});
        if (count($bornItems) + count($diedItems) + count($attestedItems) != count($dates)) {
            throw new BadRequestHttpException('Invalid date type used.');
        }
    }

    private function updateDates(Person $person, array $dates): void
    {
        $bornItems = array_values(array_filter($dates, function ($item) {return $item->type == 'born';}));
        if ($person->getBornDate() == null && count($bornItems) != 0) {
            $this->dbs->insertDate($person->getId(), 'born', $this->getDBDate($bornItems[0]->date));
        } elseif ($person->getBornDate() != null && count($bornItems) != 0) {
            if ($person->getBornDate()->getFloor() != new DateTime($bornItems[0]->date->floor)
                || $person->getBornDate()->getCeiling() != new DateTime($bornItems[0]->date->ceiling)
            ) {
                $this->dbs->updateDate($person->getId(), 'born', $this->getDBDate($bornItems[0]->date));
            }
        } elseif ($person->getBornDate() != null && count($bornItems) == 0) {
            $this->dbs->deleteDateOrInterval($person->getId(), 'born');
        }

        $diedItems = array_values(array_filter($dates, function ($item) {return $item->type == 'died';}));
        if ($person->getDeathDate() == null && count($diedItems) != 0) {
            $this->dbs->insertDate($person->getId(), 'died', $this->getDBDate($diedItems[0]->date));
        } elseif ($person->getDeathDate() != null && count($diedItems) != 0) {
            if ($person->getDeathDate()->getFloor() != new DateTime($diedItems[0]->date->floor)
                || $person->getDeathDate()->getCeiling() != new DateTime($diedItems[0]->date->ceiling)
            ) {
                $this->dbs->updateDate($person->getId(), 'died', $this->getDBDate($diedItems[0]->date));
            }
        } elseif ($person->getDeathDate() != null && count($diedItems) == 0) {
            $this->dbs->deleteDateOrInterval($person->getId(), 'died');
        }

        $attestedItems = array_values(array_filter($dates, function ($item) {return $item->type == 'attested';}));
        $oldIndexes = [];
        $newIndexes = [];
        foreach ($person->getAttestedDatesAndIntervals() as $oldIndex => $old) {
            foreach ($attestedItems as $newIndex => $new) {
                if (in_array($newIndex, $newIndexes)) {
                    continue;
                }
                if (get_class($old) === 'AppBundle\Model\FuzzyDate' && !$new->isInterval) {
                    if ($old->getFloor() == new DateTime($new->date->floor)
                        && $old->getCeiling() == new DateTime($new->date->ceiling)
                    ) {
                        $oldIndexes[] = $oldIndex;
                        $newIndexes[] = $newIndex;
                        break;
                    }
                } elseif (get_class($old) === 'AppBundle\Model\FuzzyInterval' && $new->isInterval) {
                    if ($old->getStart()->getFloor() == new DateTime($new->interval->start->floor)
                        && $old->getStart()->getCeiling() == new DateTime($new->interval->start->ceiling)
                        && $old->getEnd()->getFloor() == new DateTime($new->interval->end->floor)
                        && $old->getEnd()->getCeiling() == new DateTime($new->interval->end->ceiling)
                    ) {
                        $oldIndexes[] = $oldIndex;
                        $newIndexes[] = $newIndex;
                        break;
                    }
                }
            }
        }

        if (count($person->getAttestedDatesAndIntervals()) == count($oldIndexes)) {
            if (count($attestedItems) != count($newIndexes)) {
                foreach ($attestedItems as $newIndex => $new) {
                    if (!in_array($newIndex, $newIndexes)) {
                        if (!$new->isInterval) {
                            $this->dbs->insertDate($person->getId(), 'attested', $this->getDBDate($new->date));
                        } else {
                            $this->dbs->insertInterval($person->getId(), 'attested', $this->getDBInterval($new->interval));
                        }
                    }
                }
            }
        } else {
            $this->dbs->deleteDateOrInterval($person->getId(), 'attested');
            foreach ($attestedItems as $newIndex => $new) {
                if (!$new->isInterval) {
                    $this->dbs->insertDate($person->getId(), 'attested', $this->getDBDate($new->date));
                } else {
                    $this->dbs->insertInterval($person->getId(), 'attested', $this->getDBInterval($new->interval));
                }
            }
        }
    }

    /**
     * @param Person $person
     * @param array  $offices
     */
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

    public function updateElasticSelfDesignation(array $ids): void
    {
        if (!empty($ids)) {
            $rawSelfDesignations = $this->dbs->getSelfDesignations($ids);
            if (!empty($rawSelfDesignations)) {
                $selfDesignations = $this->container->get('self_designation_manager')->getWithData($rawSelfDesignations);
                $data = [];

                foreach ($rawSelfDesignations as $rawSelfDesignation) {
                    if (!isset($data[$rawSelfDesignation['person_id']])) {
                        $data[$rawSelfDesignation['person_id']] = [
                            'id' => $rawSelfDesignation['person_id'],
                            'self_designation' => [],
                        ];
                    }
                    $data[$rawSelfDesignation['person_id']]['self_designation'][] =
                        $selfDesignations[$rawSelfDesignation['self_designation_id']]->getShortJson();
                }

                $this->ess->updateMultiple($data);
            }
        }
    }

    /**
     * Construct the data to update a dependent entity when merging persons
     * @param  array $personRoles
     * @param  int   $primaryId
     * @param  int   $secondaryId
     * @return array
     */
    private static function getMergeUpdate(array $personRoles, int $primaryId, int $secondaryId): array
    {
        $update = [];
        foreach ($personRoles as $personRole) {
            list($role, $persons) = $personRole;
            if (in_array($secondaryId, array_keys($persons))) {
                $update[$role->getSystemName()] = [];
                foreach (array_keys($persons) as $id) {
                    if ($id == $secondaryId) {
                        // Prevent duplicate values
                        if (!in_array($primaryId, array_keys($persons))) {
                            $update[$role->getSystemName()][] = ['id' => $primaryId];
                        }
                    } else {
                        $update[$role->getSystemName()][] = ['id' => $id];
                    }
                }
            }
        }
        return $update;
    }

    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $old = $this->getFull($id);

            // get dependencies
            $dependencies = [];
            foreach ([
                    'manuscript',
                    'occurrence',
                    'type',
                    'article',
                    'book',
                    'book_chapter',
                ] as $entity) {
                $ids = $this->container->get($entity .'_manager')->getPersonDependencies($id, 'getId');
                if (!empty($ids)) {
                    $dependencies[$entity] = $ids;
                }
            }

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            // remove from elasticsearch
            $this->updateElasticByIds([$id]);

            $this->cache->invalidateTags([$this->entityType . 's']);

            // update dependencies
            foreach ($dependencies as $entity => $ids) {
                $this->container->get($entity .'_manager')->updateElasticByIds($ids);
            }

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
