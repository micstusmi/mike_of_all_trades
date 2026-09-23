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
CREATE TABLE work_tasks (id INT UNSIGNED PRIMARY KEY,job_id INT UNSIGNED NOT NULL);
CREATE TABLE work_receipts (id INT UNSIGNED PRIMARY KEY,job_id INT UNSIGNED NOT NULL);
INSERT INTO work_customers VALUES (17,'Example customer','example@example.invalid','0400000000');
INSERT INTO work_properties VALUES (32,17,'1 Example Street, Melbourne');
INSERT INTO work_jobs VALUES (91,17,32,'Example customer','1 Example Street, Melbourne','Repair showroom tapware\nSecond line','Replacement requested','paused','2026-09-01 12:00:00',1542.74,10.00,90.00,'daily',560.00,500.00,50.00,0.00,'2026-09-25 12:00:00',NULL,'https://drive.google.com/drive/folders/example');
INSERT INTO work_tasks VALUES (1,91);
INSERT INTO work_receipts VALUES (2,91);
