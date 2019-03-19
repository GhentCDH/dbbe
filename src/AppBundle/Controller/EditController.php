<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

abstract class EditController extends BaseController
{
    /**
     * @param Request $request
     */
    public function add(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR_VIEW');

        return $this->edit(null, $request);
    }

    abstract public function edit(int $id = null, Request $request);
}
