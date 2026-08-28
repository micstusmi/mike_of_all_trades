<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$id=(int)($_POST['job_id']??0); $job=wt_job($pdo,$id); $tot=wt_totals($pdo,$id);
$date=date('Y-m-d');
$stmt=$pdo->prepare("INSERT INTO work_reports(job_id,report_date,work_summary,issues_summary,next_steps,labour_total,materials_total,job_total_to_date,payments_to_date,outstanding_balance)
VALUES(?,?,?,?,?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE work_summary=VALUES(work_summary),issues_summary=VALUES(issues_summary),next_steps=VALUES(next_steps),labour_total=VALUES(labour_total),materials_total=VALUES(materials_total),job_total_to_date=VALUES(job_total_to_date),payments_to_date=VALUES(payments_to_date),outstanding_balance=VALUES(outstanding_balance)");
$stmt->execute([$id,$date,trim($_POST['work_summary']),trim($_POST['issues_summary']??''),trim($_POST['next_steps']??''),$tot['labour'],$tot['materials'],$tot['total'],$tot['payments'],$tot['outstanding']]);
$msg="Mike of All Trades daily report ".date('j M').": job total to date ".wt_money($tot['total'])."; paid ".wt_money($tot['payments'])."; outstanding ".wt_money($tot['outstanding']).". Work summary and job record: ".wt_public_url($job);
wt_send_sms($pdo,$id,$job['customer_phone'],$msg,'daily_report');
header("Location: ../../admin/work/job.php?id=$id");
