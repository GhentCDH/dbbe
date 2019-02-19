<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

/**
 */
class Article extends Document
{
    /**
     * @var string
     */
    const CACHENAME = 'article';

    use StartEndPagesTrait;
    use RawPagesTrait;

    /**
     * @var string
     */
    protected $title;
    /**
     * @var Journal
     */
    protected $journal;

    /**
     * @param int     $id
     * @param string  $title
     * @param Journal $journal
     */
    public function __construct(
        int $id,
        string $title,
        Journal $journal
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->journal = $journal;

        // All articles are public
        $this->public = true;

        return $this;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return Journal
     */
    public function getJournal(): Journal
    {
        return $this->journal;
    }

    /**
     * @return string
     */
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

    /**
     * @return array
     */
    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getDescription(),
        ];
    }

    /**
     * @return array
     */
    public function getJson(): array
    {
        $result = parent::getJson();

        if (!empty($this->title)) {
            $result['title'] = $this->title;
        }
        if (!empty($this->journal)) {
            $result['journal'] = $this->journal->getShortJson();
        }
        if (!empty($this->getStartPage())) {
            $result['startPage'] = (int)$this->getStartPage();
        }
        if (!empty($this->getEndPage())) {
            $result['endPage'] = (int)$this->getEndPage();
        }
        if (!empty($this->getRawPages())) {
            $result['rawPages'] = $this->getRawPages();
        }

        return $result;
    }

    /**
     * @return array
     */
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
}
