<?php
declare(strict_types=1);
require_once __DIR__.'/_admin_auth.php';require_once __DIR__.'/../../includes/work_tracker.php';require_once __DIR__.'/../../includes/zoho_functions.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required.');}
$customerId=(int)($_POST['customer_id']??0);$number=strtoupper(trim((string)($_POST['invoice_number']??'')));$jobIds=array_values(array_unique(array_filter(array_map('intval',$_POST['job_ids']??[]))));
try{
 if($customerId<=0||$number===''||!$jobIds)throw new InvalidArgumentException('Enter an invoice number and select at least one related job.');
 $customer=wt_customer($pdo,$customerId);$contactId=trim((string)$customer['zoho_contact_id']);if($contactId==='')throw new RuntimeException('Link the Zoho customer first.');
 $marks=implode(',',array_fill(0,count($jobIds),'?'));$q=$pdo->prepare("SELECT COUNT(*) FROM work_jobs WHERE id IN ($marks) AND customer_id=?");$q->execute(array_merge($jobIds,[$customerId]));if((int)$q->fetchColumn()!==count($jobIds))throw new RuntimeException('Every selected job must belong to this customer.');
 $match=null;foreach(listZohoInvoicesForCustomer($contactId) as $candidate){if(strtoupper(trim((string)($candidate['invoice_number']??'')))===$number){$match=$candidate;break;}}
 if(!$match)throw new RuntimeException('That invoice number was not found under the linked Zoho customer. Nothing was imported.');
 $fresh=getZohoInvoice((string)$match['invoice_id']);$invoice=$fresh['json']['invoice']??null;if(!is_array($invoice))throw new RuntimeException('Zoho could not verify that invoice.');
 if((string)($invoice['customer_id']??'')!==$contactId)throw new RuntimeException('Safety stop: this invoice belongs to a different Zoho customer.');
 $total=round((float)($invoice['total']??0),2);$balance=round((float)($invoice['balance']??$invoice['balance_due']??0),2);$paid=max(0,round((float)($invoice['payment_made']??($total-$balance)),2));$status=$balance<=0.01?'paid':strtolower((string)($invoice['status']??'imported'));
 $pdo->beginTransaction();
 $q=$pdo->prepare("INSERT INTO work_invoice_ledger(customer_id,zoho_invoice_id,invoice_number,invoice_date,due_date,status,total_amount,paid_amount,balance_amount,tax_amount,source,zoho_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),customer_id=VALUES(customer_id),invoice_number=VALUES(invoice_number),invoice_date=VALUES(invoice_date),due_date=VALUES(due_date),status=VALUES(status),total_amount=VALUES(total_amount),paid_amount=VALUES(paid_amount),balance_amount=VALUES(balance_amount),tax_amount=VALUES(tax_amount),zoho_json=VALUES(zoho_json)");
 $q->execute([$customerId,(string)$invoice['invoice_id'],(string)$invoice['invoice_number'],($invoice['date']??null)?:null,($invoice['due_date']??null)?:null,$status,$total,$paid,$balance,round((float)($invoice['tax_total']??$invoice['total_tax']??0),2),'historical',json_encode($invoice,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);$ledgerId=(int)$pdo->lastInsertId();
 foreach($jobIds as $jobId)$pdo->prepare('INSERT IGNORE INTO work_invoice_jobs(invoice_id,job_id) VALUES(?,?)')->execute([$ledgerId,$jobId]);$pdo->commit();
 header('Location: ../../admin/work/invoice_reconciliation.php?id='.$ledgerId.'&imported=1');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(400);echo 'Invoice import stopped: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');}
