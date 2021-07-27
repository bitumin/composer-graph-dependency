<?php

namespace Kassko\Composer\GraphDependency;

class DependencyPackageFilterOptions
{
    private $dev = false;

    public function makeDev($dev = true): DependencyPackageFilterOptions
    {
        $this->dev = $dev;

        return $this;
    }

    public function isDev(): bool
    {
        return $this->dev;
    }
}
