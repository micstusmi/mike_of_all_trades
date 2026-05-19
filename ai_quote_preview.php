<?php
require_once __DIR__ . '/includes/db.php';

$token = trim($_GET['token'] ?? '');
$estimatedHours = trim($_GET['hours'] ?? '4');
$estimatedPrice = trim($_GET['price'] ?? '400');
$service = trim($_GET['service'] ?? '');
$suburb = trim($_GET['suburb'] ?? '');

$conversation = null;

if ($token) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM ai_conversations
        WHERE conversation_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
}

include __DIR__ . '/includes/header.php';
?>

<main class="py-5 bg-dark text-white">
    <div class="container" style="max-width:900px;">

        <h2 class="text-info mb-4">AI Quote Preview</h2>

        <?php if (!$conversation): ?>

            <div class="alert alert-warning">
                AI conversation not found.
            </div>

        <?php else: ?>

            <div class="card bg-secondary text-white p-4 rounded-4 border-0 shadow-lg">

                <h4 class="text-info">Quote Draft</h4>

                <p class="text-warning small">
                    Estimated pricing and timeframes are a guide only. Final pricing may vary depending on materials, access, existing conditions, and any unexpected issues discovered during the job.
                </p>

                <hr>

                <label class="form-label fw-bold">Customer Email</label>
                <input
                    id="customerEmail"
                    class="form-control mb-3"
                    value="<?= htmlspecialchars($conversation['customer_email'] ?? '') ?>"
                    placeholder="Customer email"
                >

                <label class="form-label fw-bold">Job Summary / Quote Notes</label>
                <textarea
                    id="quoteNotes"
                    class="form-control mb-3"
                    rows="12"
                ><?= htmlspecialchars($conversation['conversation_text']) ?></textarea>

                <label class="form-label fw-bold">Estimated Labour Hours</label>
                <input
                    id="estimatedHours"
                    type="number"
                    step="0.5"
                    class="form-control mb-3"
                    value="<?= htmlspecialchars($estimatedHours) ?>"
                >

                <label class="form-label fw-bold">Estimated Price</label>
                <input
                    id="estimatedPrice"
                    type="number"
                    class="form-control mb-3"
                    value="<?= htmlspecialchars($estimatedPrice) ?>"
                >

                <input
                    type="hidden"
                    id="conversationToken"
                    value="<?= htmlspecialchars($token) ?>"
                >

                <div class="d-grid gap-2">
                    <button
                        class="btn btn-warning rounded-pill fw-bold"
                        type="button"
                        onclick="sendAiQuote()"
                    >
                        Email quote now with chat details
                    </button>

                    <a
                        class="btn btn-outline-light rounded-pill"
                        href="view_ai_conversation.php?token=<?= urlencode($token) ?>"
                        target="_blank"
                    >
                        View full AI conversation
                    </a>
                </div>

                <div id="quoteStatus" class="mt-3 small"></div>

            </div>

        <?php endif; ?>

    </div>
</main>

<script>
function sendAiQuote(){
    const fd = new FormData();

    fd.append('email', document.getElementById('customerEmail').value);
    fd.append('notes', document.getElementById('quoteNotes').value);
    fd.append('hours', document.getElementById('estimatedHours').value);
    fd.append('price', document.getElementById('estimatedPrice').value);
    fd.append('conversation_token', document.getElementById('conversationToken').value);

    const status = document.getElementById('quoteStatus');
    status.innerText = 'Sending quote...';

    fetch('generate_quote_request.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        status.innerText = res.success
            ? 'Quote sent successfully.'
            : (res.message || 'Quote failed.');
    })
    .catch(err => {
        status.innerText = 'Quote failed: ' + err.message;
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>