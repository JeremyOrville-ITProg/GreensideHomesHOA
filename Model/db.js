// Model/db.js
// SQLite database connection & schema initialization using Node.js native sqlite
const { DatabaseSync } = require('node:sqlite');
const path = require('node:path');
const fs = require('node:fs');
const crypto = require('node:crypto');

const dbPath = path.join(__dirname, '..', 'hoa.db');
const db = new DatabaseSync(dbPath);

// Enable WAL mode and foreign key enforcement
db.exec('PRAGMA foreign_keys = ON;');

// Utility: Hash passwords securely using PBKDF2
function hashPassword(password) {
    const salt = 'greenside_hoa_salt_2026';
    return crypto.pbkdf2Sync(password, salt, 1000, 64, 'sha512').toString('hex');
}

// Initialize tables from schema.sql
const schemaPath = path.join(__dirname, 'schema.sql');
const schemaSql = fs.readFileSync(schemaPath, 'utf8');
db.exec(schemaSql);

// Seed initial users if none exist
const countStmt = db.prepare('SELECT COUNT(*) as count FROM users');
const { count } = countStmt.get();

if (count === 0) {
    console.log('[DB] Seeding initial users, dues, and announcements...');

    // 1. Preconfigured Admin/Treasurer: admin / password
    const insertUser = db.prepare(`
        INSERT INTO users (name, username, password_hash, role, block_lot)
        VALUES (?, ?, ?, ?, ?)
    `);

    insertUser.run(
        'HOA Admin & Treasurer',
        'admin',
        hashPassword('password'),
        'admin',
        'Admin Clubhouse'
    );

    // 2. Demo Resident: johndoe / password
    insertUser.run(
        'John Doe',
        'johndoe',
        hashPassword('password'),
        'resident',
        'Block 4, Lot 12'
    );

    // 3. Demo Resident 2: maria / password
    insertUser.run(
        'Maria Santos',
        'maria',
        hashPassword('password'),
        'resident',
        'Block 2, Lot 5'
    );

    // 4. Seed Monthly Due
    const insertDue = db.prepare(`
        INSERT INTO dues (title, amount, description, due_date)
        VALUES (?, ?, ?, ?)
    `);
    const dueResult = insertDue.run(
        'October 2026 Monthly Dues & Water Maintenance',
        1250.00,
        'Includes 24/7 security patrol, street lighting, and deep-well water pump maintenance.',
        '2026-10-31'
    );
    const dueId = dueResult.lastInsertRowid;

    // 5. Seed Announcement
    const insertAnnounce = db.prepare(`
        INSERT INTO announcements (author_id, title, category, body)
        VALUES (?, ?, ?, ?)
    `);
    insertAnnounce.run(
        1,
        'Scheduled Water Interruption',
        'Maintenance',
        'Maintenance on the primary water distribution line will occur tomorrow from 1:00 PM to 5:00 PM. Please store sufficient water.'
    );
    insertAnnounce.run(
        1,
        'Annual General Homeowners Meeting',
        'Event',
        'Join us this Saturday at 10:00 AM at the Clubhouse Pavilion to discuss neighborhood gate automation and year-end financial reports.'
    );

    // 6. Seed Community Forum Posts (Authentic Tagalog/Taglish, lowercase sentence starts)
    const insertPost = db.prepare(`
        INSERT INTO posts (user_id, title, body, category, vote_score)
        VALUES (?, ?, ?, ?, ?)
    `);

    // Post 1: Lost Cat
    const p1 = insertPost.run(
        2, // John Doe
        'may nakakita po ba sa orange tabby cat namin bandang playground?',
        'kaninang umaga pa po nawawala si milo, orange cat na may suot na green collar. baka po napadpad sa garahe ninyo sa phase 2 paki-message na lang po ako dito. salamat mga kapitbahay.',
        'Lost & Found',
        7
    );
    const post1Id = Number(p1.lastInsertRowid);

    // Post 2: Garbage collection
    const p2 = insertPost.run(
        3, // Maria
        'tanong lang po sa garbage collection schedule sa block 4',
        'bakit kaya hindi nahakot yung mga nabubulok na basura kahapon? nakalabas na sa gate namin baka kalkalin ng mga pusa sa gabi. kailan po kaya babalikan ng truck?',
        'Maintenance',
        4
    );
    const post2Id = Number(p2.lastInsertRowid);

    // Post 3: Internet outage
    const p3 = insertPost.run(
        2, // John Doe
        'mga kapitbahay may pldt fiber outage ba sa phase 1?',
        'bigla na lang nawalan ng internet kaninang 10 am tapos red los na yung modem. may maintenance ba sa linya natin o sa area lang namin may sira?',
        'General',
        9
    );
    const post3Id = Number(p3.lastInsertRowid);

    // Post 4: RFID Sticker
    const p4 = insertPost.run(
        3, // Maria
        'update po sa rfid gate sticker para sa bagong sasakyan',
        'open na po ba yung admin office bukas ng sabado para sa application ng sticker? magkano po ulit bayad bawat sasakyan at anong requirements ang kailangan dalhin?',
        'Safety',
        3
    );
    const post4Id = Number(p4.lastInsertRowid);

    // Seed votes
    const insertVote = db.prepare(`
        INSERT INTO votes (user_id, post_id, value)
        VALUES (?, ?, ?)
    `);
    insertVote.run(1, post1Id, 1);
    insertVote.run(3, post1Id, 1);
    insertVote.run(1, post3Id, 1);
    insertVote.run(2, post3Id, 1);

    // Seed Comments (Facebook-style top-level + direct replies)
    const insertComment = db.prepare(`
        INSERT INTO comments (post_id, user_id, parent_id, content)
        VALUES (?, ?, ?, ?)
    `);

    // Comments for Post 1
    const c1 = insertComment.run(
        post1Id,
        3, // Maria
        null,
        'nakita ko kanina bandang block 3 may hinahabol na ibon sa bakanteng lote. puntahan niyo po agad baka nandoon pa.'
    );
    insertComment.run(
        post1Id,
        2, // John Doe
        Number(c1.lastInsertRowid),
        'sige po puntahan ko ngayon agad, maraming salamat ma\'am maria!'
    );
    insertComment.run(
        post1Id,
        1, // Admin
        null,
        'pina-check ko rin sa guardhouse sa gate 2 kung may napansin silang pusa. update kita kapag may balita.'
    );

    // Comments for Post 2
    const c2 = insertComment.run(
        post2Id,
        1, // Admin
        null,
        'sabi nung contractor kanina nasiraan daw yung dump truck ng barangay. bukas daw po ng 7 am ang daan ulit para hakutin lahat.'
    );
    insertComment.run(
        post2Id,
        3, // Maria
        Number(c2.lastInsertRowid),
        'salamat sa update admin, ipasok ko muna yung drum sa loob ng bakod para safe.'
    );

    // Comments for Post 3
    insertComment.run(
        post3Id,
        3, // Maria
        null,
        'red los din kami dito sa block 2 lot 5. tumawag na ako sa hotline may tinatamaan daw na linya sa may highway, ongoing repair na raw.'
    );
    insertComment.run(
        post3Id,
        1, // Admin
        null,
        'noted ito mga kapitbahay. tinawagan na rin ng admin ang pldt enterprise account manager natin para ma-expedite.'
    );

    // Comments for Post 4
    insertComment.run(
        post4Id,
        1, // Admin
        null,
        'open po ang office bukas 8 am to 12 nn. 250 pesos po per vehicle, paki-dala lang photocopy ng or/cr at valid id.'
    );

    console.log('[DB] Seeding completed successfully.');
}

module.exports = {
    db,
    hashPassword
};
