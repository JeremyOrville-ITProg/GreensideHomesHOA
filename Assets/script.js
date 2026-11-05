// ========================================================
// Assets/script.js - Greenside Homes HOA Portal Frontend Engine
// Minimalist, De-AI Design, Filipino Community Mock Data,
// and Facebook-Style Comment Architecture.
// ========================================================

// ---------------- Application State ----------------
const state = {
    token: localStorage.getItem('hoa_token') || null,
    currentUser: null,
    currentPage: 'home',
    activeForumCategory: 'All',
    activeVerificationFilter: 'Pending',
    dues: [],
    receiptBase64: null,
    postImageBase64: null,
    expandedComments: new Set(),
    lastBroadcastId: null
};

// Try loading persisted user
try {
    const savedUser = localStorage.getItem('hoa_user');
    if (savedUser) state.currentUser = JSON.parse(savedUser);
} catch (e) {
    state.currentUser = null;
}

// Page Titles Map
const titles = {
    'home': 'Home Dashboard',
    'payments': 'Payments & Dues Assessment',
    'verification': 'Payment Verification Queue',
    'forum': 'Community Discussion Forum',
    'announcements': 'Official HOA Announcements',
    'profile': 'Resident & Account Profile'
};

// ---------------- Initialization ----------------
document.addEventListener('DOMContentLoaded', async () => {
    // If no user is logged in, default to the demo resident for seamless evaluation
    if (!state.token || !state.currentUser) {
        await quickLogin('johndoe', 'password', false);
    } else {
        updateAuthUI();
    }

    // Initial data loading
    loadCurrentPageData();
    checkPushPermissionState();

    // Start background polling for announcement broadcasts
    setInterval(pollLatestBroadcast, 8000);
});

// Helper for authenticated API calls
async function apiRequest(endpoint, method = 'GET', body = null) {
    const headers = { 'Content-Type': 'application/json' };
    if (state.token) {
        headers['Authorization'] = `Bearer ${state.token}`;
    }

    const options = { method, headers };
    if (body) options.body = JSON.stringify(body);

    const res = await fetch(endpoint, options);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.error || `Request failed (${res.status})`);
    }
    return data;
}

// ---------------- Navigation ----------------
function switchPage(pageId, event) {
    if (event) event.preventDefault();

    state.currentPage = pageId;

    // 1. Hide all page views
    const pages = document.querySelectorAll('.page-view');
    pages.forEach(p => p.classList.add('hidden'));

    // 2. Show active page
    const targetPage = document.getElementById('page-' + pageId);
    if (targetPage) targetPage.classList.remove('hidden');

    // 3. Update topbar header
    const titleEl = document.getElementById('page-title');
    if (titleEl) titleEl.textContent = titles[pageId] || 'Dashboard';

    // 4. Update sidebar nav active link
    const navLinks = document.querySelectorAll('.sidebar-nav a');
    navLinks.forEach(l => l.classList.remove('active'));
    const activeLink = document.getElementById('nav-' + pageId);
    if (activeLink) activeLink.classList.add('active');

    // 5. Fetch fresh data for the page
    loadCurrentPageData();
}

function loadCurrentPageData() {
    fetchAnnouncements();
    fetchDues();
    if (state.currentPage === 'home' || state.currentPage === 'payments') {
        fetchMyPayments();
    }
    if (state.currentPage === 'verification' && state.currentUser?.role === 'admin') {
        fetchAdminVerification();
    }
    if (state.currentPage === 'forum') {
        fetchForumPosts();
    }
}

// ================= MODULE 1: AUTHENTICATION & ROLES ================= //

function updateAuthUI() {
    const user = state.currentUser;
    const isLogged = !!user;
    const isAdmin = user?.role === 'admin';

    // Sidebar footer
    const nameEl = document.getElementById('sidebar-user-name');
    const roleBadge = document.getElementById('sidebar-role-badge');
    const authBtn = document.getElementById('btn-sidebar-auth');

    if (nameEl) nameEl.textContent = isLogged ? user.name : 'Guest';
    if (roleBadge) {
        roleBadge.textContent = isLogged ? (isAdmin ? 'Admin / Treasurer' : 'Resident') : 'Visitor';
        roleBadge.className = 'badge badge-role' + (isAdmin ? ' admin' : '');
    }
    if (authBtn) {
        authBtn.textContent = isLogged ? 'Log Out' : 'Log In';
        authBtn.onclick = isLogged ? logout : () => openAuthModal('login');
    }

    // Topbar
    const unitEl = document.getElementById('user-unit-display');
    const topRoleBadge = document.getElementById('topbar-role-badge');
    const quickSwitchBtn = document.getElementById('btn-quick-switch');

    if (unitEl) unitEl.textContent = user?.block_lot || 'Greenside Subdivision';
    if (topRoleBadge) {
        topRoleBadge.textContent = isAdmin ? 'Admin / Treasurer' : 'Resident';
        topRoleBadge.className = 'badge badge-role' + (isAdmin ? ' admin' : '');
    }
    if (quickSwitchBtn) {
        quickSwitchBtn.textContent = isAdmin ? 'Switch to Resident' : 'Switch to Admin';
    }

    // Profile Page
    const profName = document.getElementById('profile-name');
    const profUser = document.getElementById('profile-username');
    const profRole = document.getElementById('profile-role');
    const profUnit = document.getElementById('profile-unit');
    if (profName) profName.textContent = user?.name || '—';
    if (profUser) profUser.textContent = user?.username || '—';
    if (profRole) {
        profRole.textContent = isAdmin ? 'Admin / Treasurer' : 'Resident';
        profRole.className = 'badge badge-role' + (isAdmin ? ' admin' : '');
    }
    if (profUnit) profUnit.textContent = user?.block_lot || '—';

    // Toggle Admin-only UI elements
    const adminElements = document.querySelectorAll('.admin-only');
    adminElements.forEach(el => {
        if (isAdmin) {
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    });

    if (isAdmin) {
        updatePendingCounter();
    }
}

async function quickLogin(username, password, showToastAlert = true) {
    try {
        const res = await apiRequest('/api/auth/login', 'POST', { username, password });
        state.token = res.token;
        state.currentUser = res.user;
        localStorage.setItem('hoa_token', res.token);
        localStorage.setItem('hoa_user', JSON.stringify(res.user));

        updateAuthUI();
        loadCurrentPageData();

        if (showToastAlert) {
            showToast(`Logged in as ${res.user.name} (${res.user.role.toUpperCase()})`, 'success');
        }
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function toggleDemoRole() {
    if (state.currentUser?.role === 'admin') {
        quickLogin('johndoe', 'password');
    } else {
        quickLogin('admin', 'password');
    }
}

function logout() {
    state.token = null;
    state.currentUser = null;
    localStorage.removeItem('hoa_token');
    localStorage.removeItem('hoa_user');
    updateAuthUI();
    showToast('Logged out successfully.', 'info');
    switchPage('home');
}

function openAuthModal(tab = 'login') {
    switchAuthTab(tab);
    openModal('modal-auth');
}

function switchAuthTab(tab) {
    const tabLogin = document.getElementById('tab-login');
    const tabSignup = document.getElementById('tab-signup');
    const formLogin = document.getElementById('form-login');
    const formSignup = document.getElementById('form-signup');
    const title = document.getElementById('auth-modal-title');

    if (tab === 'login') {
        tabLogin.classList.add('active');
        tabSignup.classList.remove('active');
        formLogin.classList.remove('hidden');
        formSignup.classList.add('hidden');
        title.textContent = 'Sign In to Greenside HOA';
    } else {
        tabLogin.classList.remove('active');
        tabSignup.classList.add('active');
        formLogin.classList.add('hidden');
        formSignup.classList.remove('hidden');
        title.textContent = 'Register Resident Account';
    }
}

async function handleLoginSubmit(event) {
    event.preventDefault();
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;

    try {
        const res = await apiRequest('/api/auth/login', 'POST', { username, password });
        state.token = res.token;
        state.currentUser = res.user;
        localStorage.setItem('hoa_token', res.token);
        localStorage.setItem('hoa_user', JSON.stringify(res.user));

        closeModal('modal-auth');
        updateAuthUI();
        loadCurrentPageData();
        showToast(`Welcome back, ${res.user.name}!`, 'success');
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function handleSignupSubmit(event) {
    event.preventDefault();
    const name = document.getElementById('signup-name').value.trim();
    const username = document.getElementById('signup-username').value.trim();
    const block_lot = document.getElementById('signup-block-lot').value.trim();
    const password = document.getElementById('signup-password').value;

    try {
        const res = await apiRequest('/api/auth/register', 'POST', {
            name, username, block_lot, password
        });
        state.token = res.token;
        state.currentUser = res.user;
        localStorage.setItem('hoa_token', res.token);
        localStorage.setItem('hoa_user', JSON.stringify(res.user));

        closeModal('modal-auth');
        updateAuthUI();
        loadCurrentPageData();
        showToast(`Account created for ${res.user.name}!`, 'success');
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ================= MODULE 2: DUES & PAYMENT VERIFICATION ================= //

async function fetchDues() {
    try {
        const res = await apiRequest('/api/dues');
        state.dues = res.dues || [];

        // Populate Dues list in Payments tab
        const container = document.getElementById('active-dues-container');
        if (container) {
            if (state.dues.length === 0) {
                container.innerHTML = '<p class="text-muted">No monthly dues assessed yet.</p>';
            } else {
                container.innerHTML = state.dues.map(d => `
                    <div class="announcement-card">
                        <div class="announcement-header">
                            <span class="announcement-title">${escapeHtml(d.title)}</span>
                            <span class="balance-amount" style="font-size:1.15rem; margin:0;">₱ ${formatCurrency(d.amount)}</span>
                        </div>
                        <p class="announcement-text">${escapeHtml(d.description || 'Standard monthly homeowners assessment.')}</p>
                        <small class="announcement-meta">Due by: <strong>${d.due_date}</strong></small>
                    </div>
                `).join('');
            }
        }

        // Populate Payment Proof select dropdown
        const select = document.getElementById('payment-due-select');
        if (select) {
            select.innerHTML = '<option value="">-- Choose Due Assessment --</option>' +
                state.dues.map(d => `<option value="${d.id}" data-amount="${d.amount}">${escapeHtml(d.title)} (₱ ${formatCurrency(d.amount)})</option>`).join('');
        }
    } catch (err) {
        console.error('fetchDues error:', err);
    }
}

function onDueSelectChange() {
    const select = document.getElementById('payment-due-select');
    const amountInput = document.getElementById('payment-amount');
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.dataset.amount) {
        amountInput.value = selectedOption.dataset.amount;
    }
}

async function fetchMyPayments() {
    if (!state.token) return;

    try {
        const res = await apiRequest('/api/payments/my');
        const payments = res.payments || [];

        // Update Home Balance Card
        const balanceAmt = document.getElementById('home-balance-amount');
        const balanceBadge = document.getElementById('home-balance-badge');
        const balanceDesc = document.getElementById('home-balance-desc');

        if (balanceAmt) balanceAmt.textContent = `₱ ${formatCurrency(res.outstanding)}`;
        if (balanceBadge) {
            balanceBadge.textContent = res.latestDueStatus;
            balanceBadge.className = 'badge ' + (
                res.latestDueStatus === 'Paid' ? 'badge-success' :
                res.latestDueStatus === 'Pending review' ? 'badge-warning' : 'badge-danger'
            );
        }
        if (balanceDesc) {
            balanceDesc.textContent = res.latestDue ? `${res.latestDue.title} (Due: ${res.latestDue.due_date})` : 'All dues up to date.';
        }

        // Update Resident Billing History Table
        const tbody = document.getElementById('my-payments-table-body');
        if (tbody) {
            if (payments.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No payment proofs submitted yet.</td></tr>';
            } else {
                tbody.innerHTML = payments.map(p => `
                    <tr>
                        <td><strong>${escapeHtml(p.due_title)}</strong></td>
                        <td>₱ ${formatCurrency(p.amount)}</td>
                        <td><code>${escapeHtml(p.ref_number)}</code></td>
                        <td>${formatDate(p.submitted_at)}</td>
                        <td>
                            <img src="${p.proof_image}" alt="Receipt" class="receipt-thumb" 
                                 onclick="inspectReceipt('${p.proof_image}', '${escapeHtml(state.currentUser?.name)}', '${escapeHtml(p.due_title)}', '${p.amount}', '${escapeHtml(p.ref_number)}')">
                        </td>
                        <td>
                            <span class="badge ${p.status === 'Approved' ? 'badge-success' : p.status === 'Pending' ? 'badge-warning' : 'badge-danger'}">
                                ${p.status}
                            </span>
                        </td>
                        <td><small class="text-muted">${escapeHtml(p.remarks || '—')}</small></td>
                    </tr>
                `).join('');
            }
        }
    } catch (err) {
        console.error('fetchMyPayments error:', err);
    }
}

function openPaymentModal() {
    if (!state.token) {
        openAuthModal('login');
        return;
    }
    state.receiptBase64 = null;
    document.getElementById('form-submit-payment').reset();
    document.getElementById('receipt-preview-container').classList.add('hidden');
    openModal('modal-payment');
}

function previewReceiptImage(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        state.receiptBase64 = e.target.result;
        const img = document.getElementById('receipt-preview-img');
        const container = document.getElementById('receipt-preview-container');
        img.src = state.receiptBase64;
        container.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}

async function handlePaymentSubmit(event) {
    event.preventDefault();
    const due_id = document.getElementById('payment-due-select').value;
    const amount = document.getElementById('payment-amount').value;
    const ref_number = document.getElementById('payment-ref-number').value.trim();

    if (!state.receiptBase64) {
        showToast('Please attach a photo or screenshot of the payment receipt.', 'error');
        return;
    }

    try {
        const res = await apiRequest('/api/payments', 'POST', {
            due_id: parseInt(due_id, 10),
            amount: parseFloat(amount),
            ref_number,
            proof_image: state.receiptBase64
        });

        closeModal('modal-payment');
        showToast(res.message, 'success');
        fetchMyPayments();
        if (state.currentUser?.role === 'admin') updatePendingCounter();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// Admin: Create Monthly Due
function openDueModal() {
    document.getElementById('form-create-due').reset();
    openModal('modal-create-due');
}

async function handleCreateDueSubmit(event) {
    event.preventDefault();
    const title = document.getElementById('due-title').value.trim();
    const amount = document.getElementById('due-amount').value;
    const due_date = document.getElementById('due-date').value;
    const description = document.getElementById('due-description').value.trim();

    try {
        await apiRequest('/api/dues', 'POST', { title, amount, due_date, description });
        closeModal('modal-create-due');
        showToast('Monthly due assessment published successfully!', 'success');
        fetchDues();
        fetchMyPayments();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// Admin Verification Dashboard
let adminPaymentsCache = [];

async function fetchAdminVerification() {
    try {
        const res = await apiRequest('/api/payments/all');
        adminPaymentsCache = res.payments || [];
        renderAdminVerification();
        updatePendingCounter();
    } catch (err) {
        console.error('fetchAdminVerification error:', err);
    }
}

function updatePendingCounter() {
    const pendingCount = adminPaymentsCache.filter(p => p.status === 'Pending').length;
    const badge = document.getElementById('pending-badge-count');
    if (badge) {
        if (pendingCount > 0) {
            badge.textContent = pendingCount;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
}

function filterVerification(filter, event) {
    state.activeVerificationFilter = filter;
    const pills = document.querySelectorAll('#verification-filter-pills .pill');
    pills.forEach(p => p.classList.remove('active'));
    if (event?.target) event.target.classList.add('active');
    renderAdminVerification();
}

function renderAdminVerification() {
    const tbody = document.getElementById('admin-verification-table-body');
    if (!tbody) return;

    let filtered = adminPaymentsCache;
    if (state.activeVerificationFilter !== 'All') {
        filtered = adminPaymentsCache.filter(p => p.status === state.activeVerificationFilter);
    }

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No ${state.activeVerificationFilter} payments found.</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map(p => `
        <tr>
            <td><strong>${escapeHtml(p.resident_name)}</strong><br><small class="text-muted">@${escapeHtml(p.resident_username)}</small></td>
            <td><span class="unit-tag">${escapeHtml(p.block_lot || '—')}</span></td>
            <td>${escapeHtml(p.due_title)}</td>
            <td><strong>₱ ${formatCurrency(p.amount)}</strong></td>
            <td><code>${escapeHtml(p.ref_number)}</code></td>
            <td><small>${formatDate(p.submitted_at)}</small></td>
            <td>
                <img src="${p.proof_image}" alt="Receipt" class="receipt-thumb" 
                     title="Click to inspect photo"
                     onclick="inspectReceipt('${p.proof_image}', '${escapeHtml(p.resident_name)}', '${escapeHtml(p.due_title)}', '${p.amount}', '${escapeHtml(p.ref_number)}')">
            </td>
            <td>
                <span class="badge ${p.status === 'Approved' ? 'badge-success' : p.status === 'Pending' ? 'badge-warning' : 'badge-danger'}">
                    ${p.status}
                </span>
                ${p.remarks ? `<br><small class="text-muted" title="${escapeHtml(p.remarks)}">${escapeHtml(p.remarks.substring(0, 20))}...</small>` : ''}
            </td>
            <td>
                ${p.status === 'Pending' ? `
                    <div class="btn-group">
                        <button class="btn btn-xs" onclick="quickVerifyPayment(${p.id}, 'Approved')">Approve</button>
                        <button class="btn btn-xs btn-danger" onclick="openRejectModal(${p.id})">Reject</button>
                    </div>
                ` : `
                    <small class="text-muted">Verified by ${escapeHtml(p.verified_by_name || 'Admin')}</small>
                `}
            </td>
        </tr>
    `).join('');
}

function inspectReceipt(imgSrc, residentName, dueTitle, amount, ref) {
    document.getElementById('inspect-receipt-img').src = imgSrc;
    document.getElementById('inspect-receipt-title').textContent = `Receipt Inspection: ${residentName}`;
    document.getElementById('inspect-receipt-meta').innerHTML = `
        <strong>Due:</strong> ${dueTitle} | 
        <strong>Amount:</strong> ₱ ${formatCurrency(amount)} | 
        <strong>Ref #:</strong> <code>${ref}</code>
    `;
    openModal('modal-inspect-receipt');
}

async function quickVerifyPayment(id, status) {
    try {
        await apiRequest(`/api/payments/${id}/verify`, 'PATCH', {
            status,
            remarks: 'Verified and approved by HOA Treasurer.'
        });
        showToast(`Payment marked as ${status}!`, 'success');
        fetchAdminVerification();
        fetchMyPayments();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function openRejectModal(paymentId) {
    document.getElementById('verify-payment-id').value = paymentId;
    document.getElementById('verify-decision-status').value = 'Rejected';
    document.getElementById('verify-remarks').value = '';
    openModal('modal-verify-decision');
}

async function handleVerifyDecisionSubmit(event) {
    event.preventDefault();
    const id = document.getElementById('verify-payment-id').value;
    const status = document.getElementById('verify-decision-status').value;
    const remarks = document.getElementById('verify-remarks').value.trim();

    try {
        await apiRequest(`/api/payments/${id}/verify`, 'PATCH', { status, remarks });
        closeModal('modal-verify-decision');
        showToast(`Payment has been ${status}.`, 'info');
        fetchAdminVerification();
        fetchMyPayments();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ================= MODULE 3: COMMUNITY FORUM (FACEBOOK-STYLE) ================= //

// Utility: Generate uppercase initials from name (e.g. John Doe -> JD)
function getInitials(name) {
    if (!name) return '??';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

// Utility: Enforce lowercase sentence starts for natural social media typing
function formatFilipinoCasual(str) {
    if (!str) return '';
    return str.replace(/(?:^|[.!?]\s+)([A-Z])/g, (match, letter) => {
        return match.replace(letter, letter.toLowerCase());
    });
}

async function fetchForumPosts() {
    try {
        let endpoint = '/api/forum/posts';
        if (state.activeForumCategory && state.activeForumCategory !== 'All') {
            endpoint += `?category=${encodeURIComponent(state.activeForumCategory)}`;
        }
        const res = await apiRequest(endpoint);
        const posts = res.posts || [];

        // Render Forum Stream
        const container = document.getElementById('forum-posts-container');
        if (container) {
            if (posts.length === 0) {
                container.innerHTML = '<p class="text-muted">Walang posts sa category na ito. Mag-post ng tanong o update!</p>';
            } else {
                container.innerHTML = posts.map(renderFacebookPostCard).join('');
            }
        }

        // Render Home Forum Preview
        const homePreview = document.getElementById('home-forum-preview');
        if (homePreview) {
            const previewPosts = posts.slice(0, 2);
            if (previewPosts.length === 0) {
                homePreview.innerHTML = '<p class="text-muted">Walang bagong discussions.</p>';
            } else {
                homePreview.innerHTML = previewPosts.map(p => `
                    <div class="announcement-item" style="cursor:pointer;" onclick="switchPage('forum', event)">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <h4 class="announcement-title">${escapeHtml(formatFilipinoCasual(p.title))}</h4>
                            <span class="badge badge-cat ${p.category.toLowerCase()}">${p.category}</span>
                        </div>
                        <p class="announcement-text">${escapeHtml(formatFilipinoCasual(p.body.substring(0, 110)))}...</p>
                        <small class="announcement-date">${p.vote_score} Likes • ${p.comment_count} Comments</small>
                    </div>
                `).join('');
            }
        }
    } catch (err) {
        console.error('fetchForumPosts error:', err);
    }
}

function filterForum(category, event) {
    state.activeForumCategory = category;
    const chips = document.querySelectorAll('#forum-tags-bar .tag-chip');
    chips.forEach(c => c.classList.remove('active'));
    if (event?.target) event.target.classList.add('active');
    fetchForumPosts();
}

// Render Facebook-Style Post Card
function renderFacebookPostCard(post) {
    const isLiked = post.user_vote === 1;
    const canDelete = state.currentUser && (state.currentUser.role === 'admin' || state.currentUser.id === post.author_id);
    const commentsExpanded = state.expandedComments.has(post.id);
    const initials = getInitials(post.author_name);
    const myInitials = getInitials(state.currentUser?.name || 'Guest');

    return `
        <div class="fb-post-card" id="post-${post.id}">
            <!-- Facebook-Style Header -->
            <div class="fb-post-header">
                <div class="user-avatar-initials">${initials}</div>
                <div class="fb-post-meta">
                    <div class="fb-post-author-line">
                        <span class="fb-post-author">${escapeHtml(post.author_name)}</span>
                        <span class="badge badge-role ${post.author_role === 'admin' ? 'admin' : ''}">${post.author_role}</span>
                        ${post.author_block_lot ? `<span class="unit-tag">${escapeHtml(post.author_block_lot)}</span>` : ''}
                    </div>
                    <div class="fb-post-subline">
                        <span class="badge badge-cat ${post.category.toLowerCase()}">${post.category}</span>
                        <span>•</span>
                        <span>${formatDate(post.created_at)}</span>
                    </div>
                </div>
            </div>

            <!-- Post Content (Filipino Context, Lowercase Sentences) -->
            <div class="fb-post-title">${escapeHtml(formatFilipinoCasual(post.title))}</div>
            <div class="fb-post-body">${escapeHtml(formatFilipinoCasual(post.body))}</div>

            ${post.image_url ? `
                <img src="${post.image_url}" alt="Attachment" class="fb-post-image">
            ` : ''}

            <!-- Post Stats Row -->
            <div class="fb-post-stats">
                <span id="likes-count-${post.id}">${post.vote_score} Likes</span>
                <span><span id="comment-count-${post.id}">${post.comment_count}</span> Comments</span>
            </div>

            <!-- Facebook-Style Action Bar (Like, Comment, Delete) -->
            <div class="fb-post-actions-bar">
                <button class="fb-action-btn ${isLiked ? 'liked' : ''}" id="like-btn-${post.id}" onclick="handleLike(${post.id}, ${post.user_vote || 0}, event)">
                    ${isLiked ? 'Liked' : 'Like'}
                </button>
                <button class="fb-action-btn" onclick="toggleComments(${post.id})">
                    Comment
                </button>
                ${canDelete ? `
                    <button class="fb-action-btn delete-action" onclick="deletePost(${post.id})">
                        Delete
                    </button>
                ` : ''}
            </div>

            <!-- Facebook-Style Comments Section -->
            <div id="comments-section-${post.id}" class="fb-comments-section ${commentsExpanded ? '' : 'hidden'}">
                <!-- Top-Level Comment Composer -->
                <div class="fb-comment-compose">
                    <div class="user-avatar-initials sm">${myInitials}</div>
                    <div class="fb-compose-input-wrapper">
                        <textarea class="fb-compose-input" id="comment-input-${post.id}" placeholder="write a comment in lowercase..."></textarea>
                        <div style="display:flex; justify-content:flex-end;">
                            <button class="btn btn-xs" onclick="submitComment(${post.id}, null)">Post</button>
                        </div>
                    </div>
                </div>

                <!-- Single-Tier / Flat Comments Thread -->
                <div id="comments-tree-${post.id}" class="fb-comments-thread">
                    <small class="text-muted">Loading comments...</small>
                </div>
            </div>
        </div>
    `;
}

// Facebook Like Handler
async function handleLike(postId, currentVote, event) {
    if (event) event.stopPropagation();
    if (!state.token) {
        openAuthModal('login');
        return;
    }

    // Toggle: 1 -> 0, or 0 -> 1
    const nextVote = currentVote === 1 ? 0 : 1;

    try {
        const res = await apiRequest(`/api/forum/posts/${postId}/vote`, 'POST', { value: nextVote });
        
        // Update Stats Count
        const countEl = document.getElementById(`likes-count-${postId}`);
        if (countEl) countEl.textContent = `${res.voteScore} Likes`;

        // Update Like Button
        const likeBtn = document.getElementById(`like-btn-${postId}`);
        if (likeBtn) {
            const isLiked = res.userVote === 1;
            likeBtn.textContent = isLiked ? 'Liked' : 'Like';
            if (isLiked) {
                likeBtn.classList.add('liked');
            } else {
                likeBtn.classList.remove('liked');
            }
            likeBtn.setAttribute('onclick', `handleLike(${postId}, ${res.userVote}, event)`);
        }
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function openCreatePostModal() {
    if (!state.token) {
        openAuthModal('login');
        return;
    }
    state.postImageBase64 = null;
    document.getElementById('form-create-post').reset();
    document.getElementById('post-image-preview-container').classList.add('hidden');
    openModal('modal-create-post');
}

function previewPostImage(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        state.postImageBase64 = e.target.result;
        const img = document.getElementById('post-image-preview-img');
        const container = document.getElementById('post-image-preview-container');
        img.src = state.postImageBase64;
        container.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}

async function handleCreatePostSubmit(event) {
    event.preventDefault();
    const titleInput = document.getElementById('post-title').value.trim();
    const bodyInput = document.getElementById('post-body').value.trim();
    const category = document.getElementById('post-category').value;

    // Apply lowercase sentence formatting
    const title = formatFilipinoCasual(titleInput);
    const body = formatFilipinoCasual(bodyInput);

    try {
        await apiRequest('/api/forum/posts', 'POST', {
            title,
            body,
            category,
            image_url: state.postImageBase64
        });

        closeModal('modal-create-post');
        showToast('Discussion post published!', 'success');
        fetchForumPosts();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function deletePost(postId) {
    if (!confirm('Are you sure you want to delete this discussion post?')) return;

    try {
        await apiRequest(`/api/forum/posts/${postId}`, 'DELETE');
        showToast('Post deleted.', 'info');
        fetchForumPosts();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// Facebook-Style Comment Threads (Flat / Single-tier direct replies)
async function toggleComments(postId) {
    const section = document.getElementById(`comments-section-${postId}`);
    if (!section) return;

    if (state.expandedComments.has(postId)) {
        state.expandedComments.delete(postId);
        section.classList.add('hidden');
    } else {
        state.expandedComments.add(postId);
        section.classList.remove('hidden');
        await loadCommentsForPost(postId);
    }
}

async function loadCommentsForPost(postId) {
    const container = document.getElementById(`comments-tree-${postId}`);
    if (!container) return;

    try {
        const res = await apiRequest(`/api/forum/posts/${postId}/comments`);
        const comments = res.comments || [];

        const countEl = document.getElementById(`comment-count-${postId}`);
        if (countEl) countEl.textContent = res.totalCount;

        if (comments.length === 0) {
            container.innerHTML = '<p class="text-muted" style="margin-top:6px;">Walang comments pa. Mag-iwan ng unang komento!</p>';
        } else {
            container.innerHTML = renderFacebookCommentList(comments, postId);
        }
    } catch (err) {
        container.innerHTML = `<p class="text-muted">Failed to load comments: ${err.message}</p>`;
    }
}

// Render Facebook-Style Single-Tier Comment Hierarchy
function renderFacebookCommentList(comments, postId) {
    return comments.map(c => {
        const canDelete = state.currentUser && (state.currentUser.role === 'admin' || state.currentUser.id === c.user_id);
        const initials = getInitials(c.author_name);
        const hasChildren = c.children && c.children.length > 0;

        return `
            <div class="fb-comment-item" id="comment-${c.id}">
                <!-- Top-Level Comment Bubble -->
                <div class="fb-comment-row">
                    <div class="user-avatar-initials sm">${initials}</div>
                    <div class="fb-comment-bubble">
                        <div class="fb-comment-author-name">
                            <span>${escapeHtml(c.author_name)}</span>
                            <span class="badge badge-role ${c.author_role === 'admin' ? 'admin' : ''}">${c.author_role}</span>
                            ${c.author_block_lot ? `<span class="unit-tag">${escapeHtml(c.author_block_lot)}</span>` : ''}
                        </div>
                        <div class="fb-comment-text">${escapeHtml(formatFilipinoCasual(c.content))}</div>
                    </div>
                </div>

                <!-- Action Row Below Bubble: Like · Reply · Time · Delete -->
                <div class="fb-comment-action-row">
                    <button class="fb-text-action-link" onclick="showToast('Liked comment', 'info')">Like</button>
                    <span>·</span>
                    <button class="fb-text-action-link" onclick="toggleReplyBox(${c.id})">Reply</button>
                    <span>·</span>
                    <span>${formatDate(c.created_at)}</span>
                    ${canDelete ? `
                        <span>·</span>
                        <button class="fb-text-action-link delete" onclick="deleteComment(${c.id}, ${postId})">Delete</button>
                    ` : ''}
                </div>

                <!-- Inline Reply Box -->
                <div id="reply-box-${c.id}" class="fb-reply-box hidden">
                    <textarea id="reply-input-${c.id}" placeholder="write a reply to ${escapeHtml(c.author_name)} in lowercase..."></textarea>
                    <div style="display:flex; gap:6px; margin-top:6px; justify-content:flex-end;">
                        <button class="btn btn-xs" onclick="submitComment(${postId}, ${c.id})">Send Reply</button>
                        <button class="btn btn-xs btn-outline" onclick="toggleReplyBox(${c.id})">Cancel</button>
                    </div>
                </div>

                <!-- Single-Tier Sub-Replies (Flat direct replies) -->
                ${hasChildren ? `
                    <div class="fb-replies-container">
                        ${c.children.map(child => {
                            const canDeleteChild = state.currentUser && (state.currentUser.role === 'admin' || state.currentUser.id === child.user_id);
                            const childInitials = getInitials(child.author_name);
                            return `
                                <div class="fb-comment-item" id="comment-${child.id}">
                                    <div class="fb-comment-row">
                                        <div class="user-avatar-initials sm">${childInitials}</div>
                                        <div class="fb-comment-bubble">
                                            <div class="fb-comment-author-name">
                                                <span>${escapeHtml(child.author_name)}</span>
                                                <span class="badge badge-role ${child.author_role === 'admin' ? 'admin' : ''}">${child.author_role}</span>
                                                ${child.author_block_lot ? `<span class="unit-tag">${escapeHtml(child.author_block_lot)}</span>` : ''}
                                            </div>
                                            <div class="fb-comment-text">${escapeHtml(formatFilipinoCasual(child.content))}</div>
                                        </div>
                                    </div>
                                    <div class="fb-comment-action-row">
                                        <button class="fb-text-action-link" onclick="showToast('Liked reply', 'info')">Like</button>
                                        <span>·</span>
                                        <span>${formatDate(child.created_at)}</span>
                                        ${canDeleteChild ? `
                                            <span>·</span>
                                            <button class="fb-text-action-link delete" onclick="deleteComment(${child.id}, ${postId})">Delete</button>
                                        ` : ''}
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');
}

function toggleReplyBox(commentId) {
    const box = document.getElementById(`reply-box-${commentId}`);
    if (box) box.classList.toggle('hidden');
}

async function submitComment(postId, parentId = null) {
    if (!state.token) {
        openAuthModal('login');
        return;
    }

    const inputId = parentId ? `reply-input-${parentId}` : `comment-input-${postId}`;
    const input = document.getElementById(inputId);
    const rawContent = input ? input.value.trim() : '';

    if (!rawContent) {
        showToast('Please type a comment before posting.', 'error');
        return;
    }

    // Force casual lowercase formatting
    const content = formatFilipinoCasual(rawContent);

    try {
        await apiRequest(`/api/forum/posts/${postId}/comments`, 'POST', {
            content,
            parent_id: parentId
        });

        if (input) input.value = '';
        if (parentId) toggleReplyBox(parentId);
        showToast('Comment submitted!', 'success');
        await loadCommentsForPost(postId);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function deleteComment(commentId, postId) {
    if (!confirm('Are you sure you want to delete this comment?')) return;

    try {
        await apiRequest(`/api/forum/comments/${commentId}`, 'DELETE');
        showToast('Comment deleted.', 'info');
        await loadCommentsForPost(postId);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ================= MODULE 4: ANNOUNCEMENTS & PUSH NOTIFICATIONS ================= //

async function fetchAnnouncements() {
    try {
        const res = await apiRequest('/api/announcements');
        const list = res.announcements || [];

        // Render on Home Page
        const homeList = document.getElementById('home-announcements-list');
        if (homeList) {
            if (list.length === 0) {
                homeList.innerHTML = '<p class="text-muted">No official announcements posted yet.</p>';
            } else {
                homeList.innerHTML = list.slice(0, 3).map(a => `
                    <div class="announcement-card ${a.category.toLowerCase()}">
                        <div class="announcement-header">
                            <span class="announcement-title">${escapeHtml(a.title)}</span>
                            <span class="badge badge-cat ${a.category.toLowerCase()}">${a.category}</span>
                        </div>
                        <p class="announcement-text">${escapeHtml(a.body)}</p>
                        <small class="announcement-meta">${formatDate(a.created_at)} • Posted by ${escapeHtml(a.author_name)}</small>
                    </div>
                `).join('');
            }
        }

        // Render on Announcements Tab
        const fullList = document.getElementById('announcements-page-list');
        if (fullList) {
            if (list.length === 0) {
                fullList.innerHTML = '<p class="text-muted">No official announcements found.</p>';
            } else {
                fullList.innerHTML = list.map(a => `
                    <div class="announcement-card ${a.category.toLowerCase()}">
                        <div class="announcement-header">
                            <span class="announcement-title">${escapeHtml(a.title)}</span>
                            <span class="badge badge-cat ${a.category.toLowerCase()}">${a.category}</span>
                        </div>
                        <p class="announcement-text">${escapeHtml(a.body)}</p>
                        <small class="announcement-meta">${formatDate(a.created_at)} • Published by ${escapeHtml(a.author_name)} (${escapeHtml(a.author_role)})</small>
                    </div>
                `).join('');
            }
        }
    } catch (err) {
        console.error('fetchAnnouncements error:', err);
    }
}

function openAnnouncementModal() {
    document.getElementById('form-create-announcement').reset();
    openModal('modal-create-announcement');
}

async function handleCreateAnnouncementSubmit(event) {
    event.preventDefault();
    const title = document.getElementById('announcement-title').value.trim();
    const category = document.getElementById('announcement-category').value;
    const body = document.getElementById('announcement-body').value.trim();

    try {
        const res = await apiRequest('/api/announcements', 'POST', { title, category, body });
        closeModal('modal-create-announcement');
        showToast('Announcement broadcasted and push notifications dispatched!', 'success');
        
        // Trigger live browser notification
        triggerLocalPushNotification(res.pushPayload?.title || title, body);

        fetchAnnouncements();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// Web Push Notification System
function checkPushPermissionState() {
    const banner = document.getElementById('push-notification-banner');
    if (!('Notification' in window)) {
        if (banner) banner.classList.add('hidden');
        return;
    }

    if (Notification.permission === 'granted' || localStorage.getItem('push_banner_dismissed')) {
        if (banner) banner.classList.add('hidden');
    } else {
        if (banner) banner.classList.remove('hidden');
    }
}

async function enablePushNotifications() {
    if (!('Notification' in window)) {
        showToast('Web Notifications are not supported in your browser.', 'error');
        return;
    }

    const permission = await Notification.requestPermission();
    if (permission === 'granted') {
        dismissPushBanner();
        showToast('Push Notifications enabled! You will receive instant HOA announcements.', 'success');
        new Notification('Greenside HOA Notifications Enabled', {
            body: 'You are now subscribed to water alerts, emergency notices, and dues announcements.'
        });
    } else {
        showToast('Notification permission was dismissed or blocked.', 'info');
    }
}

function dismissPushBanner() {
    localStorage.setItem('push_banner_dismissed', 'true');
    const banner = document.getElementById('push-notification-banner');
    if (banner) banner.classList.add('hidden');
}

function triggerLocalPushNotification(title, body) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, { body });
    }
    // Also display floating in-app toast
    showToast(`[Notice] ${title}`, 'info');
}

async function pollLatestBroadcast() {
    try {
        const res = await apiRequest('/api/announcements/latest-broadcast');
        const b = res.latestBroadcast;
        if (!b) return;

        if (state.lastBroadcastId !== null && b.id !== state.lastBroadcastId) {
            triggerLocalPushNotification(b.title, b.body);
            fetchAnnouncements();
        }
        state.lastBroadcastId = b.id;
    } catch (e) {
        // Silent poll error
    }
}

// ================= MODAL & TOAST HELPERS ================= //

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('hidden');
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add('hidden');
}

// Close modal when clicking on the dimmed background
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.add('hidden');
    }
});

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;

    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.2s ease';
        setTimeout(() => toast.remove(), 200);
    }, 4000);
}

// Utilities
function formatCurrency(val) {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}