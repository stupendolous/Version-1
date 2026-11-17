# STRGarage — AI-run workshop

A garage management system for STRGarage with an AI company built in. Four AI departments and an AI CEO work from your real workshop data. They chat with you, propose actions, and keep a shared task board.

## What's inside

**Garage operations**
- **Work orders**: customer and car check-in, services and parts, then complete and invoice. Completing a job takes its parts out of stock.
- Customers (including how they found you, e.g. TikTok), vehicles, the service menu, parts and stock, staff, and expenses.
- **Dashboard** with revenue, margin, jobs, low stock, a weekly revenue chart, revenue by category, and revenue vs expenses.

**AI company**

| Department | Can look at | Can propose (goes to Approvals) |
|---|---|---|
| **AI CEO** | Everything | Delegates work to the other departments and assigns tasks |
| **Analytics** | Revenue, margins, retention, TikTok vs walk-in, stock | Restock orders, price changes |
| **Marketing** | Customers, services, quiet days | TikTok/IG/FB posts with captions and shot lists, promotions |
| **R&D** | Service menu, car models in your customer base | New services and packages, price changes |
| **HR** | Workload per mechanic, staff | Job postings, pay changes |

- **Chat** with any department. They query your database live (read-only), and they remember past reports and your approve/reject decisions.
- **Approvals**: nothing changes your business until you approve it. In Settings you can put action types on **autopilot**. Pay changes always need you.
- **Task board**: departments and you create tasks for each other.
- **Company cycle**: one click (or a daily cron job) runs every department's review. Then the CEO writes the weekly plan.

Everything is behind a login. The AI departments cannot read user accounts, and they cannot write to the database except through approved actions.

## Setup (Mac)

```bash
brew install php composer mysql
```
```bash
brew services start mysql
```
```bash
mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '123456'; CREATE DATABASE IF NOT EXISTS barang;"
```
```bash
cd Version-1 && composer install
```
```bash
cp .env.example .env
```
Open `.env` and put in your Anthropic API key (get one at console.anthropic.com). Then create the tables:
```bash
php bin/install.php --demo
```
`--demo` loads 4 months of realistic sample garage data so you can try everything. Leave it out to start empty. Later, `php bin/install.php --clear-demo` wipes the data.

Start the app:
```bash
php -S localhost:8000 router.php
```
Open http://localhost:8000. The first visit asks you to create the owner account. Then go to **Settings** and paste in a description of your business (your TikTok bio is a good start).

## Run the AI automatically

```bash
php bin/run_cycle.php                                  # full company cycle
php bin/run_cycle.php "Push aircond before hot season" # with an instruction
php bin/run_cycle.php --dept=marketing "Plan next week's TikToks"
```
Every morning at 7:40 (`crontab -e`):
```
40 7 * * * cd /path/to/Version-1 && php bin/run_cycle.php >> storage/cycle.log 2>&1
```

## Files

| Path | What |
|---|---|
| `index.php`, `department.php`, `approvals.php`, `tasks.php`, `jobs.php`, `data.php`, `settings.php` | Pages |
| `ai/company.php` | Departments: their instructions, tools, model |
| `ai/agent.php` | Agent loop, chat memory, company cycle |
| `ai/tools.php` | Tools the departments can use (read-only SQL, KPIs, tasks, delegation, proposals) |
| `ai/actions.php` | What each approved action does |
| `database/schema.sql`, `bin/install.php`, `bin/seed.php` | Database setup and demo data |
| `legacy/` | The original toy-shop pages |

## Notes

- Model: `claude-opus-5-5`. If the model declines a request, it retries on a fallback model automatically.
- Cost: a chat message is usually a few model calls, and a full cycle is around 15–30. Token usage is shown in Settings, and exact cost is at console.anthropic.com.
- Social posts are added to a content calendar for you to film and post. The app does not post to TikTok by itself.
