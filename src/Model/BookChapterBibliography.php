<?php

namespace App\Model;

class BookChapterBibliography extends Bibliography
{
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
            . $this->formatPages(': ')
            . '.';
    }

    public function getShortJson(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'bookChapter' => $this->bookChapter->getShortJson(),
            'startPage' => $this->startPage,
            'endPage' => $this->endPage,
        ];

        if (isset($this->referenceType)) {
            $result['referenceType'] = $this->referenceType->getShortJson();
        }
        if (isset($this->image)) {
            $result['image'] = $this->image;
        }

        return $result;
    }
}
