<?php

namespace Frd\AdminBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class FrdAdminBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
