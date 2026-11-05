// Controller/announcementController.js
// Announcements publishing and Web Push notification dispatcher
const { db } = require('../Model/db');

// In-memory latest broadcast event for real-time notification synchronization
let latestBroadcast = null;

// GET /api/announcements
// List all announcements
function getAnnouncements(req, res) {
    try {
        const stmt = db.prepare(`
            SELECT a.id, a.title, a.category, a.body, a.created_at,
                   u.name AS author_name, u.role AS author_role
            FROM announcements a
            JOIN users u ON a.author_id = u.id
            ORDER BY a.created_at DESC
        `);
        const announcements = stmt.all();
        return res.json({ announcements });
    } catch (err) {
        console.error('getAnnouncements error:', err);
        return res.status(500).json({ error: 'Failed to retrieve announcements.' });
    }
}

// POST /api/announcements
// Admin creates a new announcement and triggers a Push Notification broadcast
function createAnnouncement(req, res) {
    try {
        const authorId = req.user.id;
        const { title, category, body } = req.body;

        if (!title || !body) {
            return res.status(400).json({ error: 'Announcement title and content are required.' });
        }

        const validCategories = ['Urgent', 'Maintenance', 'Event', 'Notice', 'Security'];
        const selectedCategory = validCategories.includes(category) ? category : 'Notice';

        const stmt = db.prepare(`
            INSERT INTO announcements (author_id, title, category, body)
            VALUES (?, ?, ?, ?)
        `);
        const result = stmt.run(authorId, title.trim(), selectedCategory, body.trim());
        const announcementId = Number(result.lastInsertRowid);

        // Prepare push notification payload
        latestBroadcast = {
            id: announcementId,
            title: `[HOA Notice: ${selectedCategory}] ${title.trim()}`,
            body: body.trim().length > 120 ? body.trim().substring(0, 120) + '...' : body.trim(),
            category: selectedCategory,
            timestamp: new Date().toISOString()
        };

        return res.status(201).json({
            message: 'Announcement published successfully!',
            announcementId,
            notificationDispatched: true,
            pushPayload: latestBroadcast
        });
    } catch (err) {
        console.error('createAnnouncement error:', err);
        return res.status(500).json({ error: 'Failed to publish announcement.' });
    }
}

// GET /api/announcements/latest-broadcast
// Poll or check latest broadcast for triggering browser Push Notifications
function getLatestBroadcast(req, res) {
    return res.json({ latestBroadcast });
}

module.exports = {
    getAnnouncements,
    createAnnouncement,
    getLatestBroadcast
};
