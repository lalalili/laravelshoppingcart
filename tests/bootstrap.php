<?php

declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        break;
    }
}

require_once __DIR__ . '/helpers/MockProduct.php';
require_once __DIR__ . '/helpers/CustomItemCollection.php';
require_once __DIR__ . '/helpers/StaticAssociatedModelResolver.php';
require_once __DIR__ . '/helpers/AddDiscountPipeline.php';
require_once __DIR__ . '/helpers/WarningPipeline.php';
