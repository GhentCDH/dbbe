<?php

namespace AppBundle\Model;

class BookChapterBibliography extends Bibliography
{
    use StartEndPagesTrait;

    private $bookChapter;

    public function __construct(int $id)
    {
        parent::__construct($id, 'book_chapter');
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
        $authorNames = [];
        foreach ($this->bookChapter->getAuthors() as $author) {
            $authorNames[] = $author->getShortDescription();
        }
        return implode(', ', $authorNames)
            . ' ' . $this->bookChapter->getBook()->getYear()
            . ', ' . $this->bookChapter->getTitle()
            . ', in '
            . (
                !empty($this->bookChapter->getBook()->getEditor())
                    ? $this->bookChapter->getBook()->getEditor() . ' (ed.) '
                    : ''
            )
            . ', ' . $this->bookChapter->getBook()->getTitle()
            . ', ' . $this->bookChapter->getBook()->getCity()
            . $this->bookChapter->formatStartEndPages(', ')
            . $this->formatStartEndPages(': ')
            . '.';
    }

    public function getShortJson(): array
    {
        throw new \Exception('Not implemented');
    }
}
