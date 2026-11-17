-- Tables used by the STRGarage AI company layer.
-- Import into the same database as `jadualmainan`.

CREATE TABLE IF NOT EXISTS `ai_cycles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `directive` text,
  `status` varchar(20) NOT NULL DEFAULT 'running',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ai_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cycle_id` int DEFAULT NULL,
  `department` varchar(20) NOT NULL,
  `content` mediumtext NOT NULL,
  `input_tokens` int NOT NULL DEFAULT 0,
  `output_tokens` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `dept_time` (`department`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
