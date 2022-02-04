<?php

namespace App\Model;

class ArticleBibliography extends Bibliography
{
    use RawPagesTrait;
    use StartEndPagesTrait;

    protected $article;

    public function __construct(int $id)
    {
        parent::__construct($id, 'article');
    }

    public function setArticle(Article $article): ArticleBibliography
    {
        $this->article = $article;

        return $this;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getDescription(): string
    {
        return
            $this->article->getDescription()
            . $this->formatPages(': ')
            . '.';
    }

    public function getShortJson(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'article' => $this->article->getShortJson(),
            'startPage' => $this->startPage,
            'endPage' => $this->endPage,
        ];

        if (isset($this->referenceType)) {
            $result['referenceType'] = $this->referenceType->getShortJson();
        }
        if (isset($this->image)) {
            $result['image'] = $this->image;
        }

        return $result;
    }
}
