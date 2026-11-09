/**
 * Greenside Homes HOA Portal - Android Material 3 Mobile Experience Engine
 * Touch ergonomics, bottom sheets, navigation, dynamic clearance modals, pull-to-refresh,
 * Android Navigation Drawer, and animated In-App Push Notification Popups.
 */
(() => {
    'use strict';

    // -------------------------------------------------------------
    // CSRF and Basic Constants
    // -------------------------------------------------------------
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const pageTitles = {
        home: 'Greenside Community',
        clearances: 'Permits & Clearances',
        bills: 'Bills & Payments',
        alerts: 'Announcements',
        profile: 'Resident Profile',
        admin: 'HOA Administration'
    };

    let currentPage = 'home';
    const appContainer = document.getElementById('app-container');
    const scrollContainer = document.getElementById('m3-scroll-container');
    const mainFab = document.getElementById('m3-main-fab');
    const fabLabel = document.getElementById('m3-fab-label');
    const fabIcon = mainFab?.querySelector('.m3-fab-icon');
    const scrim = document.getElementById('m3-scrim');

    // -------------------------------------------------------------
    // Animated In-App Push Notification Popups (Floating Toasts)
    // -------------------------------------------------------------
    const pushContainer = document.getElementById('m3-push-container');

    const showPushNotification = ({ type = 'info', title = '', message, duration = 4500 }) => {
        if (!pushContainer || !message) return;

        // If 3 or more notifications are active, dismiss the oldest smoothly
        const activeToasts = pushContainer.querySelectorAll('.m3-push-toast:not(.dismissing)');
        if (activeToasts.length >= 3) {
            const oldest = activeToasts[0];
            oldest.classList.add('dismissing');
            window.setTimeout(() => oldest.remove(), 280);
        }

        const toastEl = document.createElement('div');
        toastEl.className = `m3-push-toast notice-${type}`;
        toastEl.setAttribute('role', 'status');

        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        const iconSpan = document.createElement('span');
        iconSpan.className = 'm3-push-icon';
        iconSpan.setAttribute('aria-hidden', 'true');
        iconSpan.textContent = icons[type] || '●';

        const bodyDiv = document.createElement('div');
        bodyDiv.className = 'm3-push-body';

        const defaultTitles = {
            success: 'Success',
            error: 'Notice',
            warning: 'Action Required',
            info: 'Greenside Notice'
        };
        const resolvedTitle = title || defaultTitles[type] || 'Notice';

        const titleEl = document.createElement('h4');
        titleEl.className = 'm3-push-title';
        titleEl.textContent = resolvedTitle;

        const msgEl = document.createElement('p');
        msgEl.className = 'm3-push-message';
        msgEl.textContent = message;

        bodyDiv.append(titleEl, msgEl);

        const closeBtn = document.createElement('button');
        closeBtn.className = 'm3-push-close';
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Dismiss notification');
        closeBtn.textContent = '✕';

        toastEl.append(iconSpan, bodyDiv, closeBtn);

        // Function to dismiss toast
        let isDismissed = false;
        let autoDismissTimer = null;

        const dismissToast = () => {
            if (isDismissed) return;
            isDismissed = true;
            toastEl.classList.add('dismissing');
            if (autoDismissTimer) window.clearTimeout(autoDismissTimer);
            toastEl.addEventListener('animationend', () => {
                toastEl.remove();
            }, { once: true });
            window.setTimeout(() => {
                if (toastEl.parentNode) toastEl.remove();
            }, 320);
        };

        // Manual dismiss via tap on "✕" button
        closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dismissToast();
        });

        // Touch Swipe-Up gesture to dismiss
        let touchStartY = 0;
        let isSwipingToast = false;

        toastEl.addEventListener('touchstart', (e) => {
            touchStartY = e.touches[0].clientY;
            isSwipingToast = true;
        }, { passive: true });

        toastEl.addEventListener('touchmove', (e) => {
            if (!isSwipingToast) return;
            const diffY = e.touches[0].clientY - touchStartY;
            if (diffY < 0) {
                // Dragging upwards
                toastEl.style.transform = `translateY(${diffY}px)`;
                toastEl.style.opacity = String(Math.max(0, 1 + diffY / 90));
            }
        }, { passive: true });

        toastEl.addEventListener('touchend', (e) => {
            if (!isSwipingToast) return;
            isSwipingToast = false;
            const diffY = e.changedTouches[0].clientY - touchStartY;
            if (diffY < -24) {
                // Swipe up threshold met
                dismissToast();
            } else {
                // Snap back into place
                toastEl.style.transform = '';
                toastEl.style.opacity = '';
            }
        }, { passive: true });

        // Auto-dismiss after duration
        autoDismissTimer = window.setTimeout(() => {
            dismissToast();
        }, duration);

        pushContainer.appendChild(toastEl);
    };

    // Global toast helper feeding directly into push notification popup engine
    const toast = (message, type = 'info', title = '') => {
        const isError = /error|failed|could not|invalid/i.test(message);
        const isSuccess = /success|noted|verified|added|posted|completed|ready|updated|refreshed/i.test(message);
        const resolvedType = type !== 'info' ? type : (isError ? 'error' : (isSuccess ? 'success' : 'info'));
        showPushNotification({ type: resolvedType, title, message });
    };

    // Check for PHP flash data to display as in-app push notification
    const flashDataEl = document.getElementById('php-flash-data');
    if (flashDataEl) {
        const fType = flashDataEl.dataset.type || 'info';
        const fMsg = flashDataEl.dataset.message || '';
        const fTitle = flashDataEl.dataset.title || '';
        if (fMsg) {
            window.setTimeout(() => {
                showPushNotification({ type: fType, title: fTitle, message: fMsg, duration: 5000 });
            }, 300);
        }
    }

    // -------------------------------------------------------------
    // Android Status Bar Clock Ticker (Local Device Time)
    // -------------------------------------------------------------
    const updateStatusBarClock = () => {
        const clockEl = document.getElementById('android-clock');
        if (!clockEl) return;
        const now = new Date();
        const hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        clockEl.textContent = `${hours}:${minutes}`;
    };
    updateStatusBarClock();
    setInterval(updateStatusBarClock, 10000);

    // -------------------------------------------------------------
    // Generic AJAX Request Helper
    // -------------------------------------------------------------
    const request = async (action, values = {}) => {
        const body = new URLSearchParams({ ...values, csrf_token: csrf });
        const response = await fetch(`index.php?action=${encodeURIComponent(action)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-Token': csrf,
                'Accept': 'application/json'
            },
            body
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || data.error || 'The request could not be completed.');
        }
        return data;
    };

    // -------------------------------------------------------------
    // Material 3 Left-Side Navigation Drawer
    // -------------------------------------------------------------
    const navDrawer = document.getElementById('m3-nav-drawer');
    const drawerScrim = document.getElementById('m3-drawer-scrim');
    const btnOpenDrawer = document.getElementById('btn-open-drawer');
    const btnCloseDrawer = document.getElementById('btn-close-drawer');

    const openDrawer = () => {
        if (!navDrawer) return;
        closeAllSheets(); // Dismiss bottom sheets if open
        navDrawer.classList.add('open');
        drawerScrim?.classList.add('visible');
        navDrawer.setAttribute('aria-hidden', 'false');
    };

    const closeDrawer = () => {
        if (!navDrawer) return;
        navDrawer.classList.remove('open');
        drawerScrim?.classList.remove('visible');
        navDrawer.setAttribute('aria-hidden', 'true');
    };

    btnOpenDrawer?.addEventListener('click', openDrawer);
    btnCloseDrawer?.addEventListener('click', closeDrawer);
    drawerScrim?.addEventListener('click', closeDrawer);

    // Drawer menu items click handler
    document.querySelectorAll('[data-drawer-page]').forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const targetPage = item.dataset.drawerPage;
            const filter = item.dataset.drawerFilter;

            showPage(targetPage);

            // If Community Forum clicked, trigger "all" feed filter
            if (filter === 'forum') {
                const forumFilterBtn = document.querySelector('[data-feed-filter="all"]');
                forumFilterBtn?.click();
            }

            // Update active state in drawer
            document.querySelectorAll('[data-drawer-page]').forEach((btn) => {
                btn.classList.toggle('active', btn === item);
            });

            // Auto-collapse drawer on selection
            closeDrawer();
        });
    });

    // Touch Edge Swipe-from-Left Gesture to Open Drawer
    let edgeStartX = 0;
    let edgeStartY = 0;
    let isTrackingEdgeSwipe = false;

    appContainer?.addEventListener('touchstart', (e) => {
        const touch = e.touches[0];
        const rect = appContainer.getBoundingClientRect();
        const relativeX = touch.clientX - rect.left;

        // If touch starts within 34px of the left edge and drawer is closed
        if (relativeX <= 34 && !navDrawer?.classList.contains('open')) {
            edgeStartX = touch.clientX;
            edgeStartY = touch.clientY;
            isTrackingEdgeSwipe = true;
        } else {
            isTrackingEdgeSwipe = false;
        }
    }, { passive: true });

    appContainer?.addEventListener('touchmove', (e) => {
        if (!isTrackingEdgeSwipe) return;
        const currentX = e.touches[0].clientX;
        const currentY = e.touches[0].clientY;
        const deltaX = currentX - edgeStartX;
        const deltaY = Math.abs(currentY - edgeStartY);

        // Horizontal swipe right from left edge with clear horizontal bias
        if (deltaX > 40 && deltaX > deltaY * 1.2) {
            openDrawer();
            isTrackingEdgeSwipe = false;
        }
    }, { passive: true });

    appContainer?.addEventListener('touchend', () => {
        isTrackingEdgeSwipe = false;
    }, { passive: true });

    // Touch Swipe-Left to Dismiss Drawer
    let drawerStartX = 0;
    let isTrackingDrawerClose = false;

    navDrawer?.addEventListener('touchstart', (e) => {
        drawerStartX = e.touches[0].clientX;
        isTrackingDrawerClose = true;
    }, { passive: true });

    navDrawer?.addEventListener('touchmove', (e) => {
        if (!isTrackingDrawerClose) return;
        const currentX = e.touches[0].clientX;
        const deltaX = currentX - drawerStartX;
        // Swiped left by > 40px
        if (deltaX < -40) {
            closeDrawer();
            isTrackingDrawerClose = false;
        }
    }, { passive: true });

    navDrawer?.addEventListener('touchend', () => {
        isTrackingDrawerClose = false;
    }, { passive: true });

    // -------------------------------------------------------------
    // Bottom Sheet Manager
    // -------------------------------------------------------------
    const openSheet = (sheetId) => {
        const sheet = document.getElementById(sheetId);
        if (!sheet) return;
        // Close drawer if open
        closeDrawer();
        // Close any other open sheet first
        document.querySelectorAll('.m3-bottom-sheet.open').forEach(s => s.classList.remove('open'));
        sheet.classList.add('open');
        scrim?.classList.add('visible');
        sheet.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sheet-open');
    };

    const closeAllSheets = () => {
        document.querySelectorAll('.m3-bottom-sheet.open').forEach(s => {
            s.classList.remove('open');
            s.setAttribute('aria-hidden', 'true');
        });
        scrim?.classList.remove('visible');
        document.body.classList.remove('sheet-open');
    };

    // Close bottom sheet on scrim click
    scrim?.addEventListener('click', closeAllSheets);

    // Close on any close button or handle drag
    document.querySelectorAll('[data-close-sheet]').forEach(el => {
        el.addEventListener('click', closeAllSheets);
    });

    // Close on Escape key
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllSheets();
            closeDrawer();
        }
    });

    // -------------------------------------------------------------
    // Adaptive Floating Action Button (FAB) Controller
    // -------------------------------------------------------------
    const updateFab = (page) => {
        if (!mainFab) return;
        if (page === 'home') {
            mainFab.style.display = 'inline-flex';
            if (fabLabel) fabLabel.textContent = 'Create Post';
            if (fabIcon) fabIcon.textContent = '＋';
        } else if (page === 'clearances') {
            mainFab.style.display = 'inline-flex';
            if (fabLabel) fabLabel.textContent = 'Request Clearance';
            if (fabIcon) fabIcon.textContent = '＋';
        } else if (page === 'bills') {
            mainFab.style.display = 'inline-flex';
            if (fabLabel) fabLabel.textContent = 'Submit Proof';
            if (fabIcon) fabIcon.textContent = '💳';
        } else {
            // Hide on alerts, profile, admin
            mainFab.style.display = 'none';
        }
    };

    mainFab?.addEventListener('click', () => {
        if (currentPage === 'home') {
            openSheet('sheet-create-post');
        } else if (currentPage === 'clearances') {
            openSheet('sheet-clearance-request');
        } else if (currentPage === 'bills') {
            openSheet('sheet-payment');
        }
    });

    // -------------------------------------------------------------
    // Page Navigation & State Switching
    // -------------------------------------------------------------
    const showPage = (name) => {
        const target = document.getElementById(`page-${name}`);
        if (!target) return;

        currentPage = name;

        // Switch page views
        document.querySelectorAll('.page-view').forEach((page) => {
            const active = page === target;
            page.classList.toggle('hidden', !active);
            page.setAttribute('aria-hidden', String(!active));
        });

        // Update active class on bottom nav and other nav elements
        document.querySelectorAll('[data-page]').forEach((item) => {
            item.classList.toggle('active', item.dataset.page === name);
        });

        // Update active class on drawer items
        document.querySelectorAll('[data-drawer-page]').forEach((item) => {
            item.classList.toggle('active', item.dataset.drawerPage === name);
        });

        // Update Top App Bar Title
        const topTitle = document.getElementById('app-bar-title');
        if (topTitle) {
            topTitle.textContent = pageTitles[name] || 'Greenside Community';
        }

        // Update FAB configuration for current page
        updateFab(name);

        // Smooth scroll viewport content container to top
        if (scrollContainer) {
            scrollContainer.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    };

    // Attach click listener to all data-page items
    document.querySelectorAll('[data-page]').forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            showPage(item.dataset.page);
        });
    });

    // Initialize current page state
    const initialActive = document.querySelector('.m3-bottom-nav .m3-nav-item.active')?.dataset.page || 'home';
    updateFab(initialActive);

    // -------------------------------------------------------------
    // Pull-to-Refresh Touch Interaction
    // -------------------------------------------------------------
    if (scrollContainer) {
        let touchStartY = 0;
        let isPulling = false;
        const pullIndicator = document.getElementById('pull-indicator');

        scrollContainer.addEventListener('touchstart', (e) => {
            if (scrollContainer.scrollTop <= 0) {
                touchStartY = e.touches[0].clientY;
                isPulling = true;
            } else {
                isPulling = false;
            }
        }, { passive: true });

        scrollContainer.addEventListener('touchmove', (e) => {
            if (!isPulling) return;
            const currentY = e.touches[0].clientY;
            const diff = currentY - touchStartY;
            if (diff > 20 && scrollContainer.scrollTop <= 0) {
                pullIndicator?.classList.add('pulling');
            } else if (diff <= 10) {
                pullIndicator?.classList.remove('pulling');
            }
        }, { passive: true });

        scrollContainer.addEventListener('touchend', (e) => {
            if (!isPulling) return;
            const currentY = e.changedTouches[0].clientY;
            const diff = currentY - touchStartY;
            if (diff > 65 && scrollContainer.scrollTop <= 0) {
                pullIndicator?.classList.add('pulling');
                toast('Refreshing community data...', 'info');
                window.setTimeout(() => {
                    pullIndicator?.classList.remove('pulling');
                    toast('Feed updated just now.', 'success');
                }, 900);
            } else {
                pullIndicator?.classList.remove('pulling');
            }
            isPulling = false;
        }, { passive: true });
    }

    // -------------------------------------------------------------
    // Permits & Clearances Interactions
    // -------------------------------------------------------------

    // Empty state request button
    document.getElementById('btn-request-first-empty')?.addEventListener('click', () => {
        openSheet('sheet-clearance-request');
    });

    // Empty feed post button
    document.getElementById('btn-empty-create')?.addEventListener('click', () => {
        openSheet('sheet-create-post');
    });

    // Dynamic label and placeholder based on Clearance Type
    const clearanceTypeSelect = document.getElementById('select-clearance-type');
    const clearanceSubtextLabel = document.getElementById('label-clearance-subtext');
    const clearanceSubtextInput = document.getElementById('input-clearance-subtext');

    if (clearanceTypeSelect && clearanceSubtextLabel && clearanceSubtextInput) {
        const typePlaceholders = {
            vehicle_sticker: {
                label: 'Plate Number & Vehicle Model',
                placeholder: 'e.g., NDM-4821 · Honda Click 125i (or car plate)'
            },
            isp_install: {
                label: 'Internet Service Provider',
                placeholder: 'e.g., Converge ICT / PLDT Home Fiber / Globe'
            },
            utility_meter: {
                label: 'Utility Service & Provider',
                placeholder: 'e.g., Meralco Permanent Meter / Local Water District'
            },
            house_extension: {
                label: 'Renovation Scope & Contractor Info',
                placeholder: 'e.g., Gate installation, fence plastering (Contractor: R. Santos)'
            },
            gate_pass: {
                label: 'Delivery Truck & Forwarder Details',
                placeholder: 'e.g., Isuzu Elf (ABC-987) · Appliance / furniture delivery'
            }
        };

        clearanceTypeSelect.addEventListener('change', () => {
            const selected = typePlaceholders[clearanceTypeSelect.value];
            if (selected) {
                clearanceSubtextLabel.textContent = selected.label;
                clearanceSubtextInput.placeholder = selected.placeholder;
            }
        });
    }

    // Clearance Accordion Expand / Collapse
    document.querySelectorAll('.clearance-accordion-header').forEach((header) => {
        header.addEventListener('click', () => {
            const card = header.closest('.clearance-accordion');
            if (!card) return;
            const body = card.querySelector('.clearance-accordion-body');
            const isOpen = card.classList.toggle('is-open');
            header.setAttribute('aria-expanded', String(isOpen));
            if (body) {
                body.classList.toggle('hidden', !isOpen);
            }
        });
    });

    // Clearance Filter Chips
    document.querySelectorAll('[data-clearance-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-clearance-filter]').forEach((chip) => {
                chip.classList.toggle('selected', chip === button);
                chip.setAttribute('aria-pressed', String(chip === button));
            });
            const filterValue = button.dataset.clearanceFilter;
            let visibleCount = 0;
            document.querySelectorAll('.clearance-card').forEach((card) => {
                const matches = filterValue === 'all' || card.dataset.clearanceGroup === filterValue;
                card.classList.toggle('hidden', !matches);
                if (matches) visibleCount++;
            });
            const emptyState = document.getElementById('clearance-empty-state');
            if (emptyState) {
                emptyState.classList.toggle('hidden', visibleCount !== 0);
            }
        });
    });

    // Action: View Clearance Slip / QR Pass (Opens Sheet)
    document.querySelectorAll('.action-view').forEach((btn) => {
        btn.addEventListener('click', () => {
            const ref = btn.dataset.clearanceRef || 'CLR-2026-0000';
            const title = btn.dataset.clearanceTitle || 'Subdivision Clearance';
            const subtext = btn.dataset.clearanceSubtext || '';
            const date = btn.dataset.clearanceDate || '';
            const status = btn.dataset.clearanceStatus || 'Approved / Ready for Release';
            const pay = btn.dataset.clearancePay || 'Paid';
            const remarks = btn.dataset.clearanceRemarks || 'Presented for gate and village inspection.';

            const refEl = document.getElementById('slip-ref-no');
            const titleEl = document.getElementById('slip-type-title');
            const subtextEl = document.getElementById('slip-subtext');
            const dateEl = document.getElementById('slip-date');
            const statusPillEl = document.getElementById('slip-status-pill');
            const payEl = document.getElementById('slip-payment-condition');
            const remarksEl = document.getElementById('slip-remarks');

            if (refEl) refEl.textContent = ref;
            if (titleEl) titleEl.textContent = title;
            if (subtextEl) subtextEl.textContent = subtext;
            if (dateEl) dateEl.textContent = date;
            if (statusPillEl) {
                statusPillEl.textContent = status;
                statusPillEl.className = 'clearance-pill ' + (
                    status.includes('Approved') ? 'pill-app-approved' :
                    (status.includes('Requirements') ? 'pill-app-requirements' :
                    (status.includes('Review') ? 'pill-app-review' : 'pill-app-disapproved'))
                );
            }
            if (payEl) payEl.textContent = pay;
            if (remarksEl) remarksEl.textContent = 'Remarks: ' + remarks;

            openSheet('sheet-slip');
        });
    });

    // Print Clearance Slip Action
    document.getElementById('btn-print-slip')?.addEventListener('click', () => {
        window.print();
    });

    // Action: Upload Lacking Document (Opens Sheet)
    document.querySelectorAll('.action-upload').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.clearanceId;
            const title = btn.dataset.clearanceTitle || 'Water / Electric Meter Application';
            const reqDoc = btn.dataset.requiredDoc || 'Meralco Yellow Card / Water Service Order';

            const idInput = document.getElementById('upload-clearance-id');
            const titleEl = document.getElementById('upload-target-title');
            const docNameEl = document.getElementById('upload-doc-name');

            if (idInput) idInput.value = id;
            if (titleEl) titleEl.textContent = title;
            if (docNameEl) docNameEl.textContent = reqDoc;

            openSheet('sheet-upload-docs');
        });
    });

    // Action: Pay Clearance Fee via GCash (Opens Sheet)
    document.querySelectorAll('.action-pay').forEach((btn) => {
        btn.addEventListener('click', () => {
            const ref = btn.dataset.clearanceRef || 'CLR-2026-0000';
            const amount = btn.dataset.clearanceAmount || '150.00';
            const title = btn.dataset.clearanceTitle || 'Moving In / Out Gate Pass';

            const titleEl = document.getElementById('gcash-target-title');
            const amountEl = document.getElementById('gcash-amount-display');
            const refEl = document.getElementById('gcash-ref-display');

            if (titleEl) titleEl.textContent = title;
            if (amountEl) amountEl.textContent = '₱' + amount;
            if (refEl) refEl.textContent = ref;

            openSheet('sheet-gcash');
        });
    });

    // GCash Confirmation Button
    document.getElementById('btn-gcash-done')?.addEventListener('click', () => {
        closeAllSheets();
        toast('GCash payment noted. Clearance status will update upon admin SMS confirmation.', 'success');
    });

    // -------------------------------------------------------------
    // Bills & Payments Section
    // -------------------------------------------------------------

    // Bill Status Filter Chips
    document.querySelectorAll('[data-bill-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-bill-filter]').forEach((filter) => {
                filter.classList.toggle('selected', filter === button);
                filter.setAttribute('aria-pressed', String(filter === button));
            });
            let visible = 0;
            const filterValue = button.dataset.billFilter;
            document.querySelectorAll('.bill-card[data-status]').forEach((bill) => {
                const matches = filterValue === 'all' || bill.dataset.status === filterValue;
                bill.classList.toggle('hidden', !matches);
                if (matches) visible++;
            });
            document.getElementById('filtered-empty-bills')?.classList.toggle('hidden', visible !== 0);
        });
    });

    // Submit Payment Proof Button (from card)
    document.querySelectorAll('.bill-pay').forEach((button) => {
        button.addEventListener('click', () => {
            const feeId = document.getElementById('proof-fee-id');
            const label = document.getElementById('selected-bill-label');
            if (feeId) feeId.value = button.dataset.feeId;
            if (label) label.textContent = `${button.dataset.feeTitle} · ₱${button.dataset.feeAmount}`;
            openSheet('sheet-payment');
        });
    });

    // Payment History Accordion Toggle
    document.getElementById('history-toggle')?.addEventListener('click', () => {
        const history = document.getElementById('payment-history');
        const isHidden = history?.classList.toggle('hidden');
        const button = document.getElementById('history-toggle');
        if (button) {
            button.innerHTML = isHidden ? 'View Payment History →' : 'Hide Payment History ↑';
        }
    });

    // -------------------------------------------------------------
    // Community Feed (Posts, Comments, Reactions)
    // -------------------------------------------------------------

    // Feed Category Filter Chips
    document.querySelectorAll('[data-feed-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-feed-filter]').forEach((filter) => {
                filter.classList.toggle('selected', filter === button);
                filter.setAttribute('aria-pressed', String(filter === button));
            });
            let visible = 0;
            const filterValue = button.dataset.feedFilter;
            document.querySelectorAll('.post-card[data-category]').forEach((post) => {
                const matches = filterValue === 'all' || post.dataset.category === filterValue;
                post.classList.toggle('hidden', !matches);
                if (matches) visible++;
            });
            document.getElementById('empty-feed')?.classList.toggle('hidden', visible !== 0);
        });
    });

    // Expand/Collapse Comment Thread
    document.querySelectorAll('[data-comment]').forEach((button) => {
        button.addEventListener('click', () => {
            const thread = document.getElementById(button.getAttribute('aria-controls'));
            if (!thread) return;
            const expanded = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', String(expanded));
            thread.classList.toggle('hidden', !expanded);
            if (expanded) {
                thread.querySelector('input[name="body"]')?.focus();
            }
        });
    });

    // Submit Comment via AJAX
    document.querySelectorAll('[data-comment-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const input = form.elements.namedItem('body');
            const body = input.value.trim();
            if (!body) return;
            try {
                const result = await request('comment', {
                    post_id: form.dataset.postId,
                    body
                });
                const comment = document.createElement('div');
                comment.className = 'm3-comment-item';
                const author = document.createElement('strong');
                author.textContent = result.full_name;
                const text = document.createElement('span');
                text.textContent = ' ' + result.body;
                const content = document.createElement('p');
                content.append(author, text);
                comment.append(content);
                form.before(comment);
                input.value = '';
                const count = form.closest('.comment-thread')
                    ?.previousElementSibling?.querySelector('.comment-count');
                if (count) count.textContent = Number(count.textContent || 0) + 1;
                toast('Comment posted.', 'success');
            } catch (error) {
                toast(error.message, 'error');
            }
        });
    });

    // Reaction Button (Heart / Like)
    document.querySelectorAll('[data-reaction]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                const result = await request('reaction', { post_id: button.dataset.postId });
                button.classList.toggle('liked', result.reacted);
                button.setAttribute('aria-pressed', String(result.reacted));
                const count = button.querySelector('.like-count');
                if (count) count.textContent = result.count;
            } catch (error) {
                toast(error.message, 'error');
            }
        });
    });

    // -------------------------------------------------------------
    // Notifications & Announcements System
    // -------------------------------------------------------------
    const updateUnreadCount = (difference) => {
        ['top-bell-badge', 'bottom-alert-count', 'drawer-alert-count'].forEach((id) => {
            const badge = document.getElementById(id);
            if (!badge) return;
            const value = Math.max(0, Number(badge.textContent || 0) + difference);
            badge.textContent = value;
            badge.classList.toggle('hidden', value === 0);
        });
    };

    // Mark single notification read
    document.querySelectorAll('[data-notification-id]').forEach((notification) => {
        notification.dataset.read = notification.classList.contains('unread') ? '0' : '1';
        notification.addEventListener('click', async () => {
            if (notification.dataset.read === '1') return;
            try {
                await request('notification-read', {
                    notification_id: notification.dataset.notificationId
                });
                notification.dataset.read = '1';
                notification.classList.remove('unread');
                notification.querySelector('.unread-dot')?.remove();
                updateUnreadCount(-1);
            } catch (error) {
                toast(error.message, 'error');
            }
        });
    });

    // Mark all read button
    document.querySelector('[data-mark-all-read]')?.addEventListener('click', async () => {
        try {
            await request('notification-read');
            document.querySelectorAll('[data-notification-id]').forEach((notification) => {
                notification.dataset.read = '1';
                notification.classList.remove('unread');
                notification.querySelector('.unread-dot')?.remove();
            });
            ['top-bell-badge', 'bottom-alert-count', 'drawer-alert-count'].forEach((id) => {
                const badge = document.getElementById(id);
                if (!badge) return;
                badge.textContent = '0';
                badge.classList.add('hidden');
            });
            toast('All notifications marked as read.', 'success');
        } catch (error) {
            toast(error.message, 'error');
        }
    });

})();