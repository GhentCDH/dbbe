<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

class Article extends Document
{
    const CACHENAME = 'article';

    use CacheLinkTrait;
    use StartEndPagesTrait;
    use RawPagesTrait;

    protected $title;
    protected $journal;

    public function __construct(
        int $id,
        string $title,
        Journal $journal
    ) {
        parent::__construct();

        $this->id = $id;
        $this->title = $title;
        $this->journal = $journal;

        // All articles are public
        $this->public = true;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getJournal(): Journal
    {
        return $this->journal;
    }

    public function getDescription(): string
    {
        $authorNames = [];
        if (isset($this->personRoles['author'])) {
            foreach ($this->personRoles['author'][1] as $author) {
                $authorNames[] = $author->getShortDescription();
            }
        }
        return
            implode(', ', $authorNames)
            . ' ' . $this->journal->getYear()
            . ', ' . $this->title
            . ', ' . $this->journal->getTitle()
            . (
                !empty($this->journal->getVolume())
                    ? ', ' . $this->journal->getVolume()
                    : ''
            )
            . (
                !empty($this->journal->getNumber())
                    ? '(' . $this->journal->getNumber() . ')'
                    : ''
            )
            . $this->formatStartEndPages(', ', $this->rawPages);
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getDescription(),
        ];
    }

    public function getJson(): array
    {
        $result = parent::getJson();

        if (!empty($this->title)) {
            $result['title'] = $this->title;
        }
        if (!empty($this->journal)) {
            $result['journal'] = $this->journal->getShortJson();
        }

        return $result;
    }

    public function getElastic(): array
    {
        $result = parent::getElastic();

        $result['type'] = [
            'id' => 0,
            'name' => 'Article',
        ];
        $result['title'] = $this->title;
        foreach ($this->getPersonRoles() as $roleName => $personRole) {
            $result[$roleName] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        foreach ($this->getPublicPersonRoles() as $roleName => $personRole) {
            $result[$roleName . '_public'] = ArrayToJson::arrayToShortJson($personRole[1]);
        }

        return $result;
    }

    public static function unlinkCache($data)
    {
        $article = new Article($data['id'], $data['title'], $data['journal']);

        foreach ($data as $key => $value) {
            $article->set($key, $value);
        }

        return $article;
    }
}
