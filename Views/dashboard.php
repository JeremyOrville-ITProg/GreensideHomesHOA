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
    'home' => 'Greenside Homes',
    'bills' => 'Bills & Dues',
    'clearances' => 'Permits & Clearances',
    'create' => 'Create Post',
    'alerts' => 'Announcements',
    'profile' => 'Resident Profile',
    'admin' => 'HOA Administration',
][$page] ?? 'Greenside Homes';
$flashMessage = $flashMessage ?: null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#13734c">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($activePageTitle) ?> | Greenside Homes</title>
    <link rel="icon" type="image/jpeg" href="Assets/logogreenside.jpg">
    <link rel="stylesheet" href="Assets/style.css">
</head>
<body class="android-body">

    <!-- Android Device Frame Wrapper (Constrained 393-412px on Desktop, Edge-to-Edge on Mobile) -->
    <div class="android-device-frame" id="app-container">

        <!-- Android Status Bar (Native Clock, Signal, Wi-Fi, Battery) -->
        <header class="android-status-bar" aria-hidden="true">
            <span class="status-bar-time" id="android-clock">9:41</span>
            <div class="status-bar-icons">
                <svg viewBox="0 0 24 24" class="status-svg" aria-hidden="true"><path d="M2 20h20L22 4z" fill="currentColor"/></svg>
                <span class="status-network-label">5G</span>
                <svg viewBox="0 0 24 24" class="status-svg" aria-hidden="true"><path d="M12 4C7.31 4 3.07 5.9 0 8.98L12 21 24 8.98C20.93 5.9 16.69 4 12 4zm0 2.5c3.97 0 7.57 1.62 10.19 4.25L12 18.57 1.81 10.75C4.43 8.12 8.03 6.5 12 6.5z" fill="currentColor"/></svg>
                <div class="status-battery-wrap">
                    <svg viewBox="0 0 24 24" class="status-svg battery-svg" aria-hidden="true"><path d="M17 5v14c0 .55-.45 1-1 1H8c-.55 0-1-.45-1-1V5c0-.55.45-1 1-1h1.5V3c0-.55.45-1 1-1h5c.55 0 1 .45 1 1v1H16c.55 0 1 .45 1 1z" fill="currentColor"/></svg>
                    <span>100%</span>
                </div>
            </div>
        </header>

        <!-- Material 3 Top App Bar -->
        <header class="m3-top-app-bar" role="banner">
            <button class="m3-icon-btn m3-hamburger-btn" id="btn-open-drawer" type="button" aria-label="Open Navigation Drawer">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <div class="m3-top-brand" data-page="home" role="button" tabindex="0">
                <span class="m3-brand-logo"><img src="Assets/logogreenside.jpg" alt="Greenside Homes Logo"></span>
                <div class="m3-brand-text">
                    <strong class="m3-community-name" id="app-bar-title"><?= e($activePageTitle) ?></strong>
                    <small class="m3-subhead-status"><span class="m3-live-dot"></span> <?= e($user['house_no'] ?: 'HOA Resident') ?></small>
                </div>
            </div>
            <div class="m3-top-actions">
                <button class="m3-icon-btn <?= $page === 'alerts' ? 'active' : '' ?>" type="button" data-page="alerts" aria-label="Announcements & Alerts">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <span class="m3-bell-badge <?= $unreadCount === 0 ? 'hidden' : '' ?>" id="top-bell-badge"><?= $unreadCount ?></span>
                </button>
                <button class="m3-avatar-btn <?= $page === 'profile' ? 'active' : '' ?>" type="button" data-page="profile" aria-label="Open resident profile">
                    <span class="avatar avatar-small"><?= e($initials($user['full_name'])) ?></span>
                </button>
            </div>
        </header>

        <!-- Stackable In-App Push Notification Toast Container -->
        <div class="m3-push-container" id="m3-push-container" aria-live="polite"></div>

        <!-- Material 3 Left-Side Navigation Drawer & Backdrop Scrim -->
        <div class="m3-drawer-scrim" id="m3-drawer-scrim" aria-hidden="true"></div>
        <aside class="m3-nav-drawer" id="m3-nav-drawer" aria-label="Navigation Drawer" aria-hidden="true">
            <div class="m3-drawer-header">
                <div class="m3-drawer-user">
                    <span class="avatar avatar-large"><?= e($initials($user['full_name'])) ?></span>
                    <div class="m3-drawer-user-info">
                        <strong><?= e($user['full_name']) ?></strong>
                        <small><?= e($user['house_no'] ?: 'HOA Resident') ?></small>
                        <span class="m3-drawer-role-pill"><?= e(ucfirst($user['role'])) ?></span>
                    </div>
                </div>
                <button class="m3-drawer-close-btn" id="btn-close-drawer" type="button" aria-label="Close Navigation Drawer">✕</button>
            </div>

            <nav class="m3-drawer-menu" aria-label="Main Navigation Menu">
                <p class="m3-drawer-section-label">Main Navigation</p>
                <button class="m3-drawer-item <?= $page === 'home' ? 'active' : '' ?>" type="button" data-drawer-page="home">
                    <div class="m3-drawer-item-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 10l9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10z"/>
                        </svg>
                    </div>
                    <span class="m3-drawer-item-label">Home</span>
                </button>

                <button class="m3-drawer-item <?= $page === 'bills' ? 'active' : '' ?>" type="button" data-drawer-page="bills">
                    <div class="m3-drawer-item-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <line x1="2" y1="10" x2="22" y2="10"/>
                            <path d="M6 15h2"/>
                        </svg>
                    </div>
                    <span class="m3-drawer-item-label">Payment &amp; Dues</span>
                </button>

                <button class="m3-drawer-item" type="button" data-drawer-page="home" data-drawer-filter="forum">
                    <div class="m3-drawer-item-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                    </div>
                    <span class="m3-drawer-item-label">Community Forum</span>
                </button>

                <button class="m3-drawer-item <?= $page === 'clearances' ? 'active' : '' ?>" type="button" data-drawer-page="clearances">
                    <div class="m3-drawer-item-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <path d="m9 12 2 2 4-4"/>
                        </svg>
                    </div>
                    <span class="m3-drawer-item-label">Clearance &amp; Permits</span>
                </button>

                <button class="m3-drawer-item <?= $page === 'alerts' ? 'active' : '' ?>" type="button" data-drawer-page="alerts">
                    <div class="m3-drawer-item-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                    </div>
                    <span class="m3-drawer-item-label">Announcements</span>
                    <span class="m3-drawer-badge <?= $unreadCount === 0 ? 'hidden' : '' ?>" id="drawer-alert-count"><?= $unreadCount ?></span>
                </button>

                <button class="m3-drawer-item <?= $page === 'profile' ? 'active' : '' ?>" type="button" data-drawer-page="profile">
                    <div class="m3-drawer-item-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <span class="m3-drawer-item-label">Profile</span>
                </button>

                <?php if (in_array($user['role'], ['admin', 'treasurer'], true)): ?>
                    <p class="m3-drawer-section-label" style="margin-top: 14px;">Administration</p>
                    <button class="m3-drawer-item <?= $page === 'admin' ? 'active' : '' ?>" type="button" data-drawer-page="admin">
                        <div class="m3-drawer-item-icon">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                        </div>
                        <span class="m3-drawer-item-label">HOA Management</span>
                    </button>
                <?php endif; ?>
            </nav>

            <div class="m3-drawer-footer">
                <div class="m3-drawer-hotline">
                    <small>Village Guardhouse</small>
                    <strong>0917-882-4462</strong>
                </div>
                <a href="index.php?action=logout" class="m3-drawer-logout-btn">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    <span>Log Out</span>
                </a>
            </div>
        </aside>

        <!-- Main Viewport Scroll Content -->
        <main class="m3-viewport-content" id="m3-scroll-container">

            <!-- Pull-to-Refresh Visual Indicator -->
            <div class="m3-pull-indicator" id="pull-indicator" aria-hidden="true">
                <div class="m3-spinner-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                </div>
                <span class="m3-pull-label">Pull to refresh</span>
            </div>

            <!-- Flash Feedback Banner (Rendered as in-app push notification) -->
            <?php if ($flashMessage): ?>
                <div id="php-flash-data" class="hidden"
                     data-type="<?= e($flashMessage['type']) ?>"
                     data-message="<?= e($flashMessage['message']) ?>"
                     data-title="<?= $flashMessage['type'] === 'success' ? 'Success' : ($flashMessage['type'] === 'error' ? 'Notice' : 'Alert') ?>"></div>
            <?php endif; ?>

            <!-- ==========================================
                 TAB 1: HOME (Community Feed)
                 ========================================== -->
            <section class="page-view <?= $page === 'home' ? '' : 'hidden' ?>" id="page-home" aria-labelledby="home-heading" aria-hidden="<?= $page !== 'home' ? 'true' : 'false' ?>">
                <div class="m3-welcome-card">
                    <div class="m3-welcome-info">
                        <span class="m3-eyebrow">MABUHAY, <?= e(mb_strtoupper(explode(' ', trim($user['full_name']))[0])) ?>!</span>
                        <h1 id="home-heading">Greenside Community</h1>
                        <p><?= e($user['house_no'] ?: 'Verified Homeowner') ?></p>
                    </div>
                    <?php if ($unpaidTotal > 0): ?>
                        <div class="m3-mini-balance-chip" data-page="bills" role="button" tabindex="0">
                            <span class="mini-balance-label">Dues balance</span>
                            <strong>₱<?= number_format($unpaidTotal, 2) ?></strong>
                            <span class="mini-balance-action">Pay →</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sticky Category Filter Chips -->
                <div class="m3-sticky-chips" role="group" aria-label="Filter community posts">
                    <button class="m3-chip selected" type="button" data-feed-filter="all" aria-pressed="true">
                        All <span><?= count($posts) ?></span>
                    </button>
                    <button class="m3-chip" type="button" data-feed-filter="announcement" aria-pressed="false">
                        Announcements <span><?= $categoryCounts['announcement'] ?></span>
                    </button>
                    <button class="m3-chip" type="button" data-feed-filter="concern" aria-pressed="false">
                        Concerns <span><?= $categoryCounts['concern'] ?></span>
                    </button>
                    <button class="m3-chip" type="button" data-feed-filter="question" aria-pressed="false">
                        Questions <span><?= $categoryCounts['question'] ?></span>
                    </button>
                    <button class="m3-chip" type="button" data-feed-filter="suggestion" aria-pressed="false">
                        Suggestions <span><?= $categoryCounts['suggestion'] ?></span>
                    </button>
                </div>

                <!-- Post Cards Feed -->
                <div class="m3-feed-list" id="post-list">
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
                        <article class="m3-card post-card <?= $announcement ? 'pinned-post' : '' ?>" id="post-<?= (int) $post['id'] ?>"
                                 data-category="<?= e($postCategory) ?>" data-announcement="<?= $announcement ? 'true' : 'false' ?>">
                            <?php if ($announcement): ?>
                                <div class="m3-pinned-banner">
                                    <span class="banner-icon" aria-hidden="true">📢</span>
                                    <span><?= $post['is_pinned'] ? 'PINNED ANNOUNCEMENT' : 'HOA ADVISORY' ?></span>
                                    <span class="banner-time"><?= e($relativeTime($post['created_at'])) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="m3-post-author-row">
                                <span class="avatar <?= $announcement ? 'avatar-admin' : '' ?>"><?= e($initials($post['full_name'])) ?></span>
                                <div class="m3-author-meta">
                                    <strong><?= e($post['full_name']) ?></strong>
                                    <small><?= e($post['house_no'] ?: 'Resident') ?> · <?= e($relativeTime($post['created_at'])) ?></small>
                                </div>
                                <span class="post-tag <?= $announcement ? 'tag-announcement' : $statusClass ?>">
                                    <?= $announcement ? 'Official' : e(str_replace('_', ' ', ucfirst($post['status']))) ?>
                                </span>
                            </div>
                            <h2 class="m3-post-title"><?= e($post['title']) ?></h2>
                            <p class="m3-post-body"><?= nl2br(e($post['body'])) ?></p>
                            <?php if ($post['post_image']): ?>
                                <a class="m3-image-link" href="<?= e($post['post_image']) ?>" target="_blank" rel="noopener">
                                    📷 View attached image
                                </a>
                            <?php endif; ?>
                            <div class="m3-card-footer">
                                <div class="m3-actions-cluster">
                                    <button class="m3-touch-action like-button <?= $isReacted ? 'liked' : '' ?>" type="button" data-reaction data-post-id="<?= (int) $post['id'] ?>" aria-pressed="<?= $isReacted ? 'true' : 'false' ?>">
                                        <span class="action-icon" aria-hidden="true">👍</span>
                                        <span class="like-count"><?= (int) $post['reaction_count'] ?></span>
                                    </button>
                                    <button class="m3-touch-action" type="button" data-comment aria-expanded="false" aria-controls="comments-<?= (int) $post['id'] ?>">
                                        <span class="action-icon" aria-hidden="true">💬</span>
                                        <span class="comment-count"><?= (int) $post['comment_count'] ?></span>
                                    </button>
                                </div>
                                <span class="m3-views-count"><span aria-hidden="true">👁</span> <?= (int) $post['view_count'] ?></span>
                            </div>
                            <div class="comment-thread hidden" id="comments-<?= (int) $post['id'] ?>">
                                <?php foreach ($postComments as $comment): ?>
                                    <div class="comment-item">
                                        <span class="avatar comment-avatar"><?= e($initials($comment['full_name'])) ?></span>
                                        <p><strong><?= e($comment['full_name']) ?></strong><span><?= nl2br(e($comment['body'])) ?></span><small><?= e($relativeTime($comment['created_at'])) ?></small></p>
                                    </div>
                                <?php endforeach; ?>
                                <form class="inline-comment-form" data-comment-form data-post-id="<?= (int) $post['id'] ?>">
                                    <input id="reply-<?= (int) $post['id'] ?>" name="body" type="text" maxlength="2000" placeholder="Write a comment..." required>
                                    <button type="submit" class="m3-send-btn">Send</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <div class="m3-empty-card <?= $posts === [] ? '' : 'hidden' ?>" id="empty-feed">
                        <span class="empty-icon" aria-hidden="true">🏡</span>
                        <h3>No community posts yet</h3>
                        <p>Be the first to share an update, question, or neighbor suggestion.</p>
                        <button class="m3-btn-tonal" type="button" id="btn-empty-create">Create a Post</button>
                    </div>
                </div>
            </section>

            <!-- ==========================================
                 TAB 2: PERMITS & CLEARANCES (Mobile Card Feed)
                 ========================================== -->
            <section class="page-view <?= $page === 'clearances' ? '' : 'hidden' ?>" id="page-clearances" aria-labelledby="clearances-heading" aria-hidden="<?= $page !== 'clearances' ? 'true' : 'false' ?>">
                <div class="m3-page-header">
                    <div>
                        <h1 id="clearances-heading">Permits &amp; Clearances</h1>
                        <p class="m3-page-subtext">Gate passes, sticker RFID, and village utility clearances.</p>
                    </div>
                </div>

                <!-- Dues Compliance Helper Banner -->
                <div class="m3-banner-dues" role="note">
                    <div class="banner-badge-icon" aria-hidden="true">ℹ</div>
                    <div class="banner-copy">
                        <strong>Good Standing Policy:</strong> Active monthly HOA dues must be settled to date for clearance issuance and guardhouse validation.
                    </div>
                    <button class="m3-pill-link" type="button" data-page="bills">Check dues →</button>
                </div>

                <!-- Sticky Clearance Filter Chips -->
                <div class="m3-sticky-chips" role="group" aria-label="Filter clearances">
                    <button class="m3-chip selected" type="button" data-clearance-filter="all" aria-pressed="true">All <span><?= count($clearances) ?></span></button>
                    <button class="m3-chip" type="button" data-clearance-filter="approved" aria-pressed="false">Approved</button>
                    <button class="m3-chip" type="button" data-clearance-filter="pending" aria-pressed="false">In Review</button>
                    <button class="m3-chip" type="button" data-clearance-filter="action" aria-pressed="false">Needs Action</button>
                </div>

                <!-- Clearances Mobile Card Feed (Refactored from Table) -->
                <div class="m3-clearance-cards-list" id="clearance-cards-list">
                    <?php if ($clearances === []): ?>
                        <div class="m3-empty-card" id="clearance-empty-state">
                            <span class="empty-icon" aria-hidden="true">📋</span>
                            <h3>No clearance requests yet</h3>
                            <p>Need a subdivision vehicle sticker, gate pass for incoming trucks, or Meralco/water tapping clearance?</p>
                            <button class="m3-btn-primary" type="button" id="btn-request-first-empty">
                                ＋ Request New Clearance
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($clearances as $index => $c):
                            $isOpen = ($index === 0);
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
                                'approved' => '✓',
                                'pending_requirements' => '⚠',
                                'under_review' => '◷',
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

                            $catFilterGroup = match($appStatus) {
                                'approved' => 'approved',
                                'under_review' => 'pending',
                                'pending_requirements', 'disapproved' => 'action',
                                default => 'all'
                            };
                        ?>
                            <article class="m3-card clearance-card clearance-accordion <?= $isOpen ? 'is-open' : '' ?>" data-clearance-group="<?= e($catFilterGroup) ?>">
                                <!-- Accordion Header (Always Visible) -->
                                <button class="clearance-accordion-header" type="button" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>" aria-controls="clearance-body-<?= (int) $c['id'] ?>">
                                    <div class="m3-clearance-top">
                                        <div class="m3-clearance-type-cluster">
                                            <span class="m3-type-icon" aria-hidden="true">
                                                <?= match($c['clearance_type']) {
                                                    'vehicle_sticker' => '🚗',
                                                    'isp_install' => '📶',
                                                    'utility_meter' => '⚡',
                                                    'house_extension' => '🔨',
                                                    default => '🚚'
                                                } ?>
                                            </span>
                                            <div>
                                                <h2 class="m3-clearance-title"><?= e($c['type_title']) ?></h2>
                                                <span class="m3-ref-code"><?= e($c['reference_no']) ?></span>
                                            </div>
                                        </div>
                                        <time class="m3-card-date" datetime="<?= e($c['applied_date']) ?>"><?= e($dateAppliedFormatted) ?></time>
                                    </div>

                                    <div class="m3-status-badges-row">
                                        <div class="badges-group">
                                            <span class="clearance-badge <?= $payClass ?>"><?= e($payLabel) ?></span>
                                            <span class="clearance-pill <?= $appClass ?>">
                                                <span class="pill-dot" aria-hidden="true"><?= $appDot ?></span>
                                                <span><?= e($appLabel) ?></span>
                                            </span>
                                        </div>
                                        <span class="accordion-chevron" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                        </span>
                                    </div>
                                </button>

                                <!-- Accordion Collapsible Body -->
                                <div class="clearance-accordion-body <?= $isOpen ? '' : 'hidden' ?>" id="clearance-body-<?= (int) $c['id'] ?>">
                                    <div class="m3-clearance-subtext-box">
                                        <span class="subtext-detail"><?= e($c['subtext']) ?></span>
                                        <?php if (!empty($c['details'])): ?>
                                            <p class="clearance-details-desc"><?= e($c['details']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($c['remarks']): ?>
                                            <div class="m3-remarks-pill">
                                                <strong>Note:</strong> <?= e($c['remarks']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="m3-card-action-bar">
                                        <?php if ($actionType === 'upload_docs' || $appStatus === 'pending_requirements'): ?>
                                            <button type="button" class="m3-touch-btn action-upload"
                                                    data-clearance-id="<?= (int) $c['id'] ?>"
                                                    data-clearance-title="<?= e($c['type_title']) ?>"
                                                    data-required-doc="<?= e($c['lacking_document_name'] ?: 'Meralco / Water proof or OR/CR') ?>">
                                                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                Upload Lacking Docs
                                            </button>
                                        <?php elseif ($actionType === 'pay_gcash' || $payStatus === 'unpaid'): ?>
                                            <button type="button" class="m3-touch-btn action-pay"
                                                    data-clearance-ref="<?= e($c['reference_no']) ?>"
                                                    data-clearance-amount="<?= number_format((float) ($c['fee_amount'] ?: 150), 2) ?>"
                                                    data-clearance-title="<?= e($c['type_title']) ?>">
                                                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="2" y1="10" x2="22" y2="10" stroke="currentColor" stroke-width="2"/></svg>
                                                Pay via GCash (₱<?= number_format((float) ($c['fee_amount'] ?: 150), 0) ?>)
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="m3-touch-btn action-view"
                                                    data-clearance-ref="<?= e($c['reference_no']) ?>"
                                                    data-clearance-title="<?= e($c['type_title']) ?>"
                                                    data-clearance-subtext="<?= e($c['subtext']) ?>"
                                                    data-clearance-date="<?= e($dateAppliedFormatted) ?>"
                                                    data-clearance-status="<?= e($appLabel) ?>"
                                                    data-clearance-pay="<?= e($payLabel) ?>"
                                                    data-clearance-remarks="<?= e($c['remarks'] ?: 'Presented for gate and village inspection.') ?>">
                                                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="14 2 14 8 20 8" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                                View Slip / QR Pass
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ==========================================
                 TAB 3: BILLS & DUES (Mobile Feed)
                 ========================================== -->
            <section class="page-view <?= $page === 'bills' ? '' : 'hidden' ?>" id="page-bills" aria-labelledby="bills-heading" aria-hidden="<?= $page !== 'bills' ? 'true' : 'false' ?>">
                <div class="m3-page-header">
                    <div>
                        <h1 id="bills-heading">Bills &amp; Payments</h1>
                        <p class="m3-page-subtext">Monthly community dues, special assessments, and official receipts.</p>
                    </div>
                </div>

                <!-- Hero Outstanding Balance Card -->
                <div class="m3-hero-balance-card">
                    <span class="m3-hero-label">OUTSTANDING BALANCE</span>
                    <strong class="m3-hero-amount">₱<?= number_format($unpaidTotal, 2) ?></strong>
                    <div class="m3-hero-meta">
                        <?php if ($pendingTotal > 0): ?>
                            <span class="m3-pending-pill">₱<?= number_format($pendingTotal, 2) ?> pending review</span>
                        <?php else: ?>
                            <span>All filed payments verified</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sticky Category Filter Chips -->
                <div class="m3-sticky-chips" role="group" aria-label="Filter bills">
                    <?php foreach (['due' => 'Due', 'overdue' => 'Overdue', 'pending' => 'Pending', 'paid' => 'Paid'] as $status => $label): ?>
                        <button class="m3-chip <?= $status === 'due' ? 'selected' : '' ?>" type="button" data-bill-filter="<?= e($status) ?>" aria-pressed="<?= $status === 'due' ? 'true' : 'false' ?>">
                            <?= e($label) ?> <span><?= $billCounts[$status] ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Mobile Bills Feed -->
                <div class="m3-bills-feed" id="bill-list">
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
                        <article class="m3-card bill-card <?= $status === 'due' ? '' : 'hidden' ?>" data-status="<?= e($status) ?>">
                            <div class="m3-bill-top">
                                <div>
                                    <h2 class="m3-bill-title"><?= e($fee['title']) ?></h2>
                                    <small class="m3-bill-sub">
                                        <?= $status === 'pending' ? 'Submitted ' . e(date('M j, Y', strtotime($fee['payment_created_at']))) : 'Due ' . e(date('M j, Y', strtotime($fee['due_date']))) ?> · <?= e($fee['type'] === 'monthly' ? 'Monthly dues' : 'Special fee') ?>
                                    </small>
                                </div>
                                <span class="bill-status <?= $billStatusClass ?>"><?= e(ucfirst($status === 'pending' ? 'Reviewing' : $status)) ?></span>
                            </div>

                            <div class="m3-bill-amount-row">
                                <span class="amount-tag">Amount Due</span>
                                <strong class="amount-val">₱<?= number_format((float) $fee['amount'], 2) ?></strong>
                            </div>

                            <?php if ($fee['remarks']): ?>
                                <p class="m3-bill-remarks"><?= e($fee['remarks']) ?></p>
                            <?php endif; ?>

                            <?php if (in_array($status, ['due', 'overdue'], true)): ?>
                                <button class="m3-touch-btn btn-primary bill-pay" type="button"
                                        data-fee-id="<?= (int) $fee['id'] ?>"
                                        data-fee-title="<?= e($fee['title']) ?>"
                                        data-fee-amount="<?= number_format((float) $fee['amount'], 2) ?>">
                                    Submit Payment Proof
                                </button>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <p class="m3-empty-card <?= $fees === [] ? '' : 'hidden' ?>" id="empty-bills">No fees posted.</p>
                    <p class="m3-empty-card hidden" id="filtered-empty-bills">No bills under this filter.</p>
                </div>

                <!-- Payment History Accordion -->
                <div class="m3-history-toggle-box">
                    <button class="m3-touch-btn m3-btn-tonal" id="history-toggle" type="button">
                        View Payment History →
                    </button>
                </div>

                <div class="m3-history-sheet-wrap hidden" id="payment-history">
                    <h2 class="m3-section-title">Submitted Receipts &amp; Logs</h2>
                    <?php if ($paymentHistory === []): ?>
                        <p class="m3-empty-note">No payments submitted yet.</p>
                    <?php endif; ?>
                    <?php foreach ($paymentHistory as $payment): ?>
                        <div class="m3-history-item">
                            <div>
                                <strong><?= e($payment['title']) ?></strong>
                                <small><?= e(date('M j, Y', strtotime($payment['created_at']))) ?> · <?= e($payment['method']) ?><?= $payment['reference_no'] ? ' · ' . e($payment['reference_no']) : '' ?></small>
                            </div>
                            <div class="m3-history-right">
                                <strong>₱<?= number_format((float) $payment['amount'], 2) ?></strong>
                                <span class="bill-status <?= $payment['status'] === 'verified' ? 'status-paid' : ($payment['status'] === 'rejected' ? 'status-overdue' : 'status-pending') ?>">
                                    <?= e(ucfirst($payment['status'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- ==========================================
                 TAB 4: ANNOUNCEMENTS & ALERTS
                 ========================================== -->
            <section class="page-view <?= $page === 'alerts' ? '' : 'hidden' ?>" id="page-alerts" aria-labelledby="alerts-heading" aria-hidden="<?= $page !== 'alerts' ? 'true' : 'false' ?>">
                <div class="m3-page-header">
                    <div>
                        <h1 id="alerts-heading">Announcements &amp; Alerts</h1>
                        <p class="m3-page-subtext">Official updates and notices from the board.</p>
                    </div>
                    <?php if ($unreadCount > 0): ?>
                        <button class="m3-pill-btn" type="button" data-mark-all-read>Mark all read</button>
                    <?php endif; ?>
                </div>

                <div class="m3-alerts-feed">
                    <?php if ($notifications === []): ?>
                        <div class="m3-empty-card">
                            <span class="empty-icon" aria-hidden="true">🔔</span>
                            <h3>You’re all caught up</h3>
                            <p>New announcements and payment status updates will appear here.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($notifications as $notification): ?>
                        <article class="m3-alert-card <?= !(bool) $notification['is_read'] ? 'unread' : '' ?>" data-notification-id="<?= (int) $notification['id'] ?>">
                            <div class="m3-alert-icon-col">
                                <span class="alert-symbol <?= $notification['type'] === 'payment' ? 'alert-symbol-amber' : 'alert-symbol-green' ?>">
                                    <?= $notification['type'] === 'payment' ? '₱' : ($notification['type'] === 'reply' ? '↩' : '📢') ?>
                                </span>
                            </div>
                            <div class="m3-alert-body">
                                <h2><?= e(ucfirst($notification['type'])) ?></h2>
                                <p><?= e($notification['message']) ?></p>
                                <small><?= e(date('M j, Y · g:i a', strtotime($notification['created_at']))) ?></small>
                            </div>
                            <?php if (!(bool) $notification['is_read']): ?>
                                <span class="m3-unread-pip" aria-label="Unread update"></span>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- ==========================================
                 TAB 5: PROFILE
                 ========================================== -->
            <section class="page-view <?= $page === 'profile' ? '' : 'hidden' ?>" id="page-profile" aria-labelledby="profile-heading" aria-hidden="<?= $page !== 'profile' ? 'true' : 'false' ?>">
                <div class="m3-profile-card">
                    <div class="m3-profile-cover"></div>
                    <div class="m3-profile-avatar-row">
                        <span class="avatar avatar-profile"><?= e($initials($user['full_name'])) ?></span>
                        <div class="m3-profile-titles">
                            <h1 id="profile-heading"><?= e($user['full_name']) ?></h1>
                            <p><?= e($user['house_no'] ?: 'Verified Homeowner') ?></p>
                            <span class="m3-role-badge">Approved <?= e(ucfirst($user['role'])) ?></span>
                        </div>
                    </div>
                </div>

                <div class="m3-card m3-details-list">
                    <h2 class="m3-list-heading">Homeowner Details</h2>
                    <div class="m3-detail-row">
                        <span>Email</span>
                        <strong><?= e($user['email']) ?></strong>
                    </div>
                    <div class="m3-detail-row">
                        <span>House / Block / Lot</span>
                        <strong><?= e($user['house_no'] ?: 'Not provided') ?></strong>
                    </div>
                    <div class="m3-detail-row">
                        <span>Account Role</span>
                        <strong><?= e(ucfirst($user['role'])) ?></strong>
                    </div>
                </div>

                <?php if (in_array($user['role'], ['admin', 'treasurer'], true)): ?>
                    <div class="m3-card m3-admin-shortcut">
                        <div class="m3-shortcut-copy">
                            <strong>HOA Management Access</strong>
                            <p>Publish announcements, manage fees, and approve permits.</p>
                        </div>
                        <button class="m3-touch-btn m3-btn-tonal" type="button" data-page="admin">
                            Open Management Console →
                        </button>
                    </div>
                <?php endif; ?>

                <form method="post" action="index.php?action=logout" class="m3-logout-box">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="m3-touch-btn m3-btn-danger" type="submit">Sign Out of Account</button>
                </form>
            </section>

            <!-- ==========================================
                 HOA ADMINISTRATION (Management View)
                 ========================================== -->
            <?php if (in_array($user['role'], ['admin', 'treasurer'], true)): ?>
                <section class="page-view <?= $page === 'admin' ? '' : 'hidden' ?>" id="page-admin" aria-labelledby="admin-heading" aria-hidden="<?= $page !== 'admin' ? 'true' : 'false' ?>">
                    <div class="m3-page-header">
                        <div>
                            <h1 id="admin-heading">HOA Management</h1>
                            <p class="m3-page-subtext">Administer board announcements, fees, and residents.</p>
                        </div>
                        <button class="m3-pill-link" type="button" data-page="home">← Back</button>
                    </div>

                    <?php if ($user['role'] === 'admin'): ?>
                        <details class="m3-card admin-accordion">
                            <summary class="m3-accordion-head">
                                <strong>📢 Publish Announcement</strong>
                                <span class="accordion-arrow">▼</span>
                            </summary>
                            <form method="post" action="index.php?action=create-announcement" class="m3-sheet-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <label for="announcement-title">Title</label>
                                <input id="announcement-title" name="title" maxlength="200" required placeholder="Important Notice...">
                                <label for="announcement-body">Message</label>
                                <textarea id="announcement-body" name="body" rows="3" maxlength="10000" required placeholder="Details for all homeowners..."></textarea>
                                <label class="m3-checkbox-label" for="announcement-pinned">
                                    <input id="announcement-pinned" name="is_pinned" type="checkbox" value="1"> Pin to top of community feed
                                </label>
                                <button class="m3-touch-btn btn-primary" type="submit">Publish</button>
                            </form>
                        </details>
                    <?php endif; ?>

                    <details class="m3-card admin-accordion">
                        <summary class="m3-accordion-head">
                            <strong>💳 Issue HOA Fee</strong>
                            <span class="accordion-arrow">▼</span>
                        </summary>
                        <form method="post" action="index.php?action=create-fee" class="m3-sheet-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <label for="fee-title">Fee Title</label>
                            <input id="fee-title" name="title" maxlength="150" required placeholder="e.g. November 2026 Monthly Dues">
                            <label for="fee-description">Description</label>
                            <textarea id="fee-description" name="description" rows="2"></textarea>
                            <label for="fee-amount">Amount (PHP)</label>
                            <input id="fee-amount" name="amount" type="number" min="0.01" step="0.01" required placeholder="150.00">
                            <label for="fee-type">Category</label>
                            <select id="fee-type" name="type">
                                <option value="monthly">Monthly Dues</option>
                                <option value="special">Special Project</option>
                            </select>
                            <label for="fee-due-date">Due Date</label>
                            <input id="fee-due-date" name="due_date" type="date" required>
                            <button class="m3-touch-btn btn-primary" type="submit">Create Fee</button>
                        </form>
                    </details>

                    <?php if ($pendingResidents !== []): ?>
                        <div class="m3-card">
                            <h2 class="m3-list-heading">Pending Registrations (<?= count($pendingResidents) ?>)</h2>
                            <?php foreach ($pendingResidents as $resident): ?>
                                <div class="m3-admin-approval-row">
                                    <div>
                                        <strong><?= e($resident['full_name']) ?></strong>
                                        <small><?= e($resident['house_no']) ?> · <?= e($resident['email']) ?></small>
                                    </div>
                                    <div class="m3-approval-actions">
                                        <form method="post" action="index.php?action=approve-resident">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $resident['id'] ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <button class="m3-btn-small btn-primary" type="submit">Approve</button>
                                        </form>
                                        <form method="post" action="index.php?action=approve-resident">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $resident['id'] ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <button class="m3-btn-small m3-btn-danger" type="submit">Reject</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="m3-card">
                        <h2 class="m3-list-heading">Payments Awaiting Review (<?= count($pendingPayments) ?>)</h2>
                        <?php if ($pendingPayments === []): ?>
                            <p class="m3-empty-note">No payments awaiting review.</p>
                        <?php endif; ?>
                        <?php foreach ($pendingPayments as $payment): ?>
                            <div class="m3-admin-review-card">
                                <div class="m3-review-head">
                                    <strong><?= e($payment['title']) ?> · ₱<?= number_format((float) $payment['amount'], 2) ?></strong>
                                    <small><?= e($payment['full_name']) ?> · <?= e($payment['house_no']) ?> · <?= e($payment['method']) ?></small>
                                    <?php if ($payment['proof_image']): ?>
                                        <a href="index.php?action=receipt&amp;id=<?= (int) $payment['id'] ?>" target="_blank" rel="noopener" class="m3-pill-link">
                                            View Submitted Receipt ↗
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <form method="post" action="index.php?action=review-payment" class="m3-review-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                                    <input type="text" name="remarks" maxlength="2000" placeholder="Optional verification note">
                                    <div class="m3-approval-actions">
                                        <button class="m3-btn-small btn-primary" name="status" value="verified" type="submit">Verify</button>
                                        <button class="m3-btn-small m3-btn-danger" name="status" value="rejected" type="submit">Reject</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        </main>

        <!-- Material 3 Floating Action Button (Adaptive) -->
        <button class="m3-fab" id="m3-main-fab" type="button" aria-label="Action">
            <span class="m3-fab-icon" aria-hidden="true">＋</span>
            <span class="m3-fab-label" id="m3-fab-label">Create Post</span>
        </button>

        <!-- Material 3 Bottom Navigation Bar (5 Primary Tabs) -->
        <nav class="m3-bottom-nav" aria-label="Material 3 Bottom Navigation">
            <button class="m3-nav-item <?= $page === 'home' ? 'active' : '' ?>" type="button" data-page="home">
                <div class="m3-nav-icon-container">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 10l9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10z"/>
                    </svg>
                </div>
                <span class="m3-nav-label">Home</span>
            </button>
            <button class="m3-nav-item <?= $page === 'clearances' ? 'active' : '' ?>" type="button" data-page="clearances">
                <div class="m3-nav-icon-container">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                </div>
                <span class="m3-nav-label">Clearances</span>
            </button>
            <button class="m3-nav-item <?= $page === 'bills' ? 'active' : '' ?>" type="button" data-page="bills">
                <div class="m3-nav-icon-container">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <line x1="2" y1="10" x2="22" y2="10"/>
                        <path d="M6 15h2"/>
                    </svg>
                </div>
                <span class="m3-nav-label">Bills &amp; Dues</span>
            </button>
            <button class="m3-nav-item <?= $page === 'alerts' ? 'active' : '' ?>" type="button" data-page="alerts">
                <div class="m3-nav-icon-container">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <span class="m3-nav-badge <?= $unreadCount === 0 ? 'hidden' : '' ?>" id="bottom-alert-count"><?= $unreadCount ?></span>
                </div>
                <span class="m3-nav-label">Announce</span>
            </button>
            <button class="m3-nav-item <?= $page === 'profile' ? 'active' : '' ?>" type="button" data-page="profile">
                <div class="m3-nav-icon-container">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <span class="m3-nav-label">Profile</span>
            </button>
        </nav>

        <!-- Android Gesture Navigation Bar -->
        <footer class="android-gesture-bar" aria-hidden="true">
            <div class="android-gesture-pill"></div>
        </footer>

    </div><!-- /android-device-frame -->

    <!-- ==========================================================
         MATERIAL 3 BOTTOM SHEETS (Slide-Up Native Modal Drawers)
         ========================================================== -->

    <!-- Scrim Backdrop for all Bottom Sheets -->
    <div class="m3-scrim" id="m3-scrim" aria-hidden="true"></div>

    <!-- 1. Bottom Sheet: Create Community Post -->
    <div class="m3-bottom-sheet" id="sheet-create-post" role="dialog" aria-modal="true" aria-labelledby="sheet-post-title">
        <div class="m3-sheet-handle-wrap" data-close-sheet><div class="m3-sheet-handle"></div></div>
        <div class="m3-sheet-header">
            <h2 id="sheet-post-title">Create Community Post</h2>
            <button class="m3-close-btn" type="button" data-close-sheet aria-label="Close">×</button>
        </div>
        <div class="m3-sheet-scrollable">
            <form method="post" action="index.php?action=create-post" class="m3-sheet-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="m3-author-pill">
                    <span class="avatar avatar-small"><?= e($initials($user['full_name'])) ?></span>
                    <div>
                        <strong><?= e($user['full_name']) ?></strong>
                        <small>Posting to Greenside Homes</small>
                    </div>
                </div>
                <label for="post-title">Title</label>
                <input id="post-title" name="title" type="text" maxlength="200" required placeholder="What's on your mind?">
                <label for="post-category">Category</label>
                <select id="post-category" name="category">
                    <option value="concern">Raise a Concern</option>
                    <option value="question">Ask a Question</option>
                    <option value="suggestion">Share a Suggestion</option>
                </select>
                <label for="post-message">Message</label>
                <textarea id="post-message" name="body" rows="4" maxlength="10000" required placeholder="Write details for your neighbors..."></textarea>
                <p class="m3-form-hint">Please keep all discussions respectful and neighborly.</p>
                <button class="m3-touch-btn btn-primary" type="submit">Publish to Feed</button>
            </form>
        </div>
    </div>

    <!-- 2. Bottom Sheet: Request Clearance -->
    <div class="m3-bottom-sheet" id="sheet-clearance-request" role="dialog" aria-modal="true" aria-labelledby="sheet-clearance-title">
        <div class="m3-sheet-handle-wrap" data-close-sheet><div class="m3-sheet-handle"></div></div>
        <div class="m3-sheet-header">
            <div>
                <p class="m3-eyebrow">SUBDIVISION CLEARANCE</p>
                <h2 id="sheet-clearance-title">Request Clearance or Pass</h2>
            </div>
            <button class="m3-close-btn" type="button" data-close-sheet aria-label="Close">×</button>
        </div>
        <div class="m3-sheet-scrollable">
            <form method="post" action="index.php?action=request-clearance" class="m3-sheet-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="m3-dialog-advisory">
                    <strong>Monthly Dues Verification:</strong> Active monthly dues must be settled to date for clearance release and gate pass issuance.
                </div>
                <label for="select-clearance-type">Clearance Category</label>
                <select id="select-clearance-type" name="clearance_type" required>
                    <option value="vehicle_sticker">Vehicle / Motorcycle Sticker (₱150 · RFID / Gate Sticker)</option>
                    <option value="isp_install">ISP / Fiber Internet Installation (Free · Converge, PLDT, Globe)</option>
                    <option value="utility_meter">Water / Electric Meter Application (Free · Meralco / Water District)</option>
                    <option value="house_extension">House Extension / Minor Renovation (Gate, fence, grill)</option>
                    <option value="gate_pass">Moving In / Out Gate Pass (₱150 · Delivery truck / Hauling)</option>
                </select>
                <label for="input-clearance-subtext" id="label-clearance-subtext">Plate Number &amp; Vehicle Model</label>
                <input id="input-clearance-subtext" name="subtext" type="text" maxlength="255" placeholder="e.g., NDM-4821 · Honda Click 125i" required>
                <label for="input-clearance-details">Additional Details &amp; Schedule</label>
                <textarea id="input-clearance-details" name="details" rows="3" maxlength="1000" placeholder="e.g., Expected date, delivery truck forwarder, contractor..."></textarea>
                <label for="input-clearance-file">Attach Supporting Document (Optional)</label>
                <input id="input-clearance-file" name="document_file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">
                <p class="m3-form-hint">Upload OR/CR, Meralco Yellow Card, or contractor waiver (JPG, PNG, PDF up to 5 MB).</p>
                <button class="m3-touch-btn btn-primary" type="submit">Submit Clearance Request</button>
            </form>
        </div>
    </div>

    <!-- 3. Bottom Sheet: Submit Payment Proof -->
    <div class="m3-bottom-sheet" id="sheet-payment" role="dialog" aria-modal="true" aria-labelledby="sheet-payment-title">
        <div class="m3-sheet-handle-wrap" data-close-sheet><div class="m3-sheet-handle"></div></div>
        <div class="m3-sheet-header">
            <div>
                <p class="m3-eyebrow">BILL SETTLEMENT</p>
                <h2 id="sheet-payment-title">Submit Payment Proof</h2>
                <small id="selected-bill-label" class="m3-target-bill-sub">Monthly Dues</small>
            </div>
            <button class="m3-close-btn" type="button" data-close-sheet aria-label="Close">×</button>
        </div>
        <div class="m3-sheet-scrollable">
            <form method="post" action="index.php?action=submit-payment" class="m3-sheet-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input id="proof-fee-id" type="hidden" name="fee_id">
                <div class="m3-dialog-advisory">
                    <strong>Payment Verification:</strong> Pay via GCash, Maya, or bank transfer using the HOA payment details, then submit your receipt screenshot below.
                </div>
                <label for="payment-method">Payment Channel</label>
                <select id="payment-method" name="method" required>
                    <option value="e-wallet">GCash / Maya E-Wallet</option>
                    <option value="bank">Bank Transfer</option>
                    <option value="cash">Cash (Admin Office)</option>
                </select>
                <label for="reference-number">Transaction Reference Number</label>
                <input id="reference-number" name="reference_no" type="text" maxlength="150" placeholder="e.g. GCash Ref No. 1002938472">
                <label for="receipt-file">Receipt or Screenshot Proof</label>
                <input id="receipt-file" name="proof_image" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                <p class="m3-form-hint">JPG, PNG, WebP, or PDF up to 5 MB.</p>
                <button class="m3-touch-btn btn-primary" type="submit">Submit for Review</button>
            </form>
        </div>
    </div>

    <!-- 4. Bottom Sheet: Digital Gate Pass Slip / QR -->
    <div class="m3-bottom-sheet" id="sheet-slip" role="dialog" aria-modal="true" aria-labelledby="sheet-slip-title">
        <div class="m3-sheet-handle-wrap" data-close-sheet><div class="m3-sheet-handle"></div></div>
        <div class="m3-sheet-header">
            <div>
                <p class="m3-eyebrow">OFFICIAL GATE PASS</p>
                <h2 id="sheet-slip-title">Subdivision Entry Slip</h2>
            </div>
            <button class="m3-close-btn" type="button" data-close-sheet aria-label="Close">×</button>
        </div>
        <div class="m3-sheet-scrollable">
            <div class="slip-printable-card">
                <div class="slip-header">
                    <div class="slip-brand">
                        <span class="m3-brand-logo"><img src="Assets/logogreenside.jpg" alt=""></span>
                        <div>
                            <strong>Greenside Homes HOA</strong>
                            <small>SUBDIVISION GATE CLEARANCE</small>
                        </div>
                    </div>
                    <div class="slip-ref-box">
                        <span class="slip-ref-label">REF #</span>
                        <strong id="slip-ref-no">CLR-2026-0000</strong>
                    </div>
                </div>
                <div class="slip-title-banner">
                    <h3 id="slip-type-title">Subdivision Clearance</h3>
                    <span id="slip-status-pill" class="clearance-pill pill-app-approved">Approved</span>
                </div>
                <div class="slip-details-grid">
                    <div class="slip-detail-item">
                        <small>Homeowner</small>
                        <strong><?= e($user['full_name']) ?></strong>
                    </div>
                    <div class="slip-detail-item">
                        <small>House / Lot</small>
                        <strong><?= e($user['house_no'] ?: 'Resident') ?></strong>
                    </div>
                    <div class="slip-detail-item">
                        <small>Details</small>
                        <strong id="slip-subtext">Plate &amp; Model</strong>
                    </div>
                    <div class="slip-detail-item">
                        <small>Date Applied</small>
                        <strong id="slip-date">DD MMM YYYY</strong>
                    </div>
                    <div class="slip-detail-item">
                        <small>Payment Status</small>
                        <strong id="slip-payment-condition">Paid</strong>
                    </div>
                    <div class="slip-detail-item">
                        <small>HOA Standing</small>
                        <strong style="color: var(--green);">Active (Dues Settled)</strong>
                    </div>
                </div>
                <div class="slip-qr-section">
                    <div class="slip-qr-box">
                        <!-- Clean Vector QR Graphic -->
                        <svg viewBox="0 0 120 120" width="96" height="96" aria-label="QR Code">
                            <rect width="120" height="120" fill="#ffffff" />
                            <rect x="10" y="10" width="30" height="30" fill="#15764f" rx="3" />
                            <rect x="16" y="16" width="18" height="18" fill="#ffffff" />
                            <rect x="20" y="20" width="10" height="10" fill="#15764f" />
                            <rect x="80" y="10" width="30" height="30" fill="#15764f" rx="3" />
                            <rect x="86" y="16" width="18" height="18" fill="#ffffff" />
                            <rect x="90" y="20" width="10" height="10" fill="#15764f" />
                            <rect x="10" y="80" width="30" height="30" fill="#15764f" rx="3" />
                            <rect x="16" y="86" width="18" height="18" fill="#ffffff" />
                            <rect x="20" y="90" width="10" height="10" fill="#15764f" />
                            <rect x="48" y="12" width="6" height="6" fill="#15764f" />
                            <rect x="58" y="12" width="6" height="6" fill="#15764f" />
                            <rect x="48" y="48" width="12" height="12" fill="#15764f" />
                            <rect x="68" y="48" width="6" height="6" fill="#15764f" />
                            <rect x="80" y="68" width="6" height="6" fill="#15764f" />
                            <rect x="48" y="84" width="6" height="6" fill="#15764f" />
                            <rect x="60" y="88" width="6" height="6" fill="#15764f" />
                        </svg>
                    </div>
                    <div class="slip-qr-instructions">
                        <strong>Guardhouse QR Scan</strong>
                        <p>Present to village security guard upon entering the gate or during contractor check-in.</p>
                        <small id="slip-remarks" class="slip-remarks-box">Valid for gate entry.</small>
                    </div>
                </div>
            </div>
            <div class="m3-sheet-actions">
                <button class="m3-touch-btn m3-btn-tonal" type="button" id="btn-print-slip">Print Pass</button>
                <button class="m3-touch-btn btn-primary" type="button" data-close-sheet>Done</button>
            </div>
        </div>
    </div>

    <!-- 5. Bottom Sheet: Upload Lacking Document -->
    <div class="m3-bottom-sheet" id="sheet-upload-docs" role="dialog" aria-modal="true" aria-labelledby="sheet-upload-title">
        <div class="m3-sheet-handle-wrap" data-close-sheet><div class="m3-sheet-handle"></div></div>
        <div class="m3-sheet-header">
            <div>
                <p class="m3-eyebrow">REQUIREMENT COMPLIANCE</p>
                <h2 id="sheet-upload-title">Upload Lacking Document</h2>
                <small id="upload-target-title" class="m3-target-bill-sub">Clearance</small>
            </div>
            <button class="m3-close-btn" type="button" data-close-sheet aria-label="Close">×</button>
        </div>
        <div class="m3-sheet-scrollable">
            <form method="post" action="index.php?action=upload-clearance-doc" class="m3-sheet-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="clearance_id" id="upload-clearance-id" value="">
                <div class="upload-requirement-box">
                    <strong id="upload-doc-name">Meralco Yellow Card / Water Order</strong>
                    <p>Submit a clear photo or PDF scan so the HOA board can approve your application.</p>
                </div>
                <label for="upload-doc-file">Select File</label>
                <input id="upload-doc-file" name="document_file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                <button class="m3-touch-btn btn-primary" type="submit">Submit Document</button>
            </form>
        </div>
    </div>

    <!-- 6. Bottom Sheet: Pay via GCash -->
    <div class="m3-bottom-sheet" id="sheet-gcash" role="dialog" aria-modal="true" aria-labelledby="sheet-gcash-title">
        <div class="m3-sheet-handle-wrap" data-close-sheet><div class="m3-sheet-handle"></div></div>
        <div class="m3-sheet-header">
            <div>
                <p class="m3-eyebrow">GCASH PAYMENT</p>
                <h2 id="sheet-gcash-title">Pay Clearance Fee</h2>
            </div>
            <button class="m3-close-btn" type="button" data-close-sheet aria-label="Close">×</button>
        </div>
        <div class="m3-sheet-scrollable">
            <div class="gcash-box">
                <div class="gcash-amount-banner">
                    <span>TOTAL AMOUNT DUE</span>
                    <strong id="gcash-amount-display">₱150.00</strong>
                </div>
                <div class="gcash-instructions-list">
                    <div class="gcash-inst-row">
                        <span>1</span>
                        <div>
                            <strong>Open GCash App</strong>
                            <p>Go to Send Money → Express Send</p>
                        </div>
                    </div>
                    <div class="gcash-inst-row">
                        <span>2</span>
                        <div>
                            <strong>Send to HOA Official GCash</strong>
                            <p class="gcash-number-pill">0917-882-4462 (Greenside HOA Inc.)</p>
                        </div>
                    </div>
                    <div class="gcash-inst-row">
                        <span>3</span>
                        <div>
                            <strong>Put Reference in Message</strong>
                            <p>Ref: <strong id="gcash-ref-display">CLR-2026-0000</strong></p>
                        </div>
                    </div>
                </div>
                <div class="gcash-reminder-note">
                    Once paid, your clearance status will update upon admin SMS confirmation.
                </div>
                <button class="m3-touch-btn btn-primary" type="button" id="btn-gcash-done" data-close-sheet>I've Sent the Payment</button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <script src="Assets/script.js" defer></script>
</body>
</html>