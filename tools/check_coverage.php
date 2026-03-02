<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tools/check_coverage.php <clover.xml> <min-percentage>\n");
    exit(1);
}

[$script, $cloverPath, $minimumCoverage] = $argv;

if (!file_exists($cloverPath)) {
    fwrite(STDERR, "Coverage file not found: {$cloverPath}\n");
    exit(1);
}

$minimumCoverageFloat = (float) $minimumCoverage;

$targetFiles = [
    'Cart.php',
    'CartCondition.php',
    'ItemCollection.php',
];

$xml = simplexml_load_file($cloverPath);

if (!$xml) {
    fwrite(STDERR, "Unable to parse clover file: {$cloverPath}\n");
    exit(1);
}

$coverages = [];
$files = $xml->xpath('//file');

if ($files === false) {
    fwrite(STDERR, "Unable to inspect files from coverage report.\\n");
    exit(1);
}

foreach ($files as $file) {
    $attributes = $file->attributes();
    $filename = basename((string) $attributes['name']);

    if (!in_array($filename, $targetFiles, true)) {
        continue;
    }

    $metrics = $file->metrics;

    if (!$metrics) {
        continue;
    }

    $statements = (int) $metrics['statements'];
    $coveredStatements = (int) $metrics['coveredstatements'];

    if ($statements === 0) {
        $coverages[$filename] = 100.0;
        continue;
    }

    $coverages[$filename] = round(($coveredStatements / $statements) * 100, 2);
}

$missingTargets = array_values(array_diff($targetFiles, array_keys($coverages)));

if ($missingTargets !== []) {
    fwrite(STDERR, 'Missing coverage entries for: ' . implode(', ', $missingTargets) . "\n");
    exit(1);
}

$failed = false;

echo "Coverage report (threshold {$minimumCoverageFloat}%):\n";

foreach ($coverages as $target => $coverage) {
    $status = $coverage >= $minimumCoverageFloat ? 'PASS' : 'FAIL';
    echo " - {$target}: {$coverage}% ({$status})\n";

    if ($coverage < $minimumCoverageFloat) {
        $failed = true;
    }
}

$average = round(array_sum($coverages) / count($coverages), 2);
echo " - average: {$average}%\n";

if ($failed) {
    fwrite(STDERR, "Coverage threshold check failed.\n");
    exit(1);
}

exit(0);
