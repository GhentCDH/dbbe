<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use AppBundle\Model\FuzzyInterval;

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
                        ->setUnknownDate(new FuzzyDate($rawPerson['unknown_date']))
                        ->setUnknownInterval(FuzzyInterval::fromString($rawPerson['unknown_interval']))
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

                $this->setBibliographies($persons);

                $person = $persons[$id];

                // Manuscript roles
                $rawManuscripts = $this->dbs->getManuscripts([$id]);
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
                ]])[0];

                foreach ($rawOccurrences as $rawOccurrence) {
                    $person
                        ->addDocumentRole(
                            'occurrence',
                            $role,
                            $occurrences[$rawOccurrence['occurrence_id']]
                        );
                }

                // TODO: type

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

                return $person;
            }
        );
    }

    /**
     * Get all persons that are dependent on a specific office
     * @param  int   $officeId
     * @param  bool  $short    Whether to return a short or mini person (default: false => mini)
     * @return array
     */
    public function getOfficeDependencies(int $officeId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOfficeId($officeId), $short ? 'getShort' : 'getMini');
    }

    /**
     * Get all persons that are dependent on a specific office or one of its children
     * @param  int   $officeId
     * @param  bool  $short    Whether to return a short or mini person (default: false => mini)
     * @return array
     */
    public function getOfficeDependenciesWithChildren(int $officeId, bool $short = false): array
    {
        return $this->getDependencies(
            $this->dbs->getDepIdsByOfficeIdWithChildren($officeId),
            $short ? 'getShort' : 'getMini'
        );
    }

    /**
     * @return array
     */
    public function getAllHistoricalPersons(): array
    {
        return $this->wrapArrayCache(
            'historical_persons',
            ['persons'],
            function () {
                $rawIds = $this->dbs->getHistoricalIds();
                $ids = self::getUniqueIds($rawIds, 'person_id');

                $persons = array_values($this->getMini($ids));

                usort($persons, function ($a, $b) {
                    return strcmp($a->getFullDescription(), $b->getFullDescription());
                });

                return $persons;
            }
        );
    }

    /**
     * @return array
     */
    public function getAllModernPersons(): array
    {
        return $this->wrapArrayCache(
            'modern_persons',
            ['persons'],
            function () {
                $rawIds = $this->dbs->getModernIds();
                $ids = self::getUniqueIds($rawIds, 'person_id');

                $persons = array_values($this->getMini($ids));

                usort($persons, function ($a, $b) {
                    return strcmp($a->getFullDescription(), $b->getFullDescription());
                });

                return $persons;
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

            // update cache
            $this->cache->invalidateTags([$this->entityType . 's']);

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
                if (!is_bool($data->bool)) {
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
            if (property_exists($data, 'unprocessed')) {
                if (!is_string($data->unprocessed)) {
                    throw new BadRequestHttpException('Incorrect unprocessed data.');
                }
                $cacheReload['mini'] = true;
                $this->dbs->updateUnprocessed($id, $data->unprocessed);
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
            $this->updateIdentificationwrapper($old, $data, $cacheReload, 'mini', 'person');
            if (property_exists($data, 'offices')) {
                if (!is_array($data->offices)) {
                    throw new BadRequestHttpException('Incorrect office data.');
                }
                $cacheReload['short'] = true;
                $this->updateOffices($old, $data->offices);
            }
            if (property_exists($data, 'bibliography')) {
                $cacheReload['full'] = true;
                $this->updateBibliography($old, $data->bibliography);
            }
            if (property_exists($data, 'publicComment')) {
                if (!is_string($data->publicComment)) {
                    throw new BadRequestHttpException('Incorrect public comment data.');
                }
                $cacheReload['short'] = true;
                $this->dbs->updatePublicComment($id, $data->publicComment);
            }
            if (property_exists($data, 'privateComment')) {
                if (!is_string($data->privateComment)) {
                    throw new BadRequestHttpException('Incorrect private comment data.');
                }
                $cacheReload['short'] = true;
                $this->dbs->updatePrivateComment($id, $data->privateComment);
            }

            // Throw error if none of above matched
            if (!in_array(true, $cacheReload)) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $this->clearCache($id, $cacheReload);
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            // Reset elasticsearch
            $this->ess->add($new);

            // update Elastic dependencies
            if ($cacheReload['mini']) {
                $manuscripts = $this->container->get('manuscript_manager')->getPersonDependencies($id, true);
                $this->container->get('manuscript_manager')->elasticIndex($manuscripts);

                $occurrences = $this->container->get('occurrence_manager')->getPersonDependencies($id, true);
                $this->container->get('occurrence_manager')->elasticIndex($occurrences);

                // TODO: types

                $articles = $this->container->get('article_manager')->getPersonDependencies($id, true);
                $this->container->get('article_manager')->elasticIndex($articles);

                $books = $this->container->get('book_manager')->getPersonDependencies($id, true);
                $this->container->get('article_manager')->elasticIndex($books);

                $bookChapters = $this->container->get('book_chapter_manager')->getPersonDependencies($id, true);
                $this->container->get('book_chapter_manager')->elasticIndex($bookChapters);
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
        $identifiers = $this->container->get('identifier_manager')->getIdentifiersByType('person');
        foreach ($identifiers as $identifier) {
            if (empty($primary->getIdentifications()[$identifier->getSystemName()])
                && !empty($secondary->getIdentifications()[$identifier->getSystemName()])
            ) {
                $updates[$identifier->getSystemName()] =
                    implode(', ', $secondary->getIdentifications()[$identifier->getSystemName()]->getIdentifications());
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
        // TODO: types
        if ((!empty($manuscripts) || !empty($occurrences)) && !$primary->getHistorical()) {
            $updates['historical'] = true;
        }

        $articles = $this->container->get('article_manager')->getPersonDependencies($secondaryId, true);
        $books = $this->container->get('book_manager')->getPersonDependencies($secondaryId, true);
        $bookChapters = $this->container->get('book_chapter_manager')->getPersonDependencies($secondaryId, true);
        if (!empty($articles) || (!empty($books) || !empty($bookChapters)) && !$primary->getModern()) {
            $updates['modern'] = true;
        }

        $this->dbs->beginTransaction();
        try {
            if (!empty($updates)) {
                $primary = $this->update($primaryId, json_decode(json_encode($updates)));
            }

            if (!empty($manuscripts)) {
                $roles = $this->container->get('role_manager')->getRolesByType('manuscript');
                foreach ($manuscripts as $manuscript) {
                    $personRoles = $manuscript->getPersonRoles();
                    $update = $this->getMergeUpdate($personRoles, $primaryId, $secondaryId);
                    if (!empty($update)) {
                        $this->container->get('manuscript_manager')->update(
                            $manuscript->getId(),
                            json_decode(json_encode($update))
                        );
                    }
                }
            }
            if (!empty($occurrences)) {
                $roles = $this->container->get('role_manager')->getRolesByType('occurrence');
                foreach ($occurrences as $occurrence) {
                    $personRoles = $occurrence->getPersonRoles();
                    $update = $this->getMergeUpdate($personRoles, $primaryId, $secondaryId);

                    $subjects = $occurrence->getSubjects();
                    $subjectIds = array_keys($subjects);
                    if (in_array($secondaryId, $subjectIds)) {
                        $update['subjects'] = [];
                        foreach ($subjectIds as $id) {
                            if ($id == $secondaryId) {
                                // Prevent duplicate values
                                if (!in_array($primaryId, $subjectIds)) {
                                    $update['subjects'][] = ['id' => $primaryId];
                                }
                            } else {
                                $update['subjects'][] = ['id' => $id];
                            }
                        }
                    }

                    if (!empty($update)) {
                        $this->container->get('occurrence_manager')->updateOccurrence(
                            $occurrence->getId(),
                            json_decode(json_encode($update))
                        );
                    }
                }
            }
            // TODO: types
            if (!empty($articles)) {
                $roles = $this->container->get('role_manager')->getRolesByType('article');
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
                $roles = $this->container->get('role_manager')->getRolesByType('book');
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
                $roles = $this->container->get('role_manager')->getRolesByType('bookChapter');
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

            // Reset cache to renew role lists
            if (!empty($manuscripts)
                || !empty($occurrences)
                // TODO: types
                || !empty($articles)
                || !empty($books)
                || !empty($bookChapters)
            ) {
                $this->clearCache($primaryId, ['full' => true]);
            }

            $this->delete($secondaryId);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            // Reset caches and elasticsearch
            $this->reset([$primaryId]);
            foreach ($manuscripts as $manuscript) {
                $this->container->get('manuscript_manager')->reset([$manuscript->getId()]);
            }
            foreach ($occurrences as $occurrence) {
                $this->container->get('occurrence_manager')->reset([$occurrence->getId()]);
            }
            // TODO: types
            foreach ($articles as $article) {
                $this->container->get('article_manager')->reset([$article->getId()]);
            }
            foreach ($books as $book) {
                $this->container->get('book_manager')->reset([$book->getId()]);
            }
            foreach ($bookChapters as $bookChapter) {
                $this->container->get('book_chapter_manager')->reset([$bookChapter->getId()]);
            }
            throw $e;
        }

        return $primary;
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
     * @param Person $person
     * @param bool   $historical
     */
    private function updateHistorical(Person $person, bool $historical): void
    {
        if (!$historical) {
            $manuscripts = $this->container->get('manuscript_manager')->getPersonDependencies($person->getId());
            $occurrences = $this->container->get('occurrence_manager')->getPersonDependencies($person->getId());
            // TODO: types
            if (!empty($manuscripts) || !empty($occurrences)) {
                throw new BadRequestHttpException(
                    'This person is linked linked to a manuscript or occurrence, so must be historical.'
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

    /**
     * Delete a person
     * @param int $id
     */
    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $person = $this->getFull($id);

            $this->dbs->delete($id);

            $this->updateModified($person, null);

            // empty cache and remove from elasticsearch
            $this->reset([$id]);
            $this->cache->invalidateTags([$this->entityType . 's']);

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
}
