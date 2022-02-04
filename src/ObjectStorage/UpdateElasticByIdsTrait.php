<?php

namespace App\ObjectStorage;

trait UpdateElasticByIdsTrait
{
    /**
     * (Re-)index elasticsearch
     * @param  array  $ids
     */
    public function updateElasticByIds(array $ids): void
    {
        $shorts = $this->getShort($ids);
        $delIds = array_diff($ids, array_keys($shorts));
        if (count($shorts) > 0) {
            $this->ess->addMultiple($shorts);
        }
        if (count($delIds) > 0) {
            $this->ess->deleteMultiple($delIds);
        }
    }
}