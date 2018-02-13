<?php

namespace AppBundle\Model;

class Occurrence
{
    private $id;
    private $foliumStart;
    private $foliumStartRecto;
    private $foliumEnd;
    private $foliumEndRecto;
    private $generalLocation;
    private $incipit;

    public function __construct()
    {
        return $this;
    }

    public function setId(int $id): Occurrence
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setFoliumStart(string $foliumStart = null): Occurrence
    {
        $this->foliumStart = $foliumStart;

        return $this;
    }

    public function setFoliumStartRecto(string $foliumStartRecto = null): Occurrence
    {
        $this->foliumStartRecto = $foliumStartRecto;

        return $this;
    }

    public function setFoliumEnd(string $foliumEnd = null): Occurrence
    {
        $this->foliumEnd = $foliumEnd;

        return $this;
    }

    public function setFoliumEndRecto(string $foliumEndRecto = null): Occurrence
    {
        $this->foliumEndRecto = $foliumEndRecto;

        return $this;
    }

    public function setGeneralLocation(string $generalLocation = null): Occurrence
    {
        $this->generalLocation = $generalLocation;

        return $this;
    }

    public function setIncipit(string $incipit = null): Occurrence
    {
        $this->incipit = $incipit;

        return $this;
    }

    public function getDescription(): string
    {
        $result = '';
        if (!empty($this->foliumStart)) {
            if (!empty($this->foliumEnd)) {
                $result .= '(f. ' . $this->foliumStart . self::formatRecto($this->foliumStartRecto)
                    . '-' . $this->foliumEnd . self::formatRecto($this->foliumEndRecto) . ') ';
            } else {
                $result .= '(f. ' . $this->foliumStart . self::formatRecto($this->foliumStartRecto) . ') ';
            }
        }

        if (!empty($this->generalLocation)) {
            $result .= '(' . $this->generalLocation . ') ';
        }

        if (!empty($this->incipit)) {
            $result .= $this->incipit;
        }
        return $result;
    }

    private static function formatRecto(bool $recto = null): string
    {
        if (empty($recto)) {
            return '';
        }

        if ($recto) {
            return 'r';
        } else {
            return 'v';
        }
    }
}
