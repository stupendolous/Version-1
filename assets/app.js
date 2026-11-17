// STRGarage front-end helpers.

function cssVar(name) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

function rm(n) {
  return 'RM ' + Number(n).toLocaleString('en-MY', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

async function post(url, data) {
  const body = new FormData();
  Object.entries(data).forEach(([k, v]) => body.append(k, v));
  body.append('csrf', window.CSRF);
  const res = await fetch(url, { method: 'POST', body, headers: { Accept: 'application/json' } });
  const json = await res.json().catch(() => ({ error: 'Server error (' + res.status + ')' }));
  if (!res.ok || json.error) throw new Error(json.error || 'Request failed');
  return json;
}

/* ---------- Dashboard charts ---------- */
function drawDashboard() {
  if (!window.Chart || !window.DASH) return;
  const text2 = cssVar('--text-2'), grid = cssVar('--grid'), s1 = cssVar('--series-1'), s2 = cssVar('--series-2'), surface = cssVar('--surface');
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.color = text2;
  const money = { callback: v => rm(v) };
  const tooltip = { callbacks: { label: c => (c.dataset.label ? c.dataset.label + ': ' : '') + rm(c.parsed.y ?? c.parsed.x) } };
  const axes = (horizontal) => ({
    x: { grid: { display: horizontal, color: grid }, border: { display: false }, ticks: horizontal ? money : {} },
    y: { grid: { display: !horizontal, color: grid }, border: { display: false }, ticks: horizontal ? {} : money, beginAtZero: true },
  });

  new Chart(document.getElementById('cWeekly'), {
    type: 'line',
    data: { labels: DASH.weekly.labels, datasets: [{ data: DASH.weekly.values, borderColor: s1, backgroundColor: s1, borderWidth: 2,
      pointRadius: 0, pointHoverRadius: 5, pointHoverBorderWidth: 2, pointHoverBorderColor: surface, tension: .25 }] },
    options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: false }, tooltip }, scales: axes(false) },
  });

  new Chart(document.getElementById('cCats'), {
    type: 'bar',
    data: { labels: DASH.cats.labels, datasets: [{ data: DASH.cats.values, backgroundColor: s1, borderRadius: 4, borderSkipped: 'start', barPercentage: .7 }] },
    options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip }, scales: axes(true) },
  });

  new Chart(document.getElementById('cMonthly'), {
    type: 'bar',
    data: { labels: DASH.monthly.labels, datasets: [
      { label: 'Revenue', data: DASH.monthly.revenue, backgroundColor: s1, borderRadius: 4, borderSkipped: 'start', borderColor: surface, borderWidth: { left: 1, right: 1 } },
      { label: 'Expenses', data: DASH.monthly.expenses, backgroundColor: s2, borderRadius: 4, borderSkipped: 'start', borderColor: surface, borderWidth: { left: 1, right: 1 } },
    ] },
    options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 12, boxHeight: 12, useBorderRadius: true, borderRadius: 3 } }, tooltip },
      scales: axes(false) },
  });
}

/* ---------- Company cycle (dashboard) ---------- */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('cycleForm');
  const status = document.getElementById('cycleStatus');
  if (!form || !status) return;

  const poll = (id) => {
    const tick = async () => {
      const r = await fetch('api.php?action=cycle_status&id=' + id, { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => null);
      if (!r) return setTimeout(tick, 5000);
      status.textContent = 'Cycle #' + r.id + ' · ' + r.status + ' · ' + (r.progress || '');
      if (r.status === 'running') setTimeout(tick, 4000);
      else location.reload();
    };
    tick();
  };

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    form.querySelectorAll('input,button').forEach(el => el.disabled = true);
    status.textContent = 'Starting the company cycle…';
    try {
      const r = await post('api.php', { action: 'start_cycle', directive: form.directive.value });
      poll(r.cycle_id);
    } catch (err) {
      status.textContent = err.message;
      form.querySelectorAll('input,button').forEach(el => el.disabled = false);
    }
  });
  if (status.dataset.running) poll(status.dataset.running);
});

/* ---------- Department chat ---------- */
function initChat(toolLabels) {
  const box = document.getElementById('chat');
  const msgs = document.getElementById('msgs');
  const form = document.getElementById('chatForm');
  const ta = form.message;
  msgs.scrollTop = msgs.scrollHeight;

  const add = (cls, html) => {
    const el = document.createElement('div');
    el.className = cls;
    el.innerHTML = html;
    msgs.querySelector('.empty')?.remove();
    msgs.appendChild(el);
    msgs.scrollTop = msgs.scrollHeight;
    return el;
  };
  const esc = s => s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const send = async (text) => {
    if (!text.trim()) return;
    add('msg user', esc(text));
    ta.value = '';
    form.querySelector('button').disabled = ta.disabled = true;
    const wait = add('thinking', 'Working — checking data and taking actions');
    try {
      const r = await post('api.php', { action: 'chat', dept: box.dataset.dept, message: text, conversation_id: box.dataset.conv || '' });
      wait.remove();
      box.dataset.conv = r.conversation_id;
      history.replaceState(null, '', 'department.php?d=' + box.dataset.dept + '&c=' + r.conversation_id);
      r.tools.forEach(t => add('tool', '↳ ' + esc(toolLabels[t.name] || t.name) + (t.error ? ' (failed)' : '')));
      add('msg ai', r.html);
      const badge = document.querySelector('.nav .count');
      if (r.pending && badge) badge.textContent = r.pending;
      else if (r.pending) location.reload();
    } catch (err) {
      wait.remove();
      add('msg ai error', esc(err.message));
    } finally {
      form.querySelector('button').disabled = ta.disabled = false;
      ta.focus();
    }
  };

  form.addEventListener('submit', e => { e.preventDefault(); send(ta.value); });
  ta.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(ta.value); } });
  document.querySelectorAll('.chip[data-text]').forEach(c => c.addEventListener('click', () => send(c.dataset.text)));
}

/* ---------- New work order ---------- */
function initNewJob() {
  const cust = document.getElementById('custSel'), veh = document.getElementById('vehSel');
  const newCust = document.querySelector('.newcust'), newVeh = document.querySelector('.newveh');
  const sync = () => {
    newCust.style.display = cust.value ? 'none' : '';
    [...veh.options].forEach(o => { if (o.value) o.hidden = o.dataset.c !== cust.value; });
    if (veh.selectedOptions[0]?.hidden) veh.value = '';
    newVeh.style.display = veh.value ? 'none' : '';
  };
  cust.addEventListener('change', sync);
  veh.addEventListener('change', sync);
  sync();
}
