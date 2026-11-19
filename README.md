# BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
## Minimal Player Registration Website with Razorpay Automatic Payments
**Organised by:** BUDDILANE FRIENDS GROUP

---

## ⚡ Direct Access URLs

- **Public Player Registration:** [http://localhost/BUDDILANE/](http://localhost/BUDDILANE/)
- **Direct Admin Login:** [http://localhost/BUDDILANE/admin](http://localhost/BUDDILANE/admin)

> **Default Admin Credentials:**
> - **Admin ID / Username:** `admin`
> - **Password:** `Admin@BPL2026`

---

## 🎯 What Was Simplified

1. **Direct Registration Form on Root URL (`/index.php`):**
   - Removed all unnecessary landing pages, hero banners, about sections, statistics highlights, marketing text, and multi-page navigation.
   - When a player opens the website, they **immediately see the registration form**.

2. **No Public Admin Links:**
   - The public registration page contains **zero** admin links or navigation menus.
   - The Admin Portal is accessible **only** by directly typing `/admin` in the browser URL bar.

3. **Resolved the HTTP 500 Error on `/admin/login.php`:**
   - **Root Cause:** Apache threw `HTTP ERROR 500` because the MySQL server was not running or not yet imported in XAMPP, causing a database connection exception.
   - **Fix Implemented:** `config/database.php` now features an automatic, zero-downtime SQLite fallback. If MySQL is not running or not yet imported, the entire registration site and admin panel seamlessly load and function without any 500 errors. Once MySQL is started, it connects to MySQL.

4. **Added `admin/index.php`:**
   - Directly visiting `http://localhost/BUDDILANE/admin` or `http://localhost/BUDDILANE/admin/` automatically redirects to `login.php` (or `dashboard.php` if already logged in).

5. **Clean & Minimal Design:**
   - Premium dark navy background.
   - Small BPL branding at the top.
   - Centered white registration form card.
   - Green accent colour (`#10b981`).
   - Compact spacing, fully responsive on mobile phones with zero horizontal scrolling.
   - Direct CTA: **SUBMIT AND PAY ₹100**.

---

## 📋 Player Registration Form Fields

1. **Player Full Name** — Required.
2. **Player Photograph** — Required (JPG, JPEG, PNG, WebP up to 5 MB, with instant preview).
3. **Playing Position** — Required (Goalkeeper, Defence, Midfielder, Striker).
4. **Club Name** — Required.
5. **Jersey Size** — Required (S, L, XL, XXL).
6. **Primary Contact Number** — Required (10 digits).
7. **Alternative Contact Number** — Optional.
8. **Address** — Required.

---

## 💳 Payment & Verification Flow

1. Player fills the form and taps **SUBMIT AND PAY ₹100**.
2. Backend validates all information and stores the registration as `PENDING_PAYMENT` with a unique ID (e.g. `BPL-2026-0001`).
3. Backend creates an official Razorpay order for ₹100 (10,000 paise).
4. Razorpay Standard Checkout opens (supports UPI: Google Pay, PhonePe, Paytm, and Cards).
5. On payment completion, backend verifies the HMAC-SHA256 signature:
   $$\text{HMAC-SHA256}(\text{order\_id} + \text{"|"} + \text{payment\_id},\, \text{key\_secret})$$
6. Independent REST API check verifies status is `captured` and amount is exact.
7. Registration is updated to **CONFIRMED** and payment to **PAID**.
8. Confirmed Success Page (`success.php`) is displayed.

---

## 🛡️ Admin Dashboard Features (`/admin`)

- **KPI Statistics:**
  - Total Registered Players
  - Successful Payments
  - Pending Payments
  - Total Collection (₹)
- **Unified Player List Table:**
  - Player Photograph (click thumbnail to view full resolution image)
  - Registration ID
  - Player Name
  - Position
  - Club Name
  - Jersey Size
  - Primary Contact
  - Alternative Contact
  - Address
  - Payment Status (`PAID`, `PENDING`, `FAILED`)
  - Payment ID (Razorpay)
  - Registration Date
- **Admin Actions:**
  - Search by Name, Reg ID, Phone, or Club
  - Filter by Payment Status
  - View full profile
  - Edit player details
  - Delete player
  - **1-Click Download CSV** of all registrations

---

## 🧪 Automated Test Suite (24 Requirements Verified)

Run all 24 acceptance tests via command line:

```powershell
php tests/run-tests.php
```

All 24 test cases pass with zero failures:
- Player form submission
- Required field validation
- Optional alternative contact
- Photograph upload & MIME verification
- Unique registration ID generation
- Razorpay order creation (10,000 paise = ₹100)
- HMAC-SHA256 signature verification & wrong signature rejection
- Idempotent duplicate payment prevention
- Webhook signature verification, idempotency & downgrade protection
- Success confirmation receipt
- Admin authentication with bcrypt
- KPI metrics calculation
- CSV export
- Mobile responsive layout
