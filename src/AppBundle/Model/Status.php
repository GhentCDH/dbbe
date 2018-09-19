<?php

namespace AppBundle\Model;

/**
 */
class Status extends IdNameObject
{
    /**
     * @var string
     */
    const CACHENAME = 'status';
    /**
     * @var string
     */
    const OCCURRENCE_DIVIDED = 'occurrence_divided';
    /**
     * @var string
     */
    const OCCURRENCE_RECORD = 'occurrence_record';
    /**
     * @var string
     */
    const OCCURRENCE_SOURCE = 'occurrence_source';
    /**
     * @var string
     */
    const OCCURRENCE_TEXT = 'occurrence_text';
    /**
     * @var string
     */
    const MANUSCRIPT = 'manuscript';
    /**
     * @var string
     */
    const TYPE_TEXT = 'type_text';

    /**
     * @var string
     */
    private $type;

    public function __construct(int $id, string $name, string $type = null)
    {
        parent::__construct($id, $name);

        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getJson(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
        ];
    }
}
