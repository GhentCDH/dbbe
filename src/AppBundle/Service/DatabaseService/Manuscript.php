<?php

namespace AppBundle\Service\DatabaseService;

use AppBundle\Exceptions\NotFoundInDatabaseException;
use AppBundle\Model\FuzzyDate;
use AppBundle\Service\DatabaseService\DatabaseService;

class Manuscript extends DatabaseService
{
    // name, city, library, fund, shelf, content, date, origin, patron, scribe
    public function getCompleteManuscripts(): array
    {
        $manuscripts = $this->getManuscriptIds();
        $locations = $this->getAllLocations();
        $contents = $this->getAllContents();
        $completionDates = $this->getAllCompletionDates();
        $patrons = $this->getAllBibroles('patron');
        $scribes = $this->getAllBibroles('scribe');
        $origins = $this->getAllOrigins();

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

    private function getRaw(string $sql, int $wherecount, array $params, array $types, array $ids = null): array
    {
        if (isset($ids)) {
            for ($i = 0; $i < $wherecount; $i++) {
                $params[] = $ids;
                $types[] = \Doctrine\DBAL\Connection::PARAM_INT_ARRAY;
            }
        }
        $statement = $this->conn->executeQuery(
            $sql,
            $params,
            $types
        );
        return $statement->fetchAll();
    }

    private function getRawLocations(array $ids = null): array
    {
        $sql = 'SELECT
                manuscript.identity as manuscriptid,
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

            . (isset($ids) ? ' WHERE manuscript.identity in (?)' : '')

            . ' UNION '

            . 'SELECT
                manuscript.identity as manuscriptid,
                region.identity as cityid,
                region.name as cityname,
                institution.identity as libraryid,
                institution.name as libraryname,
                null as fundid,
                null as fundname,
                located_at.identification as shelf
            from data.located_at
            inner join data.manuscript on manuscript.identity = located_at.iddocument
            inner join data.location on located_at.idlocation = location.idlocation
            inner join data.institution on location.idinstitution = institution.identity
            inner join data.region on institution.idregion = region.identity
            where location.idfund is null'

            . (isset($ids) ? ' AND manuscript.identity in (?)' : '');

        return $this->getRaw($sql, 2, [], [], $ids);
    }

    private static function formatManuscriptName(array $location): string
    {
        $name = strtoupper($location['cityname']);
        if (isset($location['libraryname'])) {
            $name .= ' - ' . $location['libraryname'];
        }
        if (isset($location['fundname'])) {
            $name .= ' - ' . $location['fundname'];
        }
        if (isset($location['shelf'])) {
            $name .= ' ' . $location['shelf'];
        }

        return $name;
    }

    private function getAllLocations(): array
    {
        $locations = [];
        $rawLocations = $this->getRawLocations();
        foreach ($rawLocations as $rawLocation) {
            $locations[$rawLocation['manuscriptid']] = [
                'name' => self::formatManuscriptName($rawLocation),
                'city' => [
                    'id' => $rawLocation['cityid'],
                    'name' => $rawLocation['cityname'],
                ],
                'library' => [
                    'id' => $rawLocation['libraryid'],
                    'name' => $rawLocation['libraryname'],
                ],
                'shelf' => $rawLocation['shelf'],
            ];
            if (isset($rawLocation['fundid'])) {
                $locations[$rawLocation['manuscriptid']]['fund'] = [
                    'id' => $rawLocation['fundid'],
                    'name' => $rawLocation['fundname'],
                ];
            }
        }

        return $locations;
    }

    public function getName(int $id): string
    {
        $locations = $this->getRawLocations([$id]);
        if (count($locations) == 0) {
            throw new NotFoundInDatabaseException;
        }

        return self::formatManuscriptName($locations[0]);
    }

    private function getRawContents(array $ids = null): array
    {
        $sql = 'SELECT iddocument, idgenre
            from data.manuscript
            inner join data.document_genre on manuscript.identity = document_genre.iddocument'

            . (isset($ids) ? ' WHERE manuscript.identity in (?)' : '');

        return $this->getRaw($sql, 1, [], [], $ids);
    }

    /**
     * Get all contents linked to a manuscript from the database.
     * @return array The contents linked to a manuscript with
     * as key the manuscript id
     * as value an array ids and names of content
     */
    private function getAllContents(): array
    {
        $rawContents = $this->getRawContents();

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

    public function getContents(int $id): array
    {
        $rawContents = $this->getRawContents([$id]);

        // get content names
        $contentIds = [];
        foreach ($rawContents as $rawContent) {
            $contentIds[] = $rawContent['idgenre'];
        }
        $contentNames = $this->getContentDescriptions($contentIds);

        // construct result
        $results = [];
        foreach ($rawContents as $rawContent) {
            $contentName = $contentNames[$rawContent['idgenre']];
            $names = [];
            foreach ($contentName as $contentNamePart) {
                $names[] = $contentNamePart['name'];
            }
            $results[] = implode(' > ', $names);
        }

        return $results;
    }

    private function getRawCompletionDates(array $ids = null): array
    {
        $sql ='SELECT manuscript.identity as id, factoid_merge.factoid_date as cdate
            from data.manuscript
            inner join (
                select factoid.subject_identity as factoid_identity,
                factoid.date as factoid_date
                from data.factoid
                inner join data.factoid_type
                on factoid.idfactoid_type = factoid_type.idfactoid_type
                    and factoid_type.type = \'completed at\'
            ) factoid_merge ON manuscript.identity = factoid_merge.factoid_identity'

            . (isset($ids) ? ' WHERE manuscript.identity in (?)' : '');

        return $this->getRaw($sql, 1, [], [], $ids);
    }

    /**
     * Get the completion dates of manuscripts.
     * These completion dates are stored in the database as factoids with name 'completed at'.
     * @return array The completion dates of manuscripts with
     * as key the manuscript id
     * as value the completion date as FuzzyDate
     */
    private function getAllCompletionDates(): array
    {
        $rawCompletionDates = $this->getRawCompletionDates();
        $completionDates = [];
        foreach ($rawCompletionDates as $rawCompletionDate) {
            $completionDates[$rawCompletionDate['id']] = new FuzzyDate($rawCompletionDate['cdate']);
        }

        return $completionDates;
    }

    public function getCompletionDate(int $id)
    {
        $completionDates = $this->getRawCompletionDates([$id]);
        if (count($completionDates) == 1) {
            return (string)(new FuzzyDate($completionDates[0]['cdate']));
        }
        return null;
    }

    private function getRawBibroles(string $role, array $ids = null): array
    {
        $sql = 'SELECT idcontainer, idperson
            from data.document_contains
            inner join data.manuscript on document_contains.idcontainer = manuscript.identity
            inner join data.original_poem on document_contains.idcontent = original_poem.identity
            inner join data.bibrole on document_contains.idcontent = bibrole.iddocument
            where type = ?'

            . (isset($ids) ? ' AND manuscript.identity in (?)' : '')

            . 'group by idcontainer, idperson';

        return $this->getRaw($sql, 1, [$role], [\PDO::PARAM_STR], $ids);
    }

    private function getAllBibroles(string $role): array
    {
        $rawBibRoles = $this->getRawBibroles($role);

        $uniqueBibRoles = self::getUniqueIds($rawBibRoles, 'idperson');
        $personDescriptions = $this->getPersonFullDescriptions($uniqueBibRoles);

        $bibRoles = [];
        foreach ($rawBibRoles as $rawBibRole) {
            $bibRoles[$rawBibRole['idcontainer']][] = [
                    'id' => $rawBibRole['idperson'],
                    'name' => $personDescriptions[$rawBibRole['idperson']],
            ];
        }

        return $bibRoles;
    }

    public function getBibroles(string $role, int $id): array
    {
        $rawBibroles = $this->getRawBibroles($role, [$id]);

        // get person names
        $personIds = [];
        foreach ($rawBibroles as $rawBibrole) {
            $personIds[] = $rawBibrole['idperson'];
        }

        return $this->getPersonFullDescriptions($personIds);
    }

    private function getRawRelatedPersons(array $ids = null): array
    {
        $sql = 'SELECT factoid.subject_identity, factoid.object_identity as idperson
            from data.manuscript
            inner join data.factoid on manuscript.identity = factoid.subject_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            inner join data.person on factoid.object_identity = person.identity
            where type = ?'

            . (isset($ids) ? ' AND manuscript.identity in (?)' : '');

        return $this->getRaw($sql, 1, ['related to'], [\PDO::PARAM_STR], $ids);
    }

    public function getRelatedPersons(int $id): array
    {
        $rawRelatedPersons = $this->getRawRelatedPersons([$id]);

        // get person names
        $personIds = [];
        foreach ($rawRelatedPersons as $rawRelatedPerson) {
            $personIds[] = $rawRelatedPerson['idperson'];
        }

        return $this->getPersonFullDescriptions($personIds);
    }

    private function getRawOrigins(array $ids = null): array
    {
        $sql = 'SELECT subject_identity,
                idinstitution,
                coalesce(institution.idregion, location.idregion) as idregion,
                institution.name as institution_name
            from data.manuscript
            inner join data.factoid on manuscript.identity = factoid.subject_identity
            inner join data.factoid_type on factoid.idfactoid_type = factoid_type.idfactoid_type
            inner join data.location on factoid.idlocation = location.idlocation
            left join data.institution on location.idinstitution = institution.identity
            where type = ?'

            . (isset($ids) ? ' AND manuscript.identity in (?)' : '');

        return $this->getRaw($sql, 1, ['written'], [\PDO::PARAM_STR], $ids);
    }

    private function getAllOrigins(): array
    {
        $rawOrigins = $this->getRawOrigins();

        $uniqueRegions = self::getUniqueIds($rawOrigins, 'idregion');
        $regionDescriptions = $this->getRegions($uniqueRegions);

        $origins = [];
        foreach ($rawOrigins as $rawOrigin) {
            $regions = $regionDescriptions[$rawOrigin['idregion']];
            if (isset($rawOrigin['idinstitution']) && isset($rawOrigin['institution_name'])) {
                $regions[] = [
                    'id' => $rawOrigin['idinstitution'],
                    'name' => $rawOrigin['institution_name'],
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

    public function getOrigin(int $id)
    {
        $rawOrigins = $this->getRawOrigins([$id]);

        if (count($rawOrigins) == 0) {
            return null;
        }

        // get region parents and all region names
        $rawOrigin = $rawOrigins[0];
        $regions = $this->getRegions([$rawOrigin['idregion']]);

        // construct names array
        $names = [];
        if (isset($rawOrigin['idinstitution']) && isset($rawOrigin['institution_name'])) {
            $names[] = $rawOrigin['institution_name'];
        }
        foreach ($regions[$rawOrigin['idregion']] as $region) {
            $names[] = $region['name'];
        }

        return implode(' > ', $names);
    }

    private function getRawBibliography(array $ids): array
    {
        $sql = 'SELECT reference.idreference, reference.idtarget
            from data.manuscript
            inner join data.reference on manuscript.identity = reference.idtarget'

            . (isset($ids) ? ' WHERE manuscript.identity in (?)' : '');

        return $this->getRaw($sql, 1, [], [], $ids);
    }

    public function getBibliographys(int $id): array
    {
        $rawBibliographies = $this->getRawBibliography([$id]);

        // get bibliography descriptions
        $bibliographyIds = [];
        foreach ($rawBibliographies as $rawBibliography) {
            $bibliographyIds[] = $rawBibliography['idreference'];
        }

        return $this->getBibliographyDescriptions($bibliographyIds);
    }

    public function getRawDiktyon(array $ids = null): array
    {
        $sql = 'SELECT manuscript.identity, global_id.identifier
            from data.manuscript
            inner join data.global_id on manuscript.identity = global_id.idsubject
            inner join data.institution on global_id.idauthority = institution.identity
            where institution.name = ?'

            . (isset($ids) ? ' AND manuscript.identity in (?)' : '');

        return $this->getRaw($sql, 1, ['Diktyon'], [\PDO::PARAM_STR], $ids);
    }

    public function getDiktyon(int $id)
    {
        $rawDiktyon = $this->getRawDiktyon([$id]);

        if (count($rawDiktyon) == 0) {
            return null;
        }

        return $this->getRawDiktyon([$id])[0]['identifier'];
    }
}
