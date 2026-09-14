<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$id = (int)($_POST['job_id'] ?? 0);

if ($id <= 0) {
    exit('Invalid job.');
}

wt_job($pdo, $id);

$description = trim((string)($_POST['description'] ?? ''));
$supplier = trim((string)($_POST['supplier'] ?? ''));
$cost = (float)($_POST['cost'] ?? 0);
$paidBy = (string)($_POST['paid_by'] ?? 'mike');

if (!in_array($paidBy, ['mike','customer','other'], true)) {
    $paidBy = 'mike';
}

$financialTreatment =
    (string)($_POST['financial_treatment'] ?? 'charge_customer');

if (!in_array($financialTreatment, [
    'charge_customer',
    'included_in_price',
    'goodwill',
    'rectification',
], true)) {
    $financialTreatment = 'charge_customer';
}

if ($description === '') {
    exit('Material description required.');
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("
        INSERT INTO work_materials
        (
            job_id,
            description,
            supplier,
            cost,
            paid_by,
            financial_treatment
        )
        VALUES
        (?,?,?,?,?,?)
    ");

    $stmt->execute([
        $id,
        $description,
        $supplier,
        $cost,
        $paidBy,
        $financialTreatment,
    ]);

    $mid = (int)$pdo->lastInsertId();

    if (in_array(
        $financialTreatment,
        ['goodwill','rectification'],
        true
    )) {
        $noCharge = $pdo->prepare("
            INSERT INTO work_complimentary_items
            (
                job_id,
                item_type,
                description,
                estimated_value,
                note,
                no_charge_reason,
                labour_hours,
                labour_value,
                material_value,
                material_details,
                updated_at
            )
            VALUES
            (
                ?,
                'material',
                ?,
                ?,
                ?,
                ?,
                NULL,
                0,
                ?,
                ?,
                NOW()
            )
        ");

        $noCharge->execute([
            $id,
            $description,
            $cost,
            '[material:' . $mid . '] Automatically linked from materials ledger.',
            $financialTreatment,
            $cost,
            $description,
        ]);
    }

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

header("Location: ../../admin/work/job.php?id=$id");
exit;
