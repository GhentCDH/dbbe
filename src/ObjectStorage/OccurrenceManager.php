<?php

namespace App\ObjectStorage;

use App\ElasticSearchService\ElasticOccurrenceService;
use App\ElasticSearchService\ElasticVerseService;
use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Exceptions\DependencyException;
use App\Model\Occurrence;
use App\Model\Status;


/**
 * ObjectManager for occurrences
 */
class OccurrenceManager extends PoemManager
{
    /**
     * Get occurrences with enough information to get an id and an incipit
     * @param  array $ids
     * @return array
     */
    public function getMicro(array $ids): array
    {
        $occurrences = [];
        if (!empty($ids)) {
            $rawLocations = $this->dbs->getLocationConfirmations($ids);
            if (count($rawLocations) == 0) {
                return [];
            }
            foreach ($rawLocations as $rawLocation) {
                $occurrences[$rawLocation['occurrence_id']] = (new Occurrence())
                    ->setId($rawLocation['occurrence_id']);
            }

            // Remove all ids that did not match above
            $ids = array_keys($occurrences);

            $this->setIncipits($occurrences);
        }

        return $occurrences;
    }

    /**
     * Get occurrences with enough information to get an id and a description
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $occurrences = [];
        if (!empty($ids)) {
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
                    ->setPageStart($rawLocation['page_start'])
                    ->setPageEnd($rawLocation['page_end'])
                    ->setUnsure($rawLocation['unsure'])
                    ->setGeneralLocation($rawLocation['general_location'])
                    ->setOldLocation($rawLocation['physical_location_removeme'])
                    ->setAlternativeFoliumStart($rawLocation['alternative_folium_start'])
                    ->setAlternativeFoliumStartRecto($rawLocation['alternative_folium_start_recto'])
                    ->setAlternativeFoliumEnd($rawLocation['alternative_folium_end'])
                    ->setAlternativeFoliumEndRecto($rawLocation['alternative_folium_end_recto'])
                    ->setAlternativePageStart($rawLocation['alternative_page_start'])
                    ->setAlternativePageEnd($rawLocation['alternative_page_end']);
            }

            // Remove all ids that did not match above
            $ids = array_keys($occurrences);

            $this->setIncipits($occurrences);

            $this->setNumberOfVerses($occurrences);

            $this->setDates($occurrences);

            // Verses (needed in mini to calculate number of verses)
            $rawVerses = $this->dbs->getVerses($ids);
            $verses = $this->container->get(VerseManager::class)->getMiniWithData($rawVerses);
            foreach ($rawVerses as $rawVerse) {
                $occurrences[$rawVerse['occurrence_id']]
                    ->addVerse($verses[$rawVerse['verse_id']]);
            }

            // Mini manuscript (needed in mini to display manuscript link)
            $rawLocations = $this->dbs->getLocations($ids);
            if (count($rawLocations) == 0) {
                return [];
            }
            $manuscriptIds = self::getUniqueIds($rawLocations, 'manuscript_id');
            $manuscripts = $this->container->get(ManuscriptManager::class)->getMini($manuscriptIds);
            foreach ($rawLocations as $rawLocation) {
                if (isset($manuscripts[$rawLocation['manuscript_id']])) {
                    $manuscript = $manuscripts[$rawLocation['manuscript_id']];
                    $occurrences[$rawLocation['occurrence_id']]->setManuscript($manuscript);
                }
            }

            $this->setPublics($occurrences);
        }

        return $occurrences;
    }

    /**
     * Get occurrences with enough information to index in ElasticSearch
     * @param  array $ids
     * @return array
     */
    public function getShort(array $ids): array
    {
        $occurrences = $this->getMini($ids);

        // Remove all ids that did not match above
        $ids = array_keys($occurrences);

        // Replace mini manuscript with short manuscripts (manuscript content information)
        $manuscriptIds = [];
        foreach ($occurrences as $occurrence) {
            if (!empty($occurrence->getManuscript())) {
                $manuscriptIds[] = $occurrence->getManuscript()->getId();
            }
        }
        $manuscripts = $this->container->get(ManuscriptManager::class)->getShort($manuscriptIds);
        foreach ($occurrences as $occurrence) {
            if (!empty($occurrence->getManuscript())) {
                $occurrence->setManuscript($manuscripts[$occurrence->getManuscript()->getId()]);
            }
        }

        $this->setTitles($occurrences);

        $this->setMetres($occurrences);

        $this->setSubjects($occurrences);

        $this->setPersonRoles($occurrences);

        $this->setGenres($occurrences);

        $this->setComments($occurrences);

        // palaeographical information
        $rawPalaeographicalInfos = $this->dbs->getPalaeographicalInfos($ids);
        foreach ($rawPalaeographicalInfos as $rawPalaeographicalInfo) {
            $occurrences[$rawPalaeographicalInfo['occurrence_id']]
                ->setPalaeographicalInfo($rawPalaeographicalInfo['palaeographical_info']);
        }

        // contextual information
        $rawContextualInfos = $this->dbs->getContextualInfos($ids);
        foreach ($rawContextualInfos as $rawContextualInfo) {
            $occurrences[$rawContextualInfo['occurrence_id']]
                ->setContextualInfo($rawContextualInfo['contextual_info']);
        }

        // statuses
        $rawStatuses = $this->dbs->getStatuses($ids);
        $statuses = $this->container->get(StatusManager::class)->getWithData($rawStatuses);
        foreach ($rawStatuses as $rawStatus) {
            switch ($rawStatus['status_type']) {
                case Status::OCCURRENCE_TEXT:
                    $occurrences[$rawStatus['occurrence_id']]
                        ->setTextStatus($statuses[$rawStatus['status_id']]);
                    break;
                case Status::OCCURRENCE_RECORD:
                    $occurrences[$rawStatus['occurrence_id']]
                        ->setRecordStatus($statuses[$rawStatus['status_id']]);
                    break;
                case Status::OCCURRENCE_DIVIDED:
                    $occurrences[$rawStatus['occurrence_id']]
                        ->setDividedStatus($statuses[$rawStatus['status_id']]);
                    break;
                case Status::OCCURRENCE_SOURCE:
                    $occurrences[$rawStatus['occurrence_id']]
                        ->setSourceStatus($statuses[$rawStatus['status_id']]);
                    break;
            }
        }

        $this->setAcknowledgements($occurrences);

        // Needed to index DBBE in elasticsearch
        $this->setBibliographies($occurrences);

        $this->setIdentifications($occurrences);

        $this->setcontributorRoles($occurrences);

        $this->setManagements($occurrences);

        $this->setPrevIds($occurrences);

        $this->setCreatedAndModifiedDates($occurrences);

        return $occurrences;
    }

    /**
     * Get a single occurrence with all information
     * @param  int         $id
     * @return Occurrence
     */
    public function getFull(int $id): Occurrence
    {
        // Get basic occurrence information
        $occurrences = $this->getShort([$id]);
        if (count($occurrences) == 0) {
            throw new NotFoundHttpException('Occurrence with id ' . $id .' not found.');
        }

        $occurrence = $occurrences[$id];

        // related occurrences
        // dbs manages sorting
        $rawRelOccurrences = $this->dbs->getRelatedOccurrences([$id]);
        if (!empty($rawRelOccurrences)) {
            $relOccurrenceIds = self::getUniqueIds($rawRelOccurrences, 'related_occurrence_id');
            $relOccurrences = $this->getMini($relOccurrenceIds);
            foreach ($rawRelOccurrences as $rawRelOccurrence) {
                $occurrence->addRelatedOccurrence(
                    $relOccurrences[$rawRelOccurrence['related_occurrence_id']],
                    $rawRelOccurrence['count']
                );
            }
            $occurrence->sortRelatedOccurrences();
        }

        // types
        $rawTypes = $this->dbs->getTypes([$id]);
        if (!empty($rawTypes)) {
            $typeIds = self::getUniqueIds($rawTypes, 'type_id');
            $types =  $this->container->get(TypeManager::class)->getMini($typeIds);
            foreach ($typeIds as $typeId) {
                $occurrence->addType($types[$typeId]);
            }
        }

        // images
        $rawImages = $this->dbs->getImages([$id]);
        $images = $this->container->get(ImageManager::class)->getWithData($rawImages);
        foreach ($rawImages as $rawImage) {
            if (!empty($rawImage['filename'])) {
                $occurrence
                    ->addImage($images[$rawImage['image_id']]);
            } else {
                $occurrence
                    ->addImageLink($images[$rawImage['image_id']]);
            }
        }

        return $occurrence;
    }

    public function getNewId(int $oldId): int
    {
        $rawId = $this->dbs->getNewId($oldId);
        if (count($rawId) != 1) {
            throw new NotFoundHttpException('The occurrence with legacy id "' . $oldId . '" does not exist.');
        }
        return $rawId[0]['new_id'];
    }

    public function getManuscriptDependencies(int $manuscriptId, $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByManuscriptId($manuscriptId), $method);
    }

    public function getTypeDependencies(int $typeId, $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByTypeId($typeId), $method);
    }

    public function add(stdClass $data): Occurrence
    {
        // Incipit and manuscript are required fields
        if (!property_exists($data, 'incipit')
            || !is_string($data->incipit)
            || empty($data->incipit)
            || !property_exists($data, 'manuscript')
            || !is_object($data->manuscript)
            || !property_exists($data->manuscript, 'id')
            || !is_numeric($data->manuscript->id)
            || empty($data->manuscript->id)
        ) {
            throw new BadRequestHttpException('Incorrect data to add a new occurrence.');
        }
        $this->dbs->beginTransaction();
        try {
            $id = $this->dbs->insert($data->manuscript->id, $data->incipit);
            $manuscriptId = $data->manuscript->id;

            // Reset manuscript (personroles + number of occurrences)
            $this->container->get(ManuscriptManager::class)->updateElasticByIds([
                $manuscriptId,
            ]);

            unset($data->manuscript);

            $touched = [];
            $new = $this->update($id, $data, true, $touched);

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();

            // Reset ES
            $this->updateElasticByIds([$id]);

            // There is an issue with doing the re-indexes below after the db rollback in the update function.
            // That is why they are done here as well.

            // Reset manuscript (personroles + number of occurrences)
            $this->container->get(ManuscriptManager::class)->updateElasticByIds([
                $manuscriptId,
            ]);

            // Rest types
            $typeIds = [];
            if (isset($data->types)) {
                foreach ($data->types as $type) {
                    if (!in_array($type->id, $typeIds)) {
                        $typeIds[] = $type->id;
                    }
                }
            }
            $this->container->get(TypeManager::class)->updateElasticByIds($typeIds);

            // Reset verses
            if (isset($touched)) {
                $this->container->get(VerseManager::class)->updateElasticByIds($touched);
            }

            throw $e;
        }

        return $new;
    }

    public function update(int $id, stdClass $data, bool $isNew = false, array &$touched = null): Occurrence
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);
            if ($old == null) {
                throw new NotFoundHttpException('Occurrence with id ' . $id .' not found.');
            }

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
            if (property_exists($data, 'title')) {
                if (!is_string($data->title)) {
                    throw new BadRequestHttpException('Incorrect title data.');
                }

                $changes['short'] = true;
                $this->dbs->upsertDelTitle($id, 'GR', $data->title);
            }
            if (property_exists($data, 'manuscript')) {
                // Manuscript is a required field
                if (!is_object($data->manuscript)
                    || !property_exists($data->manuscript, 'id')
                    || !is_numeric($data->manuscript->id)
                    || empty($data->manuscript->id)
                ) {
                    throw new BadRequestHttpException('Incorrect manuscript data.');
                }

                $changes['short'] = true;
                $this->dbs->updateManuscript($id, $data->manuscript->id);
                // Reset old and new manuscript (personroles + number of occurrences)
                $this->container->get(ManuscriptManager::class)->updateElasticByIds([
                    $old->getManuscript()->getId(),
                    $data->manuscript->id,
                ]);
            }
            if (property_exists($data, 'foliumStart')) {
                if (!is_string($data->foliumStart)
                    && !is_null($data->foliumStart)
                ) {
                    throw new BadRequestHttpException('Incorrect foliumStart data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateFoliumStart($id, $data->foliumStart);
            }
            if (property_exists($data, 'foliumStartRecto')) {
                if (!is_bool($data->foliumStartRecto)
                    && !is_null($data->foliumStartRecto)
                ) {
                    throw new BadRequestHttpException('Incorrect foliumStartRecto data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateFoliumStartRecto($id, $data->foliumStartRecto);
            }
            if (property_exists($data, 'foliumEnd')) {
                if (!is_string($data->foliumEnd)
                    && !is_null($data->foliumEnd)
                ) {
                    throw new BadRequestHttpException('Incorrect foliumEnd data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateFoliumEnd($id, $data->foliumEnd);
            }
            if (property_exists($data, 'foliumEndRecto')) {
                if (!is_bool($data->foliumEndRecto)
                    && !is_null($data->foliumEndRecto)
                ) {
                    throw new BadRequestHttpException('Incorrect foliumEndRecto data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateFoliumEndRecto($id, $data->foliumEndRecto);
            }
            if (property_exists($data, 'unsure')) {
                if (!is_bool($data->unsure)
                ) {
                    throw new BadRequestHttpException('Incorrect unsure data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateUnsure($id, $data->unsure);
            }
            if (property_exists($data, 'pageStart')) {
                if (!is_string($data->pageStart)
                    && !is_null($data->pageStart)
                ) {
                    throw new BadRequestHttpException('Incorrect pageStart data.');
                }

                $changes['mini'] = true;
                $this->dbs->updatePageStart($id, $data->pageStart);
            }
            if (property_exists($data, 'pageEnd')) {
                if (!is_string($data->pageEnd)
                    && !is_null($data->pageEnd)
                ) {
                    throw new BadRequestHttpException('Incorrect pageEnd data.');
                }

                $changes['mini'] = true;
                $this->dbs->updatePageEnd($id, $data->pageEnd);
            }
            if (property_exists($data, 'generalLocation')) {
                if (!is_string($data->generalLocation)
                ) {
                    throw new BadRequestHttpException('Incorrect generalLocation data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateGeneralLocation($id, $data->generalLocation);
            }
            if (property_exists($data, 'alternativeFoliumStart')) {
                if (!is_string($data->alternativeFoliumStart)
                    && !is_null($data->alternativeFoliumStart)
                ) {
                    throw new BadRequestHttpException('Incorrect alternativeFoliumStart data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateAlternativeFoliumStart($id, $data->alternativeFoliumStart);
            }
            if (property_exists($data, 'alternativeFoliumStartRecto')) {
                if (!is_bool($data->alternativeFoliumStartRecto)
                    && !is_null($data->alternativeFoliumStartRecto)
                ) {
                    throw new BadRequestHttpException('Incorrect alternativeFoliumStartRecto data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateAlternativeFoliumStartRecto($id, $data->alternativeFoliumStartRecto);
            }
            if (property_exists($data, 'alternativeFoliumEnd')) {
                if (!is_string($data->alternativeFoliumEnd)
                    && !is_null($data->alternativeFoliumEnd)
                ) {
                    throw new BadRequestHttpException('Incorrect alternativeFoliumEnd data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateAlternativeFoliumEnd($id, $data->alternativeFoliumEnd);
            }
            if (property_exists($data, 'alternativeFoliumEndRecto')) {
                if (!is_bool($data->alternativeFoliumEndRecto)
                    && !is_null($data->alternativeFoliumEndRecto)
                ) {
                    throw new BadRequestHttpException('Incorrect alternativeFoliumEndRecto data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateAlternativeFoliumEndRecto($id, $data->alternativeFoliumEndRecto);
            }
            if (property_exists($data, 'alternativePageStart')) {
                if (!is_string($data->alternativePageStart)
                    && !is_null($data->alternativePageStart)
                ) {
                    throw new BadRequestHttpException('Incorrect alternativePageStart data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateAlternativePageStart($id, $data->alternativePageStart);
            }
            if (property_exists($data, 'alternativePageEnd')) {
                if (!is_string($data->alternativePageEnd)
                    && !is_null($data->alternativePageEnd)
                ) {
                    throw new BadRequestHttpException('Incorrect alternativePageEnd data.');
                }

                $changes['mini'] = true;
                $this->dbs->updateAlternativePageEnd($id, $data->alternativePageEnd);
            }
            if (property_exists($data, 'numberOfVerses')) {
                if (!empty($data->numberOfVerses) && !is_numeric($data->numberOfVerses)) {
                    throw new BadRequestHttpException('Incorrect number of verses data.');
                }
                $changes['mini'] = true;
                $this->dbs->updateNumberOfVerses($id, $data->numberOfVerses);
            }
            if (property_exists($data, 'verses')) {
                if (!is_array($data->verses)) {
                    throw new BadRequestHttpException('Incorrect verses data.');
                }
                $changes['mini'] = true;
                if ($touched == null) {
                    $touched = [];
                }
                $verseIds = $this->updateVerses($old, $data->verses, $touched);
            }
            if (property_exists($data, 'types')) {
                if (!is_array($data->types)) {
                    throw new BadRequestHttpException('Incorrect types data.');
                }
                $changes['full'] = true;
                $this->updateTypes($old, $data->types);
            }
            $roles = $this->container->get(RoleManager::class)->getByType('occurrence');
            $personUpdate = false;
            foreach ($roles as $role) {
                if (property_exists($data, $role->getSystemName())) {
                    $changes['short'] = true;
                    $personUpdate = true;
                    $this->updatePersonRole($old, $role, $data->{$role->getSystemName()});
                }
            }
            // Update manuscript roles
            if ($personUpdate) {
                $manuscriptId = $old->getManuscript()->getId();
                if (isset($data->manuscript)) {
                    $manuscriptId = $data->manuscript->id;
                }
                $this->container->get(ManuscriptManager::class)->updateElasticByIds([$manuscriptId]);
            }
            if (property_exists($data, 'dates')) {
                $this->validateDates($data->dates);
                $changes['mini'] = true;
                $this->updateDates($old, $data->dates);
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
            $this->updateIdentificationwrapper($old, $data, $changes, 'full', 'occurrence');
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
            if (property_exists($data, 'palaeographicalInfo')) {
                if (!is_string($data->palaeographicalInfo)) {
                    throw new BadRequestHttpException('Incorrect palaeographical information data.');
                }
                $changes['full'] = true;
                $this->dbs->updatePalaeographicalInfo($id, $data->palaeographicalInfo);
            }
            if (property_exists($data, 'contextualInfo')) {
                if (!is_string($data->contextualInfo)) {
                    throw new BadRequestHttpException('Incorrect contextual information data.');
                }
                $changes['full'] = true;
                $this->dbs->updateContextualInfo($id, $data->contextualInfo);
            }
            if (property_exists($data, 'acknowledgements')) {
                if (!is_array($data->acknowledgements)) {
                    throw new BadRequestHttpException('Incorrect acknowledgements data.');
                }
                $changes['short'] = true;
                $this->updateAcknowledgements($old, $data->acknowledgements);
            }
            if (property_exists($data, 'recordStatus')) {
                if (!(is_object($data->recordStatus) || empty($data->recordStatus))) {
                    throw new BadRequestHttpException('Incorrect record status data.');
                }
                $changes['short'] = true;
                $this->updateStatus($old, $data->recordStatus, Status::OCCURRENCE_RECORD);
            }
            if (property_exists($data, 'textStatus')) {
                if (!(is_object($data->textStatus) || empty($data->textStatus))) {
                    throw new BadRequestHttpException('Incorrect text status data.');
                }
                $changes['short'] = true;
                $this->updateStatus($old, $data->textStatus, Status::OCCURRENCE_TEXT);
            }
            if (property_exists($data, 'dividedStatus')) {
                if (!(is_object($data->dividedStatus) || empty($data->dividedStatus))) {
                    throw new BadRequestHttpException('Incorrect divided status data.');
                }
                $changes['short'] = true;
                $this->updateStatus($old, $data->dividedStatus, Status::OCCURRENCE_DIVIDED);
            }
            if (property_exists($data, 'sourceStatus')) {
                if (!(is_object($data->sourceStatus) || empty($data->sourceStatus))) {
                    throw new BadRequestHttpException('Incorrect source status data.');
                }
                $changes['short'] = true;
                $this->updateStatus($old, $data->sourceStatus, Status::OCCURRENCE_SOURCE);
            }
            if (property_exists($data, 'images')) {
                if (!(is_array($data->images))) {
                    throw new BadRequestHttpException('Incorrect images data.');
                }
                $changes['full'] = true;
                $this->updateImages($old, $data->images);
            }
            if (property_exists($data, 'imageLinks')) {
                if (!(is_array($data->imageLinks))) {
                    throw new BadRequestHttpException('Incorrect image links data.');
                }
                $changes['full'] = true;
                $this->updateImageLinks($old, $data->imageLinks);
            }
            $contributorRoles = $this->container->get(RoleManager::class)->getContributorByType('occurrence');
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

            // (re-)index in elastic search
            if ($changes['mini'] || $changes['short']) {
                $this->ess->add($new);
            }

            // commit transaction
            $this->dbs->commit();
        } catch (\Exception $e) {
            $this->dbs->rollBack();

            // Reset elasticsearch
            if (isset($new) && isset($old)) {
                $this->ess->add($old);
            }

            if (isset($old)) {
                // Reset manuscripts (potentially old and new)
                // (person roles)
                // If this is a new occurrence, the manuscript will be linked in the old occurrence
                $manuscriptIds = [$old->getManuscript()->getId()];
                if (isset($data->manuscript)) {
                    $manuscriptIds[] = $data->manuscript->id;
                }
                $this->container->get(ManuscriptManager::class)->updateElasticByIds($manuscriptIds);

                // Reset types (potentially old and new)
                // (number of occurrences)
                $typeIds = array_keys($old->getTypes());
                if (isset($data->types)) {
                    foreach ($data->types as $type) {
                        if (!in_array($type->id, $typeIds)) {
                            $typeIds[] = $type->id;
                        }
                    }
                }
                $this->container->get(TypeManager::class)->updateElasticByIds($typeIds);
            }

            // Reset verses
            if (isset($touched)) {
                $this->container->get(VerseManager::class)->updateElasticByIds($touched);
            }

            throw $e;
        }

        return $new;
    }

    public function updateVerses(Occurrence $occurrence, array $verses, array &$touched)
    {
        foreach ($verses as $verse) {
            if (!is_object($verse)
                || !property_exists($verse, 'verse')
                || !is_string($verse->verse)
                || (
                    property_exists($verse, 'id')
                    && !(empty($verse->id) || is_numeric($verse->id))
                )
            ) {
                throw new BadRequestHttpException('Incorrect verses data.');
            }
        }

        $oldVerses = $occurrence->getVerses();
        $ids = [];
        foreach ($verses as $order => $verse) {
            if (!property_exists($verse, 'id') || empty($verse->id)) {
                // new verses
                $verse->occurrence = json_decode(json_encode(['id' => $occurrence->getId()]));
                $verse->order = $order;
                $newVerse = $this->container->get(VerseManager::class)->add($verse);
                $touched[] = $newVerse->getId();
            } else {
                // old verses
                $oldVerseFilter = array_filter(
                    $oldVerses,
                    function ($oldVerse) use ($verse) {
                        return $oldVerse->getId() == $verse->id;
                    }
                );
                if (count($oldVerseFilter) != 1) {
                    throw new BadRequestHttpException('Incorrect verses data.');
                }
                $ids[] = $verse->id;
                $oldVerse = $oldVerseFilter[$verse->id];
                $data = [];
                if ($oldVerse->getOrder() != $order) {
                    $data['order'] = $order;
                }
                if ($oldVerse->getVerse() != $verse->verse) {
                    $data['verse'] = $verse->verse;
                }
                if (property_exists($verse, 'linkVerses')) {
                    if (!is_array($verse->linkVerses)
                    ) {
                        throw new BadRequestHttpException('Incorrect linkVerses data.');
                    }
                    foreach ($verse->linkVerses as $linkVerse) {
                        if (!is_object($linkVerse)
                            ||!property_exists($linkVerse, 'id')
                            ||!is_numeric($linkVerse->id)
                        ) {
                            throw new BadRequestHttpException('Incorrect linkVerses data.');
                        }
                    }
                    $data['linkVerses'] = $verse->linkVerses;
                } elseif (property_exists($verse, 'groupId')
                    && $verse->groupId == null
                    && $oldVerse->getGroupId() != null
                ) {
                    // Remove existing links
                    $data['groupId'] = null;
                }
                if (!empty($data)) {
                    $this->container->get(VerseManager::class)->update($verse->id, json_decode(json_encode($data)));
                    $touched[] = $verse->id;
                    if (property_exists($verse, 'linkVerses')) {
                        foreach ($verse->linkVerses as $linkVerse) {
                            $touched[] = $linkVerse->id;
                        }
                    }
                }
            }
        }

        // deleted verses
        foreach ($oldVerses as $oldVerse) {
            if (!in_array($oldVerse->getId(), $ids)) {
                $this->container->get(VerseManager::class)->delete($oldVerse->getId());
                $touched[] = $oldVerse->getId();
            }
        }
    }

    private function updateTypes(Occurrence $occurrence, array $types): void
    {
        foreach ($types as $type) {
            if (!is_object($type)
                || !property_exists($type, 'id')
                || !is_numeric($type->id)
            ) {
                throw new BadRequestHttpException('Incorrect content data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($types, $occurrence->getTypes());

        if (count($delIds) > 0) {
            $this->dbs->delTypes($occurrence->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addType($occurrence->getId(), $addId);
        }

        $oldTypes = $occurrence->getTypes();
        foreach ($types as $index => $type) {
            if (!isset($oldTypes[$index]) || $oldTypes[$index]->getId() != $type->id) {
                $this->dbs->updateTypeRank($occurrence->getId(), $type->id, $index + 1);
            }
        }

        // update elastic types search
        $typeIds = array_keys($occurrence->getTypes());
        foreach ($types as $type) {
            if (!in_array($type->id, $typeIds)) {
                $typeIds[] = $type->id;
            }
        }
        $this->container->get(TypeManager::class)->updateElasticByIds($typeIds);
    }

    private function updateImages(Occurrence $occurrence, array $images): void
    {
        $newIds = [];
        foreach ($images as $image) {
            if (!is_object($image)
                || !property_exists($image, 'id')
                || !is_numeric($image->id)
                || (property_exists($image, 'public') && !is_bool($image->public))
            ) {
                throw new BadRequestHttpException('Incorrect image data.');
            } else {
                $newIds[] = $image->id;
            }
        }

        list($delIds, $addIds) = self::calcDiff($images, $occurrence->getImages());

        if (count($delIds) > 0) {
            $this->dbs->delImages($occurrence->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addImage($occurrence->getId(), $addId);
        }

        // Update public state if necessary
        $oldImages = $this->container->get(ImageManager::class)->get($newIds);
        foreach ($images as $image) {
            if (property_exists($image, 'public') && $image->public != $oldImages[$image->id]->getPublic()) {
                $this->container->get(ImageManager::class)->update(
                    $image->id,
                    json_decode(json_encode(['public' => $image->public]))
                );
            }
        }
    }

    private function updateImageLinks(Occurrence $occurrence, array $imageLinks): void
    {
        $updateIds = [];
        $updateLinks = [];
        $newLinks = [];
        foreach ($imageLinks as $link) {
            if (!is_object($link)
                || (property_exists($link, 'id') && !is_numeric($link->id))
                || (property_exists($link, 'url') && !is_string($link->url))
                || (property_exists($link, 'public') && !is_bool($link->public))
                // If it is a new imageLink, both url and public are mandatory
                || (
                    !property_exists($link, 'id')
                    && (
                        !property_exists($link, 'url')
                        || empty($link->url)
                        || !property_exists($link, 'public')
                    )
                )
            ) {
                throw new BadRequestHttpException('Incorrect image link data.');
            } elseif (property_exists($link, 'id')) {
                $updateIds[] = $link->id;
                $updateLinks[] = $link;
            } else {
                $newLinks[] = $link;
            }
        }

        // Remove and add existing image links
        list($delIds, $addIds) = self::calcDiff($updateLinks, $occurrence->getImageLinks());

        if (count($delIds) > 0) {
            $this->dbs->delImages($occurrence->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addImage($occurrence->getId(), $addId);
        }


        // Update url and public state if necessary
        $oldImageLinks = [];
        if (!empty($updateIds)) {
            $oldImageLinks = $this->container->get(ImageManager::class)->get($updateIds);
        }
        foreach ($updateLinks as $link) {
            $data = [];
            if (property_exists($link, 'public') && $link->public !== $oldImageLinks[$link->id]->getPublic()) {
                $data['public'] = $link->public;
            }
            if (property_exists($link, 'url') && $link->url !== $oldImageLinks[$link->id]->getUrl()) {
                $data['url'] = $link->url;
            }
            if (!empty($data)) {
                $this->container->get(ImageManager::class)->update(
                    $link->id,
                    json_decode(json_encode($data))
                );
            }
        }

        // Add new image links
        foreach ($newLinks as $link) {
            $imageLink = $this->container->get(ImageManager::class)->add($link);
            $this->dbs->addImage($occurrence->getId(), $imageLink->getId());
        }
    }

    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $old = $this->getFull($id);

            foreach ($old->getVerses() as $verse) {
                $this->container->get(VerseManager::class)->delete($verse->getId());
            }

            $this->dbs->delete($id);

            $this->updateModified($old, null);

            // remove from elasticsearch
            $this->deleteElasticByIdIfExists($id);
            $manuscriptId=$old->getManuscript()->getId();
            $this->container->get(ManuscriptManager::class)->updateElasticByIds([$manuscriptId]);

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


    private function formatRow(array $item, string $verses = ''): array
    {
        $manuscript = $item['manuscript'] ?? [];
        $implodeNames = fn($key) => !empty($item[$key]) ? implode(' | ', array_column($item[$key], 'name')) : '';

        return [
            $item['id'] ?? '',
            $item['incipit'] ?? '',
            $verses,
            $implodeNames('genre'),
            $implodeNames('subject'),
            $implodeNames('metre'),
            $item['date_floor_year'] ?? '',
            $item['date_ceiling_year'] ?? '',
            $manuscript['id'] ?? '',
            $manuscript['name'] ?? '',
        ];
    }

    public function generateCsvStream(
        array $params,
        ElasticOccurrenceService $occurrenceService,
        ElasticVerseService $verseService,
        bool $isAuthorized
    ) {
        $stream = fopen('php://temp', 'r+');

        fwrite($stream, "\xEF\xBB\xBF");

        fputcsv($stream, [
            'id', 'incipit', 'verses', 'genres', 'subjects', 'metres',
            'date_floor_year', 'date_ceiling_year', 'manuscript_id', 'manuscript_name'
        ], ';');

        $params['limit'] = 1000;
        $params['orderBy'] = ['id'];
        $params['ascending'] = 1;
        $params['allow_large_results'] = true;

        $totalFetched = 0;
        $searchAfter = null;
        $maxResults = $isAuthorized ? 10000 : 1000;

        while (true) {
            if ($searchAfter !== null) {
                $params['search_after'] = $searchAfter;
            }

            $result = $occurrenceService->runFullSearch($params, $isAuthorized);
            $data = $result['data'] ?? [];
            $count = count($data);

            if ($count === 0) {
                break;
            }

            foreach ($data as $item) {
                if ($totalFetched >= $maxResults) {
                    break 2;
                }
                $verses = $verseService->findVersesByOccurrenceId($item['id']);
                usort($verses, static function ($a, $b) {
                    return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
                });
                $row = $this->formatRow($item,
                    implode("\n", array_column($verses, 'verse')));

                fputcsv($stream, $row, ';');
                $totalFetched++;
            }

            $last = end($data);
            if (!isset($last['_search_after'])) {
                break;
            }

            $searchAfter = $last['_search_after'];
        }

        rewind($stream);
        return $stream;
    }

}
