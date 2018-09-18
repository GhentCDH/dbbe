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

    use CacheLinkTrait;
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
        return
            implode(', ', $authorNames)
            . ' ' . $this->book->getYear()
            . ', ' . $this->getTitle()
            . ', in '
            . (
                !empty($this->book->getEditor())
                    ? $this->book->getEditor() . ' (ed.) '
                    : ''
            )
            . ', ' . $this->book->getTitle()
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

    /**
     * @param  array       $data
     * @return BookChapter
     */
    public static function unlinkCache(array $data)
    {
        $bookChapter = new BookChapter($data['id'], $data['title'], $data['book']);

        foreach ($data as $key => $value) {
            $bookChapter->set($key, $value);
        }

        return $bookChapter;
    }
}
