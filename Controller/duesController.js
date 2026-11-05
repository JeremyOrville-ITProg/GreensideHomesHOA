// Controller/duesController.js
// Dues management, proof of payment submission, and admin verification dashboard
const { db } = require('../Model/db');

// GET /api/dues
// List all dues
function getDues(req, res) {
    try {
        const stmt = db.prepare('SELECT * FROM dues ORDER BY due_date DESC, created_at DESC');
        const dues = stmt.all();
        return res.json({ dues });
    } catch (err) {
        console.error('getDues error:', err);
        return res.status(500).json({ error: 'Failed to retrieve dues.' });
    }
}

// POST /api/dues
// Admin/Treasurer creates a Monthly Due
function createDue(req, res) {
    try {
        const { title, amount, description, due_date } = req.body;

        if (!title || !amount || !due_date) {
            return res.status(400).json({ error: 'Title, amount, and due date are required.' });
        }

        const parsedAmount = parseFloat(amount);
        if (isNaN(parsedAmount) || parsedAmount <= 0) {
            return res.status(400).json({ error: 'Amount must be a positive number.' });
        }

        const stmt = db.prepare(`
            INSERT INTO dues (title, amount, description, due_date)
            VALUES (?, ?, ?, ?)
        `);
        const result = stmt.run(title.trim(), parsedAmount, (description || '').trim(), due_date);

        return res.status(201).json({
            message: 'Monthly due created successfully!',
            dueId: Number(result.lastInsertRowid)
        });
    } catch (err) {
        console.error('createDue error:', err);
        return res.status(500).json({ error: 'Failed to create monthly due.' });
    }
}

// POST /api/payments
// User submits proof of payment (image upload + reference number) linked to specific due
function submitPayment(req, res) {
    try {
        const userId = req.user.id;
        const { due_id, amount, ref_number, proof_image } = req.body;

        if (!due_id || !amount || !ref_number || !proof_image) {
            return res.status(400).json({ error: 'Due selection, amount, reference number, and receipt photo are required.' });
        }

        const parsedAmount = parseFloat(amount);
        if (isNaN(parsedAmount) || parsedAmount <= 0) {
            return res.status(400).json({ error: 'Invalid payment amount.' });
        }

        // Verify due exists
        const dueCheck = db.prepare('SELECT id FROM dues WHERE id = ?');
        const due = dueCheck.get(due_id);
        if (!due) {
            return res.status(404).json({ error: 'Specified due not found.' });
        }

        const stmt = db.prepare(`
            INSERT INTO payments (user_id, due_id, amount, ref_number, proof_image, status)
            VALUES (?, ?, ?, ?, ?, 'Pending')
        `);
        const result = stmt.run(userId, due_id, parsedAmount, ref_number.trim(), proof_image);

        return res.status(201).json({
            message: 'Payment proof submitted successfully! Pending treasurer review.',
            paymentId: Number(result.lastInsertRowid)
        });
    } catch (err) {
        console.error('submitPayment error:', err);
        return res.status(500).json({ error: 'Failed to submit payment proof.' });
    }
}

// GET /api/payments/my
// Resident retrieves their own payments & balance summary
function getMyPayments(req, res) {
    try {
        const userId = req.user.id;

        const paymentsStmt = db.prepare(`
            SELECT p.id, p.amount, p.ref_number, p.proof_image, p.status, p.remarks,
                   p.submitted_at, p.verified_at,
                   d.title AS due_title, d.due_date, d.amount AS due_amount
            FROM payments p
            JOIN dues d ON p.due_id = d.id
            WHERE p.user_id = ?
            ORDER BY p.submitted_at DESC
        `);
        const payments = paymentsStmt.all(userId);

        // Calculate latest outstanding due balance
        // Find latest due and whether resident has an approved payment for it
        const latestDueStmt = db.prepare('SELECT * FROM dues ORDER BY due_date DESC LIMIT 1');
        const latestDue = latestDueStmt.get();

        let outstanding = 0;
        let latestDueStatus = 'None';
        if (latestDue) {
            const checkPayment = db.prepare(`
                SELECT status FROM payments
                WHERE user_id = ? AND due_id = ?
                ORDER BY submitted_at DESC LIMIT 1
            `);
            const paymentRecord = checkPayment.get(userId, latestDue.id);

            if (!paymentRecord) {
                outstanding = latestDue.amount;
                latestDueStatus = 'Unpaid';
            } else if (paymentRecord.status === 'Approved') {
                outstanding = 0;
                latestDueStatus = 'Paid';
            } else if (paymentRecord.status === 'Pending') {
                outstanding = latestDue.amount;
                latestDueStatus = 'Pending review';
            } else if (paymentRecord.status === 'Rejected') {
                outstanding = latestDue.amount;
                latestDueStatus = 'Rejected - Resubmission required';
            }
        }

        return res.json({
            payments,
            latestDue,
            outstanding,
            latestDueStatus
        });
    } catch (err) {
        console.error('getMyPayments error:', err);
        return res.status(500).json({ error: 'Failed to retrieve payment history.' });
    }
}

// GET /api/payments/all
// Admin / Treasurer verification dashboard: retrieve all payment submissions
function getAllPayments(req, res) {
    try {
        const stmt = db.prepare(`
            SELECT p.id, p.amount, p.ref_number, p.proof_image, p.status, p.remarks,
                   p.submitted_at, p.verified_at,
                   u.name AS resident_name, u.username AS resident_username, u.block_lot,
                   d.title AS due_title, d.due_date,
                   v.name AS verified_by_name
            FROM payments p
            JOIN users u ON p.user_id = u.id
            JOIN dues d ON p.due_id = d.id
            LEFT JOIN users v ON p.verified_by = v.id
            ORDER BY 
                CASE p.status WHEN 'Pending' THEN 1 ELSE 2 END,
                p.submitted_at DESC
        `);
        const payments = stmt.all();
        return res.json({ payments });
    } catch (err) {
        console.error('getAllPayments error:', err);
        return res.status(500).json({ error: 'Failed to retrieve verification list.' });
    }
}

// PATCH /api/payments/:id/verify
// Admin / Treasurer approves or rejects a submission and logs the decision
function verifyPayment(req, res) {
    try {
        const paymentId = parseInt(req.params.id, 10);
        const { status, remarks } = req.body;
        const verifierId = req.user.id;

        if (!['Approved', 'Rejected'].includes(status)) {
            return res.status(400).json({ error: 'Status must be either "Approved" or "Rejected".' });
        }

        const stmt = db.prepare(`
            UPDATE payments
            SET status = ?, remarks = ?, verified_at = CURRENT_TIMESTAMP, verified_by = ?
            WHERE id = ?
        `);
        const result = stmt.run(status, (remarks || '').trim(), verifierId, paymentId);

        if (result.changes === 0) {
            return res.status(404).json({ error: 'Payment record not found.' });
        }

        return res.json({
            message: `Payment successfully marked as ${status}.`,
            status
        });
    } catch (err) {
        console.error('verifyPayment error:', err);
        return res.status(500).json({ error: 'Failed to update payment status.' });
    }
}

module.exports = {
    getDues,
    createDue,
    submitPayment,
    getMyPayments,
    getAllPayments,
    verifyPayment
};
