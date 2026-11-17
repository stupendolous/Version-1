-- STRGarage database schema. Safe to run more than once.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'owner',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) PRIMARY KEY,
  v TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Garage operations ----------

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30),
  email VARCHAR(190),
  source VARCHAR(40) COMMENT 'how they found us: tiktok, walk-in, referral, google, instagram, facebook',
  notes TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  plate VARCHAR(20) NOT NULL,
  make VARCHAR(40),
  model VARCHAR(60),
  year SMALLINT,
  mileage INT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (customer_id), KEY (plate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(40) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  labour_hours DECIMAL(5,2) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(40),
  cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  qty INT NOT NULL DEFAULT 0,
  reorder_level INT NOT NULL DEFAULT 5,
  supplier VARCHAR(120)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  role VARCHAR(60) NOT NULL,
  phone VARCHAR(30),
  hourly_rate DECIMAL(8,2) NOT NULL DEFAULT 0,
  hired_on DATE,
  status VARCHAR(20) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Work orders
CREATE TABLE IF NOT EXISTS jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  vehicle_id INT,
  employee_id INT COMMENT 'assigned mechanic',
  status VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT 'open, in_progress, completed, cancelled',
  complaint TEXT,
  notes TEXT,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME,
  KEY (status), KEY (closed_at), KEY (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS job_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  item_type VARCHAR(10) NOT NULL COMMENT 'service or part',
  ref_id INT NOT NULL,
  description VARCHAR(160) NOT NULL,
  qty DECIMAL(8,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL,
  unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  KEY (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(40) NOT NULL COMMENT 'rent, utilities, salaries, parts, marketing, equipment, other',
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(200),
  spent_on DATE NOT NULL,
  KEY (spent_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Things the AI departments create (after approval) ----------

CREATE TABLE IF NOT EXISTS purchase_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  part_id INT NOT NULL,
  qty INT NOT NULL,
  unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'ordered' COMMENT 'ordered, received, cancelled',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  received_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketing_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  channel VARCHAR(20) NOT NULL,
  caption TEXT NOT NULL,
  video_idea TEXT,
  scheduled_for DATE,
  status VARCHAR(20) NOT NULL DEFAULT 'scheduled' COMMENT 'scheduled, posted, cancelled',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promotions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  description TEXT,
  service_id INT,
  discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  start_date DATE,
  end_date DATE,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS job_postings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  employment_type VARCHAR(20) NOT NULL,
  pay_range VARCHAR(60),
  description TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT 'open, filled, closed',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- AI company ----------

CREATE TABLE IF NOT EXISTS ai_cycles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  directive TEXT,
  status VARCHAR(20) NOT NULL DEFAULT 'running',
  progress VARCHAR(200),
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department VARCHAR(20) NOT NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'chat' COMMENT 'chat, cycle, delegated',
  cycle_id INT,
  input_tokens INT NOT NULL DEFAULT 0,
  output_tokens INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (department, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exact API transcript (wire-format JSON) so conversations replay unchanged.
CREATE TABLE IF NOT EXISTS ai_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  role VARCHAR(10) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  display_text TEXT COMMENT 'what the owner typed, without injected context',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cycle_id INT,
  department VARCHAR(20) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  input_tokens INT NOT NULL DEFAULT 0,
  output_tokens INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY dept_time (department, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Actions proposed by AI departments. Nothing happens until approved
-- (unless that action type is on autopilot in Settings).
CREATE TABLE IF NOT EXISTS ai_actions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department VARCHAR(20) NOT NULL,
  type VARCHAR(40) NOT NULL,
  summary VARCHAR(255) NOT NULL,
  reason TEXT,
  payload TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, executed, rejected, failed',
  result TEXT,
  conversation_id INT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME,
  decided_by VARCHAR(100),
  KEY (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department VARCHAR(20) NOT NULL,
  title VARCHAR(200) NOT NULL,
  details TEXT,
  priority VARCHAR(10) NOT NULL DEFAULT 'medium',
  status VARCHAR(20) NOT NULL DEFAULT 'todo' COMMENT 'todo, doing, done',
  created_by VARCHAR(20) NOT NULL,
  due_on DATE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
