<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Poem;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * ObjectManager for poems (occurrences and types)
 */
abstract class PoemManager extends DocumentManager
{
    abstract public function getMicro(array $ids): array;

    protected function getAllCombined(string $level, string $sortFunction = null): array
    {
        $rawIds = $this->dbs->getIds();
        $ids = self::getUniqueIds($rawIds, $this->entityType . '_id');

        $objects = [];
        switch ($level) {
            case 'micro':
                $objects = $this->getMicro($ids);
                break;
            case 'mini':
                $objects = $this->getMini($ids);
                break;
            case 'short':
                $objects = $this->getShort($ids);
                break;
            case 'sitemap':
                $objects = $this->getSitemap($ids);
                break;
        }

        if (!empty($sortFunction)) {
            usort($objects, function ($a, $b) use ($sortFunction) {
                if ($sortFunction == 'getId') {
                    return $a->{$sortFunction}() > $b->{$sortFunction}();
                }
                return strcmp($a->{$sortFunction}(), $b->{$sortFunction}());
            });
        }

        return $objects;
    }

    public function getMetreDependencies(int $metreId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByMetreId($metreId), $method);
    }

    public function getGenreDependencies(int $genreId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByGenreId($genreId), $method);
    }

    public function getKeywordDependencies(int $keywordId, string $method): array
    {
        return $this->getDependencies($this->dbs->getDepIdsByKeywordId($keywordId), $method);
    }

    protected function setIncipits(array &$poems): void
    {
        $rawIncipits = $this->dbs->getIncipits(array_keys($poems));
        if (count($rawIncipits) > 0) {
            foreach ($rawIncipits as $rawIncipit) {
                $poems[$rawIncipit['poem_id']]
                    ->setIncipit($rawIncipit['incipit']);
            }
        }
    }

    protected function setNumberOfVerses(array &$poems): void
    {
        $rawNumbersOfVerses = $this->dbs->getNumberOfVerses(array_keys($poems));
        if (count($rawNumbersOfVerses) > 0) {
            foreach ($rawNumbersOfVerses as $rawNumberOfVerses) {
                $poems[$rawNumberOfVerses['poem_id']]
                    ->setNumberOfVerses($rawNumberOfVerses['verses']);
            }
        }
    }

    protected function setTitles(array &$poems): void
    {
        $rawTitles = $this->dbs->getTitles(array_keys($poems));
        foreach ($rawTitles as $rawTitle) {
            $poems[$rawTitle['poem_id']]
                ->setTitle($rawTitle['title']);
        }
    }

    protected function setMetres(array &$poems): void
    {
        $rawMetres = $this->dbs->getMetres(array_keys($poems));
        $metres = $this->container->get('metre_manager')->getWithData($rawMetres);
        foreach ($rawMetres as $rawMetre) {
            $poems[$rawMetre['poem_id']]
                ->addMetre($metres[$rawMetre['metre_id']]);
        }
    }

    protected function setSubjects(array &$poems): void
    {
        $rawSubjects = $this->dbs->getSubjects(array_keys($poems));
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
        $rawGenres = $this->dbs->getGenres(array_keys($poems));
        $genres = $this->container->get('genre_manager')->getWithData($rawGenres);
        foreach ($rawGenres as $rawGenre) {
            $poems[$rawGenre['poem_id']]
                ->addGenre($genres[$rawGenre['genre_id']]);
        }
    }

    protected function updateMetres(Poem $poem, array $metres): void
    {
        foreach ($metres as $metre) {
            if (!is_object($metre)
                || !property_exists($metre, 'id')
                || !is_numeric($metre->id)
            ) {
                throw new BadRequestHttpException('Incorrect metre data.');
            }
        }
        list($delIds, $addIds) = self::calcDiff($metres, $poem->getMetres());

        if (count($delIds) > 0) {
            $this->dbs->delMetres($poem->getId(), $delIds);
        }
        foreach ($addIds as $addId) {
            $this->dbs->addMetre($poem->getId(), $addId);
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

    public function updateElasticMetre(array $ids): void
    {
        if (!empty($ids)) {
            $rawMetres = $this->dbs->getMetres($ids);
            if (!empty($rawMetres)) {
                $metres = $this->container->get('metre_manager')->getWithData($rawMetres);
                $data = [];

                foreach ($rawMetres as $rawMetre) {
                    if (!isset($data[$rawMetre['poem_id']])) {
                        $data[$rawMetre['poem_id']] = [
                            'id' => $rawMetre['poem_id'],
                            'metre' => [],
                        ];
                    }
                    $data[$rawMetre['poem_id']]['metre'][] =
                        $metres[$rawMetre['metre_id']]->getShortJson();
                }

                $this->ess->updateMultiple($data);
            }
        }
    }

    public function updateElasticAcknowledgement(array $ids): void
    {
        if (!empty($ids)) {
            $rawAcknowledgements = $this->dbs->getAcknowledgements($ids);
            if (!empty($rawAcknowledgements)) {
                $acknowledgements = $this->container->get('acknowledgement_manager')->getWithData($rawAcknowledgements);
                var_dump($acknowledgements);
                var_dump($rawAcknowledgements);
                $data = [];

                foreach ($rawAcknowledgements as $rawAcknowledgement) {
                    if (!isset($data[$rawAcknowledgement['poem_id']])) {
                        $data[$rawAcknowledgement['poem_id']] = [
                            'id' => $rawAcknowledgement['poem_id'],
                            'acknowledgement' => [],
                        ];
                    }
                    $data[$rawAcknowledgement['poem_id']]['acknowledgement'][] =
                        $acknowledgements[$rawAcknowledgement['acknowledgement_id']]->getShortJson();
                }

                $this->ess->updateMultiple($data);
            }
        }
    }
}
