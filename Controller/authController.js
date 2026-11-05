// Controller/authController.js
// Authentication, Registration, and Role Management
const jwt = require('jsonwebtoken');
const { db, hashPassword } = require('../Model/db');

const JWT_SECRET = process.env.JWT_SECRET || 'greenside_hoa_jwt_secret_key_2026';

// Middleware to authenticate JWT tokens
function authenticateToken(req, res, next) {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];

    if (!token) {
        return res.status(401).json({ error: 'Authentication required. Please log in.' });
    }

    jwt.verify(token, JWT_SECRET, (err, user) => {
        if (err) {
            return res.status(403).json({ error: 'Session expired or invalid token.' });
        }
        req.user = user;
        next();
    });
}

// Middleware to restrict access to Admin / Treasurer
function requireAdmin(req, res, next) {
    if (!req.user || req.user.role !== 'admin') {
        return res.status(403).json({ error: 'Access denied: Admin or Treasurer privileges required.' });
    }
    next();
}

// Optional Auth (populates req.user if token is present, does not fail if missing)
function optionalAuth(req, res, next) {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];
    if (!token) {
        return next();
    }
    jwt.verify(token, JWT_SECRET, (err, user) => {
        if (!err) req.user = user;
        next();
    });
}

// POST /api/auth/register
// User sign-up flow
function register(req, res) {
    try {
        const { name, username, password, block_lot } = req.body;

        if (!name || !username || !password) {
            return res.status(400).json({ error: 'Name, username, and password are required.' });
        }

        const normalizedUsername = username.trim().toLowerCase();

        // Check if username already exists
        const checkUser = db.prepare('SELECT id FROM users WHERE LOWER(username) = ?');
        const existing = checkUser.get(normalizedUsername);
        if (existing) {
            return res.status(409).json({ error: 'Username or email already in use.' });
        }

        // Insert new regular resident
        const hashedPassword = hashPassword(password);
        const insertStmt = db.prepare(`
            INSERT INTO users (name, username, password_hash, role, block_lot)
            VALUES (?, ?, ?, 'resident', ?)
        `);
        const result = insertStmt.run(name.trim(), normalizedUsername, hashedPassword, (block_lot || '').trim());
        const newUserId = Number(result.lastInsertRowid);

        const userPayload = {
            id: newUserId,
            name: name.trim(),
            username: normalizedUsername,
            role: 'resident',
            block_lot: (block_lot || '').trim()
        };

        const token = jwt.sign(userPayload, JWT_SECRET, { expiresIn: '7d' });

        return res.status(201).json({
            message: 'Account created successfully!',
            token,
            user: userPayload
        });
    } catch (err) {
        console.error('Registration error:', err);
        return res.status(500).json({ error: 'Internal server error during registration.' });
    }
}

// POST /api/auth/login
// User log-in flow with role handling
function login(req, res) {
    try {
        const { username, password } = req.body;

        if (!username || !password) {
            return res.status(400).json({ error: 'Username and password are required.' });
        }

        const normalizedUsername = username.trim().toLowerCase();
        const hashedPassword = hashPassword(password);

        const userStmt = db.prepare(`
            SELECT id, name, username, role, block_lot
            FROM users
            WHERE LOWER(username) = ? AND password_hash = ?
        `);
        const user = userStmt.get(normalizedUsername, hashedPassword);

        if (!user) {
            return res.status(401).json({ error: 'Invalid username or password.' });
        }

        const token = jwt.sign(user, JWT_SECRET, { expiresIn: '7d' });

        return res.json({
            message: 'Login successful!',
            token,
            user
        });
    } catch (err) {
        console.error('Login error:', err);
        return res.status(500).json({ error: 'Internal server error during login.' });
    }
}

// GET /api/auth/me
// Get current authenticated user details
function getMe(req, res) {
    try {
        const userStmt = db.prepare(`
            SELECT id, name, username, role, block_lot, created_at
            FROM users
            WHERE id = ?
        `);
        const user = userStmt.get(req.user.id);
        if (!user) {
            return res.status(404).json({ error: 'User not found.' });
        }
        return res.json({ user });
    } catch (err) {
        return res.status(500).json({ error: 'Failed to retrieve profile.' });
    }
}

module.exports = {
    authenticateToken,
    requireAdmin,
    optionalAuth,
    register,
    login,
    getMe
};
