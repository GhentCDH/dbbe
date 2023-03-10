<?php

namespace App\Model;

use URLify;

use App\Utils\ArrayToJson;

/**
 */
class BookChapter extends Document
{
    /**
     * @var string
     */
    const CACHENAME = 'book_chapter';

    use StartEndPagesTrait;
    use RawPagesTrait;
    use UrlsTrait;

    /**
     * @var Book
     */
    protected $book;

    /**
     * @param int    $id
     * @param string $title
     * @param Book   $book
     */
    public function __construct(
        int $id,
        string $title,
        Book $book
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->book = $book;

        // All book chapters are public
        $this->public = true;

        return $this;
    }

    /**
     * @return Book
     */
    public function getBook(): Book
    {
        return $this->book;
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
        $editornames = [];
        if (isset($this->book->getPersonRoles()['editor'])) {
            foreach ($this->book->getPersonRoles()['editor'][1] as $editor) {
                $editornames[] = $editor->getShortDescription();
            }
        }
        return
            implode(', ', $authorNames)
            . ' '
            . (
                $this->book->getForthcoming()
                    ? '(forthcoming)'
                    : $this->book->getYear()
            )
            . ', ' . $this->getTitle()
            . ', in '
            . (
                !empty($editornames)
                    ? implode(', ', $editornames) . (count($editornames) > 1 ? ' (eds.), ' :  ' (ed.), ')
                    : ''
            )
            . $this->book->getFullTitleAndVolume()
            . ', ' . $this->book->getCity()
            . $this->formatStartEndPages(', ');
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

        $year = $this->book->getYear();
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
        if (!empty($this->book)) {
            $result['book'] = $this->book->getShortJson();
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
            'id' => 2,
            'name' => 'Book chapter',
            'id_name' => 2 . '_' . 'Book chapter',
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
