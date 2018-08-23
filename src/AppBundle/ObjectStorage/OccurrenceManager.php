<?php

namespace AppBundle\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Model\Genre;
use AppBundle\Model\Image;
use AppBundle\Model\Meter;
use AppBundle\Model\Status;
use AppBundle\Model\Occurrence;

class OccurrenceManager extends DocumentManager
{
    /**
     * Get occurrences with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        return $this->wrapLevelCache(
            Occurrence::CACHENAME,
            'mini',
            $ids,
            function ($ids) {
                $occurrences = [];
                $rawLocations = $this->dbs->getLocations($ids);
                if (count($rawLocations) == 0) {
                    return [];
                }
                foreach ($rawLocations as $rawLocation) {
                    $occurrences[$rawLocation['occurrence_id']] = (new Occurrence())
                        ->setId($rawLocation['occurrence_id'])
                        ->setFoliumStart($rawLocation['folium_start'])
                        ->setFoliumStartRecto($rawLocation['folium_start_recto'])
                        ->setFoliumEnd($rawLocation['folium_end'])
                        ->setFoliumEndRecto($rawLocation['folium_end_recto'])
                        ->setGeneralLocation($rawLocation['general_location']);
                }

                // Remove all ids that did not match above
                $ids = array_keys($occurrences);

                $rawIncipits = $this->dbs->getIncipits($ids);
                if (count($rawIncipits) > 0) {
                    foreach ($rawIncipits as $rawIncipit) {
                        $occurrences[$rawIncipit['occurrence_id']]
                            ->setIncipit($rawIncipit['incipit']);
                    }
                }

                $this->setPublics($occurrences);

                return $occurrences;
            }
        );
    }

    /**
     * Get occurrences with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        return $this->wrapLevelCache(
            Occurrence::CACHENAME,
            'short',
            $ids,
            function ($ids) {
                $occurrences = $this->getMini($ids);

                // Remove all ids that did not match above
                $ids = array_keys($occurrences);

                // Manuscript
                $rawLocations = $this->dbs->getLocations($ids);
                if (count($rawLocations) == 0) {
                    return [];
                }
                $manuscriptIds = self::getUniqueIds($rawLocations, 'manuscript_id');
                $manuscripts = $this->container->get('manuscript_manager')->getShort($manuscriptIds);
                foreach ($rawLocations as $rawLocation) {
                    if (isset($manuscripts[$rawLocation['manuscript_id']])) {
                        $manuscript = $manuscripts[$rawLocation['manuscript_id']];
                        $occurrences[$rawLocation['occurrence_id']]->setManuscript($manuscript);
                    }
                }

                // Title
                $rawTitles = $this->dbs->getTitles($ids);
                foreach ($rawTitles as $rawTitle) {
                    $occurrences[$rawTitle['occurrence_id']]
                        ->setTitle($rawTitle['title']);
                }

                // Text
                $rawTexts = $this->dbs->getTexts($ids);
                foreach ($rawTexts as $rawText) {
                    $occurrences[$rawText['occurrence_id']]
                        ->setText($rawText['text_content']);
                }

                // Meter
                $rawMeters = $this->dbs->getMeters($ids);
                foreach ($rawMeters as $rawMeter) {
                    // TODO: getwithdata meter
                    $occurrences[$rawMeter['occurrence_id']]
                        ->setMeter(new Meter($rawMeter['meter_id'], $rawMeter['meter_name']));
                }

                // Subject
                $rawSubjects = $this->dbs->getSubjects($ids);
                $personIds = self::getUniqueIds($rawSubjects, 'person_id');
                $persons = [];
                if (count($personIds) > 0) {
                    $persons = $this->container->get('person_manager')->getShort($personIds);
                }
                $keywordIds = self::getUniqueIds($rawSubjects, 'keyword_id');
                $keywords = [];
                if (count($keywordIds) > 0) {
                    $keywords = $this->container->get('keyword_manager')->get($keywordIds);
                }
                foreach ($rawSubjects as $rawSubject) {
                    if (isset($rawSubject['person_id'])) {
                        $occurrences[$rawSubject['occurrence_id']]->addSubject($persons[$rawSubject['person_id']]);
                    } elseif (isset($rawSubject['keyword_id'])) {
                        $occurrences[$rawSubject['occurrence_id']]->addSubject($keywords[$rawSubject['keyword_id']]);
                    }
                }

                $this->setPersonRoles($occurrences);

                $this->setDates($occurrences);

                // Genre
                $rawGenres = $this->dbs->getGenres($ids);
                foreach ($rawGenres as $rawGenre) {
                    $occurrences[$rawGenre['occurrence_id']]
                        ->setGenre(new Genre($rawGenre['genre_id'], $rawGenre['genre_name']));
                }

                $this->setComments($occurrences);

                // text and record status
                $rawStatuses = $this->dbs->getStatuses($ids);
                foreach ($rawStatuses as $rawStatus) {
                    if ($rawStatus['type'] == 'occurrence_text') {
                        $occurrences[$rawStatus['occurrence_id']]
                            ->setTextStatus(new Status($rawStatus['status_id'], $rawStatus['status_name']));
                    }
                    if ($rawStatus['type'] == 'occurrence_record') {
                        $occurrences[$rawStatus['occurrence_id']]
                            ->setRecordStatus(new Status($rawStatus['status_id'], $rawStatus['status_name']));
                    }
                }

                // Needed to index DBBE in elasticsearch
                $this->setBibliographies($occurrences);

                return $occurrences;
            }
        );
    }

    /**
     * Get a single occurrence with all information
     * @param  int         $id
     * @return Occurrence
     */
    public function getFull(int $id): Occurrence
    {
        return $this->wrapSingleLevelCache(
            Occurrence::CACHENAME,
            'full',
            $id,
            function ($id) {
                // Get basic occurrence information
                $occurrences = $this->getShort([$id]);
                if (count($occurrences) == 0) {
                    throw new NotFoundHttpException('Occurrence with id ' . $id .' not found.');
                }

                $this->setPrevIds($occurrences);

                $occurrence = $occurrences[$id];

                // type
                $rawTypes = $this->dbs->getTypes([$id]);
                // TODO: allow multiple types
                if (count($rawTypes) == 1) {
                    $type = $this->container->get('type_manager')->getMini([$rawTypes[0]['type_id']])[$rawTypes[0]['type_id']];
                    $occurrence->setType($type);
                }

                // paleographical information
                $rawPaleographicalInfos = $this->dbs->getPaleographicalInfos([$id]);
                if (count($rawPaleographicalInfos) == 1) {
                    $occurrence
                        ->setPaleographicalInfo($rawPaleographicalInfos[0]['paleographical_info']);
                }

                // contextual information
                $rawContextualInfos = $this->dbs->getContextualInfos([$id]);
                if (count($rawContextualInfos) == 1) {
                    $occurrence
                        ->setContextualInfo($rawContextualInfos[0]['contextual_info']);
                }

                // verses
                $rawVerses = $this->dbs->getVerses([$id]);
                if (count($rawContextualInfos) == 1) {
                    $occurrence
                        ->setVerses($rawVerses[0]['verses']);
                }

                // images
                $rawImages = $this->dbs->getImages([$id]);
                foreach ($rawImages as $rawImage) {
                    if (strpos($rawImage['url'], 'http') === 0) {
                        $occurrence
                            ->addImageLink(new Image($rawImage['image_id'], $rawImage['url'], !$rawImage['is_private']));
                    } else {
                        $occurrence
                            ->addImage(new Image($rawImage['image_id'], $rawImage['url'], !$rawImage['is_private']));
                    }
                }

                return $occurrence;
            }
        );
    }

    public function getManuscriptDependencies(int $manuscriptId): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByManuscriptId($manuscriptId));
    }

    public function getPersonDependencies(int $personId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByPersonId($personId), $short);
    }

    public function add(stdClass $data): Occurrence
    {
        $this->dbs->beginTransaction();
        try {
            $occurrenceId = $this->dbs->insert();

            $newOccurrence = $this->update($occurrenceId, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newOccurrence;
    }

    public function update(int $id, stdClass $data, bool $new = false): Occurrence
    {
        $this->dbs->beginTransaction();
        try {
            $occurrence = $this->getFull($id);
            if ($occurrence == null) {
                throw new NotFoundHttpException('Occurrence with id ' . $id .' not found.');
            }



            // TODO: add actual update functions
            throw new Exception('Not implemented');



            // load new occurrence data
            $this->clearCache($id, $cacheReload);
            $newOccurrence = $this->getFull($id);

            $this->updateModified($new ? null : $occurrence, $newOccurrence);

            // (re-)index in elastic search
            $this->ess->add($newOccurrence);

            if ($cacheReload['mini']) {
                // update Elastic manuscripts
                $manuscripts = $this->container->get('manuscript_manager')->getOccurrenceDependencies($id);
                $this->container->get('manuscript_manager')->elasticIndex($manuscripts);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $newOccurrence;
    }

    // TODO: delete
}
