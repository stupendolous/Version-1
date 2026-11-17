<?php
/**
 * Run the AI company from the command line or a scheduler.
 *
 *   php bin/run_cycle.php                          # full weekly cycle
 *   php bin/run_cycle.php "Focus on Raya promo"    # with an owner directive
 *   php bin/run_cycle.php --dept=marketing "Plan next week's TikToks"
 *
 * Daily at 07:40 (cron):
 *   40 7 * * * cd /path/to/Version-1 && php bin/run_cycle.php >> storage/cycle.log 2>&1
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
require_once __DIR__ . '/../ai/agent.php';

$dept = null;
$cycleId = null;
$words = [];
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--dept=')) $dept = substr($a, 7);
    elseif (str_starts_with($a, '--cycle=')) $cycleId = (int)substr($a, 8);
    else $words[] = $a;
}
$text = implode(' ', $words);
$log = fn(string $s) => print(date('H:i:s') . " $s\n");

if ($dept) {
    $r = run_agent($dept, $text ?: CYCLE_INSTRUCTION);
    foreach ($r['tools'] as $t) echo "  ↳ {$t['name']}\n";
    echo "\n{$r['text']}\n";
    exit(0);
}

if ($cycleId) {
    $text = (string)val('SELECT directive FROM ai_cycles WHERE id = ?', [$cycleId]);
}
$id = run_cycle($text, $cycleId, $log);
echo "Cycle #$id complete.\n";
