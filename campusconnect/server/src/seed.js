/**
 * Populates the database with a realistic demonstration dataset:
 * verified and pending students, an administrator, an employer, listings in
 * all six modules, reviews, reports, messages, favourites and saved searches.
 *
 *   node src/seed.js
 */
import bcrypt from 'bcryptjs';
import { pool, query, run } from './db.js';
import { threadKey } from './utils/helpers.js';

const PASSWORD = 'password123';

const USERS = [
  { full_name: 'System Administrator', email: 'admin@unilus.ac.zm', role: 'admin', verification_status: 'verified', university: 'University of Lusaka', phone: '+260 977 000 001' },
  { full_name: 'Dalitso Mwansa', email: 'dalitso.mwansa@student.unilus.ac.zm', role: 'student', student_id: 'BIT23221244', university: 'University of Lusaka', program: 'BSc Information Technology', year_of_study: '4', verification_status: 'verified', phone: '+260 977 112 233', bio: 'Final-year IT student. I sell second-hand textbooks and offer programming tutorials.' },
  { full_name: 'Fredrick Kambalanga', email: 'fredrick.k@student.unilus.ac.zm', role: 'student', student_id: 'BIT24124793', university: 'University of Lusaka', program: 'BSc Information Technology', year_of_study: '3', verification_status: 'verified', phone: '+260 966 445 566', bio: 'Third-year student living in Chalala. Looking for a roommate for next semester.' },
  { full_name: 'Changala Milunga', email: 'changala.m@student.unilus.ac.zm', role: 'student', student_id: 'BIT23223033', university: 'University of Lusaka', program: 'BSc Information Technology', year_of_study: '4', verification_status: 'verified', phone: '+260 955 778 899', bio: 'I tutor Mathematics and Statistics. Available weekday evenings.' },
  { full_name: 'Lunga Greenhead', email: 'lunga.greenhead@student.unilus.ac.zm', role: 'student', student_id: 'BIT23223584', university: 'University of Lusaka', program: 'BSc Information Technology', year_of_study: '4', verification_status: 'verified', phone: '+260 977 334 455' },
  { full_name: 'Shaun Mwela', email: 'shaun.mwela@student.unilus.ac.zm', role: 'student', student_id: 'BIT23221764', university: 'University of Lusaka', program: 'BSc Information Technology', year_of_study: '4', verification_status: 'verified', phone: '+260 966 223 344' },
  { full_name: 'Natasha Banda', email: 'natasha.banda@gmail.com', role: 'student', student_id: 'BBA22110045', university: 'University of Lusaka', program: 'Bachelor of Business Administration', year_of_study: '2', verification_status: 'pending', phone: '+260 971 556 677', bio: 'Business student, second year. Selling my old laptop.' },
  { full_name: 'Chanda Phiri', email: 'chanda.phiri@gmail.com', role: 'student', student_id: 'LLB21009812', university: 'University of Lusaka', program: 'Bachelor of Laws', year_of_study: '3', verification_status: 'pending', phone: '+260 978 889 900' },
  { full_name: 'Mubita Simasiku', email: 'mubita.s@student.unilus.ac.zm', role: 'student', student_id: 'BSC22334455', university: 'University of Lusaka', program: 'BSc Computer Science', year_of_study: '2', verification_status: 'verified', phone: '+260 955 121 314' },
  { full_name: 'Zamtel Careers Office', email: 'careers@zamtel-demo.co.zm', role: 'employer', university: null, verification_status: 'verified', phone: '+260 211 222 333', bio: 'Demonstration employer account used for the student internship programme.' },
  { full_name: 'Kabulonga Properties', email: 'lettings@kabulonga-demo.co.zm', role: 'employer', verification_status: 'verified', phone: '+260 211 445 566', bio: 'Student accommodation around Kabulonga, Chalala and Ibex Hill.' },
];

const day = (n) => new Date(Date.now() + n * 86400000).toISOString().slice(0, 10);

const LISTINGS = [
  // ---- Marketplace -------------------------------------------------------
  { owner: 'dalitso.mwansa@student.unilus.ac.zm', type: 'marketplace', title: 'Database Systems textbook (Elmasri & Navathe, 7th ed.)', description: 'Seventh edition, used for one semester only. No torn pages, minimal highlighting in chapters 3 to 6. Perfect for the BIT database course.', price: 350, price_unit: 'total', category: 'Textbooks', location: 'UNILUS Main Campus', item_condition: 'Good', contact_info: 'Message me on Campus Connect' },
  { owner: 'natasha.banda@gmail.com', type: 'marketplace', title: 'HP EliteBook 840 G5 - i5, 8GB RAM, 256GB SSD', description: 'Upgrading to a bigger machine so selling my reliable EliteBook. Battery lasts about 4 hours, charger included, Windows 11 freshly installed. Screen and keyboard are in great condition.', price: 4800, price_unit: 'total', category: 'Laptops', location: 'Chalala', item_condition: 'Good', contact_info: '+260 971 556 677' },
  { owner: 'mubita.s@student.unilus.ac.zm', type: 'marketplace', title: 'Casio fx-991EX scientific calculator', description: 'Barely used scientific calculator, all functions working. Selling because I finished the statistics module.', price: 220, price_unit: 'total', category: 'Stationery', location: 'UNILUS Main Campus', item_condition: 'Like new' },
  { owner: 'shaun.mwela@student.unilus.ac.zm', type: 'marketplace', title: 'Study desk and chair set', description: 'Wooden study desk with a matching chair. Solid and steady, ideal for a hostel room. Buyer arranges collection from Ibex Hill.', price: 900, price_unit: 'total', category: 'Furniture', location: 'Ibex Hill', item_condition: 'Good', contact_info: '+260 966 223 344' },
  { owner: 'lunga.greenhead@student.unilus.ac.zm', type: 'marketplace', title: 'Samsung Galaxy A14 - 128GB, dual SIM', description: 'Nine months old, always kept in a case with a screen protector. Comes with the original box and charger. No cracks or dents.', price: 2650, price_unit: 'total', category: 'Phones', location: 'Kabulonga', item_condition: 'Like new' },

  // ---- Accommodation -----------------------------------------------------
  { owner: 'lettings@kabulonga-demo.co.zm', type: 'accommodation', title: 'Self-contained room in Chalala, 10 minutes from UNILUS', description: 'Single self-contained room in a secure walled property with 24-hour security. Prepaid electricity, borehole water and space to park. Rent is payable three months in advance.', price: 1800, price_unit: 'per month', category: 'Self-contained', location: 'Chalala', bedrooms: 1, bathrooms: 1, furnished: 1, amenities: 'Wi-Fi, Prepaid electricity, Borehole water, 24-hour security, Parking', contact_info: '+260 211 445 566' },
  { owner: 'lettings@kabulonga-demo.co.zm', type: 'accommodation', title: 'Two-bedroom flat to share, Ibex Hill', description: 'Two-bedroom flat available for two students to share. Open-plan kitchen and lounge, tiled throughout. Water included in the rent.', price: 3200, price_unit: 'per month', category: 'Shared flat', location: 'Ibex Hill', bedrooms: 2, bathrooms: 1, furnished: 0, amenities: 'Water included, Tiled, Open-plan kitchen, Secure gate', contact_info: '+260 211 445 566' },
  { owner: 'fredrick.k@student.unilus.ac.zm', type: 'accommodation', title: 'Boarding house room near Kalingalinga', description: 'Affordable single room in a quiet boarding house. Shared bathroom and kitchen with four other students. Walking distance to the bus route into town.', price: 950, price_unit: 'per month', category: 'Boarding house', location: 'Kalingalinga', bedrooms: 1, bathrooms: 1, furnished: 1, amenities: 'Shared kitchen, Shared bathroom, Water, Security lights' },

  // ---- Tutors ------------------------------------------------------------
  { owner: 'changala.m@student.unilus.ac.zm', type: 'tutor', title: 'Mathematics and Statistics tutoring for first and second years', description: 'I have tutored MAT110 and STA210 for two years with consistently good results. Sessions cover past papers, worked examples and exam technique. Group rates available for three or more students.', price: 120, price_unit: 'per hour', category: 'Mathematics', subject: 'Mathematics, Statistics', level: 'Undergraduate', qualifications: 'Fourth-year BIT student, distinction in MAT110 and STA210', availability: 'Weekday evenings 17:00-20:00, Saturday mornings', location: 'UNILUS Main Campus or online' },
  { owner: 'dalitso.mwansa@student.unilus.ac.zm', type: 'tutor', title: 'Programming tutorials - Java, Python and SQL', description: 'Practical, project-based tutoring. We work through real code rather than slides, and I help with assignment debugging and version control basics.', price: 150, price_unit: 'per hour', category: 'Programming', subject: 'Java, Python, SQL', level: 'Undergraduate', qualifications: 'Final-year BIT student, teaching assistant for the programming lab', availability: 'Tuesdays and Thursdays after 16:00', location: 'Online or Main Campus library' },
  { owner: 'chanda.phiri@gmail.com', type: 'tutor', title: 'Legal writing and research support', description: 'Help with case briefs, legal research technique and citation. Aimed at first and second-year law students.', price: 130, price_unit: 'per hour', category: 'Law', subject: 'Legal writing, Legal research', level: 'Undergraduate', qualifications: 'Third-year LLB student', availability: 'Weekends', location: 'UNILUS Main Campus' },

  // ---- Jobs --------------------------------------------------------------
  { owner: 'careers@zamtel-demo.co.zm', type: 'job', title: 'IT Support Intern - three-month attachment', description: 'Support the internal helpdesk with first-line troubleshooting, hardware setup and user account administration. You will be paired with a mentor and rotate through the network and systems teams.', price: 2500, price_unit: 'per month', category: 'Internship', job_type: 'Internship', company: 'Zamtel (demonstration account)', location: 'Lusaka CBD', requirements: 'Third or fourth-year IT/Computer Science student. Basic Windows and networking knowledge. Available three full days a week.', apply_instructions: 'Apply through Campus Connect with a short note explaining your availability.', deadline: day(21), contact_info: 'careers@zamtel-demo.co.zm' },
  { owner: 'careers@zamtel-demo.co.zm', type: 'job', title: 'Part-time campus brand ambassador', description: 'Represent the company at campus events, run stand activations and collect student feedback. Roughly ten hours a week, mostly around lectures.', price: 1500, price_unit: 'per month', category: 'Part-time', job_type: 'Part-time', company: 'Zamtel (demonstration account)', location: 'UNILUS Main Campus', requirements: 'Confident communicator, active on campus, any programme of study.', apply_instructions: 'Apply on Campus Connect. Shortlisted candidates are contacted within a week.', deadline: day(14) },
  { owner: 'lettings@kabulonga-demo.co.zm', type: 'job', title: 'Weekend data-entry assistant', description: 'Capture tenancy records into a spreadsheet system on Saturdays. Accuracy matters more than speed. Paid weekly.', price: 400, price_unit: 'per week', category: 'Casual', job_type: 'Casual', company: 'Kabulonga Properties', location: 'Kabulonga', requirements: 'Good Excel skills, careful with detail, available Saturdays 08:00-14:00.', apply_instructions: 'Apply through Campus Connect.', deadline: day(10) },

  // ---- Roommates ---------------------------------------------------------
  { owner: 'fredrick.k@student.unilus.ac.zm', type: 'roommate', title: 'Looking for one roommate to share a two-bedroom in Chalala', description: 'I am a third-year IT student, quiet, non-smoking and study most evenings. Looking for someone tidy to take the second bedroom from the start of next semester. Rent and utilities split evenly.', category: 'Looking for a roommate', location: 'Chalala', gender_pref: 'Male', budget_min: 1200, budget_max: 1800, move_in_date: day(30), lifestyle: 'Non-smoker, quiet evenings, no pets, happy to share cooking duties', contact_info: '+260 966 445 566' },
  { owner: 'mubita.s@student.unilus.ac.zm', type: 'roommate', title: 'Second-year student seeking a room to share near campus', description: 'Looking to join an existing house or flat within walking distance of the Main Campus. I keep to myself, cook simple meals and study at the library most days.', category: 'Looking for a room to share', location: 'UNILUS Main Campus area', gender_pref: 'Any', budget_min: 800, budget_max: 1500, move_in_date: day(20), lifestyle: 'Quiet, tidy, non-smoker, early riser' },

  // ---- Lost & Found ------------------------------------------------------
  { owner: 'shaun.mwela@student.unilus.ac.zm', type: 'lostfound', title: 'Lost: black Kipling backpack near the library', description: 'Left a black Kipling backpack at the library entrance on Tuesday afternoon. It contains lecture notes, a blue folder and a phone charger. The notes are irreplaceable before exams.', category: 'Lost', location: 'UNILUS Library', item_date: day(-3), contact_info: '+260 966 223 344' },
  { owner: 'lunga.greenhead@student.unilus.ac.zm', type: 'lostfound', title: 'Found: student ID card in the cafeteria', description: 'Found a student identity card on a table in the cafeteria. Describe the name and student number to claim it, or collect it from the student affairs desk.', category: 'Found', location: 'UNILUS Cafeteria', item_date: day(-1) },
  { owner: 'changala.m@student.unilus.ac.zm', type: 'lostfound', title: 'Found: set of house keys with a red tag', description: 'A bunch of three keys on a red plastic tag, found in the car park near the science block. Held at the security office.', category: 'Found', location: 'UNILUS Car Park', item_date: day(-5) },
];

const REVIEWS = [
  { reviewer: 'mubita.s@student.unilus.ac.zm', reviewee: 'dalitso.mwansa@student.unilus.ac.zm', rating: 5, comment: 'Textbook was exactly as described and he met me on campus at the agreed time. Very straightforward transaction.' },
  { reviewer: 'shaun.mwela@student.unilus.ac.zm', reviewee: 'dalitso.mwansa@student.unilus.ac.zm', rating: 5, comment: 'Sat two programming sessions with him. Explains things clearly and stays until you actually understand the code.' },
  { reviewer: 'fredrick.k@student.unilus.ac.zm', reviewee: 'changala.m@student.unilus.ac.zm', rating: 5, comment: 'Excellent statistics tutor. My test mark went up by two grades after four sessions.' },
  { reviewer: 'lunga.greenhead@student.unilus.ac.zm', reviewee: 'changala.m@student.unilus.ac.zm', rating: 4, comment: 'Good tutor and very patient. Sessions occasionally started late but the content was worth it.' },
  { reviewer: 'dalitso.mwansa@student.unilus.ac.zm', reviewee: 'lettings@kabulonga-demo.co.zm', rating: 4, comment: 'Viewed the Chalala room. The listing was accurate and the agent answered questions honestly.' },
  { reviewer: 'changala.m@student.unilus.ac.zm', reviewee: 'lunga.greenhead@student.unilus.ac.zm', rating: 5, comment: 'Handed in a found ID card straight away. Reliable member of the campus community.' },
  { reviewer: 'natasha.banda@gmail.com', reviewee: 'shaun.mwela@student.unilus.ac.zm', rating: 4, comment: 'Desk was solid and fairly priced, though collection took some arranging.' },
];

async function main() {
  console.log('Seeding Campus Connect demonstration data...');

  await run('SET FOREIGN_KEY_CHECKS = 0');
  for (const table of ['admin_actions', 'saved_searches', 'favorites', 'notifications', 'messages', 'reports', 'reviews', 'applications', 'listing_images', 'listings', 'users']) {
    await run(`TRUNCATE TABLE ${table}`);
  }
  await run('SET FOREIGN_KEY_CHECKS = 1');

  // ---- users -------------------------------------------------------------
  const hash = await bcrypt.hash(PASSWORD, 10);
  const userIds = {};
  for (const u of USERS) {
    const result = await run(
      `INSERT INTO users (full_name, email, password_hash, role, student_id, university, program,
                          year_of_study, phone, bio, verification_status, verified_at, verification_note)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        u.full_name, u.email, hash, u.role, u.student_id ?? null, u.university ?? null,
        u.program ?? null, u.year_of_study ?? null, u.phone ?? null, u.bio ?? null,
        u.verification_status,
        u.verification_status === 'verified' ? new Date() : null,
        u.verification_status === 'verified' && u.role === 'student' ? 'Auto-verified from university e-mail domain.' : null,
      ],
    );
    userIds[u.email] = result.insertId;
  }
  console.log(`  ${USERS.length} users`);

  // ---- listings ----------------------------------------------------------
  const listingIds = {};
  for (const [i, l] of LISTINGS.entries()) {
    const { owner, ...cols } = l;
    const keys = Object.keys(cols);
    const result = await run(
      `INSERT INTO listings (user_id, expires_at, created_at, views, ${keys.join(', ')})
       VALUES (?, DATE_ADD(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL ? DAY), ?, ${keys.map(() => '?').join(', ')})`,
      [userIds[owner], LISTINGS.length - i, Math.floor(Math.random() * 60) + 5, ...keys.map((k) => cols[k])],
    );
    listingIds[l.title] = result.insertId;
  }
  console.log(`  ${LISTINGS.length} listings across all six modules`);

  // ---- reviews -----------------------------------------------------------
  for (const r of REVIEWS) {
    await run(
      'INSERT INTO reviews (reviewer_id, reviewee_id, rating, comment, created_at) VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))',
      [userIds[r.reviewer], userIds[r.reviewee], r.rating, r.comment, Math.floor(Math.random() * 20) + 1],
    );
  }
  console.log(`  ${REVIEWS.length} reviews`);

  // ---- job applications --------------------------------------------------
  const internship = listingIds['IT Support Intern - three-month attachment'];
  await run('INSERT INTO applications (listing_id, applicant_id, cover_note, status) VALUES (?, ?, ?, ?)', [
    internship, userIds['dalitso.mwansa@student.unilus.ac.zm'],
    'I am a final-year BIT student available Mondays, Wednesdays and Fridays. I have supported the campus lab helpdesk for two semesters.', 'shortlisted',
  ]);
  await run('INSERT INTO applications (listing_id, applicant_id, cover_note, status) VALUES (?, ?, ?, ?)', [
    internship, userIds['mubita.s@student.unilus.ac.zm'],
    'Second-year Computer Science student. I have built two small networks at home and I am comfortable with Windows administration.', 'submitted',
  ]);
  console.log('  2 job applications');

  // ---- messages ----------------------------------------------------------
  const bookId = listingIds['Database Systems textbook (Elmasri & Navathe, 7th ed.)'];
  const buyer = userIds['mubita.s@student.unilus.ac.zm'];
  const seller = userIds['dalitso.mwansa@student.unilus.ac.zm'];
  const key = threadKey(bookId, buyer, seller);
  const thread = [
    [buyer, seller, 'Hello, is the Database Systems textbook still available?'],
    [seller, buyer, 'Yes it is. I am on campus tomorrow between 10 and 2 if you want to see it.'],
    [buyer, seller, 'That works. Would you take 300 for it?'],
    [seller, buyer, 'I can do 325 and I will throw in my printed notes for the course.'],
  ];
  for (const [from, to, body] of thread) {
    await run(
      'INSERT INTO messages (thread_key, listing_id, sender_id, recipient_id, body, is_read) VALUES (?, ?, ?, ?, ?, 1)',
      [key, bookId, from, to, body],
    );
  }
  console.log('  1 message thread');

  // ---- a report awaiting moderation --------------------------------------
  await run(
    'INSERT INTO reports (reporter_id, target_type, target_id, reason, details) VALUES (?, ?, ?, ?, ?)',
    [
      userIds['fredrick.k@student.unilus.ac.zm'], 'listing',
      listingIds['HP EliteBook 840 G5 - i5, 8GB RAM, 256GB SSD'],
      'Item already sold or unavailable',
      'I contacted the seller and she said the laptop was sold last week, but the listing is still showing as available.',
    ],
  );
  console.log('  1 open report');

  // ---- favourites and saved searches -------------------------------------
  await run('INSERT INTO favorites (user_id, listing_id) VALUES (?, ?)', [buyer, listingIds['Self-contained room in Chalala, 10 minutes from UNILUS']]);
  await run('INSERT INTO favorites (user_id, listing_id) VALUES (?, ?)', [buyer, listingIds['Mathematics and Statistics tutoring for first and second years']]);
  await run(
    'INSERT INTO saved_searches (user_id, name, type, query_json) VALUES (?, ?, ?, ?)',
    [buyer, 'Rooms under K2000 in Chalala', 'accommodation', JSON.stringify({ type: 'accommodation', location: 'Chalala', max_price: '2000' })],
  );
  await run(
    'INSERT INTO saved_searches (user_id, name, type, query_json) VALUES (?, ?, ?, ?)',
    [userIds['shaun.mwela@student.unilus.ac.zm'], 'Cheap laptops', 'marketplace', JSON.stringify({ type: 'marketplace', category: 'Laptops', max_price: '5000' })],
  );
  console.log('  favourites and saved searches');

  // ---- a welcome notification for every account --------------------------
  const all = await query('SELECT id FROM users');
  for (const u of all) {
    await run(
      'INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)',
      [u.id, 'welcome', 'Welcome to Campus Connect', 'Browse the marketplace, accommodation, tutors, jobs, roommates and lost-and-found modules from one account.', '/'],
    );
  }

  console.log('\nDemonstration accounts (password for all: password123)');
  console.log('  Administrator : admin@unilus.ac.zm');
  console.log('  Student       : dalitso.mwansa@student.unilus.ac.zm');
  console.log('  Student       : fredrick.k@student.unilus.ac.zm');
  console.log('  Pending student: natasha.banda@gmail.com');
  console.log('  Employer      : careers@zamtel-demo.co.zm');
  console.log('  Landlord      : lettings@kabulonga-demo.co.zm');

  await pool.end();
}

main().catch(async (err) => {
  console.error('Seeding failed:', err);
  await pool.end().catch(() => {});
  process.exit(1);
});
