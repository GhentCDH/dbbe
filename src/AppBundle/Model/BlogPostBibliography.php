<?php

namespace AppBundle\Model;

class BlogPostBibliography extends Bibliography
{
    protected $blogPost;

    public function __construct(int $id)
    {
        parent::__construct($id, 'blogPost');
    }

    public function setBlogPost(BlogPost $blogPost): BlogPostBibliography
    {
        $this->blogPost = $blogPost;

        return $this;
    }

    public function getBlogPost(): BlogPost
    {
        return $this->blogPost;
    }

    public function getDescription(): string
    {
        return $this->blogPost->getDescription();
    }

    public function getShortJson(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'blogPost' => $this->blogPost->getShortJson(),
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
