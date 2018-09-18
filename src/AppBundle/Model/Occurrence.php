<?php

namespace AppBundle\Model;

use AppBundle\Utils\ArrayToJson;

/**
 */
class Occurrence extends Document
{
    /**
     * @var string
     */
    const CACHENAME = 'occurrence';

    use CacheLinkTrait;

    /**
     * @var string
     */
    protected $foliumStart;
    /**
     * @var bool
     */
    protected $foliumStartRecto;
    /**
     * @var string
     */
    protected $foliumEnd;
    /**
     * @var bool
     */
    protected $foliumEndRecto;
    /**
     * @var bool
     */
    protected $unsure;
    /**
     * @var string
     */
    protected $generalLocation;
    /**
     * @var string
     */
    protected $contextualInfo;
    /**
     * @var string
     */
    protected $alternativeFoliumStart;
    /**
     * @var bool
     */
    protected $alternativeFoliumStartRecto;
    /**
     * @var string
     */
    protected $alternativeFoliumEnd;
    /**
     * @var bool
     */
    protected $alternativeFoliumEndRecto;
    /**
     * @var Manuscript
     */
    protected $manuscript;
    /**
     * Array containing related occurrences and the number of common verses
     * Structure:
     *  [
     *      [occurrence, count],
     *      [occurrence, count],
     *      ...
     *  ]
     * @var array
     */
    protected $relatedOccurrences = [];
    /**
     * @var array
     */
    protected $types = [];
    /**
     * @var string
     */
    protected $incipit;
    /**
     * @var string
     */
    protected $title;
    /**
     * @var array
     */
    protected $verses = [];
    /**
     * @var array
     */
    protected $meters = [];
    /**
     * @var array
     */
    protected $genres = [];
    /**
     * @var array
     */
    protected $subjects = [];
    /**
     * @var Status
     */
    protected $textStatus;
    /**
     * @var Status
     */
    protected $recordStatus;
    /**
     * @var Status
     */
    protected $dividedStatus;
    /**
     * @var string
     */
    protected $paleographicalInfo;
    /**
     * @var string
     */
    protected $acknowledgement;
    /**
     * @var int
     */
    protected $numberOfVerses;
    /**
     * Links to images on the server itself
     * @var array
     */
    protected $images = [];
    /**
     * Link to images hosted externally
     * @var array
     */
    protected $imageLinks = [];
    /**
     * Free text in image field
     * @var array
     */
    protected $imageTexts = [];

    /**
     * @param  string|null $foliumStart
     * @return Occurrence
     */
    public function setFoliumStart(string $foliumStart = null): Occurrence
    {
        $this->foliumStart = $foliumStart;

        return $this;
    }

    /**
     * @param  bool|null $foliumStartRecto
     * @return Occurrence
     */
    public function setFoliumStartRecto(bool $foliumStartRecto = null): Occurrence
    {
        $this->foliumStartRecto = $foliumStartRecto;

        return $this;
    }

    /**
     * @param  string|null $foliumEnd
     * @return Occurrence
     */
    public function setFoliumEnd(string $foliumEnd = null): Occurrence
    {
        $this->foliumEnd = $foliumEnd;

        return $this;
    }

    /**
     * @param  bool|null $foliumEndRecto
     * @return Occurrence
     */
    public function setFoliumEndRecto(bool $foliumEndRecto = null): Occurrence
    {
        $this->foliumEndRecto = $foliumEndRecto;

        return $this;
    }

    /**
     * @param  bool|null $unsure
     * @return Occurrence
     */
    public function setUnsure(bool $unsure = null): Occurrence
    {
        $this->unsure = $unsure;

        return $this;
    }

    /**
     * @param  string|null $generalLocation
     * @return Occurrence
     */
    public function setGeneralLocation(string $generalLocation = null): Occurrence
    {
        $this->generalLocation = $generalLocation;

        return $this;
    }

    /**
     * @param  string|null $alternativeFoliumStart
     * @return Occurrence
     */
    public function setAlternativeFoliumStart(string $alternativeFoliumStart = null): Occurrence
    {
        $this->alternativeFoliumStart = $alternativeFoliumStart;

        return $this;
    }

    /**
     * @param  bool|null $alternativeFoliumStartRecto
     * @return Occurrence
     */
    public function setAlternativeFoliumStartRecto(bool $alternativeFoliumStartRecto = null): Occurrence
    {
        $this->alternativeFoliumStartRecto = $alternativeFoliumStartRecto;

        return $this;
    }

    /**
     * @param  string|null $alternativeFoliumEnd
     * @return Occurrence
     */
    public function setAlternativeFoliumEnd(string $alternativeFoliumEnd = null): Occurrence
    {
        $this->alternativeFoliumEnd = $alternativeFoliumEnd;

        return $this;
    }

    /**
     * @param  bool|null $alternativeFoliumEndRecto
     * @return Occurrence
     */
    public function setAlternativeFoliumEndRecto(bool $alternativeFoliumEndRecto = null): Occurrence
    {
        $this->alternativeFoliumEndRecto = $alternativeFoliumEndRecto;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLocation(): ?string
    {
        $resultArray = [];
        if (!empty($this->foliumStart)) {
            if (!empty($this->foliumEnd)) {
                $resultArray[] = 'f. ' . $this->foliumStart . self::formatRecto($this->foliumStartRecto)
                    . '-' . $this->foliumEnd . self::formatRecto($this->foliumEndRecto);
            } else {
                $resultArray[] = 'f. ' . $this->foliumStart . self::formatRecto($this->foliumStartRecto);
            }
        }

        if (!empty($this->generalLocation)) {
            $resultArray[] = '(gen.) ' . $this->generalLocation;
        }

        if (!empty($this->alternativeFoliumStart)) {
            if (!empty($this->alternativeFoliumEnd)) {
                $resultArray[] = '(alt.) f. '
                    . $this->alternativeFoliumStart
                    . self::formatRecto($this->alternativeFoliumStartRecto)
                    . '-' . $this->alternativeFoliumEnd
                    . self::formatRecto($this->alternativeFoliumEndRecto);
            } else {
                $resultArray[] = '(alt.) f. '
                    . $this->alternativeFoliumStart
                    . self::formatRecto($this->alternativeFoliumStartRecto);
            }
        }

        if (isset($this->unsure) && $this->unsure) {
            return '(unsure) ' . implode(' -- ', $resultArray);
        } else {
            return implode(' -- ', $resultArray);
        }
    }

    /**
     * @param  string|null $contextualInfo
     * @return Occurrence
     */
    public function setContextualInfo(string $contextualInfo = null): Occurrence
    {
        $this->contextualInfo = $contextualInfo;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getContextualInfo(): ?string
    {
        return $this->contextualInfo;
    }

    public function setRelatedOccurrences(array $relatedOccurrences): Occurrence
    {
        $this->relatedOccurrences = $relatedOccurrences;

        return $this;
    }

    public function addRelatedOccurrence(Occurrence $relatedOccurrence, int $count): Occurrence
    {
        $this->relatedOccurrences[] = [$relatedOccurrence, $count];

        return $this;
    }

    public function getRelatedOccurrences(): array
    {
        return $this->relatedOccurrences;
    }

    public function getPublicRelatedOccurrences(): array
    {
        return array_filter(
            $this->relatedOccurrences,
            function ($relatedOccurrence) {
                return $relatedOccurrence[0]->getPublic();
            }
        );
    }

    public function setTypes(array $types): Occurrence
    {
        $this->types = $types;

        return $this;
    }

    public function getTypes(): array
    {
        return $this->types;
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

    public function addVerse(Verse $verse): Occurrence
    {
        $this->verses[$verse->getId()] = $verse;

        return $this;
    }

    public function getVerses(): array
    {
        return $this->verses;
    }

    public function addMeter(Meter $meter = null): Occurrence
    {
        $this->meters[$meter->getId()] = $meter;

        return $this;
    }

    public function getMeters(): array
    {
        return $this->meters;
    }

    public function addGenre(Genre $genre): Occurrence
    {
        $this->genres[$genre->getId()] = $genre;

        return $this;
    }

    public function getGenres(): array
    {
        return $this->genres;
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

    public function sortSubjects(): void
    {
        usort(
            $this->subjects,
            function ($a, $b) {
                if (is_a($a, Person::class)) {
                    if (!is_a($b, Person::class)) {
                        return -1;
                    } else {
                        return strcmp($a->getFullDescriptionWithOffices(), $b->getFullDescriptionWithOffices());
                    }
                } else {
                    if (is_a($b, Person::class)) {
                        return 1;
                    } else {
                        return strcmp($a->getName(), $b->getName());
                    }
                }
            }
        );
    }

    public function getPersonSubjects(): array
    {
        return array_filter(
            $this->subjects,
            function ($subject) {
                return is_a($subject, Person::class);
            }
        );
    }

    public function getKeywordSubjects(): array
    {
        return array_filter(
            $this->subjects,
            function ($subject) {
                return is_a($subject, Keyword::class);
            }
        );
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

    public function setDividedStatus(Status $dividedStatus = null): Occurrence
    {
        $this->dividedStatus = $dividedStatus;

        return $this;
    }

    public function getDividedStatus(): ?Status
    {
        return $this->dividedStatus;
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

    public function setAcknowledgement(string $acknowledgement = null): Occurrence
    {
        $this->acknowledgement = $acknowledgement;

        return $this;
    }

    public function getAcknowledgement(): ?string
    {
        return $this->acknowledgement;
    }

    public function setNumberOfVerses(int $numberOfVerses = null): Occurrence
    {
        $this->numberOfVerses = $numberOfVerses;

        return $this;
    }

    public function getNumberOfVerses(): int
    {
        return isset($this->numberOfVerses) ? $this->numberOfVerses : count($this->verses);
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

    public function addImageText(Image $image): Occurrence
    {
        $this->imageTexts[$image->getId()] = $image;

        return $this;
    }

    public function getImageTexts(): array
    {
        return $this->imageTexts;
    }

    public function getDBBE(): bool
    {
        $textSource = $this->getTextSource();
        if (isset($textSource) && $textSource->getType() == 'onlineSource' && $textSource->getOnlineSource()->getName() == 'DBBE') {
            return true;
        }
        return false;
    }

    public function getDescription(): string
    {
        return $this->incipit;
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->incipit,
            'location' => $this->getLocation(),
        ];
    }

    public function getJson(): array
    {
        $result = parent::getJson();

        if (isset($this->incipit)) {
            $result['incipit'] = $this->incipit;
        }
        if (isset($this->title)) {
            $result['title'] = $this->title;
        }
        if (isset($this->manuscript)) {
            $result['manuscript'] = $this->manuscript->getShortJson();
        }
        if (isset($this->foliumStart)) {
            $result['foliumStart'] = $this->foliumStart;
        }
        if (isset($this->foliumStartRecto)) {
            $result['foliumStartRecto'] = $this->foliumStartRecto;
        }
        if (isset($this->foliumEnd)) {
            $result['foliumEnd'] = $this->foliumEnd;
        }
        if (isset($this->foliumEndRecto)) {
            $result['foliumEndRecto'] = $this->foliumEndRecto;
        }
        if (isset($this->unsure)) {
            $result['unsure'] = $this->unsure;
        }
        if (isset($this->generalLocation)) {
            $result['generalLocation'] = $this->generalLocation;
        }
        if (isset($this->alternativeFoliumStart)) {
            $result['alternativeFoliumStart'] = $this->alternativeFoliumStart;
        }
        if (isset($this->alternativeFoliumStartRecto)) {
            $result['alternativeFoliumStartRecto'] = $this->alternativeFoliumStartRecto;
        }
        if (isset($this->alternativeFoliumEnd)) {
            $result['alternativeFoliumEnd'] = $this->alternativeFoliumEnd;
        }
        if (isset($this->alternativeFoliumEndRecto)) {
            $result['alternativeFoliumEndRecto'] = $this->alternativeFoliumEndRecto;
        }
        if (!empty($this->numberOfVerses)) {
            $result['numberOfVerses'] = $this->numberOfVerses;
        }
        if (!empty($this->verses)) {
            $result['verses'] = ArrayToJson::arrayToJson($this->verses);
        }
        if (!empty($this->types)) {
            $result['types'] = ArrayToJson::arrayToShortJson($this->types);
        }
        if (isset($this->meters)) {
            $result['meters'] = ArrayToJson::arrayToShortJson($this->meters);
        }
        if (!empty($this->subjects)) {
            $result['subjects'] = [
                'persons' => ArrayToJson::arrayToShortJson($this->getPersonSubjects()),
                'keywords' => ArrayToJson::arrayToShortJson($this->getKeywordSubjects()),
            ];
        }
        if (isset($this->date) && !($this->date->isEmpty())) {
            $result['date'] = $this->date->getJson();
        }
        if (isset($this->genres)) {
            $result['genres'] = ArrayToJson::arrayToShortJson($this->genres);
        }
        if (isset($this->paleographicalInfo)) {
            $result['paleographicalInfo'] = $this->paleographicalInfo;
        }
        if (isset($this->contextualInfo)) {
            $result['contextualInfo'] = $this->contextualInfo;
        }
        if (isset($this->acknowledgement)) {
            $result['acknowledgement'] = $this->acknowledgement;
        }
        if (isset($this->textStatus)) {
            $result['textStatus'] = $this->textStatus->getShortJson();
        }
        if (isset($this->recordStatus)) {
            $result['recordStatus'] = $this->recordStatus->getShortJson();
        }
        if (isset($this->dividedStatus)) {
            $result['dividedStatus'] = $this->dividedStatus->getShortJson();
        }

        return $result;
    }

    public function getElastic(): array
    {
        $result = [
            'id' => $this->id,
            'public' => $this->public,
            'dbbe' => $this->getDBBE(),
        ];

        if (isset($this->incipit)) {
            $result['incipit'] = $this->incipit;
        }
        if (isset($this->title)) {
            $result['title'] = $this->title;
        }
        if (!empty($this->verses)) {
            $result['text'] = Verse::getText($this->verses);
        }
        if (isset($this->textStatus)) {
            $result['text_status'] = $this->textStatus->getShortJson();
        }
        if (isset($this->meters)) {
            $result['meter'] = ArrayToJson::arrayToShortJson($this->meters);
        }
        if (!empty($this->subjects)) {
            $result['subject'] = ArrayToJson::arrayToShortJson($this->subjects);
        }
        if (!empty($this->manuscript)) {
            $result['manuscript'] = $this->manuscript->getShortJson();
            if (!empty($this->manuscript->getContentsWithParents())) {
                $result['manuscript_content'] = ArrayToJson::arrayToShortElastic($this->manuscript->getContentsWithParents());
                if ($this->manuscript->getPublic()) {
                    $result['manuscript_content_public'] = $result['manuscript_content'];
                }
            }
        }
        foreach ($this->getPersonRoles() as $roleName => $personRole) {
            $result[$roleName] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        foreach ($this->getPublicPersonRoles() as $roleName => $personRole) {
            $result[$roleName . '_public'] = ArrayToJson::arrayToShortJson($personRole[1]);
        }
        if (isset($this->date) && !empty($this->date->getFloor())) {
            $result['date_floor_year'] = intval($this->date->getFloor()->format('Y'));
        }
        if (isset($this->date) && !empty($this->date->getCeiling())) {
            $result['date_ceiling_year'] = intval($this->date->getCeiling()->format('Y'));
        }
        if (isset($this->genres)) {
            $result['genre'] =  ArrayToJson::arrayToShortJson($this->genres);
        }
        if (isset($this->publicComment)) {
            $result['public_comment'] = $this->publicComment;
        }
        if (isset($this->privateComment)) {
            $result['private_comment'] = $this->privateComment;
        }

        return $result;
    }

    private static function formatRecto(bool $recto = null): string
    {
        if (!empty($recto) && $recto) {
            return 'r';
        } else {
            return 'v';
        }
    }
}
