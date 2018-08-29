<?php

namespace AppBundle\Model;

/**
 */
class Office extends IdNameObject
{
    /**
     * @var string
     */
    const CACHENAME = 'office';

    use CacheLinkTrait;
    use CacheObjectTrait;

    /**
     * @var RegionWithParents
     */
    protected $regionWithParents;

    /**
     * Exactly one of name, region is required
     * @param int    $id
     * @param string|null $name
     * @param RegionWithParents|null $regionWithParents
     */
    public function __construct(
        int $id,
        string $name = null,
        RegionWithParents $regionWithParents = null
    ) {
        parent::__construct($id, $name);

        $this->regionWithParents = $regionWithParents;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getRegionWithParents(): ?RegionWithParents
    {
        return $this->regionWithParents;
    }

    public function getJson(): array
    {
        $result = parent::getJson();

        $result['regionWithParents'] = $this->regionWithParents;

        return $result;
    }

    public static function unlinkCache(array $data)
    {
        $office = new Office(
            $data['id'],
            isset($data['name']) ? $data['name'] : null,
            isset($data['regionWithParents']) ? $data['regionWithParents'] : null
        );

        foreach ($data as $key => $value) {
            $office->set($key, $value);
        }

        return $office;
    }
}
