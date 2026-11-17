<?php
/**
 * Realistic demo data for a Malaysian car workshop (about 4 months of history),
 * so the dashboard and AI departments have something to work with.
 * Replace it with real data any time: php bin/install.php --clear-demo
 */
require_once __DIR__ . '/../lib/business.php';

function seed_demo(): void
{
    mt_srand(42);
    $pick = fn(array $a) => $a[mt_rand(0, count($a) - 1)];

    $services = [
        ['Servis minyak hitam (semi-synthetic)', 'Servicing', 120, 0.8, [['OIL-SEMI-4L', 1], ['FLT-OIL', 1]]],
        ['Servis minyak hitam (fully synthetic)', 'Servicing', 220, 0.8, [['OIL-FULL-4L', 1], ['FLT-OIL', 1]]],
        ['Major service (filters + plugs)', 'Servicing', 380, 2.0, [['OIL-FULL-4L', 1], ['FLT-OIL', 1], ['FLT-AIR', 1], ['PLUG-IR', 4]]],
        ['Brake pad replacement (front)', 'Brakes', 90, 1.0, [['BRK-PAD-F', 1]]],
        ['Brake disc skim', 'Brakes', 80, 1.0, []],
        ['Tyre change (per tyre)', 'Tyres', 25, 0.3, [['TYR-15', 1]]],
        ['Wheel alignment & balancing', 'Tyres', 70, 0.8, []],
        ['Aircond service & gas top-up', 'Aircond', 150, 1.2, [['AC-GAS', 1]]],
        ['Aircond compressor diagnosis', 'Aircond', 60, 0.7, []],
        ['Battery replacement', 'Electrical', 30, 0.3, [['BAT-NS60', 1]]],
        ['OBD diagnostic scan', 'Diagnostics', 50, 0.5, []],
        ['Gearbox oil change (auto)', 'Servicing', 180, 1.0, [['ATF-4L', 1]]],
        ['Absorber replacement (pair)', 'Suspension', 160, 2.0, [['ABS-F', 2]]],
        ['Coolant flush', 'Servicing', 90, 0.8, [['COOL-4L', 1]]],
        ['Wiper blade replacement', 'Servicing', 10, 0.1, [['WIPER', 2]]],
    ];
    $parts = [
        ['OIL-SEMI-4L', 'Engine oil 10W-40 semi-synthetic 4L', 'Oil', 58, 95, 34, 12, 'Petron Lubricants'],
        ['OIL-FULL-4L', 'Engine oil 5W-30 fully synthetic 4L', 'Oil', 105, 175, 9, 10, 'Petron Lubricants'],
        ['FLT-OIL', 'Oil filter (Perodua/Proton/Honda)', 'Filters', 8, 20, 61, 20, 'AutoParts KL'],
        ['FLT-AIR', 'Air filter', 'Filters', 18, 40, 14, 8, 'AutoParts KL'],
        ['PLUG-IR', 'Iridium spark plug', 'Ignition', 22, 45, 30, 16, 'NGK Distributor'],
        ['BRK-PAD-F', 'Front brake pads set', 'Brakes', 65, 130, 4, 6, 'Bendix MY'],
        ['TYR-15', 'Tyre 185/65 R15', 'Tyres', 145, 210, 18, 8, 'Tyre Mart'],
        ['AC-GAS', 'R134a aircond gas', 'Aircond', 25, 60, 22, 10, 'Cool Supply'],
        ['BAT-NS60', 'Battery NS60 maintenance-free', 'Electrical', 175, 260, 3, 5, 'Century Batteries'],
        ['ATF-4L', 'Auto transmission fluid 4L', 'Oil', 90, 150, 7, 4, 'Petron Lubricants'],
        ['ABS-F', 'Front absorber', 'Suspension', 120, 210, 6, 4, 'KYB Malaysia'],
        ['COOL-4L', 'Long-life coolant 4L', 'Fluids', 28, 55, 11, 6, 'AutoParts KL'],
        ['WIPER', 'Wiper blade 22"', 'Accessories', 9, 25, 40, 15, 'AutoParts KL'],
    ];
    $partIds = [];
    foreach ($parts as [$sku, $name, $cat, $cost, $price, $qty, $reorder, $sup]) {
        q('INSERT INTO parts (sku, name, category, cost, price, qty, reorder_level, supplier) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
          [$sku, $name, $cat, (float)$cost, (float)$price, $qty, $reorder, $sup]);
        $partIds[$sku] = ['id' => insert_id(), 'cost' => (float)$cost, 'price' => (float)$price, 'name' => $name];
    }
    $svcIds = [];
    foreach ($services as $i => [$name, $cat, $price, $hrs, $uses]) {
        q('INSERT INTO services (name, category, price, labour_hours) VALUES (?, ?, ?, ?)', [$name, $cat, (float)$price, (float)$hrs]);
        $svcIds[$i] = insert_id();
    }

    $staff = [
        ['Hafiz Rahman', 'Senior Mechanic', 18, '-6 years'], ['Kumar Selvam', 'Mechanic', 14, '-3 years'],
        ['Wei Jie Tan', 'Mechanic', 13, '-14 months'], ['Aiman Zulkifli', 'Junior Mechanic', 10, '-5 months'],
        ['Nurul Aina', 'Front Desk & Admin', 11, '-2 years'],
    ];
    $mechanics = [];
    foreach ($staff as [$n, $role, $rate, $hired]) {
        q('INSERT INTO employees (name, role, phone, hourly_rate, hired_on) VALUES (?, ?, ?, ?, ?)',
          [$n, $role, '01' . mt_rand(10000000, 99999999), (float)$rate, date('Y-m-d', strtotime($hired))]);
        if (str_contains($role, 'Mechanic')) $mechanics[] = insert_id();
    }

    $first = ['Ahmad', 'Siti', 'Muhammad', 'Nur', 'Faizal', 'Aisyah', 'Lim', 'Tan', 'Wong', 'Lee', 'Raj', 'Priya', 'Arjun', 'Farah', 'Hakim', 'Izzat', 'Mei Ling', 'Daniel', 'Syafiq', 'Kavitha', 'Amir', 'Zul', 'Chong', 'Haziq', 'Liyana'];
    $last = ['Ismail', 'Abdullah', 'Hassan', 'Yusof', 'Ong', 'Chan', 'Ng', 'Krishnan', 'Rahim', 'Osman', 'Kamal', 'Teo', 'Ramasamy', 'Aziz'];
    $cars = [['Perodua', 'Myvi'], ['Perodua', 'Axia'], ['Perodua', 'Bezza'], ['Perodua', 'Ativa'], ['Perodua', 'Alza'], ['Proton', 'Saga'],
             ['Proton', 'X50'], ['Proton', 'Persona'], ['Proton', 'X70'], ['Honda', 'City'], ['Honda', 'Civic'], ['Honda', 'HR-V'],
             ['Toyota', 'Vios'], ['Toyota', 'Hilux'], ['Nissan', 'Almera'], ['Mazda', 'CX-5']];
    $sources = ['tiktok', 'tiktok', 'tiktok', 'tiktok', 'walk-in', 'walk-in', 'walk-in', 'referral', 'referral', 'google', 'instagram', 'facebook'];

    $start = strtotime('-125 days');
    $customers = [];
    for ($i = 0; $i < 140; $i++) {
        $joined = date('Y-m-d H:i:s', mt_rand($start, time() - 86400 * 3));
        $name = $pick($first) . ' ' . $pick($last);
        q('INSERT INTO customers (name, phone, source, created_at) VALUES (?, ?, ?, ?)',
          [$name, '01' . mt_rand(10000000, 99999999), $pick($sources), $joined]);
        $cid = insert_id();
        [$make, $model] = $pick($cars);
        $plate = $pick(['W', 'V', 'B', 'J', 'P', 'A']) . chr(mt_rand(65, 90)) . chr(mt_rand(65, 90)) . ' ' . mt_rand(100, 9999);
        q('INSERT INTO vehicles (customer_id, plate, make, model, year, mileage, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
          [$cid, $plate, $make, $model, mt_rand(2012, 2024), mt_rand(15000, 190000), $joined]);
        $customers[] = ['id' => $cid, 'vehicle' => insert_id(), 'joined' => strtotime($joined)];
    }

    // Weighted service popularity (oil changes dominate, as in real workshops).
    $weights = [30, 14, 6, 9, 3, 8, 7, 9, 3, 6, 6, 3, 2, 3, 5];
    $wPick = function () use ($weights) {
        $r = mt_rand(1, array_sum($weights));
        foreach ($weights as $i => $w) { if (($r -= $w) <= 0) return $i; }
        return 0;
    };
    $complaints = ['Due for service', 'Bunyi pelik bila brek', 'Aircond tak sejuk', 'Check engine light on', 'Kereta susah start',
                   'Stereng bergegar', 'Minyak hitam dah sampai masa', 'Tayar botak', 'Bunyi dari bawah kereta', 'Pre-balik kampung check'];

    for ($d = 125; $d >= 0; $d--) {
        $day = strtotime("-$d days");
        $dow = (int)date('N', $day);
        if ($dow === 7) continue; // closed Sunday
        $base = $dow === 6 ? 9 : ($dow === 1 || $dow === 5 ? 7 : 5);
        $growth = 1 + (125 - $d) / 125 * 0.35;            // business growing ~35% over the period
        $n = max(0, (int)round($base * $growth + mt_rand(-2, 2)));
        for ($j = 0; $j < $n; $j++) {
            $eligible = array_values(array_filter($customers, fn($c) => $c['joined'] <= $day + 86400));
            if (!$eligible) break;
            $c = $pick($eligible);
            $opened = date('Y-m-d H:i:s', $day + mt_rand(9 * 3600, 16 * 3600));
            $isToday = $d === 0;
            $status = $isToday ? $pick(['open', 'in_progress']) : (mt_rand(1, 40) === 1 ? 'cancelled' : 'completed');
            $closed = $status === 'completed' ? date('Y-m-d H:i:s', strtotime($opened) + mt_rand(3600, 6 * 3600)) : null;
            q('INSERT INTO jobs (customer_id, vehicle_id, employee_id, status, complaint, opened_at, closed_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
              [$c['id'], $c['vehicle'], $pick($mechanics), $status, $pick($complaints), $opened, $closed]);
            $jobId = insert_id();
            $chosen = array_unique([$wPick(), mt_rand(1, 3) === 1 ? $wPick() : $wPick()]);
            if (mt_rand(1, 2) === 1) $chosen = [reset($chosen)];
            foreach ($chosen as $si) {
                [$sname, , $sprice, , $uses] = $services[$si];
                q("INSERT INTO job_items (job_id, item_type, ref_id, description, qty, unit_price, unit_cost) VALUES (?, 'service', ?, ?, 1, ?, 0)",
                  [$jobId, $svcIds[$si], $sname, (float)$sprice]);
                foreach ($uses as [$sku, $qty]) {
                    $p = $partIds[$sku];
                    q("INSERT INTO job_items (job_id, item_type, ref_id, description, qty, unit_price, unit_cost) VALUES (?, 'part', ?, ?, ?, ?, ?)",
                      [$jobId, $p['id'], $p['name'], (float)$qty, $p['price'], $p['cost']]);
                }
            }
            recalc_job($jobId);
        }
    }

    // Monthly fixed costs plus weekly parts purchases and occasional marketing.
    for ($m = 4; $m >= 0; $m--) {
        $month = date('Y-m-01', strtotime("first day of -$m months"));
        if (strtotime($month) > time()) continue;
        foreach ([['rent', 4500, 'Workshop rent'], ['utilities', 1100 + mt_rand(0, 300), 'TNB + water + internet'], ['salaries', 14800, 'Monthly payroll incl. EPF/SOCSO']] as [$cat, $amt, $desc]) {
            q('INSERT INTO expenses (category, amount, description, spent_on) VALUES (?, ?, ?, ?)', [$cat, (float)$amt, $desc, $month]);
        }
    }
    for ($d = 120; $d >= 0; $d -= 7) {
        q("INSERT INTO expenses (category, amount, description, spent_on) VALUES ('parts', ?, 'Weekly parts restock', ?)",
          [(float)mt_rand(2200, 3800), date('Y-m-d', strtotime("-$d days"))]);
        if (mt_rand(1, 3) === 1) {
            q("INSERT INTO expenses (category, amount, description, spent_on) VALUES ('marketing', ?, 'TikTok boost', ?)",
              [(float)mt_rand(50, 250), date('Y-m-d', strtotime("-$d days"))]);
        }
    }
    q("INSERT INTO expenses (category, amount, description, spent_on) VALUES ('equipment', 2600, 'Aircond recovery machine service', ?)", [date('Y-m-d', strtotime('-40 days'))]);
}
