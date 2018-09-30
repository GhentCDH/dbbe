<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use AppBundle\Model\Type;
use AppBundle\Model\Status;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TypeManager extends PoemManager
{
    /**
     * Get types with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        return $this->wrapLevelCache(
            Type::CACHENAME,
            'mini',
            $ids,
            function ($ids) {
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

                // Verses (needed in mini to calculate number of verses)
                $rawVerses = $this->dbs->getVerses($ids);
                foreach ($rawVerses as $rawVerse) {
                    $types[$rawVerse['type_id']]
                        ->setVerses(array_map('trim', explode("\n", $rawVerse['text_content'])));
                }

                $this->setPublics($types);

                return $types;
            }
        );
    }

    /**
     * Get types with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        return $this->wrapLevelCache(
            Type::CACHENAME,
            'short',
            $ids,
            function ($ids) {
                $types = $this->getMini($ids);

                // Remove all ids that did not match above
                $ids = array_keys($types);

                $this->setTitles($types);

                $this->setMeters($types);

                $this->setSubjects($types);

                $rawKeywords = $this->dbs->getKeywords($ids);
                $keywordIds = self::getUniqueIds($rawKeywords, 'keyword_id');
                $keywords = $this->container->get('keyword_manager')->get($keywordIds);
                foreach ($rawKeywords as $rawKeyword) {
                    $types[$rawKeyword['type_id']]
                        ->addKeyword($keywords[$rawKeyword['keyword_id']]);
                }

                $this->setPersonRoles($types);

                $this->setGenres($types);

                $this->setComments($types);

                // statuses
                $rawStatuses = $this->dbs->getStatuses($ids);
                $statuses = $this->container->get('status_manager')->getWithData($rawStatuses);
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
                    $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);
                    foreach ($rawOccurrences as $rawOccurrence) {
                        $types[$rawOccurrence['type_id']]->addOccurrence($occurrences[$rawOccurrence['occurrence_id']]);
                    }
                }

                // Needed to index DBBE in elasticsearch
                $this->setBibliographies($types);

                return $types;
            }
        );
    }

    /**
     * Get a single type with all information
     * @param  int  $id
     * @return Type
     */
    public function getFull(int $id): Type
    {
        return $this->wrapSingleLevelCache(
            Type::CACHENAME,
            'full',
            $id,
            function ($id) {
                // Get basic occurrence information
                $types = $this->getShort([$id]);
                if (count($types) == 0) {
                    throw new NotFoundHttpException('Type with id ' . $id .' not found.');
                }

                $this->setIdentifications($types);

                $this->setPrevIds($types);

                $type = $types[$id];

                // related types
                $rawRelTypes = $this->dbs->getRelatedTypes([$id]);
                if (!empty($rawRelTypes)) {
                    $typeIds = self::getUniqueIds($rawRelTypes, 'rel_type_id');
                    $relTypes =  $this->getMini($typeIds);
                    $typeRelTypes = $this->container->get('type_relation_type_manager')->getWithData($rawRelTypes);
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

                // translation
                $rawTranslations = $this->dbs->getTranslations([$id]);
                if (!empty($rawTranslations)) {
                    $type->setTranslation($rawTranslations[0]['translation']);
                }

                // based on occurrence
                $rawBasedOns = $this->dbs->getBasedOns([$id]);
                $occurrenceIds = self::getUniqueIds($rawBasedOns, 'occurrence_id');
                $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);
                if (!empty($rawBasedOns)) {
                    $type->setBasedOn($occurrences[$rawBasedOns[0]['occurrence_id']]);
                }

                return $type;
            }
        );
    }

    /**
     * Add a new Person
     * @param  stdClass $data
     * @return Type
     */
    public function add(stdClass $data): Type
    {
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert();

            $new = $this->update($id, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }



    // TODO: update

    // TODO: delete
}
