# WCSS Internal Supplies - Project Hub

This document tracks all major parts of the plugin, their purposes, and development status.

---

## 📂 frontend

Contains the **UI (views)**, CSS/JS, and route handling for the Store Manager dashboard.

- **assets/css/manager.css** → Styling for the manager dashboard
- **assets/js/manager.js** → JS logic for navigation, CRUD forms, pagination
- **pages/** → All custom pages for the dashboard
  - `dashboard.php` → Store Manager landing/dashboard
  - `products.php` → Product listing w/ pagination
  - `products-create.php` → Product create form
  - `products-edit.php` → Product edit form
- **routes/class-routes-manager.php** → Custom rewrite rules for `/manager` and routing logic

✅ Status: **Mostly working** — pagination pending refinement, product create redirect/success in progress.  
🔜 Next: Add navigation bar (logo + logout), polish CRUD UI.

---

## 📂 includes/rest

Contains all REST API endpoints.

- **class-rest-orders.php** → CRUD + status updates for orders
- **class-rest-products.php** → CRUD for products, vendors, categories
- **class-rest-stores.php** → CRUD for store entities
- **class-rest-ledger.php** → Budget/ledger API

✅ Status: APIs registered and working for orders, products, stores, ledger.  
🔜 Next: refine product meta (vendors/categories), unify error handling, pagination stable.

---

## 📂 includes (core logic)

- **class-activator.php** → Setup logic on plugin activation (tables, flush rules)
- **class-admin-menu-restrict.php** → Restricts WP Admin menus for shop managers
- **class-private-portal.php** → Forces shop managers into custom dashboard instead of WP-Admin
- **class-approval-workflow.php** → Approval/rejection workflow for orders
- **class-order-statuses.php** → Registers custom order statuses (Approved/Rejected/etc)
- **class-user-store-map.php** → Mapping users to stores

✅ Status: Working but needs cleanup of legacy functions.  
🔜 Next: Verify **capabilities (`wcss_manage_portal`)** are consistent across all files.

---

## 📂 docs

- `PROJECT_HUB.md` → You are here. Central project tracking file.

---

## 🗂️ Pending Tasks

- [ ] Fix pagination JS hook → load next/prev correctly
- [ ] Success message + redirect after product create
- [ ] Navigation bar with **site logo** + logout button
- [ ] Vendor dropdown + “Add new vendor” in product form
- [ ] CRUD for Stores (frontend form + REST already in place)
- [ ] Reporting (budget utilization, vendor comparison, etc)
- [ ] Final branding (colors, logo, typography)

---

## 🧹 Cleanup Notes

- Old unused functions inside `class-rest-products.php` (commented list/read versions) can be deleted once new listing is stable.
- Double enqueue in `class-routes-manager.php` (`wp_localize_script` repeated twice).
- Some junk logging (`wc_get_logger`) should be replaced with centralized error handler.
