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
        $authorNames = [];
        foreach ($this->article->getAuthors() as $author) {
            $authorNames[] = $author->getShortDescription();
        }
        return implode(', ', $authorNames)
            . ' ' . $this->article->getJournal()->getYear()
            . ', ' . $this->article->getTitle()
            . ', ' . $this->article->getJournal()->getTitle()
            . ', ' . $this->article->getJournal()->getVolume()
            . (
                !empty($this->article->getJournal()->getNumber())
                    ? '(' . $this->article->getJournal()->getNumber() . ')'
                    : ''
            )
            . self::formatPages($this->article->getStartPage(), $this->article->getEndPage(), ', ')
            . self::formatPages($this->startPage, $this->endPage, ': ')
            . '.';
    }
}
