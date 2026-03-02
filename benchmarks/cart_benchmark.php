<?php

declare(strict_types=1);

use Lalalili\ShoppingCart\Cart;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../tests/helpers/SessionMock.php';

final class BenchmarkDispatcher
{
    public function dispatch(string $eventName, array $payload = [], bool $halt = true): null
    {
        return null;
    }
}

/**
 * @return array{add_ms: float, update_ms: float, total_ms: float, peak_memory_mb: float}
 */
function runScenario(int $itemCount): array
{
    $cart = new Cart(
        new SessionMock(),
        new BenchmarkDispatcher(),
        'benchmark',
        'BENCHMARK_KEY_' . $itemCount,
        require __DIR__ . '/../tests/helpers/configMock.php'
    );

    $addStart = hrtime(true);

    for ($i = 1; $i <= $itemCount; $i++) {
        $cart->add($i, 'Item #' . $i, (float) (($i % 19) + 1), 1, []);
    }

    $addDurationMs = (hrtime(true) - $addStart) / 1_000_000;

    $updateStart = hrtime(true);

    for ($i = 1; $i <= (int) ($itemCount / 2); $i++) {
        $cart->update($i, ['quantity' => ['relative' => true, 'value' => 2]]);
    }

    $updateDurationMs = (hrtime(true) - $updateStart) / 1_000_000;

    $totalStart = hrtime(true);

    for ($i = 0; $i < 10; $i++) {
        $cart->getTotal(false);
    }

    $totalDurationMs = (hrtime(true) - $totalStart) / 1_000_000;

    return [
        'add_ms' => round($addDurationMs, 2),
        'update_ms' => round($updateDurationMs, 2),
        'total_ms' => round($totalDurationMs, 2),
        'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
    ];
}

$scenarios = [100, 1000, 10000];
$results = [];

echo "Cart benchmark\n";
echo str_repeat('=', 72) . "\n";
echo "items\tadd(ms)\tupdate(ms)\tgetTotal x10(ms)\tpeakMB\n";

foreach ($scenarios as $scenario) {
    gc_collect_cycles();

    $metrics = runScenario($scenario);
    $results[$scenario] = $metrics;

    echo sprintf(
        "%d\t%.2f\t%.2f\t%.2f\t\t%.2f\n",
        $scenario,
        $metrics['add_ms'],
        $metrics['update_ms'],
        $metrics['total_ms'],
        $metrics['peak_memory_mb']
    );
}

echo str_repeat('-', 72) . "\n";
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
