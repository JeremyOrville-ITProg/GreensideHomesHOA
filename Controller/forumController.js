// Controller/forumController.js
// Community Forum (Reddit-style): posts, tags, voting, threaded comments, moderation
const { db } = require('../Model/db');

// Helper to build a nested/threaded tree from flat comments list
function buildCommentTree(flatComments, parentId = null) {
    const branch = [];
    for (const comment of flatComments) {
        if (comment.parent_id === parentId) {
            const children = buildCommentTree(flatComments, comment.id);
            branch.push({
                ...comment,
                children
            });
        }
    }
    return branch;
}

// GET /api/forum/posts
// List posts optionally filtered by category
function getPosts(req, res) {
    try {
        const { category, sort } = req.query;
        const currentUserId = req.user ? req.user.id : null;

        let query = `
            SELECT p.id, p.title, p.body, p.category, p.image_url, p.vote_score, p.created_at,
                   u.id AS author_id, u.name AS author_name, u.role AS author_role, u.block_lot AS author_block_lot,
                   (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,
                   (SELECT value FROM votes v WHERE v.post_id = p.id AND v.user_id = ?) AS user_vote
            FROM posts p
            JOIN users u ON p.user_id = u.id
        `;
        const params = [currentUserId];

        if (category && category !== 'All') {
            query += ' WHERE p.category = ?';
            params.push(category);
        }

        if (sort === 'new') {
            query += ' ORDER BY p.created_at DESC';
        } else {
            // Default: sort by top votes then newest
            query += ' ORDER BY p.vote_score DESC, p.created_at DESC';
        }

        const stmt = db.prepare(query);
        const posts = stmt.all(...params);

        return res.json({ posts });
    } catch (err) {
        console.error('getPosts error:', err);
        return res.status(500).json({ error: 'Failed to retrieve forum posts.' });
    }
}

// POST /api/forum/posts
// Create a new forum post
function createPost(req, res) {
    try {
        const userId = req.user.id;
        const { title, body, category, image_url } = req.body;

        if (!title || !body) {
            return res.status(400).json({ error: 'Post title and description are required.' });
        }

        const validCategories = ['General', 'Safety', 'Maintenance', 'Events', 'Suggestions', 'Lost & Found'];
        const selectedCategory = validCategories.includes(category) ? category : 'General';

        const stmt = db.prepare(`
            INSERT INTO posts (user_id, title, body, category, image_url, vote_score)
            VALUES (?, ?, ?, ?, ?, 0)
        `);
        const result = stmt.run(userId, title.trim(), body.trim(), selectedCategory, image_url || null);

        return res.status(201).json({
            message: 'Post created successfully!',
            postId: Number(result.lastInsertRowid)
        });
    } catch (err) {
        console.error('createPost error:', err);
        return res.status(500).json({ error: 'Failed to create forum post.' });
    }
}

// POST /api/forum/posts/:id/vote
// Reddit-style Upvote / Downvote toggle
function votePost(req, res) {
    try {
        const userId = req.user.id;
        const postId = parseInt(req.params.id, 10);
        const { value } = req.body; // 1 for upvote, -1 for downvote, 0 to remove vote

        if (![1, -1, 0].includes(value)) {
            return res.status(400).json({ error: 'Vote value must be 1, -1, or 0.' });
        }

        // Check if post exists
        const postCheck = db.prepare('SELECT id FROM posts WHERE id = ?');
        const post = postCheck.get(postId);
        if (!post) {
            return res.status(404).json({ error: 'Post not found.' });
        }

        // Get existing user vote
        const getVote = db.prepare('SELECT id, value FROM votes WHERE user_id = ? AND post_id = ?');
        const existingVote = getVote.get(userId, postId);

        if (value === 0 || (existingVote && existingVote.value === value)) {
            // Clear vote
            if (existingVote) {
                db.prepare('DELETE FROM votes WHERE id = ?').run(existingVote.id);
            }
        } else if (existingVote) {
            // Update vote
            db.prepare('UPDATE votes SET value = ? WHERE id = ?').run(value, existingVote.id);
        } else {
            // Insert new vote
            db.prepare('INSERT INTO votes (user_id, post_id, value) VALUES (?, ?, ?)').run(userId, postId, value);
        }

        // Recalculate post vote score
        const scoreStmt = db.prepare('SELECT COALESCE(SUM(value), 0) AS total_score FROM votes WHERE post_id = ?');
        const { total_score } = scoreStmt.get(postId);

        db.prepare('UPDATE posts SET vote_score = ? WHERE id = ?').run(total_score, postId);

        // Fetch updated user vote
        const updatedVoteStmt = db.prepare('SELECT value FROM votes WHERE user_id = ? AND post_id = ?');
        const updatedVote = updatedVoteStmt.get(userId, postId);

        return res.json({
            message: 'Vote recorded.',
            voteScore: total_score,
            userVote: updatedVote ? updatedVote.value : 0
        });
    } catch (err) {
        console.error('votePost error:', err);
        return res.status(500).json({ error: 'Failed to process vote.' });
    }
}

// GET /api/forum/posts/:id/comments
// Retrieve comments for a post in a threaded/hierarchical tree
function getComments(req, res) {
    try {
        const postId = parseInt(req.params.id, 10);

        const stmt = db.prepare(`
            SELECT c.id, c.post_id, c.user_id, c.parent_id, c.content, c.created_at,
                   u.name AS author_name, u.role AS author_role, u.block_lot AS author_block_lot
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.post_id = ?
            ORDER BY c.created_at ASC
        `);
        const flatComments = stmt.all(postId);
        const commentTree = buildCommentTree(flatComments, null);

        return res.json({ comments: commentTree, totalCount: flatComments.length });
    } catch (err) {
        console.error('getComments error:', err);
        return res.status(500).json({ error: 'Failed to retrieve comments.' });
    }
}

// POST /api/forum/posts/:id/comments
// Create a new comment or threaded reply
function addComment(req, res) {
    try {
        const userId = req.user.id;
        const postId = parseInt(req.params.id, 10);
        const { content, parent_id } = req.body;

        if (!content || !content.trim()) {
            return res.status(400).json({ error: 'Comment content cannot be empty.' });
        }

        // Verify post exists
        const post = db.prepare('SELECT id FROM posts WHERE id = ?').get(postId);
        if (!post) {
            return res.status(404).json({ error: 'Post not found.' });
        }

        // If parent_id is provided, verify parent comment exists on this post
        let parentIdVal = null;
        if (parent_id) {
            const parent = db.prepare('SELECT id FROM comments WHERE id = ? AND post_id = ?').get(parent_id, postId);
            if (!parent) {
                return res.status(404).json({ error: 'Parent comment not found.' });
            }
            parentIdVal = parent_id;
        }

        const stmt = db.prepare(`
            INSERT INTO comments (post_id, user_id, parent_id, content)
            VALUES (?, ?, ?, ?)
        `);
        const result = stmt.run(postId, userId, parentIdVal, content.trim());

        return res.status(201).json({
            message: 'Comment added successfully!',
            commentId: Number(result.lastInsertRowid)
        });
    } catch (err) {
        console.error('addComment error:', err);
        return res.status(500).json({ error: 'Failed to post comment.' });
    }
}

// DELETE /api/forum/posts/:id
// Moderation: Admin or post author can delete a post
function deletePost(req, res) {
    try {
        const postId = parseInt(req.params.id, 10);
        const user = req.user;

        const post = db.prepare('SELECT user_id FROM posts WHERE id = ?').get(postId);
        if (!post) {
            return res.status(404).json({ error: 'Post not found.' });
        }

        // Allow deletion if user is admin or author
        if (user.role !== 'admin' && user.id !== post.user_id) {
            return res.status(403).json({ error: 'You do not have permission to delete this post.' });
        }

        db.prepare('DELETE FROM posts WHERE id = ?').run(postId);
        return res.json({ message: 'Post deleted successfully.' });
    } catch (err) {
        console.error('deletePost error:', err);
        return res.status(500).json({ error: 'Failed to delete post.' });
    }
}

// DELETE /api/forum/comments/:id
// Moderation: Admin or comment author can delete a comment
function deleteComment(req, res) {
    try {
        const commentId = parseInt(req.params.id, 10);
        const user = req.user;

        const comment = db.prepare('SELECT user_id FROM comments WHERE id = ?').get(commentId);
        if (!comment) {
            return res.status(404).json({ error: 'Comment not found.' });
        }

        if (user.role !== 'admin' && user.id !== comment.user_id) {
            return res.status(403).json({ error: 'You do not have permission to delete this comment.' });
        }

        db.prepare('DELETE FROM comments WHERE id = ?').run(commentId);
        return res.json({ message: 'Comment deleted successfully.' });
    } catch (err) {
        console.error('deleteComment error:', err);
        return res.status(500).json({ error: 'Failed to delete comment.' });
    }
}

module.exports = {
    getPosts,
    createPost,
    votePost,
    getComments,
    addComment,
    deletePost,
    deleteComment
};
