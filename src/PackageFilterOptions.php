<?php

namespace Kassko\Composer\GraphDependency;

class PackageFilterOptions
{
    private $root = true;

    public function makeRoot($root = true): PackageFilterOptions
    {
        $this->root = $root;

        return $this;
    }

    public function isRoot(): bool
    {
        return $this->root;
    }
}
