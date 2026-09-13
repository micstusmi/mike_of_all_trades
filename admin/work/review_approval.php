<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
require_once __DIR__ . '/../../includes/work_approvals.php';

$approvalId = (int)(
    $_GET['id']
    ?? 0
);

if ($approvalId <= 0) {
    http_response_code(400);
    exit('Invalid approval request.');
}

$q = $pdo->prepare("
    SELECT
        a.*,
        j.customer_name,
        j.customer_phone,
        j.agreement_signed_at,
        j.agreement_name,
        j.public_token,
        j.agreed_hourly_rate
    FROM work_approval_requests a
    JOIN work_jobs j
      ON j.id=a.job_id
    WHERE a.id=?
    LIMIT 1
");

$q->execute([
    $approvalId
]);

$approval =
    $q->fetch(PDO::FETCH_ASSOC);

if (!$approval) {
    http_response_code(404);
    exit('Approval request not found.');
}

$defaultSms =
    wt_build_approval_sms(
        $approval
    );

$isDraft =
    (
        $approval['status']
        ?? ''
    ) === 'draft';

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>
<title>
    Review Approval #<?=(int)$approvalId?>
</title>

<style>
body{
    margin:0;
    background:#f4f6f8;
    color:#17202a;
    font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif
}
.wrap{
    max-width:850px;
    margin:auto;
    padding:20px
}
.card{
    background:#fff;
    border-radius:14px;
    padding:18px;
    margin:14px 0;
    box-shadow:0 2px 10px #0001
}
textarea{
    width:100%;
    box-sizing:border-box;
    min-height:220px;
    padding:12px;
    font:inherit;
    border:1px solid #bcc7cf;
    border-radius:9px
}
.btn{
    display:inline-block;
    background:#17202a;
    color:#fff;
    border:0;
    padding:11px 15px;
    border-radius:9px;
    font-weight:850;
    cursor:pointer;
    text-decoration:none
}
.btn-send{
    background:#176b36
}
.small{
    color:#61707a;
    font-size:13px
}
.warning{
    background:#fff4d6;
    border:1px solid #dfb64e;
    border-radius:10px;
    padding:12px
}
.good{
    background:#eaf7ee;
    border:1px solid #8bc79c;
    border-radius:10px;
    padding:12px
}
pre{
    white-space:pre-wrap;
    overflow-wrap:anywhere
}
</style>
</head>

<body>

<div class="wrap">

<p>
    <a
        href="manage_job.php?id=<?=(int)$approval['job_id']?>#customer-approvals"
    >
        ← Back to job
    </a>
</p>

<h1>
    Approval Request #<?=(int)$approvalId?>
</h1>

<div class="card">

    <h2>
        <?=wt_html(
            (string)$approval['subject']
        )?>
    </h2>

    <p>
        <b>Customer:</b>
        <?=wt_html(
            (string)$approval['customer_name']
        )?>
        <br>

        <b>Phone:</b>
        <?=wt_html(
            (string)$approval['customer_phone']
        )?>
        <br>

        <b>Status:</b>
        <?=wt_html(
            wt_approval_status_label(
                (string)$approval['status']
            )
        )?>
    </p>

    <?php if (
        empty(
            $approval['agreement_signed_at']
        )
    ): ?>
        <div class="warning">
            <b>⚠ Main job agreement is not signed.</b>
            <br>
            A variation can be discussed, but approval of this
            request does not replace the main job agreement
            signature.
        </div>
    <?php else: ?>
        <div class="good">
            ✓ Main job agreement signed
            <?=wt_html(
                date(
                    'j M Y, g:i a',
                    strtotime(
                        (string)$approval[
                            'agreement_signed_at'
                        ]
                    )
                )
            )?>
            <?php if (
                !empty(
                    $approval['agreement_name']
                )
            ): ?>
                by
                <?=wt_html(
                    (string)$approval[
                        'agreement_name'
                    ]
                )?>
            <?php endif; ?>.
        </div>
    <?php endif; ?>

</div>


<div class="card">

    <h2>Exact outgoing SMS</h2>

    <?php if ($isDraft): ?>

        <p class="small">
            Nothing has been sent yet. Edit this message exactly
            as you want the customer to receive it.
            Keep the request number in the YES / NO / SNOOZE
            commands so replies can be matched safely.
        </p>

        <form
            method="post"
            action="../../api/work/send_approval_request.php"
        >

            <input
                type="hidden"
                name="approval_id"
                value="<?=(int)$approvalId?>"
            >

            <textarea
                name="sms_message"
                required
            ><?=wt_html($defaultSms)?></textarea>

            <p class="small">
                The exact text above will be preserved in the
                approval audit history.
            </p>

            <button
                type="submit"
                class="btn btn-send"
                onclick="
                    return confirm(
                        'Send this exact approval SMS to the customer now?'
                    );
                "
            >
                📱 SEND THIS EXACT SMS
            </button>

        </form>

    <?php else: ?>

        <div class="warning">
            This request is no longer a draft and cannot be
            sent as a new initial request from this screen.
        </div>

        <pre><?=wt_html($defaultSms)?></pre>

    <?php endif; ?>

</div>

</div>

</body>
</html>
