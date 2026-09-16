# Campus Connect

**University Student Marketplace and Services Management System**
University of Lusaka — Bachelor of Information Technology final-year project.

A centralised, web-based platform that replaces the scattered WhatsApp, Facebook and Telegram
groups students currently rely on. One **verified** student account gives access to six services:
marketplace, accommodation, tutors, jobs & internships, roommate finder, and lost-and-found —
with verification, ratings, reporting and administrator moderation built in.

---

## Quick start

Requires **Node.js 18+** and **MySQL / MariaDB** (XAMPP works).

```bash
cd server && npm install && npm run reset && npm start
```

Then in a second terminal:

```bash
cd client && npm install && npm run dev
```

Open **http://localhost:5173**. The API runs on **http://localhost:5050**.

`npm run reset` drops the database, recreates the schema and loads the demonstration data.
Use `npm run migrate` alone to create the tables without wiping existing rows.

### Demonstration accounts

Password for every account: `password123`

| Role | E-mail |
| --- | --- |
| Administrator | `admin@unilus.ac.zm` |
| Verified student | `dalitso.mwansa@student.unilus.ac.zm` |
| Verified student | `fredrick.k@student.unilus.ac.zm` |
| Student awaiting verification | `natasha.banda@gmail.com` |
| Employer | `careers@zamtel-demo.co.zm` |
| Landlord | `lettings@kabulonga-demo.co.zm` |

---

## Architecture

```
campusconnect/
├── server/                 Node.js + Express REST API
│   ├── src/
│   │   ├── index.js        app entry, route mounting, error handling
│   │   ├── config.js       environment configuration
│   │   ├── db.js           MySQL connection pool + query helpers
│   │   ├── schema.sql      11-table relational schema
│   │   ├── migrate.js      creates the database and applies the schema
│   │   ├── seed.js         demonstration dataset
│   │   ├── middleware/     auth (JWT), file uploads
│   │   ├── routes/         12 route modules (see API reference below)
│   │   ├── utils/          shared helpers, listing definitions, query builder
│   │   └── jobs/           listing expiry + saved-search alerting
│   └── uploads/            uploaded listing photos and avatars
└── client/                 React 18 + Vite single-page application
    └── src/
        ├── lib/            API wrapper, auth/meta/toast contexts, formatters
        ├── components/     layout, listing card, filter panel, UI primitives
        └── pages/          public pages, student dashboard, admin console
```

**Stack:** React 18, React Router 6, Vite · Node.js, Express 4, MySQL2 · JWT + bcrypt · Multer · Nodemailer.

### Design decisions

- **One `listings` table for all six modules,** discriminated by a `type` column with nullable
  module-specific columns. Search, filtering, favourites, reporting, expiry and moderation are
  therefore implemented once and work identically everywhere.
- **Field definitions live in `server/src/utils/listingTypes.js`** and are served to the client via
  `GET /api/meta`. The dynamic listing form and filter panel are generated from the same source the
  server validates against, so the two cannot drift apart.
- **Messaging is deliberately asynchronous** (stored messages + notifications), not real-time chat,
  which the proposal places out of scope.

---

## Mapping to the project proposal

### Objectives (§1.3.1)

| # | Objective | Where it lives |
| --- | --- | --- |
| 1 | Secure registration and authentication, verified students only | `routes/auth.js`, `middleware/auth.js` (`requireVerified`) |
| 2 | Student profile module showing verification status | `routes/users.js`, `pages/Profile.jsx` |
| 3 | Buy-and-sell marketplace | `type: 'marketplace'` |
| 4 | Accommodation listing and search | `type: 'accommodation'` |
| 5 | Tutor finder | `type: 'tutor'` |
| 6 | Part-time job and internship board | `type: 'job'` + `routes/applications.js` |
| 7 | Roommate finder | `type: 'roommate'` |
| 8 | Lost-and-found | `type: 'lostfound'` |
| 9 | Search and filtering across all modules | `utils/listingQuery.js`, `components/Filters.jsx` |
| 10 | Reviews, ratings and reporting | `routes/reviews.js`, `routes/reports.js` |

### Core functions (§1.4)

Registration & authentication · profile management · all six listing modules · search and
filtering · reviews and ratings · reporting · notifications · administrator dashboard.

### Enhanced functions (§1.4.1)

- **Advanced search filters** — price range, location, condition, bedrooms, furnished, subject,
  level, opportunity type, gender preference, minimum seller rating, posting age, verified-only.
- **Automated notifications** — in-app for every event, mirrored to e-mail when SMTP is configured.
- **Saved searches and favourites** — save any filter set; new matching listings raise an alert.
- **Listing expiry and auto-archive** — background job archives expired posts, warns owners three
  days ahead, and offers one-click renewal.
- **Messaging system** — in-platform enquiries so phone numbers stay private.
- **Responsive design** — single-column layouts and a collapsing menu below 760px.
- **Category-specific fields** — each module renders and validates only its own fields.

### Special features (§1.4.2)

- **Student verification** — a university e-mail domain auto-verifies; everyone else joins an
  administrator queue. Unverified accounts cannot post (`REQUIRE_VERIFIED_TO_POST`).
- **Integrated accountability** — verification + ratings + reporting feed one another.
- **Comprehensive services integration** and **single account access** across all six modules.
- **Administrator oversight** — moderation queue, user management, suspension, and an **audit
  trail** recording every administrator decision.
- **Verification history** — profiles show verification status alongside the full review history.

### Explicitly out of scope (§1.4.3)

No payment processing, no delivery or logistics, no real-time chat, no legal or tenancy
verification, no external university system integration, no native mobile applications, no
criminal background checks, and no counselling services.

---

## API reference

All endpoints are prefixed with `/api`. Authenticated requests send `Authorization: Bearer <token>`.

### Authentication
| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/auth/register` | Create a student or employer account |
| POST | `/auth/login` | Sign in, returns a JWT |
| GET | `/auth/me` | Current session |
| POST | `/auth/change-password` | Change own password |

### Users and profiles
| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/users/:id` | Public profile with stats, listings and reviews |
| GET | `/users` | Directory search |
| PATCH | `/users/me` | Update own profile |
| POST | `/users/me/avatar` | Upload profile picture |
| POST | `/users/me/verification` | Submit student details for verification |

### Listings (all six modules)
| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/listings` | Browse and filter |
| GET | `/listings/featured` | Newest per module |
| GET | `/listings/mine` | Own listings, any status |
| GET | `/listings/:id` | Full listing detail |
| POST | `/listings` | Publish (verified accounts only) |
| PATCH | `/listings/:id` | Edit or change status |
| POST | `/listings/:id/renew` | Extend an expired listing |
| POST | `/listings/:id/images` | Add photos |
| DELETE | `/listings/:id/images/:imageId` | Remove a photo |
| DELETE | `/listings/:id` | Delete |

**Filter parameters:** `q`, `type`, `types`, `category`, `location`, `min_price`, `max_price`,
`condition`, `bedrooms`, `furnished`, `subject`, `level`, `job_type`, `gender_pref`, `min_rating`,
`posted_within`, `verified_only`, `status`, `user_id`, `sort`, `page`, `limit`.

### Search and reference data
| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/search` | Global search with per-module counts |
| GET | `/meta` | Module definitions, categories and option lists |
| GET | `/stats` | Public headline figures |
| GET | `/trending` | Most-viewed recent listings |
| GET | `/health` | Service and database health |

### Engagement
| Method | Endpoint | Description |
| --- | --- | --- |
| GET / POST / DELETE | `/favorites`, `/favorites/:id`, `/favorites/:id/toggle` | Favourites |
| GET / POST / PATCH / DELETE | `/saved-searches`, `/saved-searches/:id`, `/saved-searches/:id/run` | Saved searches and alerts |
| GET / POST | `/messages`, `/messages/thread/:key`, `/messages/unread-count` | Listing enquiries |
| GET / PATCH / POST / DELETE | `/notifications`, `/notifications/read-all`, `/notifications/unread-count` | Notifications |
| POST / GET / DELETE | `/reviews`, `/reviews/user/:id`, `/reviews/mine` | Reviews and ratings |
| POST / GET | `/reports`, `/reports/mine`, `/reports/reasons` | Reporting |
| POST / GET / PATCH / DELETE | `/applications`, `/applications/mine`, `/applications/received` | Job applications |

### Administrator (role `admin` only)
| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/admin/stats` | Dashboard metrics and 14-day trends |
| GET | `/admin/users` | Filterable user list |
| POST | `/admin/users` | Create an account |
| GET | `/admin/verifications` | Verification queue |
| POST | `/admin/users/:id/verify` | Approve or reject verification |
| POST | `/admin/users/:id/suspend` | Suspend or restore an account |
| POST | `/admin/users/:id/role` | Change a role |
| GET | `/admin/listings` | Moderate listings |
| POST | `/admin/listings/:id/moderate` | Remove, archive or restore |
| GET | `/admin/reports` | Moderation queue |
| PATCH | `/admin/reports/:id` | Progress or close a report |
| GET | `/admin/audit` | Administrator audit trail |
| POST | `/admin/maintenance/run` | Run listing expiry on demand |

---

## Configuration

Server settings live in `server/.env` (see `.env.example`):

| Variable | Default | Purpose |
| --- | --- | --- |
| `PORT` | `5050` | API port |
| `DB_HOST` / `DB_PORT` / `DB_USER` / `DB_PASSWORD` / `DB_NAME` | localhost / 3306 / root / *(blank)* / `campusconnect_db` | Database connection |
| `JWT_SECRET` / `JWT_EXPIRES_IN` | dev secret / `7d` | Token signing |
| `AUTO_VERIFY_DOMAINS` | `unilus.ac.zm,student.unilus.ac.zm` | Domains that verify automatically |
| `REQUIRE_VERIFIED_TO_POST` | `true` | Enforce the verification gate |
| `LISTING_TTL_DAYS` | `60` | Default listing lifetime |
| `MAINTENANCE_INTERVAL_MINUTES` | `30` | Expiry job interval |
| `MAIL_ENABLED` + SMTP settings | `false` | E-mail notifications; when off, mail is logged instead |

## Database schema

`users` · `listings` · `listing_images` · `applications` · `reviews` · `reports` · `messages` ·
`notifications` · `favorites` · `saved_searches` · `admin_actions`

## Building for production

```bash
cd client && npm run build
```

Outputs to `client/dist/`. Serve those static files from any web server and point it at the API,
or set `CLIENT_ORIGIN` in `server/.env` to the deployed front-end origin.
