<?php

namespace Kassko\Composer\GraphDependency;

class PackageFilter
{
    private $filterConfig;

    public function __construct(array $filterConfig)
    {
        $this->filterConfig = $filterConfig;
    }

    public function filter($packageFullName, PackageFilterOptions $packageFilterOptions, array $packageData): bool
    {
        if ($packageFilterOptions->isRoot()) {
            return false;
        }

        if (
            !empty($this->filterConfig['include_tags'])
            && count($this->filterConfig['include_tags'])
        ) {
            if (!isset($packageData['extra'])) {
                return true;
            }

            $nb = count($this->filterConfig['include_tags']);
            for ($i = 0; $i < $nb; $i += 2) {
                if (
                $this->valueExistsInPath(
                    $this->filterConfig['include_tags'][$i],
                    $this->filterConfig['include_tags'][$i + 1],
                    $packageData['extra']
                )
                ) {
                    return false;
                }
            }
        }

        if (
            !empty($this->filterConfig['exclude_tags'])
            && count($this->filterConfig['exclude_tags'])
            && isset($packageData['extra'])
        ) {
            $nb = count($this->filterConfig['exclude_tags']);
            for ($i = 0; $i < $nb; $i += 2) {
                if (
                $this->valueExistsInPath(
                    $this->filterConfig['exclude_tags'][$i],
                    $this->filterConfig['exclude_tags'][$i + 1],
                    $packageData['include_tags']['extra']
                )
                ) {
                    return true;
                }
            }
        }

        if ('includes_all' === $this->filterConfig['default_filtering_mode']) {
            if (
                !empty($this->filterConfig['exclude_packages'])
                && in_array($packageFullName, $this->filterConfig['exclude_packages'], true)
            ) {
                return true;
            }

            list($vendorName,) = explode('/', $packageFullName, 2);

            if (
                !empty($this->filterConfig['exclude_vendors'])
                && in_array($vendorName, $this->filterConfig['exclude_vendors'], true)
            ) {
                return true;
            }

            return false;
        }

        if (
            !empty($this->filterConfig['include_packages'])
            && in_array($packageFullName, $this->filterConfig['include_packages'], true)
        ) {
            return false;
        }

        list($vendorName,) = explode('/', $packageFullName, 2);
        if (
            !empty($this->filterConfig['include_vendors'])
            && in_array($vendorName, $this->filterConfig['include_vendors'], true)
        ) {
            return false;
        }

        return true;
    }

    public function filterDependency($packageFullName, DependencyPackageFilterOptions $packageFilterOptions, array $parentPackageData): bool
    {
        if (
            !empty($this->filterConfig['no_root_dev_dep'])
            && $this->filterConfig['no_root_dev_dep']
            && $packageFilterOptions->isDev()
        ) {
            return true;
        }

        list($vendorName,) = Utils::extractPackageNameParts($packageFullName);

        if (
            !empty($this->filterConfig['exclude_dep_packages'])
            && count($this->filterConfig['exclude_dep_packages'])
            && in_array($packageFullName, $this->filterConfig['exclude_dep_packages'], true)) {
            return true;
        }

        if (
            !empty($this->filterConfig['include_dep_packages'])
            && count($this->filterConfig['include_dep_packages'])
            && !in_array($packageFullName, $this->filterConfig['include_dep_packages'], true)
        ) {
            return true;
        }

        if (
            !empty($this->filterConfig['exclude_dep_vendors'])
            && count($this->filterConfig['exclude_dep_vendors'])
            && in_array($vendorName, $this->filterConfig['exclude_dep_vendors'], true)
        ) {
            return true;
        }

        if (
            !empty($this->filterConfig['include_dep_vendors'])
            && count($this->filterConfig['include_dep_vendors'])
            && !in_array($vendorName, $this->filterConfig['include_dep_vendors'], true)
        ) {
            return true;
        }

        return false;
    }

    protected function valueExistsInPath($path, $value, array $config): bool
    {
        $pathParts = explode('.', $path);

        $nbPartsFound = 0;
        foreach ($pathParts as $pathPart) {
            if (isset($config[$pathPart])) {
                $config = $config[$pathPart];
                $nbPartsFound++;
            } else {
                break;
            }
        }

        return count($pathParts) === $nbPartsFound && $config === $value;
    }
}
