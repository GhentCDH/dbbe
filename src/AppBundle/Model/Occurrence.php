<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

class Occurrence extends Document
{
    use CacheDependenciesTrait;

    private $foliumStart;
    private $foliumStartRecto;
    private $foliumEnd;
    private $foliumEndRecto;
    private $generalLocation;
    private $type;
    private $manuscript;
    private $incipit;
    private $text;
    private $meter;
    private $genre;
    private $subjects;
    private $textStatus;
    private $recordStatus;
    private $paleographicalInfo;
    private $contextualInfo;
    private $verses;
    // Links to images on the server itself
    private $images;
    // Link to images hosted externally
    private $imageLinks;

    public function __construct()
    {
        parent::__construct();

        $this->subjects = [];
        $this->images = [];
        $this->imageLinks = [];

        return $this;
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

    public function getLocation(): ?string
    {
        $result = '';
        if (!empty($this->foliumStart)) {
            if (!empty($this->foliumEnd)) {
                $result .= 'f. ' . $this->foliumStart . self::formatRecto($this->foliumStartRecto)
                    . '-' . $this->foliumEnd . self::formatRecto($this->foliumEndRecto);
            } else {
                $result .= 'f. ' . $this->foliumStart . self::formatRecto($this->foliumStartRecto);
            }
        }

        if (!empty($this->generalLocation)) {
            $result .= $this->generalLocation;
        }

        return $result;
    }

    public function setType(Type $type = null): Occurrence
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setManuscript(Manuscript $manuscript = null): Occurrence
    {
        $this->manuscript = $manuscript;

        return $this;
    }

    public function getManuscript(): ?Manuscript
    {
        return $this->manuscript;
    }

    public function setIncipit(string $incipit): Occurrence
    {
        $this->incipit = $incipit;

        return $this;
    }

    public function getIncipit(): string
    {
        return $this->incipit;
    }

    public function setText(string $text = null): Occurrence
    {
        $this->text = $text;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setMeter(Meter $meter = null): Occurrence
    {
        $this->meter = $meter;

        return $this;
    }

    public function getMeter(): ?Meter
    {
        return $this->meter;
    }

    public function setGenre(Genre $genre): Occurrence
    {
        $this->genre = $genre;

        return $this;
    }

    public function getGenre(): Genre
    {
        return $this->genre;
    }

    public function addSubject(SubjectInterface $subject): Occurrence
    {
        $this->subjects[$subject->getId()] = $subject;

        return $this;
    }

    public function getSubjects(): array
    {
        return $this->subjects;
    }

    public function getTextSource(): ?Bibliography
    {
        $textSources = array_filter($this->bibliographies, function ($bibliography) {
            return $bibliography->getRefType() == 'Text source';
        });
        if (count($textSources) == 1) {
            return reset($textSources);
        }
        return null;
    }

    public function setTextStatus(Status $textStatus = null): Occurrence
    {
        $this->textStatus = $textStatus;

        return $this;
    }

    public function getTextStatus(): ?Status
    {
        return $this->textStatus;
    }

    public function setRecordStatus(Status $recordStatus = null): Occurrence
    {
        $this->recordStatus = $recordStatus;

        return $this;
    }

    public function getRecordStatus(): ?Status
    {
        return $this->recordStatus;
    }

    public function setPaleographicalInfo(string $paleographicalInfo = null): Occurrence
    {
        $this->paleographicalInfo = $paleographicalInfo;

        return $this;
    }

    public function getPaleographicalInfo(): ?string
    {
        return $this->paleographicalInfo;
    }

    public function setContextualInfo(string $contextualInfo = null): Occurrence
    {
        $this->contextualInfo = $contextualInfo;

        return $this;
    }

    public function getContextualInfo(): ?string
    {
        return $this->contextualInfo;
    }

    public function setVerses(int $verses = null): Occurrence
    {
        $this->verses = $verses;

        return $this;
    }

    public function getVerses(): ?int
    {
        return $this->verses;
    }

    public function addImage(Image $image): Occurrence
    {
        $this->images[$image->getId()] = $image;

        return $this;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function addImageLink(Image $image): Occurrence
    {
        $this->imageLinks[$image->getId()] = $image;

        return $this;
    }

    public function getImageLinks(): array
    {
        return $this->imageLinks;
    }

    public function getDescription(): string
    {
        $result = '';
        if (!empty($this->getLocation())) {
            $result .= '(' . $this->getLocation() . ')';
        }

        if (!empty($this->incipit)) {
            $result .= ' ' . $this->incipit;
        }
        return $result;
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getDescription(),
        ];
    }

    public function getElastic(): array
    {
        $result = [
            'id' => $this->id,
            'public' => $this->getPublic(),
        ];

        if (isset($this->incipit)) {
            $result['incipit'] = $this->incipit;
        }
        if (isset($this->text)) {
            $result['text'] = $this->text;
        }
        if (isset($this->meter)) {
            $result['meter'] = $this->meter->getShortJson();
        }
        if (!empty($this->subjects)) {
            $result['subject'] = ArrayToJson::arrayToShortJson($this->subjects);
        }
        if (!empty($this->manuscript)) {
            $result['manuscript'] = $this->manuscript->getShortJson();
            if (!empty($this->manuscript->getContentsWithParents())) {
                $contents = [];
                foreach ($this->manuscript->getContentsWithParents() as $contentWithParents) {
                    $contents = array_merge($contents, $contentWithParents->getElastic());
                }
                $result['manuscript_content'] = $contents;
            }
        }
        if (!empty($this->patrons)) {
            $result['patron'] = [];
            foreach ($this->patrons as $patron) {
                $result['patron'][] = $patron->getShortJson();
            }
        }
        if (!empty($this->scribes)) {
            $result['scribe'] = [];
            foreach ($this->scribes as $scribe) {
                $result['scribe'][] = $scribe->getShortJson();
            }
        }
        if (isset($this->date) && !empty($this->date->getFloor())) {
            $result['date_floor_year'] = intval($this->date->getFloor()->format('Y'));
        }
        if (isset($this->date) && !empty($this->date->getCeiling())) {
            $result['date_ceiling_year'] = intval($this->date->getCeiling()->format('Y'));
        }
        if (isset($this->genre)) {
            $result['genre'] = $this->genre->getShortJson();
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
