<?php

namespace App\Model;

use URLify;

use App\Utils\ArrayToJson;

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
    use UrlsTrait;

    /**
     * @var JournalIssue
     */
    protected $journalIssue;

    /**
     * @param int     $id
     * @param string  $title
     * @param JournalIssue $journalIssue
     */
    public function __construct(
        int $id,
        string $title,
        JournalIssue $journalIssue
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->journalIssue = $journalIssue;

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
     * @return JournalIssue
     */
    public function getJournalIssue(): JournalIssue
    {
        return $this->journalIssue;
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
            . ' '
            . (
                $this->journalIssue->getForthcoming()
                    ? '(forthcoming)'
                    : $this->journalIssue->getYear()
            )
            . ', ' . $this->title
            . ', ' . $this->journalIssue->getJournal()->getTitle()
            . (
            !empty($this->series)
                ? ' (Series ' . $this->series . ')'
                : ''
            )
            . (
                !empty($this->journalIssue->getVolume())
                    ? ', ' . $this->journalIssue->getVolume()
                    : ''
            )
            . (
                !empty($this->journalIssue->getNumber())
                    ? '(' . $this->journalIssue->getNumber() . ')'
                    : ''
            )
            . $this->formatStartEndPages(', ', $this->rawPages);
    }

    /**
     * Generate a sortKey; see Entity -> getBibliographiesForDisplay()
     *
     * @return string
     */
    public function getSortKey(): string
    {
        $sortKey = 'a';

        if (!empty($this->personRoles['author'])) {
            $lastName = reset($this->personRoles['author'][1])->getLastName();
            if (!empty($lastName)) {
                $sortKey .= URLify::filter($lastName);
            } else {
                $sortKey .= 'zzz';
            }
        } else {
            $sortKey .= 'zzz';
        }

        $year = $this->journalIssue->getYear();
        if (!empty($year)) {
            $sortKey .= $year;
        } else {
            $sortKey .= '9999';
        }

        return $sortKey;
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
        if (!empty($this->journalIssue)) {
            $result['journal'] = $this->journalIssue->getJournal()->getShortJson();
            $result['journalIssue'] = $this->journalIssue->getShortJson();
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
            'id_name' => 0 . '_' . 'Article',
        ];
        $result['title'] = $this->title;
        $personRoles = $this->getPersonRoles();
        foreach ($personRoles as $roleName => $personRole) {
            $result[$roleName] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        if (isset($personRoles['author']) && count($personRoles['author'][1]) > 0) {
            $result['author_last_name'] = reset($personRoles['author'][1])->getLastName();
        }
        $publicPersonRoles = $this->getPublicPersonRoles();
        foreach ($publicPersonRoles as $roleName => $personRole) {
            $result[$roleName . '_public'] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        if (isset($publicPersonRoles['author']) && count($publicPersonRoles['author'][1]) > 0) {
            $result['author_last_name_public'] = reset($publicPersonRoles['author'][1])->getLastName();
        }

        return $result;
    }
}
