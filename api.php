<?php
/** JSON endpoints used by the pages (chat, cycles). */
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/ai/agent.php';
require_login();

header('Content-Type: application/json');
$action = $_REQUEST['action'] ?? '';

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    switch ($action) {
        case 'chat':
            csrf_check();
            set_time_limit(0);
            session_write_close(); // don't block other pages while the department works
            $dept = $_POST['dept'] ?? '';
            $msg = trim($_POST['message'] ?? '');
            $conv = (int)($_POST['conversation_id'] ?? 0) ?: null;
            if (!isset(DEPARTMENTS[$dept]) || $msg === '') out(['error' => 'Missing department or message'], 400);
            if ($conv && val("SELECT department FROM ai_conversations WHERE id = ?", [$conv]) !== $dept) out(['error' => 'Unknown conversation'], 400);
            $r = run_agent($dept, $msg, $conv);
            out([
                'conversation_id' => $r['conversation_id'],
                'html' => md($r['text']),
                'tools' => array_map(fn($t) => ['name' => $t['name'], 'error' => $t['error']], $r['tools']),
                'pending' => (int)val("SELECT COUNT(*) FROM ai_actions WHERE status = 'pending'"),
            ]);

        case 'start_cycle':
            csrf_check();
            if (val("SELECT COUNT(*) FROM ai_cycles WHERE status = 'running' AND started_at > NOW() - INTERVAL 1 HOUR")) {
                out(['error' => 'A cycle is already running.'], 409);
            }
            if (!ai_configured()) out(['error' => 'Add ANTHROPIC_API_KEY to .env first.'], 400);
            $id = start_cycle_background(trim($_POST['directive'] ?? ''));
            out(['cycle_id' => $id]);

        case 'cycle_status':
            $c = row('SELECT id, status, progress, started_at, finished_at FROM ai_cycles WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
            out($c ?: ['error' => 'not found']);
    }
    out(['error' => 'Unknown action'], 400);
} catch (Throwable $e) {
    out(['error' => $e->getMessage()], 500);
}
