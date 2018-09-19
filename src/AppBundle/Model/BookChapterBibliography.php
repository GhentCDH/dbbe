<?php

namespace AppBundle\Model;

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
            . $this->formatStartEndPages(': ')
            . '.'
            . (!empty($this->sourceRemark) ? ' (' . $this->sourceRemark . ')' : '')
            . (!empty($this->note) ? ' (' . $this->note . ')' : '');
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
        if (isset($this->sourceRemark)) {
            $result['sourceRemark'] = $this->sourceRemark;
        }
        if (isset($this->note)) {
            $result['note'] = $this->note;
        }

        return $result;
    }
}
