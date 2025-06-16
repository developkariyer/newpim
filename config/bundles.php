<?php

use Pimcore\Bundle\DataHubBundle\PimcoreDataHubBundle;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\PerspectiveEditorBundle\PimcorePerspectiveEditorBundle;



return [
    Pimcore\Bundle\SimpleBackendSearchBundle\PimcoreSimpleBackendSearchBundle::class => ['all' => true],
    PimcoreDataHubBundle::class => ['all' => true],
    PimcoreDataImporterBundle::class => ['all' => true],
    PimcorePerspectiveEditorBundle::class => ['all' => true],
];
