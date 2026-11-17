<?php
/**
 * Set up the database.
 *
 *   php bin/install.php                 # create/upgrade tables
 *   php bin/install.php --demo          # also load ~4 months of realistic demo garage data
 *   php bin/install.php --clear-demo    # delete all garage + AI data (keeps users & settings)
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
require_once __DIR__ . '/../lib/db.php';

$args = array_slice($argv, 1);

$sql = file_get_contents(__DIR__ . '/../database/schema.sql');
db()->multi_query($sql);
do { if ($r = db()->store_result()) $r->free(); } while (db()->more_results() && db()->next_result());

// Upgrade tables created by the first version of the AI layer.
$cols = array_column(rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_cycles'"), 'COLUMN_NAME');
if (!in_array('progress', $cols, true)) {
    db()->query('ALTER TABLE ai_cycles ADD COLUMN progress VARCHAR(200) NULL AFTER status');
}
echo "Tables ready.\n";

$garageTables = ['job_items', 'jobs', 'vehicles', 'customers', 'services', 'parts', 'employees', 'expenses', 'purchase_orders',
                 'marketing_posts', 'promotions', 'job_postings', 'ai_actions', 'ai_tasks', 'ai_reports', 'ai_messages', 'ai_conversations', 'ai_cycles'];

if (in_array('--clear-demo', $args, true)) {
    foreach ($garageTables as $t) db()->query("TRUNCATE TABLE $t");
    echo "All garage and AI data cleared.\n";
}

if (in_array('--demo', $args, true)) {
    if ((int)val('SELECT COUNT(*) FROM jobs') > 0) {
        exit("There is already data in `jobs`. Run with --clear-demo --demo to replace it.\n");
    }
    require __DIR__ . '/seed.php';
    seed_demo();
    echo "Demo data loaded.\n";
}

if ((int)val('SELECT COUNT(*) FROM users') === 0) {
    echo "No users yet - open the app in your browser to create the owner account.\n";
}
