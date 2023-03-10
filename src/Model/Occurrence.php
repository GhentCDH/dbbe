<?php

namespace App\Model;

use App\Utils\ArrayToJson;

/**
 */
class Occurrence extends Poem
{
    /**
     * @var string
     */
    const CACHENAME = 'occurrence';

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
    protected $pageStart;
    /**
     * @var string
     */
    protected $pageEnd;
    /**
     * @var string
     */
    protected $generalLocation;
    /**
     * @var string
     */
    protected $oldLocation;
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
     * @var string
     */
    protected $alternativePageStart;
    /**
     * @var string
     */
    protected $alternativePageEnd;
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
     * @var Status
     */
    protected $recordStatus;
    /**
     * @var Status
     */
    protected $dividedStatus;
    /**
     * @var Status
     */
    protected $sourceStatus;
    /**
     * @var string
     */
    protected $palaeographicalInfo;
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
     * @param  string|null $pageStart
     * @return Occurrence
     */
    public function setPageStart(string $pageStart = null): Occurrence
    {
        $this->pageStart = $pageStart;

        return $this;
    }

    /**
     * @param  string|null $pageEnd
     * @return Occurrence
     */
    public function setPageEnd(string $pageEnd = null): Occurrence
    {
        $this->pageEnd = $pageEnd;

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
     * @param  string|null $oldLocation
     * @return Occurrence
     */
    public function setOldLocation(string $oldLocation = null): Occurrence
    {
        $this->oldLocation = $oldLocation;

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
     * @param  string|null $alternativePageStart
     * @return Occurrence
     */
    public function setAlternativePageStart(string $alternativePageStart = null): Occurrence
    {
        $this->alternativePageStart = $alternativePageStart;

        return $this;
    }

    /**
     * @param  string|null $alternativePageEnd
     * @return Occurrence
     */
    public function setAlternativePageEnd(string $alternativePageEnd = null): Occurrence
    {
        $this->alternativePageEnd = $alternativePageEnd;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLocation(): ?string
    {
        $resultArray = [];
        if (!empty($this->foliumStart)) {
            if (!empty($this->foliumEnd)
                && (
                    $this->foliumStart !== $this->foliumEnd
                    || $this->foliumStartRecto !== $this->foliumEndRecto
                )
            ) {
                $resultArray[] = 'f. ' . $this->foliumStart . self::formatRecto($this->foliumStartRecto)
                    . '-' . $this->foliumEnd . self::formatRecto($this->foliumEndRecto);
            } else {
                $resultArray[] = 'f. ' . $this->foliumStart . self::formatRecto($this->foliumStartRecto);
            }
        }
        if (!empty($this->pageStart)) {
            if (!empty($this->pageEnd) && $this->pageStart !== $this->pageEnd) {
                $resultArray[] = 'p. ' . $this->pageStart
                    . '-' . $this->pageEnd;
            } else {
                $resultArray[] = 'p. ' . $this->pageStart;
            }
        }

        if (!empty($this->generalLocation)) {
            $resultArray[] = '(gen.) ' . $this->generalLocation;
        }

        if (!empty($this->alternativeFoliumStart)) {
            if (!empty($this->alternativeFoliumEnd)
                && (
                    $this->alternativeFoliumStart !== $this->alternativeFoliumEnd
                    || $this->alternativeFoliumStartRecto !== $this->alternativeFoliumEndRecto
                )
            ) {
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
        if (!empty($this->alternativePageStart)) {
            if (!empty($this->alternativePageEnd) && $this->alternativePageStart !== $this->alternativePageEnd) {
                $resultArray[] = '(alt.) p. ' . $this->alternativePageStart
                    . '-' . $this->alternativePageEnd;
            } else {
                $resultArray[] = '(alt.) p. ' . $this->alternativePageStart;
            }
        }

        if (empty($resultArray)) {
            return null;
        }

        if (isset($this->unsure) && $this->unsure) {
            return '(unsure) ' . implode(' -- ', $resultArray);
        } else {
            return implode(' -- ', $resultArray);
        }
    }

    public function addVerse(Verse $verse): Occurrence
    {
        $this->verses[$verse->getId()] = $verse;

        return $this;
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

    public function sortRelatedOccurrences(): void
    {
        usort(
            $this->relatedOccurrences,
            function ($a, $b) {
                return $a[0]->getSortKey() <=> $b[0]->getSortKey();
            }
        );
    }

    public function addType($type): Occurrence
    {
        $this->types[] = $type;

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

    public function setSourceStatus(Status $sourceStatus = null): Occurrence
    {
        $this->sourceStatus = $sourceStatus;

        return $this;
    }

    public function getSourceStatus(): ?Status
    {
        return $this->sourceStatus;
    }

    public function setPalaeographicalInfo(string $palaeographicalInfo = null): Occurrence
    {
        $this->palaeographicalInfo = $palaeographicalInfo;

        return $this;
    }

    public function getPalaeographicalInfo(): ?string
    {
        return $this->palaeographicalInfo;
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

    public function getSortKey(): string
    {
        $sortKey = '';

        // manuscript
        $sortKey .= $this->manuscript->getSortKey();

        // folium
        if ($this->foliumStart != null) {
            $sortKey .= str_pad($this->foliumStart, 10, '0', STR_PAD_LEFT);
        } else {
            $sortKey .= '9999999999';
        }
        if ($this->foliumStartRecto != null) {
            $sortKey .= $this->foliumStartRecto ? '0' : '1';
        } else {
            $sortKey .= '9';
        }
        if ($this->foliumEnd != null) {
            $sortKey .= str_pad($this->foliumEnd, 10, '0', STR_PAD_LEFT);
        } else {
            $sortKey .= '9999999999';
        }
        if ($this->foliumEndRecto != null) {
            $sortKey .= $this->foliumEndRecto ? '0' : '1';
        } else {
            $sortKey .= '9';
        }
        return $sortKey;
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

        if (!empty($this->manuscript)) {
            $result['manuscript'] = $this->manuscript->getShortJson();
        }
        if (!empty($this->foliumStart)) {
            $result['foliumStart'] = $this->foliumStart;
        }
        if (isset($this->foliumStartRecto)) {
            $result['foliumStartRecto'] = $this->foliumStartRecto;
        }
        if (!empty($this->foliumEnd)) {
            $result['foliumEnd'] = $this->foliumEnd;
        }
        if (isset($this->foliumEndRecto)) {
            $result['foliumEndRecto'] = $this->foliumEndRecto;
        }
        if (isset($this->unsure)) {
            $result['unsure'] = $this->unsure;
        }
        if (!empty($this->pageStart)) {
            $result['pageStart'] = $this->pageStart;
        }
        if (!empty($this->pageEnd)) {
            $result['pageEnd'] = $this->pageEnd;
        }
        if (!empty($this->generalLocation)) {
            $result['generalLocation'] = $this->generalLocation;
        }
        if (!empty($this->oldLocation)) {
            $result['oldLocation'] = $this->oldLocation;
        }
        if (!empty($this->alternativeFoliumStart)) {
            $result['alternativeFoliumStart'] = $this->alternativeFoliumStart;
        }
        if (isset($this->alternativeFoliumStartRecto)) {
            $result['alternativeFoliumStartRecto'] = $this->alternativeFoliumStartRecto;
        }
        if (!empty($this->alternativeFoliumEnd)) {
            $result['alternativeFoliumEnd'] = $this->alternativeFoliumEnd;
        }
        if (isset($this->alternativeFoliumEndRecto)) {
            $result['alternativeFoliumEndRecto'] = $this->alternativeFoliumEndRecto;
        }
        if (!empty($this->alternativePageStart)) {
            $result['alternativePageStart'] = $this->alternativePageStart;
        }
        if (!empty($this->alternativePageEnd)) {
            $result['alternativePageEnd'] = $this->alternativePageEnd;
        }
        if (!empty($this->verses)) {
            $result['verses'] = ArrayToJson::arrayToJson($this->verses);
        }
        if (!empty($this->types)) {
            $result['types'] = ArrayToJson::arrayToShortJson($this->types);
        }
        $result['images'] = [
            'images' => ArrayToJson::arrayToJson($this->getImages()),
            'imageLinks' => ArrayToJson::arrayToJson($this->getImageLinks()),
        ];
        $result['dates'] = [];
        if (!empty($this->date) && !($this->date->isEmpty())) {
            $result['dates'][] = [
                'type' => 'completed at',
                'isInterval' => false,
                'date' => $this->date->getJson(),
            ];
        }
        if (!empty($this->palaeographicalInfo)) {
            $result['palaeographicalInfo'] = $this->palaeographicalInfo;
        }
        if (!empty($this->contextualInfo)) {
            $result['contextualInfo'] = $this->contextualInfo;
        }
        if (!empty($this->textStatus)) {
            $result['textStatus'] = $this->textStatus->getShortJson();
        }
        if (!empty($this->recordStatus)) {
            $result['recordStatus'] = $this->recordStatus->getShortJson();
        }
        if (!empty($this->dividedStatus)) {
            $result['dividedStatus'] = $this->dividedStatus->getShortJson();
        }
        if (!empty($this->sourceStatus)) {
            $result['sourceStatus'] = $this->sourceStatus->getShortJson();
        }

        return $result;
    }

    public function getElastic(): array
    {
        $result = parent::getElastic();

        if (!empty($this->verses)) {
            $result['text_stemmer'] = Verse::getText($this->verses);
            $result['text_original'] = Verse::getText($this->verses);
        }
        if (!empty($this->textStatus)) {
            $result['text_status'] = $this->textStatus->getShortJson();
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
        if (!empty($this->getLocation())) {
            $result['location'] = $this->getLocation();
        }
        if (!empty($this->date) && !empty($this->date->getFloor())) {
            $result['date_floor_year'] = intval($this->date->getFloor()->format('Y'));
        }
        if (!empty($this->date) && !empty($this->date->getCeiling())) {
            $result['date_ceiling_year'] = intval($this->date->getCeiling()->format('Y'));
        }
        if (!empty($this->getPalaeographicalInfo())) {
            $result['palaeographical_info'] = $this->getPalaeographicalInfo();
        }
        if (!empty($this->getContextualInfo())) {
            $result['contextual_info'] = $this->getContextualInfo();
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
