<?php

namespace AppBundle\Model;

/**
 * A single occurrence verse
 */
class Verse implements IdJsonInterface, IdElasticInterface
{
    /**
     * @var string
     */
    const CACHENAME = 'verse';

    /**
     * @var int
     */
    protected $id;
    /**
     * @var int
     */
    protected $groupId;
    /**
     * @var string
     */
    protected $verse;
    /**
     * @var int
     */
    protected $order;
    /**
     * @var Occurrence
     */
    protected $occurrence;
    /**
     * @var Manuscript
     */
    protected $manuscript;

    /**
     * @param int      $id
     * @param int|null $groupId
     * @param string   $verse
     * @param int      $order
     */
    public function __construct(int $id, int $groupId = null, string $verse, int $order)
    {
        $this->id = $id;
        $this->groupId = $groupId;
        $this->verse = $verse;
        $this->order = $order;

        return $this;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int|null
     */
    public function getGroupId(): ?int
    {
        return $this->groupId;
    }

    /**
     * @return string
     */
    public function getVerse(): string
    {
        return $this->verse;
    }

    /**
     * @return int
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * @param  Occurrence $occurrence
     * @return Verse
     */
    public function setOccurrence(Occurrence $occurrence): Verse
    {
        $this->occurrence = $occurrence;

        return $this;
    }

    /**
     * @return Occurrence
     */
    public function getOccurrence(): Occurrence
    {
        return $this->occurrence;
    }

    /**
     * @param  Manuscript $manuscript
     * @return Verse
     */
    public function setManuscript(Manuscript $manuscript): Verse
    {
        $this->manuscript = $manuscript;

        return $this;
    }

    /**
     * @return Manuscript
     */
    public function getManuscript(): Manuscript
    {
        return $this->manuscript;
    }

    /**
     * @return array
     */
    public function getJson(): array
    {
        $result = [
            'id' => $this->id,
            'groupId' => $this->groupId,
            'verse' => $this->verse,
            'order' => $this->order,
        ];
        if (isset($this->occurrence)) {
            $result['occurrence'] = $this->occurrence->getShortJson();
        }
        if (isset($this->manuscript)) {
            $result['manuscript'] = $this->manuscript->getShortJson();
        }
        return $result;
    }

    /**
     * @return array
     */
    public function getElastic(): array
    {
        $result = [
            'id' => $this->id,
            'group_id' => $this->groupId,
            'verse' => $this->verse,
            'order' => $this->order,
        ];
        if (isset($this->occurrence)) {
            $result['occurrence'] = $this->occurrence->getShortJson();
        }
        if (isset($this->manuscript)) {
            $result['manuscript'] = $this->manuscript->getShortJson();
        }
        return $result;
    }

    /**
     * Reconstruct a complete text from an array of verses
     * @param  array  $verses
     * @return string
     */
    public static function getText(array $verses): string
    {
        return implode(
            "\n",
            array_map(
                function ($verse) {
                    return $verse->getVerse();
                },
                $verses
            )
        );
    }
}
