<?php
declare(strict_types=1);

require_once __DIR__ . '/work_receipts.php';

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

function wri_storage_dir(int $jobId): string
{
    return dirname(__DIR__)
        . '/storage/private/work_receipt_imports/job_'
        . $jobId;
}

function wri_safe_original_name(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));

    return mb_substr(
        preg_replace('/[^A-Za-z0-9._ -]+/u', '_', $name)
            ?: 'receipt-spreadsheet',
        0,
        240
    );
}

function wri_store_upload(
    PDO $pdo,
    int $jobId,
    ?int $taskId,
    ?int $sessionId,
    array $file
): array {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'The spreadsheet upload failed with code ' . $error . '.'
        );
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $original = wri_safe_original_name((string)($file['name'] ?? ''));
    $size = (int)($file['size'] ?? 0);
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('The spreadsheet upload was not received.');
    }

    if (!in_array($extension, ['xlsx', 'csv'], true)) {
        throw new RuntimeException('Choose an XLSX or CSV spreadsheet.');
    }

    if ($size < 1 || $size > 25 * 1024 * 1024) {
        throw new RuntimeException(
            'The spreadsheet must be no larger than 25 MB.'
        );
    }

    if (
        !class_exists(
            \PhpOffice\PhpSpreadsheet\IOFactory::class
        )
    ) {
        throw new RuntimeException(
            'PhpSpreadsheet is not installed on this server.'
        );
    }

    $mime = $extension === 'xlsx'
        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        : 'text/csv';

    $directory = wri_storage_dir($jobId);

    if (
        !is_dir($directory)
        && !mkdir($directory, 0770, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException(
            'Private spreadsheet storage could not be created.'
        );
    }

    $storedName = 'receipt_import_'
        . date('Ymd_His')
        . '_'
        . bin2hex(random_bytes(8))
        . '.'
        . $extension;

    $absolute = $directory . '/' . $storedName;

    if (!move_uploaded_file($tmp, $absolute)) {
        throw new RuntimeException(
            'The spreadsheet could not be saved privately.'
        );
    }

    $sha = hash_file('sha256', $absolute) ?: '';

    $duplicate = $pdo->prepare(
        'SELECT id
         FROM work_receipt_imports
         WHERE job_id=?
           AND sha256=?
         LIMIT 1'
    );
    $duplicate->execute([$jobId, $sha]);
    $existingId = (int)$duplicate->fetchColumn();

    if ($existingId > 0) {
        @unlink($absolute);

        return [
            'id' => $existingId,
            'duplicate' => true,
        ];
    }

    $relative = 'storage/private/work_receipt_imports/job_'
        . $jobId
        . '/'
        . $storedName;

    $insert = $pdo->prepare(
        "INSERT INTO work_receipt_imports(
            job_id,
            task_id,
            session_id,
            source_type,
            original_name,
            relative_path,
            mime_type,
            file_size,
            sha256,
            status
        ) VALUES(?,?,?,'spreadsheet',?,?,?,?,?,'processing')"
    );

    $insert->execute([
        $jobId,
        $taskId,
        $sessionId,
        $original,
        $relative,
        $mime,
        (int)(filesize($absolute) ?: $size),
        $sha,
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'duplicate' => false,
    ];
}

function wri_extract_spreadsheet(string $path): array
{
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($path);

    $sheets = [];
    $totalRows = 0;
    $totalCharacters = 0;

    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
        $sheetRows = [];
        $structuredRows = [];

        foreach (
            $worksheet->toArray(null, true, true, true)
            as $rowNumber => $row
        ) {
            $values = [];
            $cells = [];

            foreach ($row as $column => $value) {
                if ($value === null) {
                    continue;
                }

                $clean = trim((string)$value);

                if ($clean !== '') {
                    $values[] = $column . '=' . $clean;
                    $cells[$column] = $clean;
                }
            }

            if (!$values) {
                continue;
            }

            $line = 'Row ' . $rowNumber . ': ' . implode(' | ', $values);
            $totalCharacters += mb_strlen($line);

            if (++$totalRows > 2500 || $totalCharacters > 300000) {
                throw new RuntimeException(
                    'The spreadsheet contains too much data for one import.'
                );
            }

            $sheetRows[] = $line;
            $structuredRows[] = [
                'row_number' => (int)$rowNumber,
                'cells' => $cells,
            ];
        }

        if ($sheetRows) {
            $sheets[] = [
                'title' => (string)$worksheet->getTitle(),
                'rows' => $sheetRows,
                'structured_rows' => $structuredRows,
            ];
        }
    }

    $spreadsheet->disconnectWorksheets();

    if (!$sheets) {
        throw new RuntimeException(
            'The spreadsheet did not contain readable rows.'
        );
    }

    $parts = [];

    foreach ($sheets as $sheet) {
        $parts[] = "SHEET: " . $sheet['title']
            . "\n"
            . implode("\n", $sheet['rows']);
    }

    return [
        'sheet_count' => count($sheets),
        'row_count' => $totalRows,
        'text' => implode("\n\n", $parts),
        'sheets' => $sheets,
    ];
}

function wri_parse_money(mixed $value): ?float
{
    if (is_int($value) || is_float($value)) {
        return round((float)$value, 2);
    }

    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }

    $negative = preg_match('/(?:^|\s)(?:credit|refund|return)(?:\s|$)/iu', $text)
        || str_contains($text, '−')
        || str_contains($text, '(');
    $normalised = str_replace(
        ["\u{00A0}", '−', ',', '$', '(', ')'],
        ['', '-', '', '', '', ''],
        $text
    );

    if (!preg_match('/-?\d+(?:\.\d+)?/', $normalised, $match)) {
        return null;
    }

    $amount = round((float)$match[0], 2);
    return $negative ? -abs($amount) : $amount;
}

function wri_number(mixed $value): ?float
{
    return wri_parse_money($value);
}

function wri_parse_date(string $value): ?string
{
    $value = trim(preg_replace('/\b(\d{1,2})(?:st|nd|rd|th)\b/i', '$1', $value));
    if ($value === '') {
        return null;
    }

    if (!preg_match('/\b\d{4}\b/', $value)) {
        $value .= ' ' . date('Y');
    }

    foreach (['j F Y', 'd F Y', 'j M Y', 'd M Y', 'Y-m-d', 'd/m/Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}

function wri_receipt_summary(string $text): ?array
{
    $parts = preg_split('/\s+[—–-]\s+/u', trim($text));
    if (!is_array($parts) || count($parts) < 3) {
        return null;
    }

    $date = wri_parse_date(trim((string)$parts[0]));
    $supplier = trim((string)$parts[1]);
    $totalText = implode(' ', array_slice($parts, 2));
    $gross = wri_parse_money($totalText);

    if ($supplier === '' || $gross === null) {
        return null;
    }

    if (preg_match('/\b(?:credit|refund)\b/i', $totalText)) {
        $gross = -abs($gross);
    }

    return [
        'purchase_date' => $date,
        'supplier' => mb_substr($supplier, 0, 190),
        'gross_total' => $gross,
        'is_credit_or_return' => $gross < 0,
        'payment_card_last4' => wri_card_last4($text),
        'paid_by' => wri_paid_by_text($text),
    ];
}

function wri_card_last4(string $text): ?string
{
    if (preg_match(
        '/(?:ending(?:\s+in)?|last\s*4|card|x{2,}|\*{2,})\D{0,12}(\d{4})\b/i',
        $text,
        $match
    )) {
        return $match[1];
    }

    return null;
}

function wri_paid_by_text(string $text): string
{
    if (preg_match('/\b(?:customer|client|owner)\b/i', $text)) {
        return 'customer';
    }

    if (preg_match('/\bmike\b/i', $text)) {
        return 'mike';
    }

    return 'unknown';
}

function wri_flat_receipts(array $extracted): array
{
    $groups = [];
    $warnings = [];
    $aliases = [
        'description' => ['description', 'item', 'material', 'purchase', 'line item'],
        'amount' => ['amount', 'gross', 'gross amount', 'total', 'cost', 'incl gst'],
        'gst' => ['gst', 'gst amount', 'tax'],
        'supplier' => ['supplier', 'store', 'merchant', 'vendor'],
        'date' => ['date', 'purchase date', 'transaction date'],
        'receipt' => ['receipt', 'receipt number', 'invoice', 'invoice number', 'reference'],
        'paid_by' => ['paid by', 'payer', 'card owner'],
        'card_last4' => ['card last 4', 'last 4', 'card ending', 'card last four'],
        'job_use' => ['job use', 'use', 'notes', 'purpose'],
    ];

    foreach ((array)($extracted['sheets'] ?? []) as $sheet) {
        $map = [];
        $headerFound = false;

        foreach ((array)($sheet['structured_rows'] ?? []) as $row) {
            $cells = (array)($row['cells'] ?? []);

            if (!$headerFound) {
                foreach ($cells as $column => $value) {
                    $header = mb_strtolower(trim((string)$value));
                    foreach ($aliases as $field => $names) {
                        if (in_array($header, $names, true)) {
                            $map[$field] = (string)$column;
                        }
                    }
                }

                if (isset($map['description'], $map['amount'])) {
                    $headerFound = true;
                } else {
                    $map = [];
                }
                continue;
            }

            $description = trim((string)($cells[$map['description']] ?? ''));
            $amount = wri_parse_money($cells[$map['amount']] ?? null);
            if ($description === '' || $amount === null) {
                continue;
            }

            $receiptNumber = trim((string)($cells[$map['receipt'] ?? ''] ?? ''));
            $supplier = trim((string)($cells[$map['supplier'] ?? ''] ?? ''));
            $date = wri_parse_date(trim((string)($cells[$map['date'] ?? ''] ?? '')));
            $paidText = trim((string)($cells[$map['paid_by'] ?? ''] ?? ''));
            $paidBy = wri_paid_by_text($paidText);
            $cardText = trim((string)($cells[$map['card_last4'] ?? ''] ?? ''));
            $cardLast4 = preg_match('/^\d{4}$/', $cardText)
                ? $cardText
                : wri_card_last4($cardText);
            $key = $receiptNumber !== ''
                ? 'receipt:' . mb_strtolower($receiptNumber)
                : 'row:' . (int)($row['row_number'] ?? 0);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'source_label' => $receiptNumber !== ''
                        ? 'Receipt ' . $receiptNumber
                        : 'Spreadsheet row ' . (int)($row['row_number'] ?? 0),
                    'supplier' => mb_substr($supplier, 0, 190),
                    'receipt_number' => mb_substr($receiptNumber, 0, 120),
                    'purchase_date' => $date,
                    'gross_total' => 0.0,
                    'gst_total' => null,
                    'net_total' => null,
                    'paid_by' => $paidBy,
                    'payment_card_last4' => $cardLast4,
                    'is_credit_or_return' => false,
                    'confidence_note' => '',
                    'lines' => [],
                ];
            }

            $gst = wri_parse_money($cells[$map['gst'] ?? ''] ?? null);
            $accounting = preg_match('/\b(?:gift card|payment|tender|cash|change)\b/i', $description) === 1;
            $isCredit = $amount < 0
                || preg_match('/\b(?:return|credit|refund)\b/i', $description) === 1;
            if (!$accounting) {
                $groups[$key]['gross_total'] = round(
                    (float)$groups[$key]['gross_total'] + $amount,
                    2
                );
            }
            if ($gst !== null && !$accounting) {
                $groups[$key]['gst_total'] = round(
                    (float)($groups[$key]['gst_total'] ?? 0) + $gst,
                    2
                );
            }
            $groups[$key]['is_credit_or_return'] =
                !empty($groups[$key]['is_credit_or_return']) || $isCredit;
            $groups[$key]['lines'][] = [
                'description' => mb_substr($description, 0, 255),
                'job_use' => mb_substr(
                    trim((string)($cells[$map['job_use'] ?? ''] ?? '')),
                    0,
                    1000
                ),
                'quantity' => null,
                'unit_price' => null,
                'gross_amount' => $amount,
                'gst_amount' => $gst,
                'net_amount' => $gst === null ? null : round($amount - $gst, 2),
                'is_credit_or_return' => $isCredit,
                'include_default' => !$accounting,
                'exclusion_reason' => $accounting ? 'Accounting/payment row' : '',
            ];
        }
    }

    if (!$groups) {
        $warnings[] = 'No supported receipt-section layout or flat table with description and amount columns was found.';
    }

    return [array_values($groups), $warnings];
}

function wri_direct_proposal(array $extracted): array
{
    $receipts = [];
    $warnings = [];
    $labels = [];
    $current = null;

    $finish = static function () use (&$current, &$receipts, &$warnings): void {
        if (!is_array($current)) {
            return;
        }

        if (!$current['lines']) {
            $warnings[] = $current['source_label'] . ' contains no readable purchase lines.';
        }

        $lineTotal = 0.0;
        foreach ($current['lines'] as $line) {
            if (!empty($line['include_default'])) {
                $lineTotal += (float)($line['gross_amount'] ?? 0);
            }
        }

        if (
            $current['gross_total'] !== null
            && abs($lineTotal - (float)$current['gross_total']) > 0.02
        ) {
            $warnings[] = $current['source_label']
                . ' line total $'
                . number_format($lineTotal, 2)
                . ' differs from its stated receipt total $'
                . number_format((float)$current['gross_total'], 2)
                . '.';
        }

        $receipts[] = $current;
        $current = null;
    };

    foreach ((array)($extracted['sheets'] ?? []) as $sheet) {
        foreach ((array)($sheet['structured_rows'] ?? []) as $row) {
            $cells = (array)($row['cells'] ?? []);
            $a = trim((string)($cells['A'] ?? ''));
            $b = $cells['B'] ?? null;
            $c = trim((string)($cells['C'] ?? ''));

            if (preg_match('/^receipt\s+(?!line\s+item\b)(.+?)[.]?$/i', $a, $match)) {
                $finish();
                $label = 'Receipt ' . rtrim(trim((string)$match[1]), '.');
                $normalised = mb_strtolower($label);
                if (isset($labels[$normalised])) {
                    $warnings[] = $label . ' is repeated as a label. Both sections were retained for review.';
                }
                $labels[$normalised] = true;
                $current = [
                    'source_label' => $label,
                    'supplier' => '',
                    'receipt_number' => $label,
                    'purchase_date' => null,
                    'gross_total' => null,
                    'gst_total' => null,
                    'net_total' => null,
                    'paid_by' => 'unknown',
                    'payment_card_last4' => null,
                    'is_credit_or_return' => false,
                    'confidence_note' => '',
                    'lines' => [],
                ];
                continue;
            }

            if (!is_array($current)) {
                continue;
            }

            $rowText = implode(' ', array_map('strval', $cells));
            if ($current['payment_card_last4'] === null) {
                $current['payment_card_last4'] = wri_card_last4($rowText);
            }
            if ($current['paid_by'] === 'unknown') {
                $current['paid_by'] = wri_paid_by_text($rowText);
            }

            $summary = wri_receipt_summary($a);
            if ($summary !== null && !$current['lines']) {
                $current = array_replace($current, $summary);
                continue;
            }

            if (
                $a === ''
                || preg_match('/^receipt line item$/i', $a)
            ) {
                continue;
            }

            $amount = wri_parse_money($b);
            if ($amount === null) {
                continue;
            }

            $derived = preg_match('/^(?:net result|subtotal|total)$/i', $a) === 1;
            $accounting = preg_match(
                '/\b(?:gift card|payment|tender|cash|change)\b/i',
                $a
            ) === 1;
            $isCredit = $amount < 0
                || preg_match('/\b(?:return|credit|refund)\b/i', $a) === 1;

            $current['lines'][] = [
                'description' => mb_substr($a, 0, 255),
                'job_use' => mb_substr($c, 0, 1000),
                'quantity' => null,
                'unit_price' => null,
                'gross_amount' => $amount,
                'gst_amount' => null,
                'net_amount' => null,
                'is_credit_or_return' => $isCredit,
                'include_default' => !$derived && !$accounting,
                'exclusion_reason' => $derived
                    ? 'Derived total row'
                    : ($accounting ? 'Accounting/payment row' : ''),
            ];
        }
    }

    $finish();

    if (!$receipts) {
        [$receipts, $flatWarnings] = wri_flat_receipts($extracted);
        $warnings = array_merge($warnings, $flatWarnings);
    }

    if (!$receipts) {
        throw new RuntimeException(
            'No importable rows were found. Use receipt sections or a table containing Description and Amount columns.'
        );
    }

    $spreadsheetGross = 0.0;
    $hasTotal = false;
    foreach ($receipts as $receipt) {
        if ($receipt['gross_total'] !== null) {
            $spreadsheetGross += (float)$receipt['gross_total'];
            $hasTotal = true;
        }
    }

    return [
        'spreadsheet_gross_total' => $hasTotal ? round($spreadsheetGross, 2) : null,
        'confidence_note' => 'Imported directly from the spreadsheet structure without AI. Review unticked accounting rows, receipt totals, GST and duplicate-label warnings before approval.',
        'reconciliation_warnings' => array_values(array_unique($warnings)),
        'parser_mode' => 'direct',
        'receipts' => $receipts,
    ];
}

function wri_progress(
    PDO $pdo,
    int $importId,
    int $percent,
    string $stage,
    string $detail
): void {
    $update = $pdo->prepare(
        "UPDATE work_receipt_imports
         SET progress_percent=?,processing_stage=?,processing_detail=?,
             heartbeat_at=NOW(),started_at=COALESCE(started_at,NOW())
         WHERE id=? AND status='processing'"
    );
    $update->execute([
        max(0, min(100, $percent)),
        mb_substr($stage, 0, 100),
        mb_substr($detail, 0, 255),
        $importId,
    ]);
}

function wri_process_import(PDO $pdo, array $import): void
{
    $importId = (int)$import['id'];
    $path = dirname(__DIR__)
        . '/'
        . ltrim((string)$import['relative_path'], '/');

    if (!is_file($path)) {
        throw new RuntimeException('The privately stored spreadsheet is missing.');
    }

    wri_progress($pdo, $importId, 10, 'reading', 'Opening the spreadsheet.');
    $extracted = wri_extract_spreadsheet($path);
    wri_progress(
        $pdo,
        $importId,
        45,
        'mapping',
        'Mapping ' . (int)$extracted['row_count'] . ' readable rows.'
    );
    $proposal = wri_direct_proposal($extracted);

    $receiptCount = count((array)$proposal['receipts']);
    $lineCount = 0;
    $parsedGross = 0.0;
    foreach ((array)$proposal['receipts'] as $receipt) {
        foreach ((array)($receipt['lines'] ?? []) as $line) {
            $lineCount++;
            if (empty($line['exclusion_reason'])) {
                $parsedGross += (float)($line['gross_amount'] ?? 0);
            }
        }
    }

    $spreadsheetGross = wri_number($proposal['spreadsheet_gross_total'] ?? null);
    $difference = $spreadsheetGross === null
        ? null
        : round($spreadsheetGross - $parsedGross, 2);
    $warnings = (array)($proposal['reconciliation_warnings'] ?? []);

    wri_progress($pdo, $importId, 85, 'checking', 'Checking receipt and line totals.');

    $update = $pdo->prepare(
        "UPDATE work_receipt_imports
         SET status='ready',
             sheet_count=?,
             receipt_count=?,
             line_count=?,
             spreadsheet_gross_total=?,
             parsed_gross_total=?,
             parsed_gst_total=?,
             parsed_net_total=?,
             reconciliation_difference=?,
             confidence_note=?,
             reconciliation_warnings=?,
             extracted_text=?,
             proposal_json=?,
             error_message=NULL,
             progress_percent=100,
             processing_stage='ready',
             processing_detail='Ready for review and approval.',
             heartbeat_at=NOW(),
             completed_at=NOW()
         WHERE id=?"
    );

    $update->execute([
        (int)$extracted['sheet_count'],
        $receiptCount,
        $lineCount,
        $spreadsheetGross,
        round($parsedGross, 2),
        null,
        null,
        $difference,
        mb_substr(
            trim((string)($proposal['confidence_note'] ?? '')),
            0,
            4000
        ) ?: null,
        $warnings
            ? json_encode(
                $warnings,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
            : null,
        $extracted['text'],
        json_encode(
            $proposal,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ),
        $importId,
    ]);
}

function wri_start_worker(?int $jobId = null): bool
{
    if (!function_exists('exec')) {
        return false;
    }

    $script = dirname(__DIR__)
        . '/tools/process_receipt_spreadsheet_queue.php';

    $defaultPhp = PHP_BINDIR
        . DIRECTORY_SEPARATOR
        . (PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php');

    $php = (string)wt_env(
        'WORKTRACKER_PHP_BIN',
        $defaultPhp
    );

    if (!is_file($script) || !is_file($php)) {
        return false;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $command = 'start /B "" '
            . escapeshellarg($php)
            . ' '
            . escapeshellarg($script)
            . ($jobId ? ' ' . (int)$jobId : '')
            . ' > NUL 2>&1';
    } else {
        if (!is_executable($php)) {
            return false;
        }

        $command = escapeshellarg($php)
            . ' '
            . escapeshellarg($script)
            . ($jobId ? ' ' . (int)$jobId : '')
            . ' > /dev/null 2>&1 &';
    }

    @exec($command);

    return true;
}
