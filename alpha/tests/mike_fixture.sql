-- Disposable synthetic Mike-shaped source database. Never use a real customer dump in CI.
CREATE TABLE work_customers (id INT UNSIGNED PRIMARY KEY,display_name VARCHAR(190) NOT NULL,email VARCHAR(190),phone VARCHAR(30));
CREATE TABLE work_properties (id INT UNSIGNED PRIMARY KEY,customer_id INT UNSIGNED NOT NULL,address VARCHAR(500) NOT NULL);
CREATE TABLE work_jobs (
  id INT UNSIGNED PRIMARY KEY,customer_id INT UNSIGNED,property_id INT UNSIGNED,
  customer_name VARCHAR(190),job_address VARCHAR(500),original_scope TEXT,current_scope TEXT,
  status VARCHAR(40),created_at DATETIME,original_estimate_amount DECIMAL(12,2),
  original_estimate_hours DECIMAL(10,2),agreed_hourly_rate DECIMAL(12,2),payment_mode VARCHAR(40),
  unpaid_balance_limit DECIMAL(12,2),work_already_value DECIMAL(12,2),
  materials_already_value DECIMAL(12,2),payments_received DECIMAL(12,2),
  planned_start_at DATETIME,planned_finish_at DATETIME,media_archive_url VARCHAR(2048)
);
CREATE TABLE work_tasks (id INT UNSIGNED PRIMARY KEY,job_id INT UNSIGNED NOT NULL,task_order INT NOT NULL,title VARCHAR(255) NOT NULL,description TEXT,status VARCHAR(40),customer_visible TINYINT(1),completed_at DATETIME,created_at DATETIME);
CREATE TABLE work_sessions (id INT UNSIGNED PRIMARY KEY,job_id INT UNSIGNED NOT NULL,task_id INT UNSIGNED NULL,started_at DATETIME NOT NULL,ended_at DATETIME NULL,category VARCHAR(40),billable TINYINT(1),notes TEXT,session_source VARCHAR(40),retrospective_hours DECIMAL(12,3));
CREATE TABLE work_session_breaks (id INT UNSIGNED PRIMARY KEY,session_id INT UNSIGNED NOT NULL,started_at DATETIME NOT NULL,ended_at DATETIME NULL,reason VARCHAR(50),note VARCHAR(500));
CREATE TABLE work_materials (id INT UNSIGNED PRIMARY KEY,job_id INT UNSIGNED NOT NULL,purchased_at DATETIME,description VARCHAR(255),supplier VARCHAR(150),cost DECIMAL(12,2),paid_by VARCHAR(40),receipt_path VARCHAR(500),notes TEXT);
CREATE TABLE work_payments (id INT UNSIGNED PRIMARY KEY,job_id INT UNSIGNED NOT NULL,amount DECIMAL(12,2),payment_type VARCHAR(40),method VARCHAR(50),notes TEXT,paid_at DATETIME);
CREATE TABLE work_receipts (id INT UNSIGNED PRIMARY KEY,job_id INT UNSIGNED NOT NULL);
INSERT INTO work_customers VALUES (17,'Example customer','example@example.invalid','0400000000');
INSERT INTO work_properties VALUES (32,17,'1 Example Street, Melbourne');
INSERT INTO work_jobs VALUES (91,17,32,'Example customer','1 Example Street, Melbourne','Repair showroom tapware\nSecond line','Replacement requested','paused','2026-09-01 12:00:00',1542.74,10.00,90.00,'daily',560.00,500.00,50.00,0.00,'2026-09-25 12:00:00',NULL,'https://drive.google.com/drive/folders/example');
INSERT INTO work_tasks VALUES (1,91,10,'Change tapware','Showroom mixer','in_progress',1,NULL,'2026-09-01 12:00:00');
INSERT INTO work_sessions VALUES (3,91,1,'2026-09-02 09:00:00','2026-09-02 11:00:00','onsite',1,'Started replacement','live',NULL);
INSERT INTO work_session_breaks VALUES (4,3,'2026-09-02 10:00:00','2026-09-02 10:15:00','coffee_break','Tea');
INSERT INTO work_materials VALUES (5,91,'2026-09-02 09:00:00','Mixer','Bunnings',75.00,'mike','storage/private/example-receipt.jpg','Purchased on site');
INSERT INTO work_payments VALUES (6,91,100.00,'deposit','card','On account','2026-09-01 12:00:00');
INSERT INTO work_receipts VALUES (2,91);
