<?php

namespace AppBundle\Model;

class ArticleBibliography extends Bibliography
{
    use StartEndPagesTrait;

    private $article;

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
            . $this->formatStartEndPages(': ')
            . '.';
    }

    public function getShortJson(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'article' => $this->article->getShortJson(),
            'startPage' => $this->startPage,
            'endPage' => $this->endPage,
        ];
    }
}
