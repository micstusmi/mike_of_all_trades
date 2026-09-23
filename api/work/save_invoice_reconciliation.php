<?php
declare(strict_types=1);
require_once __DIR__.'/_admin_auth.php';require_once __DIR__.'/../../includes/work_tracker.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required.');}$invoiceId=(int)($_POST['invoice_id']??0);
try{
 $q=$pdo->prepare('SELECT * FROM work_invoice_ledger WHERE id=?');$q->execute([$invoiceId]);$invoice=$q->fetch(PDO::FETCH_ASSOC);if(!$invoice)throw new RuntimeException('Invoice not found.');
 $sessions=array_values(array_unique(array_filter(array_map('intval',$_POST['session_ids']??[]))));$materials=array_values(array_unique(array_filter(array_map('intval',$_POST['material_ids']??[]))));
 $pdo->beginTransaction();$pdo->prepare('DELETE FROM work_invoice_sessions WHERE invoice_id=?')->execute([$invoiceId]);$pdo->prepare('DELETE FROM work_invoice_materials WHERE invoice_id=?')->execute([$invoiceId]);
 foreach($sessions as $id){$q=$pdo->prepare('SELECT COUNT(*) FROM work_sessions s JOIN work_jobs j ON j.id=s.job_id JOIN work_invoice_jobs ij ON ij.job_id=j.id WHERE s.id=? AND ij.invoice_id=?');$q->execute([$id,$invoiceId]);if(!(int)$q->fetchColumn())throw new RuntimeException('A selected labour session does not belong to an invoice job.');$pdo->prepare('INSERT INTO work_invoice_sessions(invoice_id,session_id) VALUES(?,?)')->execute([$invoiceId,$id]);}
 foreach($materials as $id){$q=$pdo->prepare('SELECT COUNT(*) FROM work_materials m JOIN work_invoice_jobs ij ON ij.job_id=m.job_id WHERE m.id=? AND ij.invoice_id=?');$q->execute([$id,$invoiceId]);if(!(int)$q->fetchColumn())throw new RuntimeException('A selected material does not belong to an invoice job.');$pdo->prepare('INSERT INTO work_invoice_materials(invoice_id,material_id) VALUES(?,?)')->execute([$invoiceId,$id]);}
 $pdo->prepare('UPDATE work_invoice_ledger SET reconciled_at=NOW() WHERE id=?')->execute([$invoiceId]);$pdo->commit();header('Location: ../../admin/work/invoice_reconciliation.php?id='.$invoiceId.'&saved=1');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(400);echo 'Reconciliation stopped: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');}
