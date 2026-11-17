<?php
require_once __DIR__ . '/db.php';

/** Headline numbers used by the dashboard and given to every AI department. */
function business_kpis(int $days = 30): array
{
    $since = date('Y-m-d', strtotime("-$days days"));
    $prevSince = date('Y-m-d', strtotime('-' . (2 * $days) . ' days'));

    $rev = (float)val("SELECT COALESCE(SUM(total),0) FROM jobs WHERE status='completed' AND closed_at >= ?", [$since]);
    $revPrev = (float)val("SELECT COALESCE(SUM(total),0) FROM jobs WHERE status='completed' AND closed_at >= ? AND closed_at < ?", [$prevSince, $since]);
    $jobs = (int)val("SELECT COUNT(*) FROM jobs WHERE status='completed' AND closed_at >= ?", [$since]);
    $cost = (float)val("SELECT COALESCE(SUM(ji.qty*ji.unit_cost),0) FROM job_items ji JOIN jobs j ON j.id=ji.job_id WHERE j.status='completed' AND j.closed_at >= ?", [$since]);
    $exp = (float)val("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE spent_on >= ?", [$since]);

    return [
        'period_days' => $days,
        'revenue' => round($rev, 2),
        'revenue_previous_period' => round($revPrev, 2),
        'revenue_change_pct' => $revPrev > 0 ? round(($rev - $revPrev) / $revPrev * 100, 1) : null,
        'completed_jobs' => $jobs,
        'average_ticket' => $jobs ? round($rev / $jobs, 2) : 0,
        'parts_cost' => round($cost, 2),
        'gross_margin_pct' => $rev > 0 ? round(($rev - $cost) / $rev * 100, 1) : null,
        'expenses' => round($exp, 2),
        'open_jobs' => (int)val("SELECT COUNT(*) FROM jobs WHERE status IN ('open','in_progress')"),
        'new_customers' => (int)val('SELECT COUNT(*) FROM customers WHERE created_at >= ?', [$since]),
        'low_stock_parts' => (int)val('SELECT COUNT(*) FROM parts WHERE qty <= reorder_level'),
        'active_staff' => (int)val("SELECT COUNT(*) FROM employees WHERE status='active'"),
        'pending_approvals' => (int)val("SELECT COUNT(*) FROM ai_actions WHERE status='pending'"),
    ];
}

/** Weekly revenue for the last N complete weeks (Monday-start), zero-filled. */
function revenue_by_week(int $weeks = 12): array
{
    $start = date('Y-m-d', strtotime('monday this week -' . $weeks . ' weeks'));
    $data = [];
    for ($i = 0; $i < $weeks; $i++) {
        $data[date('Y-m-d', strtotime("$start +$i weeks"))] = 0.0;
    }
    $rs = rows("SELECT DATE(DATE_SUB(closed_at, INTERVAL WEEKDAY(closed_at) DAY)) wk, SUM(total) t
                FROM jobs WHERE status='completed' AND closed_at >= ? AND closed_at < ? GROUP BY wk", [$start, date('Y-m-d', strtotime('monday this week'))]);
    foreach ($rs as $r) {
        if (isset($data[$r['wk']])) {
            $data[$r['wk']] = (float)$r['t'];
        }
    }
    return $data;
}

function revenue_by_category(int $days = 90): array
{
    return rows("SELECT COALESCE(s.category, 'Parts') category, ROUND(SUM(ji.qty*ji.unit_price),2) revenue
                 FROM job_items ji JOIN jobs j ON j.id=ji.job_id
                 LEFT JOIN services s ON ji.item_type='service' AND s.id=ji.ref_id
                 WHERE j.status='completed' AND j.closed_at >= ?
                 GROUP BY category ORDER BY revenue DESC", [date('Y-m-d', strtotime("-$days days"))]);
}

function revenue_vs_expenses(int $months = 6): array
{
    $out = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("first day of -$i months"));
        $out[$m] = ['revenue' => 0.0, 'expenses' => 0.0];
    }
    $first = array_key_first($out) . '-01';
    foreach (rows("SELECT DATE_FORMAT(closed_at,'%Y-%m') m, SUM(total) t FROM jobs WHERE status='completed' AND closed_at >= ? GROUP BY m", [$first]) as $r) {
        if (isset($out[$r['m']])) $out[$r['m']]['revenue'] = (float)$r['t'];
    }
    foreach (rows("SELECT DATE_FORMAT(spent_on,'%Y-%m') m, SUM(amount) t FROM expenses WHERE spent_on >= ? GROUP BY m", [$first]) as $r) {
        if (isset($out[$r['m']])) $out[$r['m']]['expenses'] = (float)$r['t'];
    }
    return $out;
}

/** Recompute a job's total from its items. */
function recalc_job(int $jobId): void
{
    q('UPDATE jobs SET total = (SELECT COALESCE(SUM(qty*unit_price),0) FROM job_items WHERE job_id = ?) WHERE id = ?', [$jobId, $jobId]);
}
