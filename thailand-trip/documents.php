<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/trip_auth.php';
requireTripLogin();

$tripId = (int) $_SESSION['trip_id'];

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

$weeksStmt = $pdo->prepare("
    SELECT id, week_number, title
    FROM trip_weeks
    WHERE trip_id = ?
    ORDER BY week_number
");

$weeksStmt->execute([$tripId]);
$weeks = $weeksStmt->fetchAll(PDO::FETCH_ASSOC);

$documentsStmt = $pdo->prepare("
    SELECT
        td.*,
        tw.week_number,
        tm.name AS uploaded_by_name
    FROM trip_documents td
    LEFT JOIN trip_weeks tw
        ON tw.id = td.trip_week_id
    LEFT JOIN trip_members tm
        ON tm.id = td.uploaded_by_member_id
    WHERE td.trip_id = ?
    ORDER BY td.created_at DESC
");

$documentsStmt->execute([$tripId]);
$documents = $documentsStmt->fetchAll(PDO::FETCH_ASSOC);

$message = $_SESSION['document_message'] ?? '';
$error = $_SESSION['document_error'] ?? '';

unset(
    $_SESSION['document_message'],
    $_SESSION['document_error']
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta name="robots" content="noindex, nofollow">

    <title>Trip Documents</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/planner.css">

    <style>
        .documents-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 22px;
        }

        .document-card {
            margin-bottom: 12px;
            padding: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            border: 1px solid #dce4e8;
            border-radius: 14px;
            background: white;
        }

        .document-card h3 {
            margin-bottom: 4px;
            font-size: 1.08rem;
        }

        .document-meta {
            color: #667681;
            font-size: 0.84rem;
        }

        @media (max-width: 850px) {
            .documents-layout {
                grid-template-columns: 1fr;
            }

            .document-card {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<?php require __DIR__ . '/includes/private_nav.php'; ?>



<header class="planner-header">
    <div class="container">
        <span>PRIVATE FILE STORAGE</span>
        <h1>Bookings and documents</h1>
        <p>
            Upload confirmations, receipts, route files and travel documents.
        </p>
    </div>
</header>

<main class="container py-4">

    <?php if ($message): ?>
        <div class="alert alert-success">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="documents-layout">

        <section class="planner-form-card">

            <span class="section-label">UPLOAD</span>
            <h2>Add document</h2>

            <form
                action="actions/upload_document.php"
                method="post"
                enctype="multipart/form-data"
            >

                <label>
                    Document title
                    <input
                        type="text"
                        name="title"
                        maxlength="200"
                        required
                    >
                </label>

                <label>
                    Related week
                    <select name="trip_week_id">
                        <option value="">
                            General trip document
                        </option>

                        <?php foreach ($weeks as $week): ?>
                            <option value="<?= (int) $week['id'] ?>">
                                Week <?= (int) $week['week_number'] ?>:
                                <?= e($week['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Description
                    <textarea
                        name="description"
                        rows="4"
                        maxlength="2000"
                    ></textarea>
                </label>

                <label>
                    Choose file
                    <input
                        type="file"
                        name="document"
                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                        required
                    >
                </label>

                <p class="text-muted small">
                    PDF, JPG, PNG or WEBP. Maximum 10 MB.
                </p>

                <button class="btn btn-primary" type="submit">
                    Upload securely
                </button>

            </form>

        </section>

        <section>

            <span class="section-label">AVAILABLE FILES</span>
            <h2 class="mb-3">Group documents</h2>

            <?php if (!$documents): ?>
                <div class="alert alert-info">
                    No documents have been uploaded yet.
                </div>
            <?php endif; ?>

            <?php foreach ($documents as $document): ?>

                <article class="document-card">

                    <div>
                        <h3><?= e($document['title']) ?></h3>

                        <div class="document-meta">
                            <?= e($document['original_filename']) ?>

                            · <?= number_format(
                                (int) $document['file_size'] / 1024,
                                1
                            ) ?> KB

                            <?php if ($document['week_number']): ?>
                                · Week <?= (int) $document['week_number'] ?>
                            <?php endif; ?>

                            <?php if ($document['uploaded_by_name']): ?>
                                · Uploaded by
                                <?= e($document['uploaded_by_name']) ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($document['description']): ?>
                            <p class="mt-2 mb-0">
                                <?= nl2br(e($document['description'])) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <a
                        class="btn btn-outline-primary"
                        href="document.php?id=<?= (int) $document['id'] ?>"
                    >
                        Download
                    </a>

                </article>

            <?php endforeach; ?>

        </section>

    </div>

</main>

</body>
</html>
