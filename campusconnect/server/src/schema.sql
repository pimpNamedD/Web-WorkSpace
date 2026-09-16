-- ============================================================================
-- Campus Connect - University Student Marketplace and Services Management
-- Database schema (MySQL / MariaDB)
-- ============================================================================

-- ---------------------------------------------------------------------------
-- users: students, employers/landlords and administrators (single account,
-- 1.4.2 "Single Account Access"). verification_status drives the trust badge.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  full_name           VARCHAR(120)  NOT NULL,
  email               VARCHAR(160)  NOT NULL UNIQUE,
  password_hash       VARCHAR(255)  NOT NULL,
  role                ENUM('student','employer','admin') NOT NULL DEFAULT 'student',
  student_id          VARCHAR(40)   NULL,
  university          VARCHAR(120)  NULL,
  program             VARCHAR(120)  NULL,
  year_of_study       VARCHAR(20)   NULL,
  phone               VARCHAR(40)   NULL,
  bio                 TEXT          NULL,
  avatar_url          VARCHAR(255)  NULL,
  verification_status ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
  verification_note   VARCHAR(255)  NULL,
  verified_at         DATETIME      NULL,
  is_suspended        TINYINT(1)    NOT NULL DEFAULT 0,
  suspension_reason   VARCHAR(255)  NULL,
  email_notifications TINYINT(1)    NOT NULL DEFAULT 1,
  last_login_at       DATETIME      NULL,
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_status (verification_status),
  INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- listings: one table serving all six service modules (1.4). The `type`
-- column discriminates; module-specific columns are nullable so that search,
-- favourites, reporting, expiry and moderation work uniformly across modules.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS listings (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  user_id            INT NOT NULL,
  type               ENUM('marketplace','accommodation','tutor','job','roommate','lostfound') NOT NULL,
  title              VARCHAR(160) NOT NULL,
  description        TEXT NOT NULL,
  status             ENUM('active','sold','closed','filled','resolved','archived','removed') NOT NULL DEFAULT 'active',

  -- shared / filterable
  price              DECIMAL(12,2) NULL,
  price_unit         VARCHAR(20)   NULL,
  category           VARCHAR(80)   NULL,
  location           VARCHAR(140)  NULL,
  contact_info       VARCHAR(200)  NULL,

  -- marketplace
  item_condition     VARCHAR(30)   NULL,

  -- accommodation
  bedrooms           TINYINT       NULL,
  bathrooms          TINYINT       NULL,
  furnished          TINYINT(1)    NULL,
  amenities          VARCHAR(400)  NULL,

  -- tutoring
  subject            VARCHAR(100)  NULL,
  level              VARCHAR(60)   NULL,
  qualifications     VARCHAR(400)  NULL,
  availability       VARCHAR(200)  NULL,

  -- jobs and internships
  company            VARCHAR(140)  NULL,
  job_type           VARCHAR(40)   NULL,
  requirements       TEXT          NULL,
  apply_instructions VARCHAR(400)  NULL,
  deadline           DATE          NULL,

  -- roommate finder
  gender_pref        VARCHAR(20)   NULL,
  budget_min         DECIMAL(12,2) NULL,
  budget_max         DECIMAL(12,2) NULL,
  move_in_date       DATE          NULL,
  lifestyle          VARCHAR(400)  NULL,

  -- lost and found
  item_date          DATE          NULL,

  views              INT NOT NULL DEFAULT 0,
  expires_at         DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_listings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_listings_type_status (type, status),
  INDEX idx_listings_category (category),
  INDEX idx_listings_location (location),
  INDEX idx_listings_price (price),
  INDEX idx_listings_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS listing_images (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  url        VARCHAR(255) NOT NULL,
  sort_order TINYINT NOT NULL DEFAULT 0,
  CONSTRAINT fk_images_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  INDEX idx_images_listing (listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- job applications (1.5 Job Board - students can view and apply)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS applications (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  listing_id   INT NOT NULL,
  applicant_id INT NOT NULL,
  cover_note   TEXT NULL,
  status       ENUM('submitted','reviewed','shortlisted','rejected','accepted') NOT NULL DEFAULT 'submitted',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_user FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_application (listing_id, applicant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- reviews and ratings (1.4 Reviews and Ratings)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  reviewer_id INT NOT NULL,
  reviewee_id INT NOT NULL,
  listing_id  INT NULL,
  rating      TINYINT NOT NULL,
  comment     TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rev_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_reviewee FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_listing  FOREIGN KEY (listing_id)  REFERENCES listings(id) ON DELETE SET NULL,
  UNIQUE KEY uq_review (reviewer_id, reviewee_id, listing_id),
  INDEX idx_rev_reviewee (reviewee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- reports -> administrator dashboard (1.4.2 Reporting and Moderation)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reports (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  reporter_id INT NOT NULL,
  target_type ENUM('listing','user') NOT NULL,
  target_id   INT NOT NULL,
  reason      VARCHAR(80) NOT NULL,
  details     TEXT NULL,
  status      ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  admin_note  VARCHAR(400) NULL,
  handled_by  INT NULL,
  handled_at  DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rep_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rep_admin FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_rep_status (status),
  INDEX idx_rep_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- messages: asynchronous listing enquiries. NOT real-time chat, which is
-- explicitly out of scope (1.4.3).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  thread_key   VARCHAR(80) NOT NULL,
  listing_id   INT NULL,
  sender_id    INT NOT NULL,
  recipient_id INT NOT NULL,
  body         TEXT NOT NULL,
  is_read      TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_recipient FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_msg_thread (thread_key, created_at),
  INDEX idx_msg_recipient (recipient_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- notifications (1.4 Notification System)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  type       VARCHAR(40) NOT NULL,
  title      VARCHAR(160) NOT NULL,
  body       VARCHAR(400) NULL,
  link       VARCHAR(200) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notif_user (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- favourites and saved searches (1.4.1 Saved Searches and Favorites)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS favorites (
  user_id    INT NOT NULL,
  listing_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, listing_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS saved_searches (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  name            VARCHAR(120) NOT NULL,
  type            VARCHAR(30) NULL,
  query_json      TEXT NOT NULL,
  notify          TINYINT(1) NOT NULL DEFAULT 1,
  last_alerted_at DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ss_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_ss_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- admin audit trail - supports Administrator Oversight (1.4.2)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_actions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT NOT NULL,
  action      VARCHAR(60) NOT NULL,
  target_type VARCHAR(30) NOT NULL,
  target_id   INT NOT NULL,
  note        VARCHAR(400) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aa_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_aa_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
