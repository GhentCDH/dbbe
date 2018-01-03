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

        $mcs = $this->getManuscriptContents();
        $uniqueMCIds = $this->getUniqueIds($mcs);
        $contents = $this->getcontents($uniqueMCIds);
        $completionDates = $this->getCompletionDates();
        $mps = $this->getPatrons();
        $uniqueMPIds = $this->getUniqueIds($mps);
        $patrons = $this->getPersonDescriptions($uniqueMPIds);

        foreach ($manuscripts as $key => $ms) {
            if (isset($locations[$ms['id']])) {
                foreach ($locations[$ms['id']] as $field => $value) {
                    $manuscripts[$key][$field] = $value;
                }
            }

            if (isset($mcs[$ms['id']])) {
                $contentNames = [];
                foreach ($mcs[$ms['id']] as $contentid) {
                    $contentNames[] = implode(':', $contents[$contentid]);
                }
                $manuscripts[$key]['content'] = $contentNames;
            }

            if (isset($completionDates[$ms['id']])) {
                $completionDate = new FuzzyDate($completionDates[$ms['id']]);
                $manuscripts[$key]['date_floor_year'] =
                    !empty($completionDate->getFloor()) ? $completionDate->getFloor()->format('Y') : null;
                $manuscripts[$key]['date_ceiling_year'] =
                    !empty($completionDate->getCeiling()) ? $completionDate->getCeiling()->format('Y') : null;
            }

            if (isset($mps[$ms['id']])) {
                $patronNames = [];
                foreach ($mps[$ms['id']] as $patronid) {
                    $patronNames[] = $patrons[$patronid];
                }
                $manuscripts[$key]['patron'] = implode('|', $patronNames);
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

    private function getLocations(): array
    {
        $statement = $this->conn->prepare(
            'SELECT manuscript.identity as manuscriptid,
                    region.name as city,
                    institution.name as library,
                    fund.name as fund,
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
                    region.name as city,
                    institution.name as library,
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
                'city' => $fl['city'],
                'library' => $fl['library'],
                'shelf' => $fl['shelf']
            ];
            if (isset($fl['fund'])) {
                $locations[$fl['manuscriptid']]['fund'] = $fl['fund'];
            }
        }

        return $locations;
    }

    /**
     * Get all contents linked to a manuscript from the database.
     * @return array The contents linked to a manuscript with
     * as key the manuscript id
     * as value an array with the linked content ids
     */
    private function getManuscriptContents(): array
    {
        $statement = $this->conn->prepare(
            'SELECT iddocument, idgenre
            from data.manuscript
            inner join data.document_genre on manuscript.identity = document_genre.iddocument'
        );
        $statement->execute();
        $rawContents = $statement->fetchAll();
        $contents = [];
        foreach ($rawContents as $rawContent) {
            $contents[$rawContent['iddocument']][] = $rawContent['idgenre'];
        }
        return $contents;
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

    private function getPatrons(): array
    {
        $statement = $this->conn->prepare(
            'SELECT idcontainer, idperson
            from data.document_contains
            inner join data.manuscript on document_contains.idcontainer = manuscript.identity
            inner join data.original_poem on document_contains.idcontent = original_poem.identity
            inner join data.bibrole on document_contains.idcontent = bibrole.iddocument
            where type = \'patron\'
            group by idcontainer, idperson
            order by idcontainer'
        );
        $statement->execute();
        $rawPatrons = $statement->fetchAll();
        $patrons = [];
        foreach ($rawPatrons as $rawPatron) {
            $patrons[$rawPatron['idcontainer']][] = $rawPatron['idperson'];
        }
        return $patrons;
    }
}
