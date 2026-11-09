(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const pageTitles = {
        home: 'Community',
        bills: 'Bills & payments',
        create: 'Create a post',
        alerts: 'Alerts',
        profile: 'My profile',
        admin: 'HOA management'
    };

    const showPage = (name) => {
        const target = document.getElementById(`page-${name}`);
        if (!target) return;
        document.querySelectorAll('.page-view').forEach((page) => {
            const active = page === target;
            page.classList.toggle('hidden', !active);
            page.setAttribute('aria-hidden', String(!active));
        });
        document.querySelectorAll('[data-page]').forEach((item) => {
            item.classList.toggle('active', item.dataset.page === name);
        });
        const title = document.getElementById('page-title');
        if (title) title.textContent = pageTitles[name] || 'Community';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    document.querySelectorAll('[data-page]').forEach((item) => {
        item.addEventListener('click', () => showPage(item.dataset.page));
    });

    const toast = (message) => {
        const element = document.getElementById('toast');
        if (!element) return;
        element.textContent = message;
        element.classList.add('visible');
        window.clearTimeout(toast.timer);
        toast.timer = window.setTimeout(() => element.classList.remove('visible'), 3200);
    };

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

    document.querySelectorAll('[data-feed-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-feed-filter]').forEach((filter) => {
                filter.classList.toggle('selected', filter === button);
                filter.setAttribute('aria-pressed', String(filter === button));
            });
            let visible = 0;
            document.querySelectorAll('.post-card[data-category]').forEach((post) => {
                const matches = button.dataset.feedFilter === 'all'
                    || post.dataset.category === button.dataset.feedFilter;
                post.classList.toggle('hidden', !matches);
                if (matches) visible++;
            });
            document.getElementById('empty-feed')?.classList.toggle('hidden', visible !== 0);
        });
    });

    document.querySelectorAll('[data-comment]').forEach((button) => {
        button.addEventListener('click', () => {
            const thread = document.getElementById(button.getAttribute('aria-controls'));
            if (!thread) return;
            const expanded = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', String(expanded));
            thread.classList.toggle('hidden', !expanded);
        });
    });

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
                comment.className = 'comment-item';
                const author = document.createElement('strong');
                author.textContent = result.full_name;
                const text = document.createElement('span');
                text.textContent = result.body;
                const content = document.createElement('p');
                content.append(author, text);
                comment.append(content);
                form.before(comment);
                input.value = '';
                const count = form.closest('.comment-thread')
                    ?.previousElementSibling?.querySelector('.comment-count');
                if (count) count.textContent = Number(count.textContent || 0) + 1;
                toast('Comment added.');
            } catch (error) {
                toast(error.message);
            }
        });
    });

    document.querySelectorAll('[data-reaction]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                const result = await request('reaction', { post_id: button.dataset.postId });
                button.classList.toggle('liked', result.reacted);
                button.setAttribute('aria-pressed', String(result.reacted));
                const count = button.querySelector('.like-count');
                if (count) count.textContent = result.count;
            } catch (error) {
                toast(error.message);
            }
        });
    });

    const updateUnreadCount = (difference) => {
        ['nav-alert-count', 'mobile-alert-count'].forEach((id) => {
            const badge = document.getElementById(id);
            if (!badge) return;
            const value = Math.max(0, Number(badge.textContent || 0) + difference);
            badge.textContent = value;
            badge.classList.toggle('hidden', value === 0);
        });
    };

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
                toast(error.message);
            }
        });
    });

    document.querySelector('[data-mark-all-read]')?.addEventListener('click', async () => {
        try {
            await request('notification-read');
            document.querySelectorAll('[data-notification-id]').forEach((notification) => {
                notification.dataset.read = '1';
                notification.classList.remove('unread');
                notification.querySelector('.unread-dot')?.remove();
            });
            ['nav-alert-count', 'mobile-alert-count'].forEach((id) => {
                const badge = document.getElementById(id);
                if (!badge) return;
                badge.textContent = '0';
                badge.classList.add('hidden');
            });
            toast('All notifications marked as read.');
        } catch (error) {
            toast(error.message);
        }
    });

    document.querySelectorAll('[data-bill-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-bill-filter]').forEach((filter) => {
                filter.classList.toggle('selected', filter === button);
                filter.setAttribute('aria-pressed', String(filter === button));
            });
            let visible = 0;
            document.querySelectorAll('.bill-card[data-status]').forEach((bill) => {
                const matches = bill.dataset.status === button.dataset.billFilter;
                bill.classList.toggle('hidden', !matches);
                if (matches) visible++;
            });
            document.getElementById('filtered-empty-bills')?.classList.toggle('hidden', visible !== 0);
        });
    });

    const dialog = document.getElementById('payment-dialog');
    document.querySelectorAll('[data-fee-id]').forEach((button) => {
        button.addEventListener('click', () => {
            const feeId = document.getElementById('proof-fee-id');
            const label = document.getElementById('selected-bill-label');
            if (feeId) feeId.value = button.dataset.feeId;
            if (label) label.textContent = `${button.dataset.feeTitle} · ₱${button.dataset.feeAmount}`;
            dialog?.showModal();
        });
    });
    document.querySelector('[data-close-payment]')?.addEventListener('click', () => dialog?.close());
    dialog?.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });

    document.getElementById('history-toggle')?.addEventListener('click', () => {
        const history = document.getElementById('payment-history');
        const visible = history?.classList.toggle('hidden') === false;
        const button = document.getElementById('history-toggle');
        if (button) button.innerHTML = visible ? 'Hide history <span aria-hidden="true">↑</span>' : 'Payment history <span aria-hidden="true">→</span>';
    });
})();
