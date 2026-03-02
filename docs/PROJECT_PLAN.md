# RePlug — Technical Project Plan (Core PHP + MySQL + XAMPP)

## 0) Goal & Scope
RePlug is a web app for **electronics + small appliances recycling**:
- Recyclers list items for **free pickup**
- Drivers pick up items
- Technicians inspect items (working / repairable / non-repairable)
- Admin can approve items for **internal marketplace resale**
- If sold, revenue split is recorded (depot share + small % recycler payout)

**Tech constraint:** Core PHP (no frameworks), MySQL, HTML/CSS/JS, XAMPP local dev.

---

## 1) Local Development Setup (XAMPP)
### 1.1 Folder
Create:
- `C:\xampp\htdocs\replug\`

### 1.2 Apache / PHP
- Start **Apache** and **MySQL** in XAMPP
- Confirm: `http://localhost/replug/` loads

### 1.3 Database
- Open phpMyAdmin: `http://localhost/phpmyadmin`
- Create DB: `replug_db` (utf8mb4)
- Create DB user (optional): `replug_user` with password

---

## 2) Project Structure (Core PHP “MVC-ish”)
Create this folder structure:

replug/
  public/
    index.php
    assets/
      css/
      js/
      img/
  app/
    config/
      config.php
      db.php
    core/
      auth.php
      csrf.php
      helpers.php
      router.php
      validator.php
      upload.php
    middleware/
      require_login.php
      require_role.php
    models/
      User.php
      Item.php
      Pickup.php
      Inspection.php
      Marketplace.php
      Order.php
      Payout.php
      AuditLog.php
    controllers/
      AuthController.php
      RecyclerController.php
      DriverController.php
      TechnicianController.php
      AdminController.php
      MarketplaceController.php
    views/
      layouts/
        header.php
        footer.php
        nav.php
      auth/
      recycler/
      driver/
      technician/
      admin/
      marketplace/
  storage/
    uploads/
      items/
  sql/
    schema.sql
    seed.sql
  .htaccess
  README.md

**Notes**
- Public entry is `public/index.php`
- Use `router.php` to map routes to controllers
- Keep secrets out of Git

---

## 3) Core Conventions
### 3.1 Roles
- recycler
- driver
- technician
- admin
- buyer (or treat buyer as normal user with buyer permissions)

### 3.2 Item Status Flow (strict)
draft -> pickup_requested -> scheduled -> picked_up -> inspected
-> (recycled | repair_in_progress | approved_for_sale)
-> listed_for_sale -> sold

Enforce transitions in code (no skipping).

### 3.3 Security Baseline
- Password hashing: `password_hash()` / `password_verify()`
- Sessions + regeneration on login
- CSRF tokens for POST forms
- Validate + sanitize all inputs
- File upload: only images, size limit, random filename, store in `storage/uploads/items/`

---

## 4) Database Design (MySQL)
Create `sql/schema.sql` with these tables.

### 4.1 users
- id (PK)
- name
- email (unique)
- password_hash
- phone (nullable)
- role (ENUM: recycler,driver,technician,admin,buyer)
- created_at, updated_at

### 4.2 addresses (optional, or store in pickups)
- id (PK)
- user_id (FK users.id)
- line1, line2, city, province, postal_code
- notes

### 4.3 items
- id (PK)
- recycler_user_id (FK users.id)
- category (ENUM or VARCHAR)
- title
- description
- condition_notes
- photos_json (TEXT)  // array of image paths
- status (VARCHAR)
- created_at, updated_at

### 4.4 pickups
- id (PK)
- recycler_user_id (FK)
- driver_user_id (FK, nullable until assigned)
- pickup_window_start (DATETIME)
- pickup_window_end (DATETIME)
- address_text (TEXT)
- status (requested|scheduled|picked_up|failed|cancelled)
- created_at, updated_at

### 4.5 pickup_items (many-to-many)
- pickup_id (FK)
- item_id (FK)
- PRIMARY KEY (pickup_id, item_id)

### 4.6 inspections
- id (PK)
- item_id (FK)
- technician_user_id (FK)
- result (working|repairable|not_repairable)
- notes (TEXT)
- estimated_repair_cost (DECIMAL)
- status_after (VARCHAR) // recycled / repair_in_progress / approved_for_sale
- created_at

### 4.7 marketplace_listings
- id (PK)
- item_id (FK unique)
- admin_user_id (FK)
- price (DECIMAL)
- title
- description
- is_active (TINYINT)
- created_at, updated_at

### 4.8 orders
- id (PK)
- buyer_user_id (FK users.id)
- listing_id (FK marketplace_listings.id)
- amount (DECIMAL)
- status (pending|paid|cancelled|refunded)
- created_at, updated_at

### 4.9 payouts
- id (PK)
- recycler_user_id (FK)
- order_id (FK)
- amount (DECIMAL)
- status (unpaid|paid)
- created_at, updated_at

### 4.10 audit_logs
- id (PK)
- actor_user_id (FK users.id)
- entity_type (item|pickup|listing|order|payout)
- entity_id (INT)
- action (VARCHAR)
- meta_json (TEXT)
- created_at

**Indexes**
- users.email unique
- items.recycler_user_id
- pickups.driver_user_id, pickups.recycler_user_id
- marketplace_listings.item_id unique
- orders.buyer_user_id, orders.listing_id

---

## 5) Routes (Simple Router)
### Public
- GET / -> landing page
- GET /register, POST /register
- GET /login, POST /login
- POST /logout

### Recycler
- GET /recycler/dashboard
- GET /recycler/items/new, POST /recycler/items
- GET /recycler/items/{id}
- POST /recycler/pickups/request  (select items + pickup window + address)
- GET /recycler/pickups

### Driver
- GET /driver/dashboard
- GET /driver/pickups
- POST /driver/pickups/{id}/complete
- POST /driver/pickups/{id}/fail

### Technician
- GET /tech/dashboard
- GET /tech/items/to-inspect
- GET /tech/items/{id}
- POST /tech/items/{id}/inspect

### Admin
- GET /admin/dashboard
- GET /admin/pickups (assign driver)
- POST /admin/pickups/{id}/assign
- GET /admin/inspections
- POST /admin/items/{id}/approve-for-sale
- GET /admin/listings
- GET /admin/payouts
- POST /admin/payouts/{id}/mark-paid

### Marketplace
- GET /marketplace
- GET /marketplace/{listingId}
- POST /marketplace/{listingId}/purchase (simulate checkout)
- GET /orders

---

## 6) Execution Plan (Build Order)
### Milestone 1 — Skeleton + Auth (Day 1–2)
- Create folder structure
- Build DB connection (PDO)
- Implement register/login/logout
- Role-based middleware

### Milestone 2 — Recycler Item Listings (Day 3–4)
- CRUD: create item + upload photos
- Recycler dashboard (my items + statuses)

### Milestone 3 — Pickup Requests (Day 5–6)
- Recycler selects items -> creates pickup request
- Admin view pickups
- Admin assigns driver
- Driver marks complete/failed

### Milestone 4 — Inspection Workflow (Day 7–8)
- Technician queue: items picked up not inspected
- Save inspection result
- Update item status accordingly
- Audit logs for all status changes

### Milestone 5 — Marketplace + Orders (Day 9–10)
- Admin converts approved items into marketplace listing
- Buyer browses and “purchases” (simulate payment)
- Create order record

### Milestone 6 — Revenue Split + Payout (Day 11)
- On paid order: compute payout amount for recycler
- Create payout record (unpaid)
- Admin marks payout paid

### Milestone 7 — Polish + Test (Day 12–14)
- Input validation everywhere
- Access control checks
- Edge cases: cancel pickup, missing items, invalid transitions
- Basic UI and responsive layout

---

## 7) Revenue Split Rule (Simple)
Define in config:
- depot_share_percent = 90
- recycler_share_percent = 10

On successful order (paid):
- payout = amount * recycler_share_percent/100
- depot = amount - payout

Store payout record linked to the order.

---

## 8) Minimal UI Pages (What must exist)
- Landing
- Auth pages (login/register)
- Dashboards per role
- Listing form + item detail
- Pickup request form
- Driver pickup list
- Technician inspection form
- Admin pickup assignment + listing creation + payouts
- Marketplace browse + detail + purchase confirmation

---

## 9) README Checklist (for submission/demo)
- How to set up DB (`sql/schema.sql`)
- Default seed users:
  - admin@example.com / Password123!
  - driver@example.com / Password123!
  - tech@example.com / Password123!
  - recycler@example.com / Password123!
  - buyer@example.com / Password123!
- Demo script:
  1) Recycler creates item + pickup
  2) Admin assigns driver
  3) Driver completes pickup
  4) Tech inspects item as repairable
  5) Admin approves listing
  6) Buyer purchases
  7) Payout created for recycler

---

## 10) Definition of Done (DoD)
- All roles can log in
- Status transitions are enforced
- Pickup → inspection → listing → order flow works end-to-end
- Data stored in MySQL
- No framework usage
- Code is organized and readable
