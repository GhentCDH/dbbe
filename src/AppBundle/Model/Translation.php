<?php

namespace AppBundle\Model;

/**
 */
class Translation extends Document
{
    /**
     * @var Language
     */
    private $language;
    /**
     * @var string
     */
    private $text;

    public function setLanguage(Language $language): Translation
    {
        $this->language = $language;

        return $this;
    }

    public function getLanguage(): Language
    {
        return $this->language;
    }

    public function setText(string $text): Translation
    {
        $this->text = $text;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getJson(): array
    {
        $result = parent::getJson();

        $result['language'] = $this->language->getShortJson();
        $result['text'] = $this->text;

        return $result;
    }
}
