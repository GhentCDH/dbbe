<?php

namespace AppBundle\Service\DatabaseService;

use AppBundle\Service\DatabaseService\DatabaseService;
use Doctrine\ORM\Mapping\Id;
use Twig\Node\WithNode;

class Manuscript extends DatabaseService
{
    // city, library, fund, shelf, content, date, origin, patron, scribe
    public function getCompleteManuscripts(): array
    {
        $manuscripts = $this->getManuscriptIds();

        $locations = $this->getLocations();

        $mcs = $this->getManuscriptContents();
        $uniqueIds = $this->getUniqueDocumentContentIds($mcs);
        $contents = $this->getcontents($uniqueIds);

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
                $manuscripts[$key]['content'] = implode('|', $contentNames);
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
     * as value the content id
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
}
