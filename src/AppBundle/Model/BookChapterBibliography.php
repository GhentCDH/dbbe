<?php

namespace AppBundle\Model;

class BookChapterBibliography extends Bibliography
{
    const CACHENAME = 'book_chapter_bibliography';

    use RawPagesTrait;
    use StartEndPagesTrait;

    protected $bookChapter;

    public function __construct(int $id)
    {
        parent::__construct($id, 'bookChapter');
    }

    public function setBookChapter(BookChapter $bookChapter): BookChapterBibliography
    {
        $this->bookChapter = $bookChapter;

        return $this;
    }

    public function getBookChapter(): BookChapter
    {
        return $this->bookChapter;
    }

    public function getDescription(): string
    {
        return
            $this->bookChapter->getDescription()
            . $this->formatStartEndPages(': ')
            . '.';
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'bookChapter' => $this->bookChapter->getShortJson(),
            'startPage' => $this->startPage,
            'endPage' => $this->endPage,
        ];
    }
}
