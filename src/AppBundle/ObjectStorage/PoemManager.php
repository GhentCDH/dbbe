<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Poem;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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

    public function getAcknowledgementDependencies(int $acknowledgementId, bool $short = false): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByAcknowledgementId($acknowledgementId), $short ? 'getShort' : 'getMini');
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

    protected function setNumberOfVerses(array &$poems): void
    {
        $rawNumbersOfVerses = $this->dbs->getNumberOfVerses(self::getIds($poems));
        if (count($rawNumbersOfVerses) > 0) {
            foreach ($rawNumbersOfVerses as $rawNumberOfVerses) {
                $poems[$rawNumberOfVerses['poem_id']]
                    ->setNumberOfVerses($rawNumberOfVerses['verses']);
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
        $acknowledgements = $this->container->get('acknowledgement_manager')->getWithData($rawAcknowledgements);
        foreach ($rawAcknowledgements as $rawAcknowledgement) {
            $poems[$rawAcknowledgement['poem_id']]
                ->addAcknowledgement($acknowledgements[$rawAcknowledgement['acknowledgement_id']]);
        }
        foreach (array_keys($poems) as $poemId) {
            $poems[$poemId]->sortAcknowledgements();
        }
    }

    protected function updateMeters(Poem $poem, array $meters): void
    {
        foreach ($meters as $meter) {
            if (!is_object($meter)
                || !property_exists($meter, 'id')
                || !is_numeric($meter->id)
            ) {
                throw new BadRequestHttpException('Incorrect meter data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($meters, $poem->getMeters());

        if (count($delIds) > 0) {
            $this->dbs->delMeters($poem->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addMeter($poem->getId(), $addId);
        }
    }

    protected function updateGenres(Poem $poem, array $genres): void
    {
        foreach ($genres as $genre) {
            if (!is_object($genre)
                || !property_exists($genre, 'id')
                || !is_numeric($genre->id)
            ) {
                throw new BadRequestHttpException('Incorrect genre data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($genres, $poem->getGenres());

        if (count($delIds) > 0) {
            $this->dbs->delGenres($poem->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addGenre($poem->getId(), $addId);
        }
    }

    protected function updatePersonSubjects(Poem $poem, array $persons): void
    {
        foreach ($persons as $person) {
            if (!is_object($person)
                || !property_exists($person, 'id')
                || !is_numeric($person->id)
            ) {
                throw new BadRequestHttpException('Incorrect person subject data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($persons, $poem->getPersonSubjects());

        if (count($delIds) > 0) {
            $this->dbs->delSubjects($poem->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addSubject($poem->getId(), $addId);
        }
    }

    protected function updateKeywordSubjects(Poem $poem, array $keywords): void
    {
        foreach ($keywords as $keyword) {
            if (!is_object($keyword)
                || !property_exists($keyword, 'id')
                || !is_numeric($keyword->id)
            ) {
                throw new BadRequestHttpException('Incorrect keyword subject data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($keywords, $poem->getKeywordSubjects());

        if (count($delIds) > 0) {
            $this->dbs->delSubjects($poem->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addSubject($poem->getId(), $addId);
        }
    }

    protected function updateAcknowledgements(Poem $poem, array $acknowledgements): void
    {
        foreach ($acknowledgements as $acknowledgement) {
            if (!is_object($acknowledgement)
                || !property_exists($acknowledgement, 'id')
                || !is_numeric($acknowledgement->id)
            ) {
                throw new BadRequestHttpException('Incorrect acknowledgement data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($acknowledgements, $poem->getAcknowledgements());

        if (count($delIds) > 0) {
            $this->dbs->delAcknowledgements($poem->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addAcknowledgement($poem->getId(), $addId);
        }
    }
}
