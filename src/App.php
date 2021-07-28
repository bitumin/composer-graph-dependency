<?php

namespace Kassko\Composer\GraphDependency;

use Kassko\Composer\GraphDependency\Command;
use Symfony\Component\Console\Application as BaseApp;

class App extends BaseApp
{
    public function __construct()
    {
        parent::__construct('composer-dependency', '@git_tag@');

        $this->add(new Command\Export());
    }
}
