<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$currentPrivatePage = basename(
    parse_url(
        $_SERVER['REQUEST_URI'] ?? '',
        PHP_URL_PATH
    )
);

$privateRole = $_SESSION['trip_role'] ?? 'viewer';
$privateName = $_SESSION['trip_member_name'] ?? 'Traveller';
$privateTripId = (int) ($_SESSION['trip_id'] ?? 0);

$pendingProposalCount = 0;

if (
    $privateRole === 'admin'
    && isset($pdo)
    && $pdo instanceof PDO
    && $privateTripId > 0
) {
    try {
        $pendingStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM trip_itinerary_proposals
            WHERE trip_id = ?
              AND status = 'pending'
        ");

        $pendingStmt->execute([$privateTripId]);

        $pendingProposalCount =
            (int) $pendingStmt->fetchColumn();

    } catch (Throwable $e) {
        /*
         * The navigation should still work if the proposal table has
         * not yet been created or the database is temporarily unavailable.
         */
        $pendingProposalCount = 0;
    }
}

if (!function_exists('privateNavActive')) {
    function privateNavActive(
        string $filename,
        string $currentPage
    ): string {
        return $filename === $currentPage
            ? ' private-nav-active'
            : '';
    }
}
?>

<nav class="private-trip-nav">

    <div class="container private-trip-nav-inner">

        <a
            class="private-trip-brand"
            href="planner.php"
        >
            <span>🏍️</span>

            <span>
                <strong>Thailand 2027</strong>
                <small>Private planner</small>
            </span>
        </a>

        <button
            class="private-nav-toggle"
            type="button"
            aria-expanded="false"
            aria-controls="privateNavLinks"
            onclick="
                const links =
                    document.getElementById('privateNavLinks');

                const isOpen =
                    links.classList.toggle('private-nav-open');

                this.setAttribute(
                    'aria-expanded',
                    isOpen ? 'true' : 'false'
                );
            "
        >
            ☰ Menu
        </button>

        <div
            class="private-nav-links"
            id="privateNavLinks"
        >

            <a
                class="<?=
                    privateNavActive(
                        'planner.php',
                        $currentPrivatePage
                    )
                ?>"
                href="planner.php"
            >
                Planner
            </a>

            <a
                class="<?=
                    privateNavActive(
                        'itinerary_editor.php',
                        $currentPrivatePage
                    )
                ?>"
                href="itinerary_editor.php"
            >
                Itinerary
            </a>

            <a
                class="<?=
                    privateNavActive(
                        'attendance.php',
                        $currentPrivatePage
                    )
                ?>"
                href="attendance.php"
            >
                My attendance
            </a>

            <a
                class="<?=
                    privateNavActive(
                        'documents.php',
                        $currentPrivatePage
                    )
                ?>"
                href="documents.php"
            >
                Documents
            </a>

            <?php if ($privateRole === 'admin'): ?>

                <a
                    class="<?=
                        privateNavActive(
                            'members.php',
                            $currentPrivatePage
                        )
                    ?>"
                    href="members.php"
                >
                    Travellers
                </a>

                <a
                    class="private-review-link<?=
                        privateNavActive(
                            'itinerary_reviews.php',
                            $currentPrivatePage
                        )
                    ?>"
                    href="itinerary_reviews.php"
                >
                    Approvals

                    <?php if ($pendingProposalCount > 0): ?>
                        <span class="private-nav-badge">
                            <?= $pendingProposalCount ?>
                        </span>
                    <?php endif; ?>
                </a>

            <?php endif; ?>

            <a href="index.php" target="_blank">
                Public overview ↗
            </a>

        </div>

        <div class="private-nav-account">

            <span>
                Hi, <?= htmlspecialchars(
                    $privateName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>

            <a
                class="private-nav-logout"
                href="logout.php"
            >
                Log out
            </a>

        </div>

    </div>

</nav>
