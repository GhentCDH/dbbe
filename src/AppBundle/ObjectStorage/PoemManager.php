<?php

namespace AppBundle\ObjectStorage;

/**
 * ObjectManager for poems (occurrences and types)
 */
class PoemManager extends DocumentManager
{
    public function getPersonDependencies(int $personId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByPersonId($personId), $short ? 'getShort' : 'getMini');
    }

    public function getMeterDependencies(int $meterId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByMeterId($meterId), $short ? 'getShort' : 'getMini');
    }

    public function getGenreDependencies(int $genreId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByGenreId($genreId), $short ? 'getShort' : 'getMini');
    }

    public function getKeywordDependencies(int $keywordId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByKeywordId($keywordId), $short ? 'getShort' : 'getMini');
    }

    protected function setIncipits(array &$poems): void
    {
        $rawIncipits = $this->dbs->getIncipits(self::getIds($poems));
        if (count($rawIncipits) > 0) {
            foreach ($rawIncipits as $rawIncipit) {
                $poems[$rawIncipit['poem_id']]
                    ->setIncipit($rawIncipit['incipit']);
            }
        }
    }

    protected function setTitles(array &$poems): void
    {
        $rawTitles = $this->dbs->getTitles(self::getIds($poems));
        foreach ($rawTitles as $rawTitle) {
            $poems[$rawTitle['poem_id']]
                ->setTitle($rawTitle['title']);
        }
    }

    protected function setMeters(array &$poems): void
    {
        $rawMeters = $this->dbs->getMeters(self::getIds($poems));
        $meters = $this->container->get('meter_manager')->getWithData($rawMeters);
        foreach ($rawMeters as $rawMeter) {
            $poems[$rawMeter['poem_id']]
                ->addMeter($meters[$rawMeter['meter_id']]);
        }
    }

    protected function setSubjects(array &$poems): void
    {
        $rawSubjects = $this->dbs->getSubjects(self::getIds($poems));
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
                $poems[$rawSubject['poem_id']]
                    ->addSubject($persons[$rawSubject['person_id']]);
            } elseif (isset($rawSubject['keyword_id'])) {
                $poems[$rawSubject['poem_id']]
                    ->addSubject($keywords[$rawSubject['keyword_id']]);
            }
        }
        foreach (array_keys($poems) as $poemId) {
            $poems[$poemId]->sortSubjects();
        }
    }

    protected function setGenres(array &$poems): void
    {
        $rawGenres = $this->dbs->getGenres(self::getIds($poems));
        $genres = $this->container->get('genre_manager')->getWithData($rawGenres);
        foreach ($rawGenres as $rawGenre) {
            $poems[$rawGenre['poem_id']]
                ->addGenre($genres[$rawGenre['genre_id']]);
        }
    }

    protected function setAcknowledgements(array &$poems)
    {
        $rawAcknowledgements = $this->dbs->getAcknowledgements(self::getIds($poems));
        foreach ($rawAcknowledgements as $rawAcknowledgement) {
            $poems[$rawAcknowledgement['poem_id']]
                ->setAcknowledgement($rawAcknowledgement['acknowledgement']);
        }
    }
}
