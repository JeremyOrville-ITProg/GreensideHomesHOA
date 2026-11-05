// test_api.js - Automated test suite for Greenside Homes HOA Portal API
const http = require('node:http');

// Helper to make HTTP requests
function request(options, data = null) {
    return new Promise((resolve, reject) => {
        const req = http.request(options, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                try {
                    const parsed = body ? JSON.parse(body) : null;
                    resolve({ status: res.statusCode, headers: res.headers, data: parsed });
                } catch (e) {
                    resolve({ status: res.statusCode, headers: res.headers, data: body });
                }
            });
        });
        req.on('error', reject);
        if (data) {
            req.write(typeof data === 'string' ? data : JSON.stringify(data));
        }
        req.end();
    });
}

async function runTests() {
    console.log('--- Starting API Integration Tests ---');
    const port = 8001; // Run tests on dedicated port
    process.env.PORT = port;
    const app = require('./server');

    // Wait a brief moment for server to listen
    await new Promise(r => setTimeout(r, 500));

    let adminToken = '';
    let residentToken = '';
    let testDueId = null;
    let testPaymentId = null;
    let testPostId = null;
    let testCommentId = null;

    // 1. Test Admin Login
    console.log('\n[1/12] Testing Admin Login (admin / password)...');
    const adminLoginRes = await request({
        hostname: 'localhost',
        port,
        path: '/api/auth/login',
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    }, { username: 'admin', password: 'password' });

    if (adminLoginRes.status === 200 && adminLoginRes.data.user.role === 'admin') {
        adminToken = adminLoginRes.data.token;
        console.log('✅ Admin Login Passed. Role:', adminLoginRes.data.user.role);
    } else {
        throw new Error('Admin Login Failed: ' + JSON.stringify(adminLoginRes.data));
    }

    // 2. Test Resident Registration
    console.log('\n[2/12] Testing Resident Registration...');
    const testUsername = 'testuser_' + Date.now();
    const regRes = await request({
        hostname: 'localhost',
        port,
        path: '/api/auth/register',
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    }, {
        name: 'Test Resident',
        username: testUsername,
        password: 'password123',
        block_lot: 'Block 9, Lot 3'
    });

    if (regRes.status === 201 && regRes.data.user.role === 'resident') {
        residentToken = regRes.data.token;
        console.log('✅ Resident Registration Passed. Username:', testUsername);
    } else {
        throw new Error('Resident Registration Failed: ' + JSON.stringify(regRes.data));
    }

    // 3. Test Due Creation (Admin Only)
    console.log('\n[3/12] Testing Due Creation by Admin...');
    const dueRes = await request({
        hostname: 'localhost',
        port,
        path: '/api/dues',
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + adminToken
        }
    }, {
        title: 'November 2026 Clubhouse & Security Dues',
        amount: 1500.00,
        description: 'Monthly dues including security and lawn maintenance.',
        due_date: '2026-11-30'
    });

    if (dueRes.status === 201) {
        testDueId = dueRes.data.dueId;
        console.log('✅ Due Creation Passed. Due ID:', testDueId);
    } else {
        throw new Error('Due Creation Failed: ' + JSON.stringify(dueRes.data));
    }

    // 4. Test Payment Submission by Resident
    console.log('\n[4/12] Testing Payment Proof Submission by Resident...');
    const paymentRes = await request({
        hostname: 'localhost',
        port,
        path: '/api/payments',
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + residentToken
        }
    }, {
        due_id: testDueId,
        amount: 1500.00,
        ref_number: 'GCASH-9876543210',
        proof_image: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
    });

    if (paymentRes.status === 201) {
        testPaymentId = paymentRes.data.paymentId;
        console.log('✅ Payment Proof Submitted. Payment ID:', testPaymentId);
    } else {
        throw new Error('Payment Submission Failed: ' + JSON.stringify(paymentRes.data));
    }

    // 5. Test Admin Payment Verification Dashboard & Approval
    console.log('\n[5/12] Testing Admin Payment Verification Approval...');
    const verifyRes = await request({
        hostname: 'localhost',
        port,
        path: `/api/payments/${testPaymentId}/verify`,
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + adminToken
        }
    }, {
        status: 'Approved',
        remarks: 'GCash reference verified with treasury ledger.'
    });

    if (verifyRes.status === 200 && verifyRes.data.status === 'Approved') {
        console.log('✅ Admin Payment Verification Passed.');
    } else {
        throw new Error('Payment Verification Failed: ' + JSON.stringify(verifyRes.data));
    }

    // 6. Test Resident Payment History & Updated Outstanding Balance
    console.log('\n[6/12] Testing Resident Payment History & Outstanding Calculation...');
    const myPaymentsRes = await request({
        hostname: 'localhost',
        port,
        path: '/api/payments/my',
        method: 'GET',
        headers: { 'Authorization': 'Bearer ' + residentToken }
    });

    if (myPaymentsRes.status === 200 && myPaymentsRes.data.latestDueStatus === 'Paid') {
        console.log('✅ Resident Outstanding Balance dynamically reflects Paid status.');
    } else {
        throw new Error('Resident Payment History Failed: ' + JSON.stringify(myPaymentsRes.data));
    }

    // 7. Test Forum Post Creation
    console.log('\n[7/12] Testing Community Forum Post Creation...');
    const postRes = await request({
        hostname: 'localhost',
        port,
        path: '/api/forum/posts',
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + residentToken
        }
    }, {
        title: 'Lost Cat in Block 9',
        body: 'Orange tabby cat wearing a green collar missing since yesterday.',
        category: 'Lost & Found'
    });

    if (postRes.status === 201) {
        testPostId = postRes.data.postId;
        console.log('✅ Forum Post Created. Post ID:', testPostId);
    } else {
        throw new Error('Forum Post Creation Failed: ' + JSON.stringify(postRes.data));
    }

    // 8. Test Reddit-style Upvoting
    console.log('\n[8/12] Testing Reddit-style Upvoting...');
    const voteRes = await request({
        hostname: 'localhost',
        port,
        path: `/api/forum/posts/${testPostId}/vote`,
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + adminToken
        }
    }, { value: 1 });

    if (voteRes.status === 200 && voteRes.data.voteScore === 1) {
        console.log('✅ Upvote Recorded. Net Score:', voteRes.data.voteScore);
    } else {
        throw new Error('Upvoting Failed: ' + JSON.stringify(voteRes.data));
    }

    // 9. Test Forum Comments (Top-Level)
    console.log('\n[9/12] Testing Top-Level Forum Comment...');
    const commentRes = await request({
        hostname: 'localhost',
        port,
        path: `/api/forum/posts/${testPostId}/comments`,
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + adminToken
        }
    }, {
        content: 'I will inform the security guardhouse at Gate 1 to keep an eye out.'
    });

    if (commentRes.status === 201) {
        testCommentId = commentRes.data.commentId;
        console.log('✅ Top-Level Comment Created. Comment ID:', testCommentId);
    } else {
        throw new Error('Comment Creation Failed: ' + JSON.stringify(commentRes.data));
    }

    // 10. Test Threaded / Nested Comment Reply
    console.log('\n[10/12] Testing Threaded Comment Reply...');
    const replyRes = await request({
        hostname: 'localhost',
        port,
        path: `/api/forum/posts/${testPostId}/comments`,
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + residentToken
        }
    }, {
        parent_id: testCommentId,
        content: 'Thank you so much, appreciate the help!'
    });

    if (replyRes.status === 201) {
        console.log('✅ Threaded Nested Reply Created.');
    } else {
        throw new Error('Threaded Reply Failed: ' + JSON.stringify(replyRes.data));
    }

    // 11. Verify Comment Tree Hierarchy
    console.log('\n[11/12] Verifying Threaded Comment Tree Response...');
    const treeRes = await request({
        hostname: 'localhost',
        port,
        path: `/api/forum/posts/${testPostId}/comments`,
        method: 'GET'
    });

    if (treeRes.status === 200 && treeRes.data.comments.length > 0 && treeRes.data.comments[0].children.length === 1) {
        console.log('✅ Comment Tree successfully formed with nested child reply.');
    } else {
        throw new Error('Comment Tree verification failed: ' + JSON.stringify(treeRes.data));
    }

    // 12. Test Announcement & Web Push Dispatch
    console.log('\n[12/12] Testing Announcement Publishing & Push Notification Trigger...');
    const announceRes = await request({
        hostname: 'localhost',
        port,
        path: '/api/announcements',
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + adminToken
        }
    }, {
        title: 'Emergency Generator Maintenance',
        category: 'Urgent',
        body: 'The backup generator for streetlights will be tested today at 3 PM.'
    });

    if (announceRes.status === 201 && announceRes.data.notificationDispatched === true) {
        console.log('✅ Announcement Published & Push Notification Dispatched.');
        console.log('   Payload:', announceRes.data.pushPayload.title);
    } else {
        throw new Error('Announcement Test Failed: ' + JSON.stringify(announceRes.data));
    }

    console.log('\n======================================================');
    console.log('🎉 ALL 12 API MODULE INTEGRATION TESTS PASSED!');
    console.log('======================================================\n');
    process.exit(0);
}

runTests().catch(err => {
    console.error('❌ Test Suite Failed:', err);
    process.exit(1);
});
