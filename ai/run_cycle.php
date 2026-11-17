<?php
/**
 * Run a full AI company cycle from the command line (or cron).
 *
 *   php ai/run_cycle.php                       # full cycle
 *   php ai/run_cycle.php "Focus on Hari Raya"   # with an owner directive
 *   php ai/run_cycle.php --dept=marketing       # one department only
 *
 * Example daily cron (07:40):
 *   40 7 * * * cd /path/to/Version-1 && ANTHROPIC_API_KEY=... php ai/run_cycle.php >> ai/cycle.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
require_once __DIR__ . '/engine.php';

$args = array_slice($argv, 1);
$dept = null;
foreach ($args as $i => $a) {
    if (str_starts_with($a, '--dept=')) {
        $dept = substr($a, 7);
        unset($args[$i]);
    }
}
$directive = implode(' ', $args);

if ($dept) {
    $inputs = [];
    if ($dept !== 'analytics') {
        foreach (latest_reports($connect) as $key => $row) {
            if ($key !== $dept && $key !== 'ceo') {
                $inputs[$key] = $row['content'];
            }
        }
    }
    $r = run_department($connect, $dept, $inputs, $directive);
    echo $r['content'], "\n";
    exit(0);
}

$id = run_cycle($connect, $directive, fn($s) => print(date('H:i:s') . " $s\n"));
echo "Cycle #$id complete. Open ai.php to read the reports.\n";
