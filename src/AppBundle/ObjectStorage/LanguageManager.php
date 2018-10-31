<?php

namespace AppBundle\ObjectStorage;

use AppBundle\Model\Language;
use AppBundle\Utils\ArrayToJson;

/**
 * ObjectManager for articles
 * Servicename: article_manager
 */
class LanguageManager extends ObjectManager
{
    /**
     * Get all languages
     * @return array
     */
    public function getAll(): array
    {
        $languages = [];
        $rawLanguages = $this->dbs->getAllLanguages();

        foreach ($rawLanguages as $rawLanguage) {
            $languages[$rawLanguage['language_id']] = new Language(
                $rawLanguage['language_id'],
                $rawLanguage['language_name']
            );
        }

        return $languages;
    }

    /**
     * Get all languages with minimal information
     * @return array
     */
    public function getAllShortJson(): array
    {
        return $this->wrapArrayCache(
            'languages',
            ['languages'],
            function () {
                $languages = $this->getAll();

                // Sort by name
                usort($languages, function ($a, $b) {
                    return strcmp($a->getName(), $b->getName());
                });

                return ArrayToJson::arrayToShortJson($languages);
            }
        );
    }
}
