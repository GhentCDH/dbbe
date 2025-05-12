<?php

namespace App\ObjectStorage;

use DateTime;
use Exception;
use stdClass;

use App\Model\FuzzyInterval;

use App\Utils\ArrayToJson;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\ElasticSearchService\ElasticOccurrenceService;
use App\ElasticSearchService\ElasticTypeService;
use App\Exceptions\DependencyException;
use App\Model\FuzzyDate;
use App\Model\Origin;
use App\Model\Person;
use App\Model\Poem;
use App\Model\Role;

/**
 * ObjectManager for persons
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
        $locations = $this->container->get(LocationManager::class)->get($locationIds);

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
        $selfDesignations = $this->container->get(SelfDesignationManager::class)->getWithData($rawSelfDesignations);
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
            $roles = $this->container->get(RoleManager::class)->get($roleIds);

            foreach ($rawRoles as $rawRole) {
                $persons[$rawRole['person_id']]->addRole($roles[$rawRole['role_id']]);
            }
        }

        // Pseudo roles
        $contentRole = Role::getContentRole();
        $subjectRole = Role::getSubjectRole();

        // Manuscript contents
        $rawManuscripts = $this->dbs->getManuscriptsAsContents($ids);
        foreach ($rawManuscripts as $rawManuscript) {
            $persons[$rawManuscript['person_id']]->addRole($contentRole);
        }

        // Occurrence subjects
        $rawOccurrences = $this->dbs->getOccurrencesAsSubjects($ids);
        foreach ($rawOccurrences as $rawOccurrence) {
            $persons[$rawOccurrence['person_id']]->addRole($subjectRole);
        }

        // Type subjects
        $rawTypes = $this->dbs->getTypesAsSubjects($ids);
        foreach ($rawTypes as $rawType) {
            $persons[$rawType['person_id']]->addRole($subjectRole);
        }

        // offices
        $rawOffices = $this->dbs->getOffices($ids);

        $offices = [];
        if (count($rawOffices) > 0) {
            $officeIds = self::getUniqueIds($rawOffices, 'office_id');
            $officesWithParents = $this->container->get(OfficeManager::class)->getWithParents($officeIds);

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

        $this->setAcknowledgements($persons);

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

        $manuscripts = $this->container->get(ManuscriptManager::class)->getMini($manuscriptIds);
        $occurrences = $this->container->get(OccurrenceManager::class)->getMini($occurrenceIds);
        $roles = $this->container->get(RoleManager::class)->get($roleIds);

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

        $occurrences = $this->container->get(OccurrenceManager::class)->getMini($occurrenceIds);
        $roles = $this->container->get(RoleManager::class)->get($roleIds);

        foreach ($rawOccurrences as $rawOccurrence) {
            $person
                ->addDocumentRole(
                    'occurrence',
                    $roles[$rawOccurrence['role_id']],
                    $occurrences[$rawOccurrence['occurrence_id']]
                );
        }

        // Type roles
        $rawTypes = $this->dbs->getTypesAsRoles([$id]);
        $typeIds = self::getUniqueIds($rawTypes, 'type_id');
        $roleIds = self::getUniqueIds($rawTypes, 'role_id');

        $types = $this->container->get(TypeManager::class)->getMini($typeIds);
        $roles = $this->container->get(RoleManager::class)->get($roleIds);

        foreach ($rawTypes as $rawType) {
            $person
                ->addDocumentRole(
                    'type',
                    $roles[$rawType['role_id']],
                    $types[$rawType['type_id']]
                );
        }

        // Pseudo roles
        $contentRole = Role::getContentRole();
        $subjectRole = Role::getSubjectRole();

        // Manuscript contents
        $rawManuscripts = $this->dbs->getManuscriptsAsContents([$id]);
        $manuscriptIds = self::getUniqueIds($rawManuscripts, 'manuscript_id');
        $manuscripts = $this->container->get(ManuscriptManager::class)->getMini($manuscriptIds);
        foreach ($rawManuscripts as $rawManuscript) {
            $person->addManuscriptRole(
                $contentRole,
                $manuscripts[$rawManuscript['manuscript_id']]
            );
        }

        // occurrence subjects
        $rawOccurrences = $this->dbs->getOccurrencesAsSubjects([$id]);
        $occurrenceIds = self::getUniqueIds($rawOccurrences, 'occurrence_id');

        $occurrences = $this->container->get(OccurrenceManager::class)->getMini($occurrenceIds);

        foreach ($rawOccurrences as $rawOccurrence) {
            $person
                ->addDocumentRole(
                    'occurrence',
                    $subjectRole,
                    $occurrences[$rawOccurrence['occurrence_id']]
                );
        }

        // type subjects
        $rawTypes = $this->dbs->getTypesAsSubjects([$id]);
        $typeIds = self::getUniqueIds($rawTypes, 'type_id');

        $types = $this->container->get(TypeManager::class)->getMini($typeIds);

        foreach ($rawTypes as $rawType) {
            $person
                ->addDocumentRole(
                    'type',
                    $subjectRole,
                    $types[$rawType['type_id']]
                );
        }

        // Article roles
        $raws = $this->dbs->getArticles([$id]);
        $ids = self::getUniqueIds($raws, 'article_id');
        $roleIds = self::getUniqueIds($raws, 'role_id');

        $articles = $this->container->get(ArticleManager::class)->getMini($ids);
        $roles = $this->container->get(RoleManager::class)->get($roleIds);

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

        $books = $this->container->get(BookManager::class)->getMini($ids);
        $roles = $this->container->get(RoleManager::class)->get($roleIds);

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

        $bookChapters = $this->container->get(BookChapterManager::class)->getMini($ids);
        $roles = $this->container->get(RoleManager::class)->get($roleIds);

        foreach ($raws as $raw) {
            $person
                ->addDocumentRole(
                    'bookChapter',
                    $roles[$raw['role_id']],
                    $bookChapters[$raw['book_chapter_id']]
                );
        }

        // Blog post roles
        $raws = $this->dbs->getBlogPosts([$id]);
        $ids = self::getUniqueIds($raws, 'blog_post_id');
        $roleIds = self::getUniqueIds($raws, 'role_id');

        $blogPosts = $this->container->get(BlogPostManager::class)->getMini($ids);
        $roles = $this->container->get(RoleManager::class)->get($roleIds);

        foreach ($raws as $raw) {
            $person
                ->addDocumentRole(
                    'blogPost',
                    $roles[$raw['role_id']],
                    $blogPosts[$raw['blog_post_id']]
                );
        }

        // PhD thesis roles
        $raws = $this->dbs->getPhds([$id]);
        $ids = self::getUniqueIds($raws, 'phd_id');
        $roleIds = self::getUniqueIds($raws, 'role_id');

        $phds = $this->container->get(PhdManager::class)->getMini($ids);
        $roles = $this->container->get(RoleManager::class)->get($roleIds);

        foreach ($raws as $raw) {
            $person
                ->addDocumentRole(
                    'phd',
                    $roles[$raw['role_id']],
                    $phds[$raw['phd_id']]
                );
        }

        // Bib varia roles
        $raws = $this->dbs->getBibvarias([$id]);
        $ids = self::getUniqueIds($raws, 'bib_varia_id');
        $roleIds = self::getUniqueIds($raws, 'role_id');

        $bibVarias = $this->container->get(BibVariaManager::class)->getMini($ids);
        $roles = $this->container->get(RoleManager::class)->get($roleIds);

        foreach ($raws as $raw) {
            $person
                ->addDocumentRole(
                    'bibVaria',
                    $roles[$raw['role_id']],
                    $bibVarias[$raw['bib_varia_id']]
                );
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
        return ArrayToJson::arrayToShortJson($this->getByType('historical'));
    }

    /**
     * @return array
     */
    public function getAllModernShortJson(): array
    {
        return ArrayToJson::arrayToShortJson($this->getByType('modern'));
    }

    /**
     * @return array
     */
    public function getAllDBBEShortJson(): array
    {
        return ArrayToJson::arrayToShortJson($this->getByType('dbbe'));
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
        // Invalidate cache
        $contentIds = $this->container->get(ContentManager::class)->getPersonDependencies($id);

        // Elastic update manuscript and occurrences that have this person as (manuscript) content
        $contentManuscripts = $this->container->get(ManuscriptManager::class)->getPersonContentDependenciesWithChildren($id, 'getShort');
        $contentManuscriptOccurrenceIds = [];
        foreach ($contentManuscripts as $contentManuscript) {
            $contentManuscriptOccurrenceIds = array_merge(
                $contentManuscriptOccurrenceIds,
                array_map(
                    function ($occurrence) {
                        return $occurrence->getId();
                    },
                    $contentManuscript->getOccurrences()
                )
            );
        }

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

            if (property_exists($data, 'acknowledgements')) {
                if (!is_array($data->acknowledgements)) {
                    throw new BadRequestHttpException('Incorrect acknowledgements data.');
                }
                $changes['short'] = true;
                $this->updateAcknowledgements($old, $data->acknowledgements);
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

            // Reset elasticsearch
            $this->ess->add($new);

            // update Elastic dependencies
            if ($changes['mini']) {
                foreach (array_values(ElasticManagers::MANAGERS) as $manager) {
                    if (method_exists($this->container->get($manager), 'getPersonDependencies')) {
                        $this->container->get($manager)->updateElasticByIds(
                            $this->container->get($manager)->getPersonDependencies($id, 'getId')
                        );
                    }
                }
                // Update manuscript and occurrences that have this person as (manuscript) content
                $this->container->get(ManuscriptManager::class)->updateElasticByIds(array_keys($contentManuscripts));
                $this->container->get(OccurrenceManager::class)->updateElasticByIds($contentManuscriptOccurrenceIds);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // Reset elasticsearch
            if ($isNew) {
                $this->deleteElasticByIdIfExists($id);
            } elseif (isset($new) && isset($old)) {
                $this->ess->add($old);
            }

            // Reset Elastic dependencies
            if (isset($changes) && $changes['mini']) {
                foreach (array_values(ElasticManagers::MANAGERS) as $manager) {
                    if (method_exists($this->container->get($manager), 'getPersonDependencies')) {
                        $this->container->get($manager)->updateElasticByIds(
                            $this->container->get($manager)->getPersonDependencies($id, 'getId')
                        );
                    }
                }
                // Update manuscript and occurrences that have this person as (manuscript) content
                $this->container->get(ManuscriptManager::class)->updateElasticByIds(array_keys($contentManuscripts));
                $this->container->get(OccurrenceManager::class)->updateElasticByIds($contentManuscriptOccurrenceIds);
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

        $fieldsToCheck = [
            'getFirstName' => 'firstName',
            'getLastName' => 'lastName',
            'getExtra' => 'extra',
            'getUnprocessed' => 'unprocessed',
            'getBornDate' => 'bornDate',
            'getDeathDate' => 'deathDate',
            'getPublicComment' => 'publicComment',
            'getPrivateComment' => 'privateComment',
        ];

        foreach ($fieldsToCheck as $method => $field) {
            $value = $primary->$method();
            if (!empty($value)) {
                $updates[$field] = $value;
            }
        }

        $updates = $this->mergeAndSetIfNotEmpty(
            fn() => $primary->getSelfDesignations(),
            fn() => $secondary->getSelfDesignations(),
            'selfDesignations',
            $updates
        );

        $updates = $this->mergeAndSetIfNotEmpty(
            fn() => $primary->getOfficesWithParents(),
            fn() => $secondary->getOfficesWithParents(),
            'offices',
            $updates
        );

        $mergedAttested = array_unique(array_merge(
            $primary->getAttestedDatesAndIntervals() ?? [],
            $secondary->getAttestedDatesAndIntervals() ?? []
        ));
        $updates['dates'] = array_map(function ($item) {
            return [
                'date' => [
                    'floor' => $item->getFloor()->format('Y-m-d'),
                    'ceiling' => $item->getCeiling()->format('Y-m-d'),
                ],
                'isInterval' => false,
                'type' => 'attested',
            ];
        }, $mergedAttested);

        if ($primary->getBornDate() !== null) {
            $updates['dates'][] = [
                'date' => [
                    'floor' => $primary->getBornDate()->getFloor()->format('Y-m-d'),
                    'ceiling' => $primary->getBornDate()->getCeiling()->format('Y-m-d'),
                ],
                'isInterval' => false,
                'type' => 'born',
            ];
        }

        if ($primary->getDeathDate() !== null) {
            $updates['dates'][] = [
                'date' => [
                    'floor' => $primary->getDeathDate()->getFloor()->format('Y-m-d'),
                    'ceiling' => $primary->getDeathDate()->getCeiling()->format('Y-m-d'),
                ],
                'isInterval' => false,
                'type' => 'died',
            ];
        }


        $identifiers = $this->container->get(IdentifierManager::class)->getByType('person');
        foreach ($identifiers as $identifier) {
            if (!isset($updates['identification'])) {
                $updates['identification'] = [];
            }

            $systemName = $identifier->getSystemName();
            $primary_identifications = $primary->getIdentifications()[$systemName][1] ?? null;
            $secondary_identifications = $secondary->getIdentifications()[$systemName][1] ?? null;

            $merged_identifications = array_merge($primary_identifications, $secondary_identifications);
            $merged_identifications = array_filter(array_unique($merged_identifications));

            if (!empty($merged_identifications)) {
                $updates['identification'][$systemName] = ArrayToJson::arrayToJson($merged_identifications);
            }
        }

        if(!empty($primary->getOrigin())) {
            $updates['origin'] = $primary->getOrigin()->getShortJson();
        }

        $allManagements = array_merge(
            iterator_to_array($primary->getManagements() ?? []),
            iterator_to_array($secondary->getManagements() ?? [])
        );
        $managementArray=[];
        foreach($allManagements as $management) {
            $jsonManagement = $management->getJson();
            $managementArray[] = $jsonManagement;
        }
        $updates['managements']  = $managementArray;

        $manuscripts = $this->container->get(ManuscriptManager::class)->getPersonDependencies($secondaryId, 'getShort');
        $occurrences = $this->container->get(OccurrenceManager::class)->getPersonDependencies($secondaryId, 'getShort');
        $types = $this->container->get(TypeManager::class)->getPersonDependencies($secondaryId, 'getShort');
        $poems = $occurrences + $types;

        if ((!empty($manuscripts) || !empty($occurrences) || !empty($types)) && !$primary->getHistorical()) {
            $updates['historical'] = true;
        }

        $articles = $this->container->get(ArticleManager::class)->getPersonDependencies($secondaryId, 'getShort');
        $books = $this->container->get(BookManager::class)->getPersonDependencies($secondaryId, 'getShort');
        $bookChapters = $this->container->get(BookChapterManager::class)->getPersonDependencies($secondaryId, 'getShort');
        if (!empty($articles) || (!empty($books) || !empty($bookChapters)) && !$primary->getModern()) {
            $updates['modern'] = true;
        }

        $contentIds = $this->container->get(ContentManager::class)->getPersonDependencies($secondaryId);

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
                        $this->container->get(ManuscriptManager::class)->update(
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
                        if (get_class($poem) == 'App\Model\Occurrences') {
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
                $this->container->get(ElasticOccurrenceService::class)->updateMultiple(
                    array_filter(
                        $esData,
                        function ($key) use ($occurrences) {
                            return in_array($key, array_keys($occurrences));
                        },
                        ARRAY_FILTER_USE_KEY
                    )
                );
                $this->container->get(ElasticTypeService::class)->updateMultiple(
                    array_filter(
                        $esData,
                        function ($key) use ($types) {
                            return in_array($key, array_keys($types));
                        },
                        ARRAY_FILTER_USE_KEY
                    )
                );

                // Reindex manuscripts (occurrencePersonRoles)
                $this->container->get(ManuscriptManager::class)->updateElasticByIds($manuscriptIds);
            }
            if (!empty($articles)) {
                foreach ($articles as $article) {
                    $personRoles = $article->getPersonRoles();
                    $update = $this->getMergeUpdate($personRoles, $primaryId, $secondaryId);
                    if (!empty($update)) {
                        $this->container->get(ArticleManager::class)->update(
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
                        $this->container->get(BookManager::class)->update(
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
                        $this->container->get(BookChapterManager::class)->update(
                            $bookChapter->getId(),
                            json_decode(json_encode($update))
                        );
                    }
                }
            }

            if (!empty($contentIds)) {
                foreach ($contentIds as $contentId) {
                    $this->container->get(ContentManager::class)->update(
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
            $this->updateElasticByIds([$primaryId, $secondaryId]);

            $this->container->get(ManuscriptManager::class)->updateElasticByIds(array_keys($manuscripts));
            $this->container->get(OccurrenceManager::class)->updateElasticByIds(array_keys($occurrences));
            $this->container->get(TypeManager::class)->updateElasticByIds(array_keys($types));
            $this->container->get(ArticleManager::class)->updateElasticByIds(array_keys($articles));
            $this->container->get(BookManager::class)->updateElasticByIds(array_keys($books));
            $this->container->get(BookChapterManager::class)->updateElasticByIds(array_keys($bookChapters));

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
            $manuscripts = $this->container->get(ManuscriptManager::class)->getPersonDependencies($person->getId());
            $occurrences = $this->container->get(OccurrenceManager::class)->getPersonDependencies($person->getId());
            $types = $this->container->get(TypeManager::class)->getPersonDependencies($person->getId());
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
            $articles = $this->container->get(ArticleManager::class)->getPersonDependencies($person->getId());
            $books = $this->container->get(BookManager::class)->getPersonDependencies($person->getId());
            $bookChapters = $this->container->get(BookChapterManager::class)->getPersonDependencies($person->getId());
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
                if (get_class($old) === 'App\Model\FuzzyDate' && !$new->isInterval) {
                    if ($old->getFloor() == new DateTime($new->date->floor)
                        && $old->getCeiling() == new DateTime($new->date->ceiling)
                    ) {
                        $oldIndexes[] = $oldIndex;
                        $newIndexes[] = $newIndex;
                        break;
                    }
                } elseif (get_class($old) === 'App\Model\FuzzyInterval' && $new->isInterval) {
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
                $selfDesignations = $this->container->get(SelfDesignationManager::class)->getWithData($rawSelfDesignations);
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
            foreach (array_values(ElasticManagers::MANAGERS) as $manager) {
                if (method_exists($this->container->get($manager), 'getPersonDependencies')) {
                    $ids = $this->container->get($manager)->getPersonDependencies($id, 'getId');
                    if (!empty($ids)) {
                        $dependencies[$entity] = $ids;
                    }
                }
            }

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            // remove from elasticsearch
            $this->deleteElasticByIdIfExists($id);

            // update dependencies
            foreach ($dependencies as $entityType => $ids) {
                $this->container->get(ElasticManagers::MANAGERS[$entityType])->updateElasticByIds($ids);
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

    function mergeAndSetIfNotEmpty(
        callable $primaryGetter,
        callable $secondaryGetter,
        string $key,
        array $updates
    ): array {
        $merged = array_unique(array_merge(
            $primaryGetter() ?? [],
            $secondaryGetter() ?? []
        ));

        if (!empty($merged)) {
            $updates[$key] = ArrayToJson::arrayToShortJson($merged);
        }

        return $updates;
    }

    protected function updateAcknowledgements(Person $person, array $acknowledgements): void
    {
        foreach ($acknowledgements as $acknowledgement) {
            if (!is_object($acknowledgement)
                || !property_exists($acknowledgement, 'id')
                || !is_numeric($acknowledgement->id)
            ) {
                throw new BadRequestHttpException('Incorrect acknowledgement data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($acknowledgements, $person->getAcknowledgements());

        if (count($delIds) > 0) {
            $this->dbs->delAcknowledgements($person->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addAcknowledgement($person->getId(), $addId);
        }
    }

    protected function setAcknowledgements(array &$persons)
    {
        $rawAcknowledgements = $this->dbs->getAcknowledgements(array_keys($persons));
        $acknowledgements = $this->container->get(AcknowledgementManager::class)->getWithData($rawAcknowledgements);
        foreach ($rawAcknowledgements as $rawAcknowledgement) {
            $persons[$rawAcknowledgement['person_id']]
                ->addAcknowledgement($acknowledgements[$rawAcknowledgement['acknowledgement_id']]);
        }
        foreach (array_keys($persons) as $personId) {
            $persons[$personId]->sortAcknowledgements();
        }
    }
}
