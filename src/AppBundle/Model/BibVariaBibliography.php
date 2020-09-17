<?php

namespace AppBundle\Model;

class BibVariaBibliography extends Bibliography
{
    use RawPagesTrait;
    use StartEndPagesTrait;

    protected $bibVaria;

    public function __construct(int $id)
    {
        parent::__construct($id, 'bibVaria');
    }

    public function setBibVaria(BibVaria $bibVaria): BibVariaBibliography
    {
        $this->bibVaria = $bibVaria;

        return $this;
    }

    public function getBibVaria(): BibVaria
    {
        return $this->bibVaria;
    }

    public function getDescription(): string
    {
        return
            $this->bibVaria->getDescription()
            . $this->formatPages(': ')
            . '.';
    }

    public function getShortJson(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'bibVaria' => $this->bibVaria->getShortJson(),
            'startPage' => $this->startPage,
            'endPage' => $this->endPage,
            'rawPages' => $this->rawPages,
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
