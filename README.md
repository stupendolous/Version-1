# STRGarage — AI Company

Inventory app (PHP + MySQL) with an AI-run company layer on top. It has four AI departments and an AI CEO. All of them are powered by Claude and work from your live inventory data.

| Department | What it produces each cycle |
|---|---|
| **Analytics** | Key numbers, stock risks (low/over stock, pricing anomalies), opportunities, metrics to start tracking |
| **Marketing** | One-week plan: products to push, 3 ready-to-post social posts (BM + English), a promo/bundle, channels and budget |
| **R&D** | Catalogue gaps, new product ideas with price points, a system improvement, a 2-week validation experiment |
| **HR** | Staffing needs, a ready-to-post job description, onboarding checklist, a policy improvement |
| **AI CEO** | Reads all four reports and turns them into one plan: top 5 priorities, how conflicts between departments are resolved, and a **"Needs owner approval"** list |

A cycle runs in this order: Analytics → Marketing and R&D → HR (staffs their plans) → CEO. The departments write reports and recommendations. Anything that spends money, changes prices or affects people is listed for the owner to approve.

## Setup

```bash
composer install
mysql -u root -p barang < jadualmainan.sql   # if not already imported
mysql -u root -p barang < ai/schema.sql
export ANTHROPIC_API_KEY=sk-ant-...
```

Database settings default to the values in `config.php` and can be overridden with `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`.

Describe your business accurately in `ai/company.php` (`COMPANY_PROFILE`). Every department reads it. You can also tune each department's instructions there.

## Use

- **Web:** open `ai.php` (linked from the inventory page). From there you can run a full cycle or a single department. You can also give an owner directive, such as "Prepare for school holidays".
- **CLI / cron:**
  ```bash
  php ai/run_cycle.php "Focus on school holidays"
  php ai/run_cycle.php --dept=marketing
  # daily at 07:40
  40 7 * * * cd /path/to/Version-1 && ANTHROPIC_API_KEY=... php ai/run_cycle.php >> ai/cycle.log 2>&1
  ```

All reports are stored in the `ai_reports` table, with token usage, so you keep a history.

## Notes

- Model: `claude-opus-5-5`. If the model declines a request, it falls back server-side automatically.
- A full cycle makes 5 API calls. Each one is billed by token usage, which the dashboard shows.
- `ai.php` has no login. Put it behind authentication before exposing it on the internet, because anyone who can reach it can spend API credit.
