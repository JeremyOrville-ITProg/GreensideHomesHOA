USE greenside_hoa;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE reactions;
TRUNCATE TABLE notifications;
TRUNCATE TABLE payments;
TRUNCATE TABLE expenses;
TRUNCATE TABLE fees;
TRUNCATE TABLE posts;
TRUNCATE TABLE users;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO users (id, full_name, email, password_hash, phone, house_no, role, status, created_at) VALUES
(1, 'HOA Administrator', 'admin@greenside.local', '$2y$12$N9NyZ1AaNwV81EwJEI4xLOt0bYE4vC7gp8G77CjUtSrlOHWvIZKGe', '0917-888-0001', 'Block 1 Lot 1', 'admin', 'approved', '2026-11-01 08:00:00'),
(2, 'Marites Ramos', 'marites.blk8@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0917-111-2233', 'Blk 8 Lot 14', 'resident', 'approved', '2026-11-02 09:15:00'),
(3, 'Kuya Jun Reyes', 'jun.reyes@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0918-222-3344', 'Phase 2 Blk 4 Lot 9', 'resident', 'approved', '2026-11-02 10:30:00'),
(4, 'Elena Cruz', 'elena.cruz@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0919-333-4455', 'Blk 12 Lot 18', 'resident', 'approved', '2026-11-03 11:20:00'),
(5, 'Bong Santos', 'bong.santos@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0920-444-5566', 'Phase 1 Blk 3 Lot 2', 'resident', 'approved', '2026-11-03 14:00:00'),
(6, 'Arnel Bautista', 'arnel.bautista@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0921-555-6677', 'Blk 15 Lot 5', 'resident', 'approved', '2026-11-04 15:45:00'),
(7, 'Aling Nida Mercado', 'nida.mercado@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0922-666-7788', 'Phase 1 Blk 7 Lot 21', 'resident', 'approved', '2026-11-05 16:30:00'),
(8, 'Carlo Dizon', 'carlo.dizon@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0923-777-8899', 'Phase 2 Blk 10 Lot 3', 'resident', 'approved', '2026-11-06 17:10:00'),
(9, 'Grace Tolentino', 'grace.tolentino@greenside.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0924-888-9900', 'Blk 5 Lot 12', 'resident', 'approved', '2026-11-07 08:40:00'),
(10, 'Wilson Gomez', 'wilson@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0925-999-0011', 'Phase 2 Blk 9 Lot 15', 'resident', 'pending', '2026-11-21 15:14:52'),
(11, 'Jeremy Desiderio', 'jeremytest@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0926-000-1122', 'Block 6 Lot 7', 'resident', 'pending', '2026-11-21 15:15:43'),
(12, 'Ricardo Admin', 'ricardoadmin@gmail.com', '$2y$12$N9NyZ1AaNwV81EwJEI4xLOt0bYE4vC7gp8G77CjUtSrlOHWvIZKGe', '0917-888-0002', 'Admin Office', 'admin', 'approved', '2026-11-21 15:17:25');

INSERT INTO fees (id, title, description, amount, due_date, type, created_by, created_at) VALUES
(1, 'Monthly HOA Maintenance Dues - November 2026', 'Routine village maintenance, security guard compensation, and street illumination', 350.00, '2026-11-20', 'monthly', 1, '2026-11-01 08:30:00'),
(2, 'Monthly HOA Maintenance Dues - December 2026', 'December regular assessment for village sanitation, security, and administration', 350.00, '2026-12-20', 'monthly', 1, '2026-11-20 08:30:00'),
(3, 'Quarterly Garbage Collection Fee - Q4', 'CENRO coordination, dump truck collection passes, and village waste hauling fund', 150.00, '2026-11-30', 'special', 1, '2026-11-01 09:00:00'),
(4, '2026 Resident Vehicle & Gate Sticker', 'Annual vehicle and motorcycle RFID gate pass sticker registration', 150.00, '2026-12-15', 'special', 1, '2026-11-05 10:00:00'),
(5, 'Streetlight & Perimeter Security Repair Fund', 'Special assessment for replacing damaged LED fixtures along Main Avenue', 100.00, '2026-12-05', 'special', 1, '2026-11-10 11:00:00');

INSERT INTO payments (id, user_id, fee_id, amount, method, reference_no, proof_image, status, remarks, verified_by, verified_at, created_at) VALUES
(1, 2, 1, 350.00, 'e-wallet', 'GCASH-20261118-98214', NULL, 'verified', 'Verified via GCash statement. Official receipt issued at admin desk.', 1, '2026-11-19 14:20:00', '2026-11-18 16:30:00'),
(2, 4, 3, 150.00, 'e-wallet', 'MAYA-881290348', NULL, 'verified', 'Maya payment confirmed by HOA Admin.', 1, '2026-11-15 10:15:00', '2026-11-14 18:00:00'),
(3, 3, 1, 350.00, 'cash', 'OR-2026-1104', NULL, 'verified', 'Direct cash payment received at admin office.', 1, '2026-11-10 11:30:00', '2026-11-10 11:25:00'),
(4, 5, 4, 150.00, 'e-wallet', 'GCASH-20261122-44102', NULL, 'pending', 'Sticker payment for motorcycle plate 381-NDM; awaiting cashier verification.', NULL, NULL, '2026-11-22 17:45:00'),
(5, 6, 1, 350.00, 'e-wallet', 'GCASH-20261124-77192', NULL, 'pending', 'November dues via GCash transfer; awaiting bank match.', NULL, NULL, '2026-11-24 19:10:00');

INSERT INTO expenses (id, description, amount, category, expense_date, receipt_image, recorded_by, created_at) VALUES
(1, 'Replacement of 40W LED streetlights along Phase 1 & 2 Main Avenue', 1850.00, 'Repairs & Maintenance', '2026-11-15', NULL, 1, '2026-11-15 16:00:00'),
(2, 'Hakot basura honorarium and barangay CENRO coordination fee', 1200.00, 'Sanitation', '2026-11-18', NULL, 1, '2026-11-18 11:30:00'),
(3, 'Covered court water pump electric bill - Meralco', 2430.50, 'Utilities', '2026-11-20', NULL, 1, '2026-11-20 14:15:00'),
(4, 'Main gate guardhouse logbooks, batteries, and flashlights', 650.00, 'Security', '2026-11-22', NULL, 1, '2026-11-22 09:45:00');

INSERT INTO posts (id, user_id, title, body, post_image, category, status, is_announcement, is_pinned, created_at, updated_at) VALUES
(1, 1, 'Scheduled Maynilad Water Interruption (Nov 28-29)', 'Please be advised that Maynilad will conduct emergency main line repairs from Saturday 10:00 PM until Sunday 6:00 AM. Water service across Phase 1 and Phase 2 will be temporarily cut. Kindly store enough water for your household needs before the maintenance period.', NULL, 'concern', 'open', TRUE, TRUE, '2026-11-24 08:00:00', '2026-11-24 08:00:00'),
(2, 1, 'Hakot Basura Schedule: Tuesdays and Fridays Only (6:00 AM - 9:00 AM)', 'Official reminder from the HOA Board and CENRO: Garbage collection is strictly scheduled every Tuesday and Friday morning. Please bring out tightly tied trash bags only on the morning of collection to prevent stray animals from scattering waste along village streets.', NULL, 'concern', 'open', TRUE, TRUE, '2026-11-23 09:30:00', '2026-11-23 09:30:00'),
(3, 1, 'FINAL NOTICE: 2026 Homeowner Vehicle & Motorcycle Gate Stickers', 'All residents with private vehicles and motorcycles are required to renew their village gate stickers on or before December 15, 2026. Vehicles without valid 2026 stickers will be required to surrender an ID at the guardhouse prior to entry. Applications are processed at the HOA Admin Office.', NULL, 'concern', 'open', TRUE, TRUE, '2026-11-22 10:00:00', '2026-11-22 10:00:00'),
(4, 1, 'HOA Advisory: Stray Dog & Pet Leash Policy', 'In accordance with village bylaws and Republic Act 9482 (Anti-Rabies Act), pet owners must ensure that dogs and cats are kept within private property or on a leash when in common village areas. Please clean up pet waste immediately. Unattended pets will be coordinated with the City Veterinary Office.', NULL, 'concern', 'open', TRUE, FALSE, '2026-11-20 14:00:00', '2026-11-20 14:00:00'),
(5, 1, 'Quarterly General Assembly & Financial Report Presentation', 'All homeowners and residents are cordially invited to our Q4 General Assembly this coming Sunday, 9:00 AM at the Phase 1 Covered Court. Agenda includes: (1) 2026 financial statement, (2) village streetlights upgrade, and (3) election of committee volunteers. Snacks will be provided.', NULL, 'suggestion', 'open', TRUE, FALSE, '2026-11-18 16:30:00', '2026-11-18 16:30:00'),
(6, 1, 'Dengue Fogging & Mist Spraying Operation This Saturday', 'The Barangay Health Center in coordination with Greenside Homes HOA will conduct dengue misting and drainage clearing this Saturday starting at 6:00 AM. Please ensure household water containers are covered, windows are closed during spraying, and children/pets stay indoors.', NULL, 'concern', 'open', TRUE, FALSE, '2026-11-17 11:15:00', '2026-11-17 11:15:00'),
(7, 9, 'may nakapark po na red wigo sa harap ng gate namin', 'good morning po sa may ari ng red wigo with plate number ending in 84 nakaharang po kse kau sa tapat ng gate namin sa blk 5 lot 12 d kme makalabas ng motor papasok na po sa work pakiusap nman po pakiurong salamat po', NULL, 'concern', 'open', FALSE, FALSE, '2026-11-26 07:45:00', '2026-11-26 07:45:00'),
(8, 2, 'yung mga aso po sa blk 8 pakitali nman po', 'pakiusap nman po sa mga may alagang aso dyan sa tapat ng vacant lot pakitali nyo po kase kinalat nanaman ung mga nakasabit na plastic ng basura kaninang madaling araw ang baho tuloy sa kalsada ty', NULL, 'concern', 'resolved', FALSE, FALSE, '2026-11-25 08:30:00', '2026-11-25 15:00:00'),
(9, 3, 'mahina po ba tubig sa inyo ngaun?', 'tanong ko lng po kung mahina din tubig sa phase 2? samen sa blk 4 halos patak lng kanina pang 3pm d kme makapag laba at sahod ng tubig. may sira po ba pump naten?', NULL, 'concern', 'open', FALSE, FALSE, '2026-11-25 16:20:00', '2026-11-25 16:20:00'),
(10, 4, 'lf honest na nag aayos ng automatic washing machine', 'hello kapitbahay baka may marerecommend kau na marunong mag ayos ng sharp automatic washing machine ayaw kse mag drain ung tubig eh. ung taga loob lng sana ng village para madali kontakin salamat!', NULL, 'question', 'open', FALSE, FALSE, '2026-11-25 18:15:00', '2026-11-25 18:15:00'),
(11, 5, 'proposal: palagyan sana ng speed bump / humps sa main road', 'pansin ko po kse ang bibilis magpatakbo ng mga delivery rider at tricycle sa may main avenue delikado po sa mga bata na naglalaro sa hapon. suggestion ko po sana pag usapan sa next assembly kung pde maglagay kahit dalawang humps dun. ty!', NULL, 'suggestion', 'in_progress', FALSE, FALSE, '2026-11-24 14:10:00', '2026-11-25 10:00:00'),
(12, 6, 'ano oras po dating ng hakot basura bukas?', 'mga kapitbahay dumadaan ba sila maaga kapag friday? baka kse maiwan basura nmin kung sakali d pa nakalabas. salamat sa sasagot', NULL, 'question', 'open', FALSE, FALSE, '2026-11-24 19:40:00', '2026-11-24 19:40:00'),
(13, 7, 'pundido na ilaw sa poste tapat ng blk 7 lot 19', 'magandang gabi po hoa admin ireport ko lng po sana ung ilaw sa poste dito sa tapat namin sobrang dilim na po pag gabi baka pde mapalitan ng bumbilya kse maraming dumadaan na studyante umuuwi gabi na. salamat po', NULL, 'concern', 'in_progress', FALSE, FALSE, '2026-11-23 20:15:00', '2026-11-24 09:30:00'),
(14, 8, 'pakiusap sa nagpapatugtog ng videoke past 10pm', 'konting konsiderasyon nman po sa mga kapitbahay lalo na may mga pasok sa work at school bukas. weekday po ngaun wag po sana lakasan ung bass kse rinig na rinig sa loob ng kwarto namin sa phase 2. salamat po', NULL, 'concern', 'open', FALSE, FALSE, '2026-11-23 22:50:00', '2026-11-23 22:50:00'),
(15, 3, 'magkano po pamasahe sa tricycle papuntang palengke?', 'singil po kse sakin kanina 40 pesos mag isa lng ako. standard fare na po ba un or overpricing c manong? baka may copy kau ng fare matrix ng toda naten dito ty', NULL, 'question', 'resolved', FALSE, FALSE, '2026-11-22 11:30:00', '2026-11-22 17:10:00'),
(16, 2, 'sino po may extra mineral water galon pahiram muna', 'sarado po kse ung refilling station sa may tapat ng gate kanina baka may extra po kau dyan isa bukas ko na po palitan pag bukas nila thank u po blk 8 lot 14 po kme', NULL, 'question', 'resolved', FALSE, FALSE, '2026-11-21 16:45:00', '2026-11-21 19:00:00'),
(17, 6, 'baka may nakapulot po ng susi ng motor may kasamang keychain na spongebob', 'nawala po kaninang mga 5pm galing covered court baka may nakapulot paki text nman po ako 0921-555-6677 or paki iwan sa guardhouse salamat po', NULL, 'question', 'open', FALSE, FALSE, '2026-11-20 17:30:00', '2026-11-20 17:30:00'),
(18, 8, 'suggestion: maglagay sana ng badminton net sa covered court', 'maganda po sana kung may designated time slot or net para sa badminton tuwing sunday morning para d lng puro basketball nagagamit covered court natin. sino po may gusto sumama?', NULL, 'suggestion', 'open', FALSE, FALSE, '2026-11-19 13:20:00', '2026-11-19 13:20:00');

INSERT INTO reactions (id, post_id, user_id, type, created_at) VALUES
(1, 1, 2, 'upvote', '2026-11-24 08:15:00'),
(2, 1, 3, 'upvote', '2026-11-24 08:30:00'),
(3, 1, 4, 'upvote', '2026-11-24 09:00:00'),
(4, 2, 2, 'upvote', '2026-11-23 09:45:00'),
(5, 2, 5, 'upvote', '2026-11-23 10:10:00'),
(6, 2, 7, 'upvote', '2026-11-23 11:00:00'),
(7, 3, 3, 'upvote', '2026-11-22 10:30:00'),
(8, 3, 6, 'upvote', '2026-11-22 11:20:00'),
(9, 7, 2, 'upvote', '2026-11-26 08:00:00'),
(10, 8, 4, 'upvote', '2026-11-25 08:45:00'),
(11, 8, 7, 'upvote', '2026-11-25 09:10:00'),
(12, 11, 2, 'upvote', '2026-11-24 14:30:00'),
(13, 11, 3, 'upvote', '2026-11-24 15:00:00'),
(14, 11, 8, 'upvote', '2026-11-24 16:15:00'),
(15, 13, 5, 'upvote', '2026-11-23 20:30:00'),
(16, 14, 2, 'upvote', '2026-11-23 23:05:00'),
(17, 14, 4, 'upvote', '2026-11-23 23:15:00'),
(18, 14, 7, 'upvote', '2026-11-23 23:30:00'),
(19, 18, 4, 'upvote', '2026-11-19 14:00:00');

INSERT INTO notifications (id, user_id, type, message, link, is_read, created_at) VALUES
(1, 1, 'announcement', 'New resident concern submitted: "may nakapark po na red wigo sa harap ng gate namin" by Grace Tolentino (Blk 5 Lot 12).', 'index.php', FALSE, '2026-11-26 07:45:00'),
(2, 1, 'payment', 'New payment recorded: Elena Cruz settled ₱150.00 for Garbage Collection Fee via Maya (Ref: MAYA-881290348).', 'index.php?action=bills', FALSE, '2026-11-15 10:15:00'),
(3, 1, 'payment', 'Pending payment uploaded: Bong Santos uploaded ₱150.00 for 2026 Vehicle Sticker awaiting verification.', 'index.php?action=bills', FALSE, '2026-11-22 17:45:00'),
(4, 1, 'reply', 'New resident registration pending approval: Wilson Gomez (Phase 2 Blk 9 Lot 15).', 'index.php?action=profile', TRUE, '2026-11-21 15:15:00'),
(5, 1, 'announcement', 'New resident concern submitted: "pundido na ilaw sa poste tapat ng blk 7 lot 19" by Aling Nida Mercado.', 'index.php', TRUE, '2026-11-23 20:15:00'),
(6, 12, 'announcement', 'New resident concern submitted: "may nakapark po na red wigo sa harap ng gate namin" by Grace Tolentino (Blk 5 Lot 12).', 'index.php', FALSE, '2026-11-26 07:45:00'),
(7, 12, 'payment', 'Pending payment verification: Bong Santos (₱150.00) and Arnel Bautista (₱350.00).', 'index.php?action=bills', FALSE, '2026-11-24 19:15:00'),
(8, 2, 'reminder', 'Reminder: Monthly HOA Maintenance Dues for November (₱350.00) was due on Nov 20, 2026. Please settle at the Admin Office or via GCash.', 'index.php?action=bills', FALSE, '2026-11-21 08:00:00'),
(9, 2, 'announcement', 'Advisory: Scheduled Maynilad water service interruption on Saturday (Nov 28) from 10:00 PM to 6:00 AM affecting Phase 1 & 2.', 'index.php', FALSE, '2026-11-24 08:00:00'),
(10, 2, 'reply', 'Your concern "yung mga aso po sa blk 8 pakitali nman po" has been marked as RESOLVED. Village security warned the pet owner.', 'index.php', TRUE, '2026-11-25 15:00:00'),
(11, 2, 'payment', 'Payment Verified: Your GCash payment of ₱350.00 for November Maintenance Dues has been verified and recorded.', 'index.php?action=bills', TRUE, '2026-11-19 14:20:00'),
(12, 3, 'announcement', 'Advisory: Scheduled Maynilad water service interruption on Saturday (Nov 28) from 10:00 PM to 6:00 AM affecting Phase 1 & 2.', 'index.php', FALSE, '2026-11-24 08:00:00'),
(13, 3, 'reminder', 'Reminder: Deadline for 2026 Homeowner Vehicle & Motorcycle Stickers is Dec 15, 2026. Register at the Admin Office.', 'index.php', FALSE, '2026-11-22 10:00:00'),
(14, 3, 'reply', 'Status update: Your inquiry "mahina po ba tubig sa inyo ngaun?" is being inspected by the village maintenance crew.', 'index.php', TRUE, '2026-11-25 17:00:00'),
(15, 3, 'payment', 'Payment Received: ₱350.00 Cash for November Dues recorded by HOA Admin (OR: OR-2026-1104).', 'index.php?action=bills', TRUE, '2026-11-10 11:30:00'),
(16, 4, 'payment', 'Payment Verified: Your Maya payment of ₱150.00 for Quarterly Garbage Collection Fee (Q4) has been approved.', 'index.php?action=bills', FALSE, '2026-11-15 10:15:00'),
(17, 4, 'announcement', 'Notice: Hakot Basura schedule is strictly every Tuesday and Friday morning (6:00 AM - 9:00 AM).', 'index.php', TRUE, '2026-11-23 09:30:00'),
(18, 4, 'reminder', 'Reminder: Monthly HOA Maintenance Dues for December (₱350.00) will be due on Dec 20, 2026.', 'index.php?action=bills', FALSE, '2026-11-25 09:00:00'),
(19, 5, 'reply', 'Your suggestion "proposal: palagyan sana ng speed bump / humps sa main road" was updated to IN PROGRESS and added to the General Assembly agenda.', 'index.php', FALSE, '2026-11-25 10:00:00'),
(20, 5, 'payment', 'Payment Upload Received: Your GCash transaction (₱150.00 for Vehicle Sticker) is currently pending review.', 'index.php?action=bills', FALSE, '2026-11-22 17:45:00'),
(21, 7, 'reply', 'Your concern "pundido na ilaw sa poste tapat ng blk 7 lot 19" has been marked as IN PROGRESS. Electrician scheduled for inspection tomorrow.', 'index.php', FALSE, '2026-11-24 09:30:00'),
(22, 7, 'announcement', 'Advisory: Dengue mist spraying operation scheduled this coming Saturday at 6:00 AM.', 'index.php', FALSE, '2026-11-17 11:15:00'),
(23, 9, 'reply', 'Your report "may nakapark po na red wigo sa harap ng gate namin" was acknowledged; village guardhouse has been alerted to page the vehicle owner.', 'index.php', FALSE, '2026-11-26 08:05:00'),
(24, 9, 'announcement', 'Advisory: Scheduled Maynilad water service interruption on Saturday (Nov 28) from 10:00 PM to 6:00 AM affecting Phase 1 & 2.', 'index.php', FALSE, '2026-11-24 08:00:00');
