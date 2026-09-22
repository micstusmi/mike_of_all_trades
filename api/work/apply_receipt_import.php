<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_receipts.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

function sri_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    exit('Spreadsheet import could not be applied: ' . $message);
}

function sri_text(mixed $value, int $length): string
{
    return mb_substr(trim((string)$value), 0, $length);
}

function sri_money(mixed $value, string $label): ?float
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        throw new RuntimeException($label . ' must be a valid number.');
    }

    return round((float)$value, 2);
}

function sri_task_id(PDO $pdo, int $jobId, mixed $value): ?int
{
    $taskId = (int)$value;

    if ($taskId < 1) {
        return null;
    }

    $check = $pdo->prepare(
        'SELECT id FROM work_tasks WHERE id=? AND job_id=?'
    );
    $check->execute([$taskId, $jobId]);

    if (!$check->fetchColumn()) {
        throw new RuntimeException('A selected task does not belong to this job.');
    }

    return $taskId;
}

$jobId = (int)($_POST['job_id'] ?? 0);
$importId = (int)($_POST['import_id'] ?? 0);
wt_job($pdo, $jobId);

if (empty($_POST['confirm_original'])) {
    sri_fail('Confirm that you compared the draft with the spreadsheet.');
}

if (empty($_POST['confirm_reconciliation'])) {
    sri_fail('Confirm that you reviewed totals, duplicates, credits and warnings.');
}

$submitted = (array)($_POST['receipts'] ?? []);

try {
    $pdo->beginTransaction();

    $query = $pdo->prepare(
        "SELECT *
         FROM work_receipt_imports
         WHERE id=? AND job_id=?
         FOR UPDATE"
    );
    $query->execute([$importId, $jobId]);
    $import = $query->fetch(PDO::FETCH_ASSOC);

    if (!$import) {
        throw new RuntimeException('The spreadsheet import was not found.');
    }

    if ((string)$import['status'] === 'applied') {
        throw new RuntimeException('This spreadsheet import was already applied.');
    }

    if ((string)$import['status'] !== 'ready') {
        throw new RuntimeException('Only a ready spreadsheet import can be approved.');
    }

    $proposal = json_decode((string)$import['proposal_json'], true);
    $sourceReceipts = is_array($proposal['receipts'] ?? null)
        ? array_values($proposal['receipts'])
        : [];

    if (!$sourceReceipts) {
        throw new RuntimeException('The spreadsheet draft contains no receipts to approve.');
    }

    $materialInsert = $pdo->prepare(
        "INSERT INTO work_materials(
            job_id,task_id,description,material_status,supplier,
            actual_cost,cost,source_type,paid_by,payment_card_last4,reimbursement_status,
            financial_treatment,receipt_gst_amount,receipt_number,
            purchase_date,notes,purchased_at,updated_at
         ) VALUES(
            ?,?,?,'mike_has_it',?,?,?,'supplier_purchase',?,?,?,?,?,?,?,?,NOW(),NOW()
         )"
    );

    $complimentaryInsert = $pdo->prepare(
        "INSERT INTO work_complimentary_items(
            job_id,item_type,description,estimated_value,note,
            no_charge_reason,labour_hours,labour_value,
            material_value,material_details,updated_at
         ) VALUES(?,'material',?,?,?,?,NULL,0,?,?,NOW())"
    );

    $allowedPaidBy = ['mike', 'customer', 'other'];
    $allowedReimbursement = [
        'not_applicable',
        'reimbursement_due',
        'reimbursed',
        'no_reimbursement_due',
    ];
    $allowedTreatment = [
        'charge_customer',
        'included_in_price',
        'goodwill',
        'rectification',
    ];

    $approved = [];
    $materialIds = [];
    $approvedGross = 0.0;
    $approvedGst = 0.0;

    foreach ($submitted as $receiptIndexRaw => $fields) {
        $receiptIndex = filter_var(
            $receiptIndexRaw,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]]
        );

        if (
            $receiptIndex === false
            || !isset($sourceReceipts[$receiptIndex])
            || !is_array($fields)
            || empty($fields['include'])
        ) {
            continue;
        }

        $source = is_array($sourceReceipts[$receiptIndex])
            ? $sourceReceipts[$receiptIndex]
            : [];
        $taskId = sri_task_id($pdo, $jobId, $fields['task_id'] ?? 0);
        $supplier = sri_text($fields['supplier'] ?? '', 190);
        $number = sri_text($fields['receipt_number'] ?? '', 120);
        $date = trim((string)($fields['purchase_date'] ?? ''));
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
        $paidBy = in_array(($fields['paid_by'] ?? ''), $allowedPaidBy, true)
            ? (string)$fields['paid_by']
            : 'mike';
        $cardLast4 = trim((string)($fields['payment_card_last4'] ?? ''));
        if ($cardLast4 !== '' && !preg_match('/^\d{4}$/', $cardLast4)) {
            throw new RuntimeException(
                'Card last four digits must contain exactly four numbers.'
            );
        }
        $reimbursement = in_array(
            ($fields['reimbursement_status'] ?? ''),
            $allowedReimbursement,
            true
        ) ? (string)$fields['reimbursement_status'] : 'not_applicable';
        $treatment = in_array(
            ($fields['financial_treatment'] ?? ''),
            $allowedTreatment,
            true
        ) ? (string)$fields['financial_treatment'] : 'charge_customer';
        $isCredit = !empty($fields['is_credit_or_return'])
            || !empty($source['is_credit_or_return']);
        $lineInputs = is_array($fields['lines'] ?? null)
            ? $fields['lines']
            : [];
        $sourceLines = is_array($source['lines'] ?? null)
            ? array_values($source['lines'])
            : [];
        $approvedLines = [];

        $candidateGross=0.0;$hasCandidate=false;
        foreach($lineInputs as $candidate){if(!is_array($candidate)||empty($candidate['include']))continue;$amount=sri_money($candidate['gross_amount']??'','Selected line gross amount');if($amount!==null){$candidateGross+=$amount;$hasCandidate=true;}}
        $duplicate=wr_find_ledger_duplicate($pdo,$jobId,$supplier,$number,$date,$hasCandidate?round($candidateGross,2):null);
        if($duplicate&&empty($fields['allow_duplicate']))throw new RuntimeException('Possible duplicate receipt blocked: '.$supplier.($number!==''?' #'.$number:'').' '.$date.' $'.number_format($candidateGross,2).'. Return to review and use the override only if it is genuinely a different purchase.');

        foreach ($lineInputs as $lineIndexRaw => $lineFields) {
            $lineIndex = filter_var(
                $lineIndexRaw,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0]]
            );

            if (
                $lineIndex === false
                || !is_array($lineFields)
                || empty($lineFields['include'])
            ) {
                continue;
            }

            if ($sourceLines && !isset($sourceLines[$lineIndex])) {
                continue;
            }

            $description = sri_text($lineFields['description'] ?? '', 255);
            if ($description === '') {
                throw new RuntimeException(
                    'Every selected purchase line needs a description.'
                );
            }

            $gross = sri_money(
                $lineFields['gross_amount'] ?? '',
                $description . ' gross amount'
            );
            $gst = sri_money(
                $lineFields['gst_amount'] ?? '',
                $description . ' GST amount'
            );

            if ($gross === null) {
                throw new RuntimeException(
                    $description . ' needs a gross amount before approval.'
                );
            }

            $lineIsCredit = !empty($lineFields['is_credit_or_return'])
                || !empty($sourceLines[$lineIndex]['is_credit_or_return']);

            if ($lineIsCredit && $gross > 0) {
                throw new RuntimeException(
                    $description . ' is marked as a credit/return, so its gross amount must be zero or negative.'
                );
            }

            if ($lineIsCredit && $gst !== null && $gst > 0) {
                throw new RuntimeException(
                    $description . ' is marked as a credit/return, so its GST must be zero or negative.'
                );
            }

            $notes = 'Approved from spreadsheet import #'
                . $importId
                . ', receipt group '
                . ($receiptIndex + 1)
                . '. Original: '
                . (string)$import['original_name'];

            $materialInsert->execute([
                $jobId,
                $taskId,
                $description,
                $supplier !== '' ? $supplier : null,
                $gross,
                $gross,
                $paidBy,
                $cardLast4 !== '' ? $cardLast4 : null,
                $reimbursement,
                $treatment,
                $gst,
                $number !== '' ? $number : null,
                $date,
                $notes,
            ]);

            $materialId = (int)$pdo->lastInsertId();
            $materialIds[] = $materialId;
            $approvedGross += $gross;
            $approvedGst += $gst ?? 0.0;

            if (in_array($treatment, ['goodwill', 'rectification'], true)) {
                $complimentaryInsert->execute([
                    $jobId,
                    $description,
                    $gross,
                    '[material:' . $materialId . '] Imported from approved spreadsheet.',
                    $treatment,
                    $gross,
                    $description,
                ]);
            }

            $approvedLines[] = [
                'material_id' => $materialId,
                'description' => $description,
                'gross_amount' => $gross,
                'gst_amount' => $gst,
                'net_amount' => $gst === null ? null : round($gross - $gst, 2),
                'is_credit_or_return' => $lineIsCredit,
            ];
        }

        if (!$approvedLines) {
            continue;
        }

        $approved[] = [
            'source_index' => $receiptIndex,
            'source_label' => sri_text($source['source_label'] ?? '', 255),
            'supplier' => $supplier,
            'receipt_number' => $number,
            'purchase_date' => $date,
            'paid_by' => $paidBy,
            'payment_card_last4' => $cardLast4 !== '' ? $cardLast4 : null,
            'reimbursement_status' => $reimbursement,
            'financial_treatment' => $treatment,
            'is_credit_or_return' => $isCredit,
            'lines' => $approvedLines,
        ];
    }

    if (!$materialIds) {
        throw new RuntimeException(
            'Select at least one receipt and one purchase line to approve.'
        );
    }

    $approvalRecord = [
        'approved_at' => date(DATE_ATOM),
        'approved_by' => 'Mike / admin',
        'approved_material_count' => count($materialIds),
        'approved_gross_total' => round($approvedGross, 2),
        'approved_gst_total' => round($approvedGst, 2),
        'receipts' => $approved,
    ];

    $update = $pdo->prepare(
        "UPDATE work_receipt_imports
         SET status='applied',
             approved_json=?,
             applied_material_ids_json=?,
             reviewed_at=NOW(),
             reviewed_by='Mike / admin'
         WHERE id=? AND job_id=? AND status='ready'"
    );
    $update->execute([
        json_encode($approvalRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        json_encode($materialIds),
        $importId,
        $jobId,
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException('The import changed before approval. Nothing was applied.');
    }

    $pdo->commit();

    header(
        'Location: ../../admin/work/receipt_import_review.php?id='
        . $jobId
        . '&applied_import='
        . $importId
        . '&materials_added='
        . count($materialIds)
    );
    exit;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sri_fail($error->getMessage(), 500);
}
