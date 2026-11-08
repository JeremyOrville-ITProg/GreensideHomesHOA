<?php
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = $parts[0] ?? 'R';
    $last = $parts[count($parts) - 1] ?? $first;
    return mb_strtoupper(mb_substr($first, 0, 1) . (count($parts) > 1 ? mb_substr($last, 0, 1) : ''));
};
$relativeTime = static function (string $date): string {
    $seconds = max(0, time() - strtotime($date));
    if ($seconds < 3600) return max(1, (int) floor($seconds / 60)) . 'm ago';
    if ($seconds < 86400) return (int) floor($seconds / 3600) . 'h ago';
    if ($seconds < 604800) return (int) floor($seconds / 86400) . 'd ago';
    return date('M j', strtotime($date));
};
$categoryNames = ['concern' => 'Concern', 'question' => 'Question', 'suggestion' => 'Suggestion'];
$categoryCounts = ['concern' => 0, 'question' => 0, 'suggestion' => 0, 'announcement' => 0];
foreach ($posts as $post) {
    if ((bool) $post['is_announcement']) {
        $categoryCounts['announcement']++;
    } elseif (isset($categoryCounts[$post['category']])) {
        $categoryCounts[$post['category']]++;
    }
}
$unpaidTotal = 0.0;
$pendingTotal = 0.0;
$billCounts = ['due' => 0, 'overdue' => 0, 'pending' => 0, 'paid' => 0];
foreach ($fees as $fee) {
    $status = $fee['payment_status'];
    if ($status === 'rejected') {
        $status = $fee['due_date'] < date('Y-m-d') ? 'overdue' : 'due';
    }
    if (isset($billCounts[$status])) {
        $billCounts[$status]++;
    }
    if (in_array($status, ['due', 'overdue'], true)) {
        $unpaidTotal += (float) $fee['amount'];
    } elseif ($status === 'pending') {
        $pendingTotal += (float) $fee['amount'];
    }
}
$activePageTitle = [
    'home' => 'Community',
    'bills' => 'Bills & payments',
    'clearances' => 'Permits & clearances',
    'create' => 'Create a post',
    'alerts' => 'Alerts',
    'profile' => 'My profile',
    'admin' => 'HOA management',
][$page];
$flashMessage = $flashMessage ?: null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#13734c">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($activePageTitle) ?> | Greenside Homes</title>
    <link rel="icon" type="image/jpeg" href="Assets/logogreenside.jpg">
    <link rel="stylesheet" href="Assets/style.css">
</head>
<body>
    <aside class="sidebar" aria-label="Main navigation">
        <a class="brand" href="index.php" aria-label="Greenside Homes home">
            <span class="brand-mark" aria-hidden="true"><img src="Assets/logogreenside.jpg" alt=""></span>
            <span><strong>Greenside</strong><small>HOMES COMMUNITY</small></span>
        </a>
        <div class="community-label">RESIDENT PORTAL</div>
        <nav class="sidebar-nav">
            <button class="nav-link <?= $page === 'home' ? 'active' : '' ?>" type="button" data-page="home" aria-label="Community">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg><span>Community</span>
            </button>
            <button class="nav-link <?= $page === 'bills' ? 'active' : '' ?>" type="button" data-page="bills" aria-label="Bills and payments">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6"/></svg><span>Bills &amp; payments</span>
            </button>
            <button class="nav-link <?= $page === 'clearances' ? 'active' : '' ?>" type="button" data-page="clearances" aria-label="Permits & clearances">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span>Permits &amp; clearances</span>
            </button>
            <button class="nav-link <?= $page === 'alerts' ? 'active' : '' ?>" type="button" data-page="alerts" aria-label="Alerts">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                <span>Alerts</span><span class="nav-count <?= $unreadCount === 0 ? 'hidden' : '' ?>" id="nav-alert-count"><?= $unreadCount ?></span>
            </button>
            <button class="nav-link <?= $page === 'profile' ? 'active' : '' ?>" type="button" data-page="profile" aria-label="My profile">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span>My profile</span>
            </button>
            <?php if (in_array($user['role'], ['admin', 'treasurer'], true)): ?>
                <button class="nav-link <?= $page === 'admin' ? 'active' : '' ?>" type="button" data-page="admin" aria-label="HOA management">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16M6 17V9m6 8V4m6 13v-5"/></svg><span>HOA management</span>
                </button>
            <?php endif; ?>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-help"><span class="help-icon" aria-hidden="true">?</span><div><strong>Need a hand?</strong><small>Contact your HOA admin</small></div></div>
            <button class="resident-mini" type="button" data-page="profile">
                <span class="avatar avatar-small"><?= e($initials($user['full_name'])) ?></span>
                <span class="resident-mini-copy"><strong><?= e($user['full_name']) ?></strong><small><?= e($user['house_no'] ?: ucfirst($user['role'])) ?></small></span>
                <span class="mini-chevron" aria-hidden="true">···</span>
            </button>
        </div>
    </aside>

    <div class="app-shell">
        <header class="topbar">
            <div class="mobile-brand"><span class="brand-mark" aria-hidden="true"><img src="Assets/logogreenside.jpg" alt=""></span><strong>Greenside Homes</strong></div>
            <div class="breadcrumb"><span>Greenside Homes</span><span class="breadcrumb-divider">/</span><strong id="page-title"><?= e($activePageTitle) ?></strong></div>
            <div class="topbar-actions"><span class="community-status"><span class="status-dot"></span> Community active</span>
                <button class="topbar-avatar" type="button" data-page="profile" aria-label="Open your profile"><?= e($initials($user['full_name'])) ?></button>
            </div>
        </header>

        <main class="main-content">
            <?php if ($flashMessage): ?>
                <div class="notice notice-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
            <?php endif; ?>

            <section class="page-view <?= $page === 'home' ? '' : 'hidden' ?>" id="page-home" aria-labelledby="welcome-title" aria-hidden="<?= $page !== 'home' ? 'true' : 'false' ?>">
                <div class="welcome-row">
                    <div><h1 id="welcome-title">Welcome, <?= e(explode(' ', trim($user['full_name']))[0]) ?></h1></div>
                    <button class="button button-primary desktop-create" type="button" data-page="create"><span class="button-plus" aria-hidden="true">＋</span> Create a post</button>
                </div>
                <div class="dashboard-grid">
                    <div class="feed-column">
                        <div class="section-heading"><div><h2>Community board</h2></div></div>
                        <div class="feed-filters" role="group" aria-label="Filter community posts">
                            <button class="filter-chip selected" type="button" data-feed-filter="all" aria-pressed="true">All posts <span><?= count($posts) ?></span></button>
                            <button class="filter-chip" type="button" data-feed-filter="announcement" aria-pressed="false">Announcements <span><?= $categoryCounts['announcement'] ?></span></button>
                            <button class="filter-chip" type="button" data-feed-filter="concern" aria-pressed="false">Concerns <span><?= $categoryCounts['concern'] ?></span></button>
                            <button class="filter-chip" type="button" data-feed-filter="question" aria-pressed="false">Questions <span><?= $categoryCounts['question'] ?></span></button>
                            <button class="filter-chip" type="button" data-feed-filter="suggestion" aria-pressed="false">Suggestions <span><?= $categoryCounts['suggestion'] ?></span></button>
                        </div>

                        <div class="post-list" id="post-list">
                            <?php foreach ($posts as $post):
                                $announcement = (bool) $post['is_announcement'];
                                $isReacted = in_array((int) $post['id'], $reactedPostIds, true);
                                $postComments = $commentsByPost[(int) $post['id']] ?? [];
                                $postCategory = $announcement ? 'announcement' : $post['category'];
                                $statusClass = match ($post['status']) {
                                    'resolved' => 'tag-resolved',
                                    'in_progress' => 'tag-progress',
                                    default => 'tag-open',
                                };
                            ?>
                                <article class="post-card <?= $announcement ? 'pinned-post' : '' ?>" id="post-<?= (int) $post['id'] ?>"
                                         data-category="<?= e($postCategory) ?>" data-announcement="<?= $announcement ? 'true' : 'false' ?>">
                                    <?php if ($announcement): ?>
                                        <div class="pinned-ribbon"><span aria-hidden="true">⌖</span> <?= $post['is_pinned'] ? 'PINNED ANNOUNCEMENT' : 'COMMUNITY ANNOUNCEMENT' ?><span class="post-time"><?= e($relativeTime($post['created_at'])) ?></span></div>
                                    <?php endif; ?>
                                    <div class="post-heading">
                                        <span class="avatar <?= $announcement ? 'avatar-admin' : '' ?>"><?= e($initials($post['full_name'])) ?></span>
                                        <div class="post-author"><strong><?= e($post['full_name']) ?></strong><span><?= e($post['house_no'] ?: 'Greenside resident') ?> · <?= e($relativeTime($post['created_at'])) ?></span></div>
                                        <span class="post-tag <?= $announcement ? 'tag-announcement' : $statusClass ?>"><?= $announcement ? 'Announcement' : e(str_replace('_', ' ', ucfirst($post['status']))) ?></span>
                                    </div>
                                    <h3><?= e($post['title']) ?></h3>
                                    <p class="post-copy"><?= nl2br(e($post['body'])) ?></p>
                                    <?php if ($post['post_image']): ?>
                                        <a class="post-image-link" href="<?= e($post['post_image']) ?>" target="_blank" rel="noopener">View attached image</a>
                                    <?php endif; ?>
                                    <div class="post-footer">
                                        <div class="post-actions">
                                            <button class="post-action like-button <?= $isReacted ? 'liked' : '' ?>" type="button" data-reaction data-post-id="<?= (int) $post['id'] ?>" aria-pressed="<?= $isReacted ? 'true' : 'false' ?>">
                                                <span aria-hidden="true">↑</span> <span class="like-count"><?= (int) $post['reaction_count'] ?></span>
                                            </button>
                                            <button class="post-action" type="button" data-comment aria-expanded="false" aria-controls="comments-<?= (int) $post['id'] ?>">
                                                <span aria-hidden="true">▢</span> <span class="comment-count"><?= (int) $post['comment_count'] ?></span>
                                            </button>
                                        </div>
                                        <span class="post-views" title="Counted once per logged-in session"><span aria-hidden="true">◉</span> <?= (int) $post['view_count'] ?></span>
                                    </div>
                                    <div class="comment-thread hidden" id="comments-<?= (int) $post['id'] ?>">
                                        <?php foreach ($postComments as $comment): ?>
                                            <div class="comment-item"><span class="avatar comment-avatar"><?= e($initials($comment['full_name'])) ?></span>
                                                <p><strong><?= e($comment['full_name']) ?></strong><span><?= nl2br(e($comment['body'])) ?></span><small><?= e($relativeTime($comment['created_at'])) ?></small></p>
                                            </div>
                                        <?php endforeach; ?>
                                        <form class="inline-comment-form" data-comment-form data-post-id="<?= (int) $post['id'] ?>">
                                            <label class="sr-only" for="reply-<?= (int) $post['id'] ?>">Write a reply</label>
                                            <input id="reply-<?= (int) $post['id'] ?>" name="body" type="text" maxlength="2000" placeholder="Write a reply..." required>
                                            <button type="submit">Reply</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <p class="empty-state <?= $posts === [] ? '' : 'hidden' ?>" id="empty-feed">No posts yet. Start a conversation with your neighbors.</p>
                    </div>
                    <aside class="dashboard-aside" aria-label="Community information">
                        <section class="balance-card">
                            <div class="balance-card-heading"><span>YOUR OUTSTANDING BALANCE</span><span class="balance-icon" aria-hidden="true">₱</span></div>
                            <strong class="balance-total">₱<?= number_format($unpaidTotal, 2) ?></strong>
                            <p><?= $pendingTotal > 0 ? '₱' . number_format($pendingTotal, 2) . ' pending review' : 'No payments awaiting review' ?></p>
                            <button class="button button-light" type="button" data-page="bills">View bills <span aria-hidden="true">→</span></button>
                        </section>
                        <section class="aside-card community-card"><h3>Your community</h3>
                            <div class="community-stat"><span>Community posts</span><strong><?= count($posts) ?></strong></div>
                            <div class="community-stat"><span>Outstanding fees</span><strong><?= $billCounts['due'] + $billCounts['overdue'] ?></strong></div>
                        </section>
                        <p class="aside-footnote">Greenside Homes HOA · Resident portal</p>
                    </aside>
                </div>
            </section>

            <section class="page-view <?= $page === 'bills' ? '' : 'hidden' ?>" id="page-bills" aria-labelledby="bills-title" aria-hidden="<?= $page !== 'bills' ? 'true' : 'false' ?>">
                <div class="page-heading"><div><h1 id="bills-title">Bills &amp; payments</h1></div><button class="button button-outline" id="history-toggle" type="button">Payment history <span aria-hidden="true">→</span></button></div>
                <div class="billing-summary"><div><span class="summary-label">OUTSTANDING BALANCE</span><strong>₱<?= number_format($unpaidTotal, 2) ?></strong><p>₱<?= number_format($pendingTotal, 2) ?> pending review</p></div><span class="summary-icon" aria-hidden="true">₱</span></div>
                <div class="billing-layout">
                    <div class="billing-main">
                        <div class="billing-filters" role="group" aria-label="Filter bills">
                            <?php foreach (['due' => 'Due', 'overdue' => 'Overdue', 'pending' => 'Pending', 'paid' => 'Paid'] as $status => $label): ?>
                                <button class="billing-filter <?= $status === 'due' ? 'selected' : '' ?>" type="button" data-bill-filter="<?= e($status) ?>" aria-pressed="<?= $status === 'due' ? 'true' : 'false' ?>"><?= e($label) ?> <span><?= $billCounts[$status] ?></span></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="bill-list" id="bill-list">
                            <?php foreach ($fees as $fee):
                                $status = $fee['payment_status'];
                                if ($status === 'rejected') $status = $fee['due_date'] < date('Y-m-d') ? 'overdue' : 'due';
                                $billStatusClass = match ($status) {
                                    'overdue' => 'status-overdue',
                                    'pending' => 'status-pending',
                                    'paid' => 'status-paid',
                                    default => 'status-due',
                                };
                            ?>
                                <article class="bill-card <?= $status === 'due' ? '' : 'hidden' ?>" data-status="<?= e($status) ?>">
                                    <span class="bill-icon <?= $status === 'overdue' ? 'bill-icon-warn' : ($status === 'pending' ? 'bill-icon-pending' : ($status === 'paid' ? 'bill-icon-paid' : '')) ?>" aria-hidden="true"><?= $status === 'paid' ? '✓' : '▤' ?></span>
                                    <div class="bill-description"><div class="bill-title-row"><h2><?= e($fee['title']) ?></h2><span class="bill-status <?= $billStatusClass ?>"><?= e(ucfirst($status === 'pending' ? 'Pending review' : $status)) ?></span></div>
                                        <p><?= $status === 'pending' ? 'Submitted ' . e(date('M j, Y', strtotime($fee['payment_created_at']))) : 'Due ' . e(date('M j, Y', strtotime($fee['due_date']))) ?> · <?= e($fee['type'] === 'monthly' ? 'Monthly dues' : 'Special fee') ?></p>
                                        <?php if ($fee['remarks']): ?><p><?= e($fee['remarks']) ?></p><?php endif; ?>
                                    </div>
                                    <strong class="bill-amount">₱<?= number_format((float) $fee['amount'], 2) ?></strong>
                                    <?php if (in_array($status, ['due', 'overdue'], true)): ?>
                                        <button class="button button-primary bill-pay" type="button" data-fee-id="<?= (int) $fee['id'] ?>" data-fee-title="<?= e($fee['title']) ?>" data-fee-amount="<?= number_format((float) $fee['amount'], 2) ?>">Pay now</button>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <p class="empty-state <?= $fees === [] ? '' : 'hidden' ?>" id="empty-bills">No fees have been posted yet.</p>
                        <?php if ($fees !== []): ?><p class="empty-state hidden" id="filtered-empty-bills">No bills in this category.</p><?php endif; ?>
                        <section class="payment-history hidden" id="payment-history"><h2>Payment history</h2>
                            <?php if ($paymentHistory === []): ?><p class="empty-state">No payments submitted yet.</p><?php endif; ?>
                            <?php foreach ($paymentHistory as $payment): ?>
                                <div class="history-row"><span><strong><?= e($payment['title']) ?></strong><small><?= e(date('M j, Y', strtotime($payment['created_at']))) ?> · <?= e($payment['method']) ?><?= $payment['reference_no'] ? ' · ' . e($payment['reference_no']) : '' ?></small></span>
                                    <strong>₱<?= number_format((float) $payment['amount'], 2) ?></strong><span class="bill-status <?= $payment['status'] === 'verified' ? 'status-paid' : ($payment['status'] === 'rejected' ? 'status-overdue' : 'status-pending') ?>"><?= e(ucfirst($payment['status'])) ?></span>
                                    <?php if ($payment['proof_image']): ?><a href="index.php?action=receipt&amp;id=<?= (int) $payment['id'] ?>" target="_blank" rel="noopener">Receipt</a><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </section>
                    </div>
                    <aside class="payment-aside"><section class="aside-card payment-how"><span class="how-icon" aria-hidden="true">✓</span><h3>How paying works</h3>
                        <p>Pay by cash, bank, or e-wallet using your HOA’s approved payment channel, then file your receipt and reference number.</p>
                        <p>Your payment appears as <strong>Pending</strong> until a treasurer verifies it.</p>
                    </section><section class="aside-card secure-note"><span aria-hidden="true">▣</span><p>Payment proof is stored privately and is only viewable by you and HOA reviewers.</p></section></aside>
                </div>
            </section>

            <section class="page-view <?= $page === 'clearances' ? '' : 'hidden' ?>" id="page-clearances" aria-labelledby="clearances-title" aria-hidden="<?= $page !== 'clearances' ? 'true' : 'false' ?>">
                <div class="page-heading clearance-header-row">
                    <div>
                        <h1 id="clearances-title">Permits &amp; clearances</h1>
                        <p class="section-subtitle">Track subdivision gate passes, utility tapping certifications, and village clearance applications.</p>
                    </div>
                    <button class="button button-primary" type="button" id="btn-open-clearance-modal">
                        <span class="button-plus" aria-hidden="true">＋</span> Request New Clearance
                    </button>
                </div>

                <div class="clearance-notice-banner" role="note">
                    <span class="notice-badge-icon" aria-hidden="true">ℹ</span>
                    <div class="notice-content">
                        <strong>Good Standing Policy:</strong> Active monthly HOA dues must be settled to date for clearance issuance and guardhouse validation. Applications with unpaid dues are held until assessment.
                    </div>
                    <button class="button button-outline button-tiny" type="button" data-page="bills">Check dues →</button>
                </div>

                <div class="clearance-card-container">
                    <?php if ($clearances === []): ?>
                        <div class="clearance-empty-state">
                            <div class="empty-icon-wrap" aria-hidden="true">
                                <svg viewBox="0 0 64 64" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 8h24l12 12v36a4 4 0 0 1-4 4H16a4 4 0 0 1-4-4V12a4 4 0 0 1 4-4z" />
                                    <polyline points="40 8 40 20 52 20" />
                                    <line x1="22" y1="28" x2="42" y2="28" />
                                    <line x1="22" y1="36" x2="38" y2="36" />
                                    <line x1="22" y1="44" x2="32" y2="44" />
                                </svg>
                            </div>
                            <h3>No active clearance requests</h3>
                            <p>You currently have zero pending or approved clearance requests. When you request a vehicle sticker, gate pass, or utility clearance, it will appear here.</p>
                            <button class="button button-primary button-small" type="button" id="btn-request-first-empty">
                                <span class="button-plus" aria-hidden="true">＋</span> Request New Clearance
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="clearance-table-wrapper">
                            <table class="clearance-table" aria-label="Permits and Clearances Status">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width: 33%;">Clearance Type &amp; Details</th>
                                        <th scope="col" style="width: 14%;">Date Applied</th>
                                        <th scope="col" style="width: 17%;">Payment Status</th>
                                        <th scope="col" style="width: 21%;">Approval Status</th>
                                        <th scope="col" style="width: 15%; text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clearances as $c):
                                        $payStatus = $c['payment_status'];
                                        $payClass = match ($payStatus) {
                                            'paid' => 'badge-pay-paid',
                                            'free' => 'badge-pay-free',
                                            'pending_assessment' => 'badge-pay-pending',
                                            'unpaid' => 'badge-pay-unpaid',
                                            default => 'badge-pay-pending',
                                        };
                                        $payLabel = match ($payStatus) {
                                            'paid' => 'Paid',
                                            'free' => 'Free / N/A',
                                            'pending_assessment' => 'Pending Assessment',
                                            'unpaid' => 'Unpaid (₱' . number_format((float) ($c['fee_amount'] ?: 150), 0) . ')',
                                            default => ucfirst($payStatus),
                                        };

                                        $appStatus = $c['approval_status'];
                                        $appClass = match ($appStatus) {
                                            'approved' => 'pill-app-approved',
                                            'pending_requirements' => 'pill-app-requirements',
                                            'under_review' => 'pill-app-review',
                                            'disapproved' => 'pill-app-disapproved',
                                            default => 'pill-app-review',
                                        };
                                        $appDot = match ($appStatus) {
                                            'approved' => '●',
                                            'pending_requirements' => '▲',
                                            'under_review' => '◐',
                                            'disapproved' => '✕',
                                            default => '●',
                                        };
                                        $appLabel = match ($appStatus) {
                                            'approved' => 'Approved / Ready for Release',
                                            'pending_requirements' => 'Pending Requirements',
                                            'under_review' => 'Under Review (Admin / Security)',
                                            'disapproved' => 'Disapproved (Unsettled Dues)',
                                            default => ucfirst(str_replace('_', ' ', $appStatus)),
                                        };

                                        $actionType = $c['action_type'];
                                        $dateAppliedFormatted = date('d M Y', strtotime($c['applied_date']));
                                    ?>
                                        <tr class="clearance-row" data-id="<?= (int) $c['id'] ?>">
                                            <td class="clearance-col-main" data-label="Clearance Type & Details">
                                                <div class="clearance-item-heading">
                                                    <strong class="clearance-type-title"><?= e($c['type_title']) ?></strong>
                                                    <span class="clearance-ref-badge"><?= e($c['reference_no']) ?></span>
                                                </div>
                                                <div class="clearance-subtext"><?= e($c['subtext']) ?></div>
                                                <?php if ($c['remarks']): ?>
                                                    <div class="clearance-remarks-note"><?= e($c['remarks']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="clearance-col-date" data-label="Date Applied">
                                                <time datetime="<?= e($c['applied_date']) ?>"><?= e($dateAppliedFormatted) ?></time>
                                            </td>
                                            <td class="clearance-col-payment" data-label="Payment Status">
                                                <span class="clearance-badge <?= $payClass ?>"><?= e($payLabel) ?></span>
                                            </td>
                                            <td class="clearance-col-approval" data-label="Approval Status">
                                                <span class="clearance-pill <?= $appClass ?>">
                                                    <span class="pill-dot" aria-hidden="true"><?= $appDot ?></span>
                                                    <span><?= e($appLabel) ?></span>
                                                </span>
                                            </td>
                                            <td class="clearance-col-action" data-label="Action">
                                                <?php if ($actionType === 'upload_docs' || $appStatus === 'pending_requirements'): ?>
                                                    <button type="button" class="clearance-action-btn action-upload"
                                                            data-clearance-id="<?= (int) $c['id'] ?>"
                                                            data-clearance-title="<?= e($c['type_title']) ?>"
                                                            data-required-doc="<?= e($c['lacking_document_name'] ?: 'Meralco / Water proof or OR/CR') ?>">
                                                        <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                        Upload Lacking Docs
                                                    </button>
                                                <?php elseif ($actionType === 'pay_gcash' || $payStatus === 'unpaid'): ?>
                                                    <button type="button" class="clearance-action-btn action-pay"
                                                            data-clearance-ref="<?= e($c['reference_no']) ?>"
                                                            data-clearance-amount="<?= number_format((float) ($c['fee_amount'] ?: 150), 2) ?>"
                                                            data-clearance-title="<?= e($c['type_title']) ?>">
                                                        <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="2" y1="10" x2="22" y2="10" stroke="currentColor" stroke-width="2"/></svg>
                                                        Pay via GCash
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="clearance-action-btn action-view"
                                                            data-clearance-ref="<?= e($c['reference_no']) ?>"
                                                            data-clearance-title="<?= e($c['type_title']) ?>"
                                                            data-clearance-subtext="<?= e($c['subtext']) ?>"
                                                            data-clearance-date="<?= e($dateAppliedFormatted) ?>"
                                                            data-clearance-status="<?= e($appLabel) ?>"
                                                            data-clearance-pay="<?= e($payLabel) ?>"
                                                            data-clearance-remarks="<?= e($c['remarks'] ?: 'Presented for gate and village inspection.') ?>">
                                                        <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="14 2 14 8 20 8" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                                        View Slip / QR
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="page-view <?= $page === 'create' ? '' : 'hidden' ?>" id="page-create" aria-labelledby="create-title" aria-hidden="<?= $page !== 'create' ? 'true' : 'false' ?>">
                <div class="page-heading"><div><h1 id="create-title">Create a post</h1></div></div>
                <div class="compose-layout">
                    <form class="compose-card" method="post" action="index.php?action=create-post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="compose-author"><span class="avatar avatar-small"><?= e($initials($user['full_name'])) ?></span><span><strong><?= e($user['full_name']) ?></strong><small>Posting to Greenside Homes</small></span></div>
                        <label for="post-title">Post title</label><input id="post-title" name="title" type="text" maxlength="200" required>
                        <label for="post-category">Post category</label><select id="post-category" name="category"><option value="concern">Raise a concern</option><option value="question">Ask a question</option><option value="suggestion">Share a suggestion</option></select>
                        <label for="post-message">Your message</label><textarea id="post-message" name="body" rows="5" maxlength="10000" required></textarea>
                        <div class="compose-footer"><span>Please keep posts respectful and community-friendly.</span><button class="button button-primary" type="submit">Publish post</button></div>
                    </form>
                    <aside class="aside-card posting-guidelines"><span class="guideline-icon" aria-hidden="true">✳</span><h2>Posting guidelines</h2><ul><li>Be respectful and constructive.</li><li>Keep personal information private.</li><li>For urgent emergencies, contact local services directly.</li></ul></aside>
                </div>
            </section>

            <section class="page-view <?= $page === 'alerts' ? '' : 'hidden' ?>" id="page-alerts" aria-labelledby="alerts-title" aria-hidden="<?= $page !== 'alerts' ? 'true' : 'false' ?>">
                <div class="page-heading"><div><h1 id="alerts-title">Alerts</h1></div>
                    <?php if ($unreadCount > 0): ?><button class="button button-outline" type="button" data-mark-all-read>Mark all as read</button><?php endif; ?>
                </div>
                <div class="alerts-card">
                    <?php if ($notifications === []): ?><p class="empty-state">You’re all caught up. New community updates will appear here.</p><?php endif; ?>
                    <?php foreach ($notifications as $notification): ?>
                        <article class="alert-item <?= !(bool) $notification['is_read'] ? 'unread' : '' ?>" data-notification-id="<?= (int) $notification['id'] ?>">
                            <span class="alert-icon <?= $notification['type'] === 'payment' ? 'alert-icon-amber' : 'alert-icon-green' ?>" aria-hidden="true"><?= $notification['type'] === 'payment' ? '₱' : ($notification['type'] === 'reply' ? '↩' : 'i') ?></span>
                            <div><h3><?= e(ucfirst($notification['type'])) ?></h3><p><?= e($notification['message']) ?></p><small><?= e(date('M j, Y · g:i a', strtotime($notification['created_at']))) ?></small></div>
                            <?php if (!(bool) $notification['is_read']): ?><span class="unread-dot" aria-label="Unread"></span><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="page-view <?= $page === 'profile' ? '' : 'hidden' ?>" id="page-profile" aria-labelledby="profile-title" aria-hidden="<?= $page !== 'profile' ? 'true' : 'false' ?>">
                <div class="page-heading"><div><h1 id="profile-title">My profile</h1></div></div>
                <div class="profile-layout">
                    <section class="profile-card"><div class="profile-cover"></div>
                        <div class="profile-details"><span class="avatar avatar-profile"><?= e($initials($user['full_name'])) ?></span>
                            <div><h2><?= e($user['full_name']) ?></h2><p><?= e($user['house_no'] ?: 'House details not provided') ?></p><span class="verified-label"><span class="status-dot"></span> Approved <?= e(ucfirst($user['role'])) ?></span></div>
                        </div>
                    </section>
                    <section class="aside-card account-menu"><h2>Account details</h2>
                        <div class="account-info-row"><strong>Email</strong><span><?= e($user['email']) ?></span></div>
                        <div class="account-info-row"><strong>House / block / lot</strong><span><?= e($user['house_no'] ?: 'Not provided') ?></span></div>
                        <div class="account-info-row"><strong>Account type</strong><span><?= e(ucfirst($user['role'])) ?></span></div>
                    </section>
                    <form method="post" action="index.php?action=logout"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="button button-logout" type="submit">Log out</button></form>
                </div>
            </section>

            <?php if (in_array($user['role'], ['admin', 'treasurer'], true)): ?>
                <section class="page-view <?= $page === 'admin' ? '' : 'hidden' ?>" id="page-admin" aria-labelledby="admin-title" aria-hidden="<?= $page !== 'admin' ? 'true' : 'false' ?>">
                    <div class="page-heading"><div><h1 id="admin-title">HOA management</h1></div></div>
                    <div class="admin-grid">
                        <?php if ($user['role'] === 'admin'): ?>
                            <details class="admin-card admin-collapsible">
                                <summary><span class="admin-collapsible-title">Post an announcement</span><span class="admin-collapse-icon" aria-hidden="true"></span></summary>
                                <form method="post" action="index.php?action=create-announcement" class="admin-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <label for="announcement-title">Announcement title</label><input id="announcement-title" name="title" maxlength="200" required>
                                    <label for="announcement-body">Message</label><textarea id="announcement-body" name="body" rows="3" maxlength="10000" required></textarea>
                                    <label class="admin-checkbox" for="announcement-pinned"><input id="announcement-pinned" name="is_pinned" type="checkbox" value="1"> Pin this announcement</label>
                                    <button class="button button-primary" type="submit">Publish announcement</button>
                                </form>
                            </details>
                        <?php endif; ?>
                        <details class="admin-card admin-collapsible">
                            <summary><span class="admin-collapsible-title">Create a fee</span><span class="admin-collapse-icon" aria-hidden="true"></span></summary>
                            <form method="post" action="index.php?action=create-fee" class="admin-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <label for="fee-title">Fee title</label><input id="fee-title" name="title" maxlength="150" required>
                                <label for="fee-description">Description</label><textarea id="fee-description" name="description" rows="2"></textarea>
                                <div class="admin-form-row"><div><label for="fee-amount">Amount (PHP)</label><input id="fee-amount" name="amount" type="number" min="0.01" step="0.01" required></div>
                                    <div><label for="fee-type">Type</label><select id="fee-type" name="type"><option value="monthly">Monthly</option><option value="special">Special</option></select></div></div>
                                <label for="fee-due-date">Due date</label><input id="fee-due-date" name="due_date" type="date" required>
                                <button class="button button-primary" type="submit">Create fee</button>
                            </form>
                        </details>
                        <details class="admin-card admin-collapsible">
                            <summary><span class="admin-collapsible-title">Record an expense</span><span class="admin-collapse-icon" aria-hidden="true"></span></summary>
                            <form method="post" action="index.php?action=create-expense" class="admin-form" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <label for="expense-description">Description</label><input id="expense-description" name="description" maxlength="10000" required>
                                <div class="admin-form-row"><div><label for="expense-amount">Amount (PHP)</label><input id="expense-amount" name="amount" type="number" min="0.01" step="0.01" required></div>
                                    <div><label for="expense-category">Category</label><input id="expense-category" name="category" maxlength="100" required></div></div>
                                <label for="expense-date">Expense date</label><input id="expense-date" name="expense_date" type="date" required>
                                <label for="expense-receipt">Receipt (optional)</label><input id="expense-receipt" name="receipt_image" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">
                                <button class="button button-primary" type="submit">Record expense</button>
                            </form>
                        </details>
                    </div>
                    <?php if ($pendingResidents !== []): ?>
                        <section class="admin-card admin-list-card"><h2>Resident requests <span><?= count($pendingResidents) ?></span></h2>
                            <?php foreach ($pendingResidents as $resident): ?><div class="admin-row">
                                <div><strong><?= e($resident['full_name']) ?></strong><small><?= e($resident['email']) ?> · <?= e($resident['house_no']) ?></small></div>
                                <form method="post" action="index.php?action=approve-resident"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $resident['id'] ?>"><input type="hidden" name="status" value="approved"><button class="button button-primary" type="submit">Approve</button></form>
                                <form method="post" action="index.php?action=approve-resident"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $resident['id'] ?>"><input type="hidden" name="status" value="rejected"><button class="button button-logout" type="submit">Reject</button></form>
                            </div><?php endforeach; ?>
                        </section>
                    <?php endif; ?>
                    <section class="admin-card admin-list-card"><h2>Payments awaiting review <span><?= count($pendingPayments) ?></span></h2>
                        <?php if ($pendingPayments === []): ?><p class="muted-copy">No payments are awaiting review.</p><?php endif; ?>
                        <?php foreach ($pendingPayments as $payment): ?><div class="admin-row payment-review-row">
                            <div><strong><?= e($payment['title']) ?> · ₱<?= number_format((float) $payment['amount'], 2) ?></strong><small><?= e($payment['full_name']) ?> · <?= e($payment['house_no']) ?> · <?= e($payment['method']) ?><?= $payment['reference_no'] ? ' · Ref ' . e($payment['reference_no']) : '' ?></small>
                                <?php if ($payment['proof_image']): ?><a href="index.php?action=receipt&amp;id=<?= (int) $payment['id'] ?>" target="_blank" rel="noopener">View receipt</a><?php endif; ?></div>
                            <form method="post" action="index.php?action=review-payment" class="review-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                                <input type="text" name="remarks" maxlength="2000" placeholder="Optional note">
                                <button class="button button-primary" name="status" value="verified" type="submit">Verify</button><button class="button button-logout" name="status" value="rejected" type="submit">Reject</button>
                            </form>
                        </div><?php endforeach; ?>
                    </section>
                    <section class="admin-card admin-list-card"><h2>Recent expenses</h2>
                        <?php if ($expenses === []): ?><p class="muted-copy">No expenses recorded.</p><?php endif; ?>
                        <?php foreach ($expenses as $expense): ?><div class="history-row"><span><strong><?= e($expense['description']) ?></strong><small><?= e($expense['category']) ?> · <?= e(date('M j, Y', strtotime($expense['expense_date']))) ?> · <?= e($expense['full_name']) ?></small></span><strong>₱<?= number_format((float) $expense['amount'], 2) ?></strong></div><?php endforeach; ?>
                    </section>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <nav class="mobile-nav <?= in_array($user['role'], ['admin', 'treasurer'], true) ? 'mobile-nav-management' : '' ?>" aria-label="Mobile navigation">
        <button class="mobile-nav-link <?= $page === 'home' ? 'active' : '' ?>" type="button" data-page="home"><span aria-hidden="true">⌂</span><small>Home</small></button>
        <button class="mobile-nav-link <?= $page === 'bills' ? 'active' : '' ?>" type="button" data-page="bills"><span aria-hidden="true">▤</span><small>Bills</small></button>
        <button class="mobile-nav-link <?= $page === 'clearances' ? 'active' : '' ?>" type="button" data-page="clearances" aria-label="Permits & clearances"><span aria-hidden="true">📋</span><small>Permits</small></button>
        <button class="mobile-create" type="button" data-page="create" aria-label="Create a post">＋</button>
        <button class="mobile-nav-link <?= $page === 'alerts' ? 'active' : '' ?>" type="button" data-page="alerts"><span class="mobile-alert-icon" aria-hidden="true">♧<i id="mobile-alert-count" class="<?= $unreadCount === 0 ? 'hidden' : '' ?>"><?= $unreadCount ?></i></span><small>Alerts</small></button>
        <button class="mobile-nav-link <?= $page === 'profile' ? 'active' : '' ?>" type="button" data-page="profile"><span aria-hidden="true">♙</span><small>Profile</small></button>
        <?php if (in_array($user['role'], ['admin', 'treasurer'], true)): ?>
            <button class="mobile-nav-link mobile-management-link <?= $page === 'admin' ? 'active' : '' ?>" type="button" data-page="admin" aria-label="HOA management"><span aria-hidden="true">⚙</span><small>Manage</small></button>
        <?php endif; ?>
    </nav>

    <dialog class="payment-dialog" id="payment-dialog" aria-labelledby="payment-dialog-title">
        <div class="payment-dialog-header"><div><p class="eyebrow">BILL PAYMENT</p><h2 id="payment-dialog-title">File a payment</h2><p id="selected-bill-label"></p></div>
            <button class="icon-close" type="button" data-close-payment aria-label="Close payment options">×</button></div>
        <form method="post" action="index.php?action=submit-payment" id="proof-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input id="proof-fee-id" type="hidden" name="fee_id">
            <div class="payment-demo-note"><strong>Already paid?</strong><span>Submit your reference number and proof for treasurer review. To make the payment, use the payment channel provided by your HOA.</span></div>
            <label for="payment-method">Payment method</label><select id="payment-method" name="method" required><option value="">Choose a method</option><option value="cash">Cash</option><option value="bank">Bank transfer</option><option value="e-wallet">E-wallet</option></select>
            <label for="reference-number">Payment reference number</label><input id="reference-number" name="reference_no" type="text" maxlength="150" placeholder="Enter your GCash or bank reference">
            <label for="receipt-file">Receipt or payment screenshot</label><input id="receipt-file" name="proof_image" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
            <p class="payment-step-intro">JPG, PNG, WebP, or PDF · Up to 5 MB. Files are stored privately.</p>
            <button class="button button-primary payment-next-button" type="submit">Submit for review</button>
        </form>
    </dialog>

    <!-- Request New Clearance Modal -->
    <dialog class="payment-dialog clearance-dialog" id="clearance-request-dialog" aria-labelledby="clearance-modal-title">
        <div class="payment-dialog-header">
            <div>
                <p class="eyebrow">SUBDIVISION CLEARANCE</p>
                <h2 id="clearance-modal-title">Request Clearance or Pass</h2>
                <p>Fill out the application details for admin and security evaluation.</p>
            </div>
            <button class="icon-close" type="button" id="btn-close-clearance-modal" aria-label="Close modal">×</button>
        </div>
        <form method="post" action="index.php?action=request-clearance" id="clearance-request-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="clearance-dialog-advisory">
                <strong>Monthly Dues Verification:</strong> Clearance requests require good standing. Active monthly dues must be up to date to prevent hold in processing.
            </div>

            <label for="select-clearance-type">Clearance Type</label>
            <select id="select-clearance-type" name="clearance_type" required>
                <option value="vehicle_sticker">Vehicle / Motorcycle Sticker (₱150 · RFID / Gate Sticker)</option>
                <option value="isp_install">ISP / Fiber Internet Installation (Free · Converge, PLDT, Globe)</option>
                <option value="utility_meter">Water / Electric Meter Application (Free · Meralco / Water District)</option>
                <option value="house_extension">House Extension / Minor Renovation (Gate, fence, grill)</option>
                <option value="gate_pass">Moving In / Out Gate Pass (₱150 · Delivery truck / Hauling)</option>
            </select>

            <label for="input-clearance-subtext" id="label-clearance-subtext">Plate Number &amp; Vehicle Model</label>
            <input id="input-clearance-subtext" name="subtext" type="text" maxlength="255" placeholder="e.g., NDM-4821 · Honda Click 125i" required>

            <label for="input-clearance-details">Additional Details / Schedule</label>
            <textarea id="input-clearance-details" name="details" rows="3" maxlength="1000" placeholder="e.g., Target date of installation, contractor name, or driver details..."></textarea>

            <label for="input-clearance-file">Attach Supporting Document (Optional)</label>
            <input id="input-clearance-file" name="document_file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">
            <p class="payment-step-intro">Upload OR/CR, Meralco tapping slip, contractor waiver, or ID (JPG, PNG, PDF up to 5 MB).</p>

            <button class="button button-primary payment-next-button" type="submit">Submit Clearance Request</button>
        </form>
    </dialog>

    <!-- Digital Gate Pass & Clearance Slip Modal -->
    <dialog class="payment-dialog clearance-slip-modal" id="clearance-slip-dialog" aria-labelledby="slip-dialog-title">
        <div class="payment-dialog-header">
            <div>
                <p class="eyebrow">OFFICIAL GATE PASS &amp; CLEARANCE</p>
                <h2 id="slip-dialog-title">Subdivision Entry Slip</h2>
            </div>
            <button class="icon-close" type="button" id="btn-close-slip-modal" aria-label="Close slip">×</button>
        </div>
        <div class="slip-printable-card" id="slip-print-content">
            <div class="slip-header">
                <div class="slip-brand">
                    <span class="brand-mark"><img src="Assets/logogreenside.jpg" alt=""></span>
                    <div>
                        <strong>Greenside Homes HOA</strong>
                        <small>HOMEOWNERS ASSOCIATION INC.</small>
                    </div>
                </div>
                <div class="slip-ref-box">
                    <span class="slip-ref-label">PASS REF</span>
                    <strong id="slip-ref-no">CLR-2026-0000</strong>
                </div>
            </div>

            <div class="slip-title-banner">
                <h3 id="slip-type-title">Subdivision Clearance</h3>
                <span id="slip-status-pill" class="clearance-pill pill-app-approved">Approved / Ready for Release</span>
            </div>

            <div class="slip-details-grid">
                <div class="slip-detail-item">
                    <small>Resident Name</small>
                    <strong><?= e($user['full_name']) ?></strong>
                </div>
                <div class="slip-detail-item">
                    <small>House / Lot No.</small>
                    <strong><?= e($user['house_no'] ?: 'Registered Resident') ?></strong>
                </div>
                <div class="slip-detail-item">
                    <small>Application Details</small>
                    <strong id="slip-subtext">Plate &amp; Model</strong>
                </div>
                <div class="slip-detail-item">
                    <small>Date Applied / Validated</small>
                    <strong id="slip-date">DD MMM YYYY</strong>
                </div>
                <div class="slip-detail-item">
                    <small>Payment Condition</small>
                    <strong id="slip-payment-condition">Paid</strong>
                </div>
                <div class="slip-detail-item">
                    <small>Association Status</small>
                    <strong style="color: var(--green);">Active (Dues Settled)</strong>
                </div>
            </div>

            <div class="slip-qr-section">
                <div class="slip-qr-box">
                    <!-- Clean SVG QR Code Graphic for guard validation -->
                    <svg viewBox="0 0 120 120" width="108" height="108" aria-label="Guardhouse Verification QR Code">
                        <rect width="120" height="120" fill="#ffffff" />
                        <!-- Top-left finder -->
                        <rect x="10" y="10" width="30" height="30" fill="#15764f" rx="3" />
                        <rect x="16" y="16" width="18" height="18" fill="#ffffff" />
                        <rect x="20" y="20" width="10" height="10" fill="#15764f" />
                        <!-- Top-right finder -->
                        <rect x="80" y="10" width="30" height="30" fill="#15764f" rx="3" />
                        <rect x="86" y="16" width="18" height="18" fill="#ffffff" />
                        <rect x="90" y="20" width="10" height="10" fill="#15764f" />
                        <!-- Bottom-left finder -->
                        <rect x="10" y="80" width="30" height="30" fill="#15764f" rx="3" />
                        <rect x="16" y="86" width="18" height="18" fill="#ffffff" />
                        <rect x="20" y="90" width="10" height="10" fill="#15764f" />
                        <!-- QR data dots -->
                        <rect x="48" y="12" width="6" height="6" fill="#15764f" />
                        <rect x="58" y="12" width="6" height="6" fill="#15764f" />
                        <rect x="68" y="18" width="6" height="6" fill="#15764f" />
                        <rect x="48" y="26" width="6" height="6" fill="#15764f" />
                        <rect x="60" y="28" width="6" height="6" fill="#15764f" />
                        <rect x="14" y="48" width="6" height="6" fill="#15764f" />
                        <rect x="24" y="56" width="6" height="6" fill="#15764f" />
                        <rect x="36" y="48" width="6" height="6" fill="#15764f" />
                        <rect x="48" y="48" width="12" height="12" fill="#15764f" />
                        <rect x="68" y="48" width="6" height="6" fill="#15764f" />
                        <rect x="80" y="48" width="6" height="6" fill="#15764f" />
                        <rect x="96" y="56" width="6" height="6" fill="#15764f" />
                        <rect x="48" y="68" width="6" height="6" fill="#15764f" />
                        <rect x="64" y="68" width="10" height="6" fill="#15764f" />
                        <rect x="80" y="68" width="6" height="6" fill="#15764f" />
                        <rect x="96" y="68" width="6" height="6" fill="#15764f" />
                        <rect x="48" y="84" width="6" height="6" fill="#15764f" />
                        <rect x="60" y="88" width="6" height="6" fill="#15764f" />
                        <rect x="74" y="82" width="6" height="6" fill="#15764f" />
                        <rect x="86" y="90" width="6" height="6" fill="#15764f" />
                        <rect x="100" y="84" width="6" height="6" fill="#15764f" />
                    </svg>
                </div>
                <div class="slip-qr-instructions">
                    <strong>Guardhouse QR Scan</strong>
                    <p>Present this digital slip on your mobile phone or print a physical copy. Village security personnel scan the code to verify authenticity against the gate registry.</p>
                    <small id="slip-remarks" class="slip-remarks-box">Remarks: Valid for gate inspection.</small>
                </div>
            </div>

            <div class="slip-security-notice">
                <span>⚠ NOTICE TO SECURITY:</span> Entry is strictly governed by subdivision bylaws. Delivery trucks and service contractors must exit by 5:30 PM.
            </div>
        </div>

        <div class="slip-dialog-actions">
            <button class="button button-outline" type="button" id="btn-print-slip">
                <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" fill="none" stroke="currentColor" stroke-width="2"/><rect x="6" y="14" width="12" height="8" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                Print Gate Pass
            </button>
            <button class="button button-primary" type="button" id="btn-dismiss-slip">Done</button>
        </div>
    </dialog>

    <!-- Upload Lacking Docs Modal -->
    <dialog class="payment-dialog" id="clearance-upload-dialog" aria-labelledby="upload-modal-title">
        <div class="payment-dialog-header">
            <div>
                <p class="eyebrow">REQUIREMENT COMPLIANCE</p>
                <h2 id="upload-modal-title">Upload Lacking Document</h2>
                <p id="upload-target-title">Water / Electric Meter Application</p>
            </div>
            <button class="icon-close" type="button" id="btn-close-upload-modal" aria-label="Close modal">×</button>
        </div>
        <form method="post" action="index.php?action=upload-clearance-doc" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="clearance_id" id="upload-clearance-id" value="">

            <div class="upload-requirement-box">
                <strong id="upload-doc-name">Meralco Yellow Card / Water Service Order</strong>
                <p>Please submit a clear photo or scanned copy of the required document so the HOA board can approve your application.</p>
            </div>

            <label for="upload-doc-file">Select File</label>
            <input id="upload-doc-file" name="document_file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
            <p class="payment-step-intro">JPG, PNG, WebP, or PDF up to 5 MB.</p>

            <button class="button button-primary payment-next-button" type="submit">Submit Requirement</button>
        </form>
    </dialog>

    <!-- GCash Settlement Modal -->
    <dialog class="payment-dialog" id="clearance-gcash-dialog" aria-labelledby="gcash-modal-title">
        <div class="payment-dialog-header">
            <div>
                <p class="eyebrow">GCASH PAYMENT</p>
                <h2 id="gcash-modal-title">Pay Clearance Fee</h2>
                <p id="gcash-target-title">Moving In / Out Gate Pass</p>
            </div>
            <button class="icon-close" type="button" id="btn-close-gcash-modal" aria-label="Close modal">×</button>
        </div>
        <div class="gcash-box">
            <div class="gcash-amount-banner">
                <span>TOTAL DUE</span>
                <strong id="gcash-amount-display">₱150.00</strong>
            </div>

            <div class="gcash-instructions-list">
                <div class="gcash-inst-row">
                    <span>1</span>
                    <div>
                        <strong>Open your GCash App</strong>
                        <p>Go to <em>Send Money $\rightarrow$ Express Send</em></p>
                    </div>
                </div>
                <div class="gcash-inst-row">
                    <span>2</span>
                    <div>
                        <strong>Send to HOA Admin GCash</strong>
                        <p class="gcash-number-pill">0917-882-4462 (Greenside HOA Inc.)</p>
                    </div>
                </div>
                <div class="gcash-inst-row">
                    <span>3</span>
                    <div>
                        <strong>Put Clearance Ref in Message</strong>
                        <p>Ref: <strong id="gcash-ref-display">CLR-2026-0934</strong></p>
                    </div>
                </div>
            </div>

            <div class="gcash-reminder-note">
                Once paid, the HOA security coordinator will automatically verify the payment against GCash SMS notifications and update your status to <strong>Approved</strong>.
            </div>

            <button class="button button-primary payment-next-button" type="button" id="btn-gcash-acknowledged">I've Sent the Payment</button>
        </div>
    </dialog>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <script src="Assets/script.js" defer></script>
</body>
</html>
