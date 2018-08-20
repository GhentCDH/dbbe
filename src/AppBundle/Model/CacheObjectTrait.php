<?php

namespace AppBundle\Model;

use ReflectionClass;

trait CacheObjectTrait
{

    protected function set(string $key, $value)
    {
        $this->$key = $value;

        return $this;
    }

    public function get(): array
    {
        $data = [];
        foreach ($this as $key => $value) {
            $data[$key] = $value;
        }
        return $data;
    }

    public static function unlinkCache($data)
    {
        $object = (new ReflectionClass(static::class))->newInstance();

        foreach ($data as $key => $value) {
            $object->set($key, $value);
        }

        return $object;
    }
}
