<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

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

        // All books are public
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
            . ' ' . $this->book->getYear()
            . ', ' . $this->getTitle()
            . ', in '
            . (
                !empty($editornames)
                    ? implode(', ', $editornames) . (count($editornames) > 1 ? ' (eds.), ' :  ' (ed.), ')
                    : ''
            )
            . $this->book->getTitle()
            . ', ' . $this->book->getCity()
            . $this->formatStartEndPages(', ');
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
