<?php

namespace AppBundle\Model;

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
            . $this->formatStartEndPages(': ', $this->rawPages)
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

        return $result;
    }
}
