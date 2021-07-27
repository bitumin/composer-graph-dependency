<?php

namespace Kassko\Composer\GraphDependency;

use Fhaculty\Graph\Edge\Undirected;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Attribute\AttributeAware;
use Fhaculty\Graph\Attribute\AttributeBagNamespaced;
use Graphp\GraphViz\GraphViz;
use Fhaculty\Graph\Set\Vertices;
use JMS\Composer\Graph\DependencyGraph;

class GraphComposer
{
    private $layoutVertex = [
        'fillcolor' => '#eeeeee',
        'style'     => 'filled, rounded',
        'shape'     => 'box',
        'fontcolor' => '#314B5F',
    ];

    private $layoutVertexRoot = [
        'style' => 'filled, rounded, bold',
    ];

    private $layoutEdge = [
        'fontcolor' => '#767676',
        'fontsize'  => 10,
        'color'     => '#1A2833',
    ];

    private $layoutEdgeDev = [
        'style' => 'dashed',
    ];

    private $dependencyGraph;

    /**
     * @var GraphViz
     */
    private $graphviz;

    private $edgesIdsProcessed;

    /**
     * @param GraphViz|null $graphviz
     */
    public function __construct(GraphViz $graphviz = null)
    {
        if ($graphviz === null) {
            $graphviz = new GraphViz();
            $graphviz->setFormat('svg');
        }
        $this->graphviz = $graphviz;
    }

    public function setDependencyGraph(DependencyGraph $dependencyGraph)
    {
        $this->dependencyGraph = $dependencyGraph;
    }

    /**
     * @param array $separateGraphPackagesNames
     * @param array $separateGraphVendorNames
     * @param array $separateGraphVendorPackagesNames
     *
     * @return Graph[]
     */
    public function createGraphes(
        array $separateGraphPackagesNames = [],
        array $separateGraphVendorNames = [],
        array $separateGraphVendorPackagesNames = []
    ): array {
        $completeGraph = new Graph();

        foreach ($this->dependencyGraph->getPackages() as $package) {
            $this->populateGraph($completeGraph, $package);
        }

        if (!count($separateGraphPackagesNames) && !count($separateGraphVendorNames) && !count($separateGraphVendorPackagesNames)) {
            return [$completeGraph];
        }

        $vertices              = $completeGraph->getVertices();
        $verticesToCopyByGraph = [];

        foreach ($this->dependencyGraph->getPackages() as $package) {
            $packageFullName = $package->getName();
            list($vendorName, $packageName) = Utils::extractPackageNameParts($packageFullName);

            if (in_array($vendorName, $separateGraphVendorPackagesNames, true)) {
                $verticesToCopyByGraph[$packageFullName] = Vertices::factory(
                    [$packageFullName => $vertices->getVertexId($packageFullName)]
                );
            }

            if (in_array($packageFullName, $separateGraphPackagesNames, true)) {
                $verticesToCopyByGraph[$packageFullName] = Vertices::factory(
                    [$packageFullName => $vertices->getVertexId($packageFullName)]
                );
            }

            if (in_array($vendorName, $separateGraphVendorNames, true)) {
                $verticesToCopyByGraph[$vendorName] += Vertices::factory(
                    [$packageFullName => $vertices->getVertexId($packageFullName)]
                );
            }
        }

        $graphes = [];
        foreach ($verticesToCopyByGraph as $key => $verticesToCopy) {
            $graph         = $this->partitionGraph($verticesToCopy);
            $graphes[$key] = $graph;
        }

        return $graphes;
    }

    public function partitionGraph($vertices): Graph
    {
        $graph                   = new Graph();
        $this->edgesIdsProcessed = [];

        foreach ($vertices->getMap() as $vertexId => $vertexToCopy) {
            $this->copyVertexTo($vertexId, $vertexToCopy, $graph);
        }

        return $graph;
    }

    private function copyVertexTo($vertexId, $vertexToCopy, Graph $graph)
    {
        $this->copyEdges($vertexToCopy->getEdges(), $graph, $vertexId, 0);
    }

    private function copyEdges($edgesToCopy, Graph $graph, $vertexId, $depth)
    {
        if ($depth >= 4) {//Make it configurable.
            return;
        }

        foreach ($edgesToCopy as $edgeToCopy) {

            $verticesToCopy = $edgeToCopy->getVertices()->getMap();
            $fromEdgeToCopy = current($verticesToCopy);
            $toEdgeToCopy   = end($verticesToCopy);

            if ($toEdgeToCopy->getId() === $vertexId) {
                continue;
            }

            if ($edgeToCopy instanceof Undirected) {
                continue;
            }

            $edgeId = $fromEdgeToCopy->getId() . '_' . $toEdgeToCopy->getId();
            if (isset($this->edgesIdsProcessed[$edgeId])) {
                continue;
            }
            $this->edgesIdsProcessed[$edgeId] = true;

            $fromEdge = $graph->createVertex($fromEdgeToCopy->getId(), true);
            $this->copyEdges($fromEdgeToCopy->getEdges(), $graph, $fromEdgeToCopy->getId(), ++$depth);

            $toEdge = $graph->createVertex($toEdgeToCopy->getId(), true);
            $this->copyEdges($toEdgeToCopy->getEdges(), $graph, $toEdgeToCopy->getId(), $depth);

            $fromEdge->createEdgeTo($toEdge);
        }
    }

    protected function populateGraph(Graph $graph, $package)
    {
        $name = $package->getName();

        $start = $graph->createVertex($name, true);

        $label = $name;
        if ($package->getVersion() !== null) {
            $label .= ': ' . $package->getVersion();
        }

        $this->setLayout($start, ['label' => $label] + $this->layoutVertex);

        foreach ($package->getOutEdges() as $requires) {
            $targetName = $requires->getDestPackage()->getName();
            $target     = $graph->createVertex($targetName, true);

            $label = $requires->getVersionConstraint();

            $edge = $start->createEdgeTo($target);
            $this->setLayout($edge, ['label' => $label] + $this->layoutEdge);

            if ($requires->isDevDependency()) {
                $this->setLayout($edge, $this->layoutEdgeDev);
            }
        }

        $this->setLayout($start, $this->layoutVertexRoot);
    }

    public function getImagePath(): string
    {
        $graphes = $this->createGraphes();

        return $this->graphviz->createImageFile(current($graphes));
    }

    public function getImagesPathes(
        array $separateGraphPackagesNames = [],
        array $separateGraphVendorNames = [],
        array $separateGraphVendorPackagesNames = []
    ): array {
        $graphes = $this->createGraphes($separateGraphPackagesNames, $separateGraphVendorNames, $separateGraphVendorPackagesNames);

        $imagesFiles = [];
        foreach ($graphes as $name => $graph) {
            $imagesFiles[$name] = $this->graphviz->createImageFile($graph);
        }

        return $imagesFiles;
    }

    public function setFormat($format): GraphComposer
    {
        $this->graphviz->setFormat($format);

        return $this;
    }

    protected function setLayout(AttributeAware $entity, array $layout): AttributeAware
    {
        $bag = new AttributeBagNamespaced($entity->getAttributeBag(), 'graphviz.');
        $bag->setAttributes($layout);

        return $entity;
    }
}
