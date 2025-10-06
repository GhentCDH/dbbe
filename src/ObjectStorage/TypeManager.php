<?php

namespace App\ObjectStorage;

use App\ElasticSearchService\ElasticOccurrenceService;
use App\ElasticSearchService\ElasticTypeService;
use App\ElasticSearchService\ElasticVerseService;
use App\Security\Roles;
use Exception;
use stdClass;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use App\Model\Status;
use App\Model\Translation;
use App\Model\Type;
use App\DatabaseService\DatabaseServiceInterface;
use App\ElasticSearchService\ElasticSearchServiceInterface;

class TypeManager extends PoemManager
{
    private $client;

    public function __construct(
        DatabaseServiceInterface $databaseService = null,
        ContainerInterface $container,
        ElasticSearchServiceInterface $elasticSearchService = null,
        TokenStorageInterface $tokenStorage = null,
        string $entityType = null,
        HttpClientInterface $client
    ) {
        parent::__construct($databaseService, $container, $elasticSearchService, $tokenStorage, $entityType);
        $this->client = $client;
    }

    protected function setTitles(array &$types): void
    {
        $rawTitles = $this->dbs->getTitles(array_keys($types));
        foreach ($rawTitles as $rawTitle) {
            $types[$rawTitle['poem_id']]
                ->addTitle($rawTitle['lang'], $rawTitle['title']);
        }
    }

    /**
     * Get types with enough information to get an id and an incipit
     * @param  array $ids
     * @return array
     */
    public function getMicro(array $ids): array
    {
        $types = [];
        $rawIds = $this->dbs->getIdsByIds($ids);
        if (count($rawIds) == 0) {
            return [];
        }

        foreach ($rawIds as $rawId) {
            $types[$rawId['type_id']] = (new Type())
                ->setId($rawId['type_id']);
        }

        // Remove all ids that did not match above
        $ids = array_keys($types);

        $this->setIncipits($types);

        return $types;
    }

    /**
     * Get types with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $types = [];
        $rawIds = $this->dbs->getIdsByIds($ids);
        if (count($rawIds) == 0) {
            return [];
        }

        foreach ($rawIds as $rawId) {
            $types[$rawId['type_id']] = (new Type())
                ->setId($rawId['type_id']);
        }

        // Remove all ids that did not match above
        $ids = array_keys($types);

        $this->setIncipits($types);

        $this->setNumberOfVerses($types);

        // Verses (needed in mini to calculate number of verses)
        $rawVerses = $this->dbs->getVerses($ids);
        foreach ($rawVerses as $rawVerse) {
            $types[$rawVerse['type_id']]
                ->setVerses(array_map('trim', explode("\n", $rawVerse['text_content'])));
        }

        $this->setPublics($types);

        return $types;
    }

    /**
     * Get types with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        $types = $this->getMini($ids);

        // Remove all ids that did not match above
        $ids = array_keys($types);

        $this->setTitles($types);

        $this->setMetres($types);

        $this->setSubjects($types);

        $rawKeywords = $this->dbs->getKeywords($ids);
        $keywordIds = self::getUniqueIds($rawKeywords, 'keyword_id');
        $keywords = $this->container->get(KeywordManager::class)->get($keywordIds);
        foreach ($rawKeywords as $rawKeyword) {
            $types[$rawKeyword['type_id']]
                ->addKeyword($keywords[$rawKeyword['keyword_id']]);
        }
        foreach ($types as $type) {
            $type->sortKeywords();
        }

        $this->setPersonRoles($types);

        $this->setGenres($types);

        $rawLemmas = $this->dbs->getLemmas($ids);
        foreach ($rawLemmas as $rawLemma) {
            $types[$rawLemma['type_id']]
                ->setLemmas(array_map('trim', explode("\n", $rawLemma['lemma'])));
        }

        $this->setComments($types);

        // statuses
        $rawStatuses = $this->dbs->getStatuses($ids);
        $statuses = $this->container->get(StatusManager::class)->getWithData($rawStatuses);
        foreach ($rawStatuses as $rawStatus) {
            switch ($rawStatus['status_type']) {
                case Status::TYPE_TEXT:
                    $types[$rawStatus['type_id']]
                        ->setTextStatus($statuses[$rawStatus['status_id']]);
                    break;
                case Status::TYPE_CRITICAL:
                    $types[$rawStatus['type_id']]
                        ->setCriticalStatus($statuses[$rawStatus['status_id']]);
                    break;
            }
        }

        $this->setAcknowledgements($types);

        // occurrences (needed in short to calculate number of occurrences)
        $rawOccurrences = $this->dbs->getOccurrences($ids);
        if (!empty($rawOccurrences)) {
            $occurrenceIds = self::getUniqueIds($rawOccurrences, 'occurrence_id');
            $occurrences = $this->container->get(OccurrenceManager::class)->getMini($occurrenceIds);
            foreach ($rawOccurrences as $rawOccurrence) {
                $types[$rawOccurrence['type_id']]->addOccurrence($occurrences[$rawOccurrence['occurrence_id']]);
            }
            foreach ($types as $id => $type) {
                $types[$id]->sortOccurrences();
            }
        }

        // Needed to index DBBE in elasticsearch
        $this->setBibliographies($types);

        // Needed to index translated in elasticsearch
        $rawTranslations = $this->dbs->getTranslations($ids);
        $translationIds = self::getUniqueIds($rawTranslations, 'translation_id');
        if (!empty($translationIds)) {
            $translations = $this->container->get(TranslationManager::class)->getMini($translationIds);
            foreach ($rawTranslations as $rawTranslation) {
                $types[$rawTranslation['type_id']]->addTranslation($translations[$rawTranslation['translation_id']]);
            }
        }
        foreach ($types as $type) {
            $type->sortTranslations();
        }

        $this->setIdentifications($types);

        $this->setcontributorRoles($types);

        $this->setManagements($types);

        $this->setPrevIds($types);

        $this->setCreatedAndModifiedDates($types);

        return $types;
    }

    /**
     * Get a single type with all information
     * @param  int  $id
     * @return Type
     */
    public function getFull(int $id): Type
    {
        // Get basic type information
        $types = $this->getShort([$id]);
        if (count($types) == 0) {
            throw new NotFoundHttpException('Type with id ' . $id .' not found.');
        }

        $type = $types[$id];

        // related types
        $rawRelTypes = $this->dbs->getRelatedTypes([$id]);
        if (!empty($rawRelTypes)) {
            $typeIds = self::getUniqueIds($rawRelTypes, 'rel_type_id');
            $relTypes =  $this->getMini($typeIds);
            $typeRelTypes = $this->container->get(TypeRelationTypeManager::class)->getWithData($rawRelTypes);
            foreach ($rawRelTypes as $rawRelType) {
                $type->addRelatedType(
                    $relTypes[$rawRelType['rel_type_id']],
                    $typeRelTypes[$rawRelType['type_relation_type_id']]
                );
            }
        }

        // critical apparatus
        $rawCriticalApparatuses = $this->dbs->getCriticalApparatuses([$id]);
        if (!empty($rawCriticalApparatuses)) {
            $type->setCriticalApparatus($rawCriticalApparatuses[0]['critical_apparatus']);
        }

        // based on occurrence
        $rawBasedOns = $this->dbs->getBasedOns([$id]);
        $occurrenceIds = self::getUniqueIds($rawBasedOns, 'occurrence_id');
        $occurrences = $this->container->get(OccurrenceManager::class)->getMini($occurrenceIds);
        if (!empty($rawBasedOns)) {
            $type->setBasedOn($occurrences[$rawBasedOns[0]['occurrence_id']]);
        }

        return $type;
    }

    public function getNewId(int $oldId): int
    {
        $rawId = $this->dbs->getNewId($oldId);
        if (count($rawId) != 1) {
            throw new NotFoundHttpException('The type with legacy id "' . $oldId . '" does not exist.');
        }
        return $rawId[0]['new_id'];
    }

    public function getOccurrenceDependencies(int $occurrenceId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByOccurrenceId($occurrenceId), $method);
    }

    public function getTranslationDependencies(int $translationId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByTranslationId($translationId), $method);
    }

    /**
     * Add a new type
     * @param  stdClass $data
     * @return Type
     */
    public function add(stdClass $data): Type
    {
        // Incipit is a required fields
        if (!property_exists($data, 'incipit')
            || !is_string($data->incipit)
            || empty($data->incipit)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new type.');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->incipit);

            $new = $this->update($id, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Update a new or existing type
     * @param  int      $id
     * @param  stdClass $data
     * @param  bool     $isNew Indicate whether this is a new person
     * @return Type
     */
    public function update(int $id, stdClass $data, bool $isNew = false): Type
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('Person with id ' . $id .' not found.');
            }

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
            if (property_exists($data, 'incipit')) {
                // Incipit is a required field
                if (!is_string($data->incipit)
                    || empty($data->incipit)
                ) {
                    throw new BadRequestHttpException('Incorrect incipit data.');
                }
                $data->incipit = ltrim($data->incipit);
                $changes['mini'] = true;
                $this->dbs->updateIncipit($id, $data->incipit);
            }
            if (property_exists($data, 'title_GR')) {
                if (!is_string($data->title_GR)) {
                    throw new BadRequestHttpException('Incorrect Greek title data.');
                }

                $changes['short'] = true;
                $this->dbs->upsertDelTitle($id, 'GR', $data->title_GR);
            }
            if (property_exists($data, 'title_LA')) {
                if (!is_string($data->title_LA)) {
                    throw new BadRequestHttpException('Incorrect Latin title data.');
                }

                $changes['short'] = true;
                $this->dbs->upsertDelTitle($id, 'LA', $data->title_LA);
            }
            if (property_exists($data, 'numberOfVerses')) {
                if (!empty($data->numberOfVerses) && !is_numeric($data->numberOfVerses)) {
                    throw new BadRequestHttpException('Incorrect number of verses data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateNumberOfVerses($id, $data->numberOfVerses);
            }
            if (property_exists($data, 'verses')) {
                if (!is_string($data->verses)) {
                    throw new BadRequestHttpException('Incorrect verses data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateVerses($id, $data->verses);

                $this->dbs->upsertLemmas($id, $this->lemmatize($data->verses));
            }
            if (property_exists($data, 'relatedTypes')) {
                if (!is_array($data->relatedTypes)) {
                    throw new BadRequestHttpException('Incorrect related types data.');
                }
                $changes['full'] = true;
                $this->updateTypes($old, $data->relatedTypes);
            }
            $roles = $this->container->get(RoleManager::class)->getByType('type');
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $changes['short'] = true;
                    $this->updatePersonRole($old, $role, $data->{$role->getSystemName()});
                }
            }
            if (property_exists($data, 'metres')) {
                if (!is_array($data->metres)) {
                    throw new BadRequestHttpException('Incorrect metre data.');
                }
                $changes['short'] = true;
                $this->updateMetres($old, $data->metres);
            }
            if (property_exists($data, 'genres')) {
                if (!is_array($data->genres)) {
                    throw new BadRequestHttpException('Incorrect genre data.');
                }
                $changes['short'] = true;
                $this->updateGenres($old, $data->genres);
            }
            if (property_exists($data, 'personSubjects')) {
                if (!is_array($data->personSubjects)) {
                    throw new BadRequestHttpException('Incorrect person subject data.');
                }
                $changes['short'] = true;
                $this->updatePersonSubjects($old, $data->personSubjects);
            }
            if (property_exists($data, 'keywordSubjects')) {
                if (!is_array($data->keywordSubjects)) {
                    throw new BadRequestHttpException('Incorrect keyword subject data.');
                }
                $changes['short'] = true;
                $this->updateKeywordSubjects($old, $data->keywordSubjects);
            }
            if (property_exists($data, 'keywords')) {
                if (!is_array($data->keywords)) {
                    throw new BadRequestHttpException('Incorrect keywords data.');
                }
                $changes['short'] = true;
                $this->updateKeywords($old, $data->keywords);
            }
            $this->updateIdentificationwrapper($old, $data, $changes, 'full', 'type');
            if (property_exists($data, 'bibliography')) {
                if (!is_object($data->bibliography)) {
                    throw new BadRequestHttpException('Incorrect bibliography data.');
                }
                // short is needed here to index DBBE in elasticsearch
                $changes['short'] = true;
                $this->updateBibliography($old, $data->bibliography, true);
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
            if (property_exists($data, 'criticalApparatus')) {
                if (!is_string($data->criticalApparatus)) {
                    throw new BadRequestHttpException('Incorrect critical apparatus data.');
                }
                $changes['full'] = true;
                $this->dbs->updateCriticalApparatus($id, $data->criticalApparatus);
            }
            if (property_exists($data, 'translations')) {
                if (!is_array($data->translations)) {
                    throw new BadRequestHttpException('Incorrect translations data.');
                }
                $changes['full'] = true;
                $this->updateTranslations($old, $data->translations);
            }
            if (property_exists($data, 'acknowledgements')) {
                if (!is_array($data->acknowledgements)) {
                    throw new BadRequestHttpException('Incorrect acknowledgements data.');
                }
                $changes['short'] = true;
                $this->updateAcknowledgements($old, $data->acknowledgements);
            }
            if (property_exists($data, 'criticalStatus')) {
                if (!(is_object($data->criticalStatus) || empty($data->criticalStatus))) {
                    throw new BadRequestHttpException('Incorrect record status data.');
                }
                $changes['short'] = true;
                $this->updateStatus($old, $data->criticalStatus, Status::TYPE_CRITICAL);
            }
            if (property_exists($data, 'textStatus')) {
                if (!(is_object($data->textStatus) || empty($data->textStatus))) {
                    throw new BadRequestHttpException('Incorrect text status data.');
                }
                $changes['short'] = true;
                $this->updateStatus($old, $data->textStatus, Status::TYPE_TEXT);
            }
            if (property_exists($data, 'basedOn')) {
                if (!(is_object($data->basedOn) || empty($data->basedOn))) {
                    throw new BadRequestHttpException('Incorrect based on data.');
                }
                $changes['full'] = true;
                $this->updateBasedOn($old, $data->basedOn);
            }
            $contributorRoles = $this->container->get(RoleManager::class)->getContributorByType('type');
            foreach ($contributorRoles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $changes['short'] = true;
                    $this->updateContributorRole($old, $role, $data->{$role->getSystemName()});
                }
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

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            // reset elasticsearch
            if ($isNew) {
                $this->deleteElasticByIdIfExists($id);
            } elseif (isset($new) && isset($old)) {
                $this->ess->add($old);
            }
            throw $e;
        }

        return $new;
    }

    public function updateTypes(Type $type, array $relatedTypes)
    {
        $typeIds = [];
        foreach ($relatedTypes as $relatedType) {
            if (!is_object($relatedType)
                || !property_exists($relatedType, 'type')
                || !is_object($relatedType->type)
                || !property_exists($relatedType->type, 'id')
                || !is_numeric($relatedType->type->id)
                || !property_exists($relatedType, 'relationTypes')
                || !is_array($relatedType->relationTypes)
                || in_array($relatedType->type->id, $typeIds)
            ) {
                throw new BadRequestHttpException('Incorrect related type data.');
            }
            foreach ($relatedType->relationTypes as $relationType) {
                if (!property_exists($relationType, 'id')
                    || !is_numeric($relationType->id)
                ) {
                    throw new BadRequestHttpException('Incorrect relation type data.');
                }
            }
            $typeIds[] = $relatedType->type->id;
        }

        // Only use type information to calculate diff
        $newTypes = array_map(
            function ($relatedType) {
                return $relatedType->type;
            },
            $relatedTypes
        );
        $oldTypes = array_map(
            function ($relatedType) {
                return $relatedType[0];
            },
            $type->getRelatedTypes()
        );
        list($delIds, $addIds) = self::calcDiff($newTypes, $oldTypes);

        if (count($delIds) > 0) {
            $this->dbs->delRelatedTypes($type->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            foreach ($relatedTypes as $relatedType) {
                if ($relatedType->type->id != $addId) {
                    continue;
                } else {
                    $relationTypeIds = array_map(
                        function ($relationType) {
                            return $relationType->id;
                        },
                        $relatedType->relationTypes
                    );
                    $this->dbs->addRelatedType($type->getId(), $addId, $relationTypeIds);
                    break;
                }
            }
        }

        foreach ($relatedTypes as $relatedType) {
            if (!in_array($relatedType->type->id, $addIds)) {
                foreach ($type->getRelatedTypes() as $oldRelatedType) {
                    if ($oldRelatedType[0]->getId() != $relatedType->type->id) {
                        continue;
                    } else {
                        list($relDelIds, $relAddIds) = self::calcDiff($relatedType->relationTypes, $oldRelatedType[1]);

                        if (count($relDelIds) > 0) {
                            $this->dbs->delRelatedTypeRelations($type->getId(), $relatedType->type->id, $relDelIds);
                        }
                        if (count($relAddIds) > 0) {
                            $this->dbs->addRelatedType($type->getId(), $relatedType->type->id, $relAddIds);
                        }
                        break;
                    }
                }
            }
        }
    }

    private function updateKeywords(Type $type, array $keywords): void
    {
        foreach ($keywords as $keyword) {
            if (!is_object($keyword)
                || !property_exists($keyword, 'id')
                || !is_numeric($keyword->id)
            ) {
                throw new BadRequestHttpException('Incorrect keyword data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($keywords, $type->getKeywords());

        if (count($delIds) > 0) {
            $this->dbs->delKeywords($type->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addKeyword($type->getId(), $addId);
        }
    }

    private function getTranslationWithIdFromArray(array $translations, int $id): Translation
    {
        foreach ($translations as $translation) {
            if ($translation->getId() == $id) {
                return $translation;
            }
        }
    }

    private function updateTranslations(Type $type, array $translations): void
    {
        $delIds = [];
        $oldTranslations = $type->getTranslations();
        foreach ($oldTranslations as $oldTranslation) {
            $found = false;
            foreach ($translations as $newTranslation) {
                if (property_exists($newTranslation, 'id') && $oldTranslation->getId() == $newTranslation->id) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $delIds[] = $oldTranslation->getId();
            }
        }

        foreach ($delIds as $delId) {
            $this->container->get(TranslationManager::class)->delete($delId);
        }

        foreach ($translations as $newTranslation) {
            // If a new translation has an id, it will be present in old translations
            if (property_exists($newTranslation, 'id')) {
                $this->container->get(TranslationManager::class)->updateIfRequired(
                    $this->getTranslationWithIdFromArray($oldTranslations, $newTranslation->id),
                    $newTranslation
                );
            } else {
                $this->container->get(TranslationManager::class)->add(
                    $newTranslation,
                    $type->getId()
                );
            }
        }
    }

    private function updateBasedOn(Type $type, stdClass $basedOn = null): void
    {
        if (empty($basedOn)) {
            $this->dbs->delBasedOn($type->getId());
        } elseif (!is_object($basedOn)
            || !property_exists($basedOn, 'id')
            || !is_numeric($basedOn->id)
        ) {
            throw new BadRequestHttpException('Incorrect based on data.');
        } else {
            if (empty($type->getBasedOn())) {
                $this->dbs->addBasedOn($type->getId(), $basedOn->id);
            } else {
                $this->dbs->updateBasedOn($type->getId(), $basedOn->id);
            }
        }
    }

    private function lemmatize(string $verses): string
    {
        // Remove spaces at the beginning and end of the line
        // Remove commas, dots, brackets, ...
        // Remove capital letters.
        $words = [];
        $cleanVerses = [];
        foreach (explode("\n", $verses) as $verse) {
            $cleanVerses[] = preg_replace('[,.<>()\[\]|\+\-"]', '', $verse);
        }

        foreach ($cleanVerses as $cleanVerse) {
            foreach (explode(' ', $cleanVerse) as $word) {
                $words[] = $word;
            }
        }

        $uniqueWords = array_unique($words);

        // Retrieve from cache
        $cachedLemmasRaw = $this->dbs->getCachedLemmas($uniqueWords);
        $cachedLemmas = [];
        foreach ($cachedLemmasRaw as $cachedLemmaRaw) {
            $cachedLemmas[$cachedLemmaRaw['input']] = $cachedLemmaRaw['output'];
        }

        $lineLemmas = [];

        foreach ($cleanVerses as $cleanVerse) {
            $lineLemma = [];
            foreach (explode(' ', $cleanVerse) as $word) {
                if (array_key_exists($word, $cachedLemmas)) {
                    $lineLemma[] = $cachedLemmas[$word];
                } else {
                    $lineLemma[] = $this->getMorphLemma($word);
                }
            }
            $lineLemmas[] = implode(' ', $lineLemma);
        }

        return implode("\n", $lineLemmas);
    }

    private function getMorphLemma(string $word): string
    {
        $response = $this->client->request(
            'GET',
            $this->container->getParameter('app.morph') . '/analysis/word',
            [
                'query' => [
                    'lang' => 'grc',
                    'engine' => 'morpheusgrc',
                    'word' => $word,
                ]
            ]
        );
        if ($response->getStatusCode() !== 201) {
            throw new Exception('Issue with the lemma server');
        }
        $annotation = $response->toArray()['RDF']['Annotation'];
        if (!array_key_exists('Body', $annotation)) {
            return 'na';
        }

        $lemma = '';
        // Check if array with multiple objects or single object
        if (array_key_exists(0, $annotation['Body'])) {
            $lemma = $annotation['Body'][0]['rest']['entry']['dict']['hdwd']['$'];
        } else {
            $lemma = $annotation['Body']['rest']['entry']['dict']['hdwd']['$'];
        }

        $this->dbs->addCachedLemma($word, $lemma);
        return $lemma;
    }

    private function formatRow(array $item): array
    {
        $implodeNames = fn($key) => !empty($item[$key]) ? implode(' | ', array_column($item[$key], 'name')) : '';
        $occurrenceIds = !empty($item['occurrence_ids']) ? implode(' | ', $item['occurrence_ids']) : '';
        return [
            $item['id'] ?? '',
            $implodeNames('genre'),
            $implodeNames('subject'),
            $implodeNames('metre'),
            $item['text_original'] ?? '',
            $occurrenceIds
        ];
    }

    public function generateCsvStream(
        array $params,
        ElasticTypeService $elasticTypeService,
        bool $isAuthorized
    ) {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'id', 'genres', 'subjects', 'metres', 'text', 'occurrence_ids'
        ],';');
        $maxResults = $isAuthorized ? 10000 : 1000;
        $params['limit'] = $maxResults;
        $result = $elasticTypeService->runFullSearch($params, $isAuthorized);

        $data = $result['data'] ?? [];
        $totalFetched = 0;
        foreach ($data as $item) {
            if ($totalFetched++ >= $maxResults) break;
            $row = $this->formatRow($item);
            fputcsv($stream,$row,';');
        }
        return $stream;
    }

}
