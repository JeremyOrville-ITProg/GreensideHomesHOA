// server.js - Greenside Homes HOA Portal Full-Stack Server
const express = require('express');
const cors = require('cors');
const path = require('node:path');

// Controllers
const authController = require('./Controller/authController');
const duesController = require('./Controller/duesController');
const forumController = require('./Controller/forumController');
const announcementController = require('./Controller/announcementController');

const app = express();
const PORT = process.env.PORT || 8000;

// Middleware
app.use(cors());
app.use(express.json({ limit: '15mb' })); // Support base64 receipt & post photo uploads
app.use(express.urlencoded({ extended: true, limit: '15mb' }));

// Serve static assets & views
app.use('/Assets', express.static(path.join(__dirname, 'Assets')));
app.use('/Views', express.static(path.join(__dirname, 'Views')));

// Root route serves the primary SPA view
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'Views', 'index.html'));
});

// ================= API ROUTES ================= //

// 1. Authentication & Roles
app.post('/api/auth/register', authController.register);
app.post('/api/auth/login', authController.login);
app.get('/api/auth/me', authController.authenticateToken, authController.getMe);

// 2. Dues & Transaction Verification
app.get('/api/dues', duesController.getDues);
app.post('/api/dues', authController.authenticateToken, authController.requireAdmin, duesController.createDue);

app.get('/api/payments/my', authController.authenticateToken, duesController.getMyPayments);
app.post('/api/payments', authController.authenticateToken, duesController.submitPayment);
app.get('/api/payments/all', authController.authenticateToken, authController.requireAdmin, duesController.getAllPayments);
app.patch('/api/payments/:id/verify', authController.authenticateToken, authController.requireAdmin, duesController.verifyPayment);

// 3. Community Forum (Reddit-style)
app.get('/api/forum/posts', authController.optionalAuth, forumController.getPosts);
app.post('/api/forum/posts', authController.authenticateToken, forumController.createPost);
app.post('/api/forum/posts/:id/vote', authController.authenticateToken, forumController.votePost);
app.get('/api/forum/posts/:id/comments', forumController.getComments);
app.post('/api/forum/posts/:id/comments', authController.authenticateToken, forumController.addComment);
app.delete('/api/forum/posts/:id', authController.authenticateToken, forumController.deletePost);
app.delete('/api/forum/comments/:id', authController.authenticateToken, forumController.deleteComment);

// 4. Announcements & Push Notifications
app.get('/api/announcements', announcementController.getAnnouncements);
app.post('/api/announcements', authController.authenticateToken, authController.requireAdmin, announcementController.createAnnouncement);
app.get('/api/announcements/latest-broadcast', announcementController.getLatestBroadcast);

// Fallback for SPA routing
app.get('*', (req, res) => {
    if (req.accepts('html')) {
        res.sendFile(path.join(__dirname, 'Views', 'index.html'));
    } else {
        res.status(404).json({ error: 'Endpoint not found' });
    }
});

// Start Server
app.listen(PORT, () => {
    console.log(`=======================================================`);
    console.log(` Greenside Homes HOA Portal Server Running`);
    console.log(` URL: http://localhost:${PORT}`);
    console.log(` Default Admin: admin | password`);
    console.log(` Default Resident: johndoe | password`);
    console.log(`=======================================================`);
});

module.exports = app;
