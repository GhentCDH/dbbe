<?php

namespace AppBundle\Model;

class PhdBibliography extends Bibliography
{
    use RawPagesTrait;
    use StartEndPagesTrait;

    protected $phd;

    public function __construct(int $id)
    {
        parent::__construct($id, 'phd');
    }

    public function setPhd(Phd $phd): PhdBibliography
    {
        $this->phd = $phd;

        return $this;
    }

    public function getPhd(): Phd
    {
        return $this->phd;
    }

    public function getDescription(): string
    {
        return
            $this->phd->getDescription()
            . $this->formatPages(': ')
            . '.';
    }

    public function getShortJson(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'phd' => $this->phd->getShortJson(),
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
