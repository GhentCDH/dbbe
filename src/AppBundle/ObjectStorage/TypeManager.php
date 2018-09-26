<?php

namespace AppBundle\ObjectStorage;

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

                foreach ($ids as $id) {
                    $types[$id] = (new Type())
                        ->setId($id);
                }

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
                    }
                }

                // occurrences (needed in short to calculate number of occurrences)
                $rawOccurrences = $this->dbs->getOccurrences($ids);
                if (!empty($rawOccurrences)) {
                    $occurrenceIds = self::getUniqueIds($rawOccurrences, 'occurrence_id');
                    $occurrences = $this->container->get('occurrence_manager')->getMini($occurrenceIds);
                    foreach ($rawOccurrences as $rawOccurrence) {
                        $types[$rawOccurrence['type_id']]->addOccurrence($occurrences[$rawOccurrence['occurrence_id']]);
                    }
                }

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
                    throw new NotFoundHttpException('Occurrence with id ' . $id .' not found.');
                }

                $this->setAcknowledgements($types);

                $this->setIdentifications($types);

                $this->setBibliographies($types);

                $this->setPrevIds($types);

                $type = $types[$id];

                // related types
                $rawRelTypes = $this->dbs->getRelatedTypes([$id]);
                if (!empty($rawRelTypes)) {
                    $typeIds = self::getUniqueIds($rawRelTypes, 'rel_type_id');
                    $relTypes =  $this->getMini($typeIds);
                    $type->setTypes($relTypes);
                }

                return $type;
            }
        );
    }

    // TODO: update

    // TODO: delete
}
