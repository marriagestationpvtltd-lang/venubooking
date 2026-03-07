-- Planner System Sample Data
-- Run this AFTER running database/migrations/add_planner_system.sql
-- Provides 5 demo event plans (Wedding, Birthday, Anniversary, Corporate, Engagement)
-- and 48 realistic tasks across all plans.
-- ============================================================================

-- Sample customers (inserted with INSERT IGNORE so duplicates are skipped)
INSERT IGNORE INTO customers (id, full_name, phone, email, address, city) VALUES
(1, 'Anita Sharma',    '9841234567', 'anita.sharma@gmail.com',  'Kalanki, Kathmandu',     'Kathmandu'),
(2, 'Rohan Thapa',     '9851234567', 'rohan.thapa@gmail.com',   'Lalitpur, Patan',         'Kathmandu'),
(3, 'Priya Gurung',    '9861234567', 'priya.gurung@gmail.com',  'Pokhara-6, Lakeside',     'Pokhara'),
(4, 'Sanjay Rajbanshi','9871234567', 'sanjay.raj@gmail.com',    'Biratnagar-3',            'Biratnagar'),
(5, 'Meera Acharya',   '9801234567', 'meera.acharya@gmail.com', 'Chabahil, Kathmandu',     'Kathmandu');

-- ============================================================================
-- EVENT PLANS
-- ============================================================================
INSERT IGNORE INTO event_plans (id, title, event_type, event_date, customer_id, total_budget, description, status, created_by) VALUES
(1, 'Anita & Rohan Wedding 2026',      'Wedding',        '2026-04-15', 1, 2500000.00,
    'Grand traditional wedding with approximately 500 guests. Theme: Red and Gold. Venue: 5-star hotel. Full catering, photography and decoration required.',
    'in_progress', 1),
(2, 'Priya Birthday Bash',             'Birthday',       '2026-03-25', 3,  150000.00,
    'Surprise birthday party for Priya turning 30. Western theme with DJ. Around 80 guests expected.',
    'planning', 1),
(3, 'Rajbanshi Family Anniversary',    'Anniversary',    '2026-05-10', 4,  300000.00,
    '25th Silver Wedding Anniversary. Intimate gathering of family and close friends (~100 guests). Silver and white theme.',
    'planning', 1),
(4, 'TechNepal Annual Conference 2026', 'Corporate Event','2026-06-20', NULL, 800000.00,
    'Annual technology conference with 300 attendees. Keynote sessions, workshops, lunch and evening gala dinner.',
    'in_progress', 1),
(5, 'Meera & Suresh Engagement',       'Engagement',     '2026-03-30', 5,  120000.00,
    'Traditional engagement ceremony with 50 family members. Simple but elegant setup.',
    'completed', 1);

-- ============================================================================
-- PLAN TASKS — Plan 1: Wedding (21 tasks)
-- ============================================================================
INSERT IGNORE INTO plan_tasks (plan_id, task_name, category, description, due_date, estimated_cost, actual_cost, status, priority, display_order) VALUES
(1,'Book main wedding venue (Soaltee Crowne Plaza)','Venue','Confirm hall booking with advance payment. Contact: 01-4273999','2026-01-15',300000,300000,'completed','high',1),
(1,'Book guest accommodation (20 rooms)','Venue','Reserve rooms for outstation guests. Need from Apr 14-16.','2026-02-01',80000,80000,'completed','medium',2),
(1,'Arrange parking logistics','Venue','Coordinate with venue for parking space and valet service','2026-03-15',15000,0,'in_progress','low',3),
(1,'Confirm floral decoration package with vendor','Decoration','Red roses, marigold, and jasmine theme. Contact: Puspanjali Decorators','2026-02-15',150000,150000,'completed','high',4),
(1,'Order custom wedding mandap/stage','Decoration','Traditional mandap with LED backdrop. Size: 20x30 ft','2026-02-20',80000,75000,'completed','high',5),
(1,'Setup lighting and ambiance','Lighting','String lights, uplighting, and dance floor lighting. Confirm with electrician.','2026-04-10',45000,0,'in_progress','medium',6),
(1,'Finalize menu with caterer (500 guests)','Catering','Traditional Nepali thali + continental options. Include vegetarian alternatives.','2026-02-28',500000,450000,'completed','high',7),
(1,'Arrange wedding cake (5-tier)','Cake','Custom design matching theme. Baker: Sweet Moments Bakery','2026-03-20',25000,0,'pending','medium',8),
(1,'Confirm bar/beverage setup','Catering','Soft drinks, juices, and welcome cocktails for 500 pax','2026-03-25',60000,0,'pending','medium',9),
(1,'Book photography team (8 photographers)','Photography','Full day coverage + post-processing + album. Studio: Moments Forever','2026-01-20',120000,120000,'completed','high',10),
(1,'Book videography team (4K drone)','Videography','4K cinematic coverage with drone shots. Same-day edit for reception.','2026-01-20',80000,80000,'completed','high',11),
(1,'Arrange pre-wedding photoshoot','Photography','Garden venue shoot. Date TBD in March.','2026-03-01',30000,28000,'completed','medium',12),
(1,'Book DJ and sound system','Music & DJ','DJ Rockmandu for 6-hour reception. Sound system for 500 guests.','2026-02-28',60000,60000,'completed','high',13),
(1,'Hire live classical music band for ceremony','Music & DJ','3-hour traditional music band for the ceremony rituals','2026-03-10',30000,0,'pending','medium',14),
(1,'Design and print wedding invitations (600 cards)','Invitation & Stationery','Custom design with gold foil. Both English and Nepali text.','2026-02-10',18000,18000,'completed','medium',15),
(1,'Send digital invitations via WhatsApp/Email','Invitation & Stationery','Create digital invitation and share 4 weeks before event','2026-03-15',5000,5000,'completed','low',16),
(1,'Bride lehenga fitting and confirmation','Attire & Makeup','Designer: Prabha Creations. 3 fittings required.','2026-03-01',80000,80000,'completed','high',17),
(1,'Groom sherwani tailoring','Attire & Makeup','Custom sherwani with matching accessories. Designer: Royal Attire','2026-03-01',45000,42000,'completed','high',18),
(1,'Bride makeup artist booking','Attire & Makeup','Full bridal makeup + hair + mehendi. Artist: Sujana Beauty','2026-02-15',25000,0,'pending','high',19),
(1,'Arrange wedding car (decorated)','Transportation','Luxury decorated car for bride and groom. Driver confirmed.','2026-04-01',20000,0,'in_progress','medium',20),
(1,'Book buses for guest transportation','Transportation','3 buses for guest transportation from different pickup points','2026-04-01',30000,0,'pending','medium',21);

-- ============================================================================
-- PLAN TASKS — Plan 2: Birthday (8 tasks)
-- ============================================================================
INSERT IGNORE INTO plan_tasks (plan_id, task_name, category, description, due_date, estimated_cost, actual_cost, status, priority, display_order) VALUES
(2,'Book venue (Restaurant private hall)','Venue','Fishtail Restaurant private dining hall for 80 guests','2026-03-10',30000,30000,'completed','high',1),
(2,'Order birthday cake (custom design)','Cake','3-tier cake with Priya photo. Baker: Cake Paradise','2026-03-20',8000,0,'pending','high',2),
(2,'Book DJ for party','Music & DJ','DJ for 4 hours. Western and Bollywood mix.','2026-03-15',15000,15000,'completed','medium',3),
(2,'Arrange table decoration and balloons','Decoration','Purple and silver theme. Balloon arch, table centerpieces.','2026-03-22',12000,0,'pending','medium',4),
(2,'Send invitations to 80 guests','Invitation & Stationery','Digital invite via WhatsApp. Printed for family only (20 cards).','2026-03-18',3000,0,'pending','low',5),
(2,'Hire photographer','Photography','4-hour coverage. Candid shots and group photos.','2026-03-15',12000,0,'pending','medium',6),
(2,'Arrange food buffet menu','Catering','Mix of continental and Nepali. Drinks included in venue package.','2026-03-18',45000,0,'pending','high',7),
(2,'Gift arrangements and return gifts','Gifts & Favors','Personalized return gifts for all guests (photo frames)','2026-03-20',16000,0,'pending','low',8);

-- ============================================================================
-- PLAN TASKS — Plan 3: Anniversary (5 tasks)
-- ============================================================================
INSERT IGNORE INTO plan_tasks (plan_id, task_name, category, description, due_date, estimated_cost, actual_cost, status, priority, display_order) VALUES
(3,'Book banquet hall','Venue','Hotel Annapurna banquet for 100 guests','2026-04-01',80000,0,'pending','high',1),
(3,'Silver-themed decoration','Decoration','Silver and white floral arrangements, centerpieces','2026-04-15',50000,0,'pending','high',2),
(3,'Catering for 100 guests','Catering','Four-course sit-down dinner. Continental menu.','2026-04-15',120000,0,'pending','high',3),
(3,'Hire videographer for memories','Videography','Full event coverage + compilation video','2026-04-15',25000,0,'pending','medium',4),
(3,'Print photo book/album of 25 years','Photography','Custom album with family photos over 25 years','2026-04-20',15000,0,'pending','medium',5);

-- ============================================================================
-- PLAN TASKS — Plan 4: Corporate Conference (8 tasks)
-- ============================================================================
INSERT IGNORE INTO plan_tasks (plan_id, task_name, category, description, due_date, estimated_cost, actual_cost, status, priority, display_order) VALUES
(4,'Book conference center (Hyatt)','Venue','Main hall for 300 + 3 breakout rooms. AV equipment included.','2026-04-01',200000,200000,'completed','high',1),
(4,'Confirm keynote speakers','Entertainment','Confirm 5 keynote speakers. Send invitations and travel arrangements.','2026-04-15',150000,100000,'in_progress','high',2),
(4,'Setup registration portal','Other','Online registration with payment gateway. Target: 300 registrations.','2026-05-01',30000,30000,'completed','high',3),
(4,'Print conference materials (badges, folders)','Invitation & Stationery','Name badges, conference folders, schedules for 300 attendees','2026-05-15',45000,0,'in_progress','medium',4),
(4,'Catering: Lunch and coffee breaks (2 days)','Catering','Buffet lunch + 2 coffee breaks per day for 300 people','2026-05-20',180000,0,'pending','high',5),
(4,'Gala dinner arrangements','Catering','Evening gala dinner for all attendees. Live music band.','2026-05-25',150000,0,'pending','high',6),
(4,'Audio-visual and live streaming','Other','Professional AV team, live stream setup, recording of all sessions','2026-05-01',80000,0,'in_progress','medium',7),
(4,'Sponsorship banners and branding','Other','Sponsor logos on all materials, banners, backdrop setup','2026-05-15',35000,0,'pending','low',8);

-- ============================================================================
-- PLAN TASKS — Plan 5: Engagement — all completed (6 tasks)
-- ============================================================================
INSERT IGNORE INTO plan_tasks (plan_id, task_name, category, description, due_date, estimated_cost, actual_cost, status, priority, display_order) VALUES
(5,'Book small venue (family home/garden)','Venue','Using family backyard with tent setup for 50 guests','2026-03-01',20000,18000,'completed','high',1),
(5,'Arrange traditional decoration','Decoration','Flower rangoli, marigold garlands, traditional setup','2026-03-20',15000,14000,'completed','medium',2),
(5,'Catering for 50 guests','Catering','Traditional Nepali lunch with sel roti, sweets','2026-03-25',40000,38000,'completed','high',3),
(5,'Engagement ring purchase','Gifts & Favors','Diamond solitaire ring. Budget: NPR 50,000','2026-03-10',50000,48000,'completed','high',4),
(5,'Hire photographer','Photography','Half-day coverage. Traditional shots.','2026-03-20',12000,10000,'completed','medium',5),
(5,'Invite cards (50 physical + digital)','Invitation & Stationery','Traditional design cards for family','2026-03-15',3000,2500,'completed','low',6);
