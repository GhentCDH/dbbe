<?php

namespace AppBundle\Service\DatabaseService;

use AppBundle\Model\FuzzyDate;
use AppBundle\Service\DatabaseService\DatabaseService;

class Manuscript extends DatabaseService
{
    // city, library, fund, shelf, content, date, origin, patron, scribe
    public function getCompleteManuscripts(): array
    {
        $manuscripts = $this->getManuscriptIds();
        $locations = $this->getLocations();
        $contents = $this->getContents();
        $completionDates = $this->getCompletionDates();
        $patrons = $this->getBibroles('patron');
        $scribes = $this->getBibroles('scribe');
        $origins = $this->getManuscriptOrigins();

        foreach ($manuscripts as $key => $ms) {
            if (isset($locations[$ms['id']])) {
                foreach ($locations[$ms['id']] as $field => $value) {
                    $manuscripts[$key][$field] = $value;
                }
            }

            if (isset($contents[$ms['id']])) {
                $manuscripts[$key]['content'] = $contents[$ms['id']];
            }

            if (isset($completionDates[$ms['id']])) {
                $completionDate = $completionDates[$ms['id']];
                $manuscripts[$key]['date_floor_year'] =
                    !empty($completionDate->getFloor()) ? intval($completionDate->getFloor()->format('Y')) : null;
                $manuscripts[$key]['date_ceiling_year'] =
                    !empty($completionDate->getCeiling()) ? intval($completionDate->getCeiling()->format('Y')) : null;
            }

            if (isset($patrons[$ms['id']])) {
                $manuscripts[$key]['patron'] = $patrons[$ms['id']];
            }

            if (isset($scribes[$ms['id']])) {
                $manuscripts[$key]['scribe'] = $scribes[$ms['id']];
            }

            if (isset($origins[$ms['id']])) {
                $manuscripts[$key]['origin'] = $origins[$ms['id']];
            }
        }

        return $manuscripts;
    }

    private function getManuscriptIds(): array
    {
        $statement = $this->conn->prepare(
            'SELECT manuscript.identity as id
            FROM data.manuscript'
        );
        $statement->execute();
        return $statement->fetchAll();
    }

    public function getNames(): array
    {
        $cache = $this->cache->getItem('manuscript_names');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $locations = $this->getLocations();
        $names = [];
        foreach ($locations as $id => $location) {
            $names[$id] = strtoupper($location['city']['name']);
            if (isset($location['library']['name'])) {
                $names[$id] .= ' - ' . $location['library']['name'];
            }
            if (isset($location['fund']['name'])) {
                $names[$id] .= ' - ' . $location['fund']['name'];
            }
            if (isset($location['shelf'])) {
                $names[$id] .= ' ' . $location['shelf'];
            }
        }

        $this->cache->save($cache->set($names));
        return $names;
    }

    private function getLocations(): array
    {
        $statement = $this->conn->prepare(
            'SELECT manuscript.identity as manuscriptid,
                    region.identity as cityid,
                    region.name as cityname,
                    institution.identity as libraryid,
                    institution.name as libraryname,
                    fund.idfund as fundid,
                    fund.name as fundname,
                    located_at.identification as shelf
            from data.located_at
            inner join data.manuscript on manuscript.identity = located_at.iddocument
            inner join data.location on located_at.idlocation = location.idlocation
            inner join data.fund on location.idfund = fund.idfund
            inner join data.institution on fund.idlibrary = institution.identity
            inner join data.region on institution.idregion = region.identity'
        );
        $statement->execute();
        $fundLocations = $statement->fetchAll();

        $statement = $this->conn->prepare(
            'SELECT manuscript.identity as manuscriptid,
                    region.identity as cityid,
                    region.name as cityname,
                    institution.identity as libraryid,
                    institution.name as libraryname,
                    located_at.identification as shelf
            from data.located_at
            inner join data.manuscript on manuscript.identity = located_at.iddocument
            inner join data.location on located_at.idlocation = location.idlocation
            inner join data.institution on location.idinstitution = institution.identity
            inner join data.region on institution.idregion = region.identity'
        );
        $statement->execute();
        $libraryLocations = $statement->fetchAll();

        $locations = [];
        foreach (array_merge($fundLocations, $libraryLocations) as $fl) {
            $locations[$fl['manuscriptid']] = [
                'city' => [
                    'id' => $fl['cityid'],
                    'name' => $fl['cityname'],
                ],
                'library' => [
                    'id' => $fl['libraryid'],
                    'name' => $fl['libraryname'],
                ],
                'shelf' => $fl['shelf'],
            ];
            if (isset($fl['fundid'])) {
                $locations[$fl['manuscriptid']]['fund'] = [
                    'id' => $fl['fundid'],
                    'name' => $fl['fundname'],
                ];
            }
        }

        return $locations;
    }

    public function getFormattedContents(): array
    {
        $cache = $this->cache->getItem('manuscript_formatted_contents');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $contents = $this->getContents();
        $formattedContents = [];
        foreach ($contents as $id => $content) {
            $formattedContents[$id] = $content[count($content) -1]['name'];
        }

        $this->cache->save($cache->set($formattedContents));
        return $formattedContents;
    }

    /**
     * Get all contents linked to a manuscript from the database.
     * @return array The contents linked to a manuscript with
     * as key the manuscript id
     * as value an array ids and names of content
     */
    private function getContents(): array
    {
        $statement = $this->conn->prepare(
            'SELECT iddocument, idgenre
            from data.manuscript
            inner join data.document_genre on manuscript.identity = document_genre.iddocument'
        );
        $statement->execute();
        $rawContents = $statement->fetchAll();

        $uniqueContents = self::getUniqueIds($rawContents, 'idgenre');
        $contentDescriptions = $this->getContentDescriptions($uniqueContents);

        $contents = [];
        foreach ($rawContents as $rawContent) {
            $contentEntries = $contentDescriptions[$rawContent['idgenre']];

            $names = [];
            foreach ($contentEntries as $content) {
                $names[] = $content['name'];
                $contents[$rawContent['iddocument']][] = [
                    'id' => $content['id'],
                    'name' => implode(' > ', $names),
                ];
            }
            // Only last element from eacht content parent array should be shown to the end user
            $contents[$rawContent['iddocument']][count($contents[$rawContent['iddocument']]) -1]['display'] = true;
        }

        return $contents;
    }

    /**
     * Get the completion dates of manuscripts as strings.
     * @return array The completion dates of manuscripts with
     * as key the manuscript id
     * as value the completion date formatted as a string
     */
    public function getFormattedCompletionDates(): array
    {
        $cache = $this->cache->getItem('manuscript_formatted_completion_dates');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $completionDates = $this->getCompletionDates();
        $formattedCompletionDates = [];
        foreach ($completionDates as $id => $date) {
            $formattedCompletionDates[$id] = (string)$date;
        }

        $this->cache->save($cache->set($formattedCompletionDates));
        return $formattedCompletionDates;
    }

    /**
     * Get the completion dates of manuscripts.
     * These completion dates are stored in the database as factoids with name 'completed at'.
     * @return array The completion dates of manuscripts with
     * as key the manuscript id
     * as value the completion date as FuzzyDate
     */
    private function getCompletionDates(): array
    {
        $statement = $this->conn->prepare(
            'SELECT manuscript.identity, factoid_merge.factoid_date
            from data.manuscript
            inner join (
                select factoid.subject_identity as factoid_identity,
                factoid.date as factoid_date
                from data.factoid
                inner join data.factoid_type
                on factoid.idfactoid_type = factoid_type.idfactoid_type
                    and factoid_type.type = \'completed at\'
            ) factoid_merge ON manuscript.identity = factoid_merge.factoid_identity'
        );
        $statement->execute();
        $rawCompletionDates = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
        $completionDates = [];
        foreach ($rawCompletionDates as $key => $value) {
            $completionDates[$key] = new FuzzyDate($value);
        }

        return $completionDates;
    }

    public function getBibroles(string $role): array
    {
        $cache = $this->cache->getItem('manuscript_bibrole_' . $role);
        if ($cache->isHit()) {
            return $cache->get();
        }

        $statement = $this->conn->prepare(
            'SELECT idcontainer, idperson
            from data.document_contains
            inner join data.manuscript on document_contains.idcontainer = manuscript.identity
            inner join data.original_poem on document_contains.idcontent = original_poem.identity
            inner join data.bibrole on document_contains.idcontent = bibrole.iddocument
            where type = ?
            group by idcontainer, idperson'
        );
        $statement->execute([$role]);
        $rawBibRoles = $statement->fetchAll();

        $uniqueBibRoles = self::getUniqueIds($rawBibRoles, 'idperson');
        $personDescriptions = $this->getPersonDescriptions($uniqueBibRoles);

        $bibRoles = [];
        foreach ($rawBibRoles as $rawBibRole) {
            $bibRoles[$rawBibRole['idcontainer']][] = [
                    'id' => $rawBibRole['idperson'],
                    'name' => $personDescriptions[$rawBibRole['idperson']],
            ];
        }

        $this->cache->save($cache->set($bibRoles));
        return $bibRoles;
    }

    public function getRelatedPersons(): array
    {
        $cache = $this->cache->getItem('manuscript_related_persons');
        if ($cache->isHit()) {
            return $cache->get();
        }

        $statement = $this->conn->prepare(
            'SELECT factoid.subject_identity, factoid.object_identity
            from data.manuscript
            inner join data.factoid on manuscript.identity = factoid.subject_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            inner join data.person on factoid.object_identity = person.identity
            where type = \'related to\''
        );
        $statement->execute();
        $rawRelatedPersons = $statement->fetchAll();

        $uniqueRelatedPersons = self::getUniqueIds($rawRelatedPersons, 'object_identity');
        $personDescriptions = $this->getPersonDescriptions($uniqueRelatedPersons);

        $relatedPersons = [];
        foreach ($rawRelatedPersons as $rawRelatedPerson) {
            $relatedPersons[$rawRelatedPerson['subject_identity']][] = [
                'id' => $rawRelatedPerson['object_identity'],
                'name' => $personDescriptions[$rawRelatedPerson['object_identity']],
            ];
        }

        $this->cache->save($cache->set($relatedPersons));
        return $relatedPersons;
    }

    private function getManuscriptOrigins(): array
    {
        // origin can be eather an institution or a region
        // regions can have parents

        // institution (with region)
        $statement = $this->conn->prepare(
            'SELECT subject_identity, idinstitution, coalesce(institution.idregion, location.idregion) as idregion, name
            from data.factoid
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            inner join data.location on factoid.idlocation = location.idlocation
            left join data.institution on location.idinstitution = institution.identity
            where type = \'written\''
        );
        $statement->execute();
        $rawOrigins = $statement->fetchAll();

        $uniqueRegions = self::getUniqueIds($rawOrigins, 'idregion');
        $regionDescriptions = $this->getRegions($uniqueRegions);

        $origins = [];
        foreach ($rawOrigins as $rawOrigin) {
            $regions = $regionDescriptions[$rawOrigin['idregion']];
            if (isset($rawOrigin['idinstitution']) && isset($rawOrigin['name'])) {
                $regions[] = [
                    'id' => $rawOrigin['idinstitution'],
                    'name' => $rawOrigin['name'],
                ];
            }

            $names = [];
            foreach ($regions as $region) {
                $names[] = $region['name'];
                $origins[$rawOrigin['subject_identity']][] = [
                    'id' => $region['id'],
                    'name' => implode(' > ', $names),
                ];
            }
        }
        return $origins;
    }
}
