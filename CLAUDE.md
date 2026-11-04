# Prompt for Claude Code (terminal) — Responsive UI Build Phase

Copy everything in the code block below into Claude Code in your project's terminal. Attach/reference `business-logic.md` in the same repo so Claude Code can read it first.

```
I'm building a multi-branch Bar & Restaurant Management System in Laravel (Blade + Tailwind CSS).
I'm currently in the UI-ONLY phase — no backend logic, migrations, or real data yet. Just build
fully responsive front-end views with mock/placeholder data hardcoded in the Blade views or a
simple PHP array/JSON fixture file, so I can review and approve the UI before we build the database
and backend logic.

First, read the file `business-logic.md` in this repo. It defines the full business logic, statuses,
and workflows for every module. Use it to decide what UI elements, states, and screens each module
needs — don't just build generic CRUD screens.

## Modules to build (each as its own set of Blade views/routes for now):
1. Dashboard
2. POS (Point of Sale)
3. Tables
4. Kitchen (KDS)
5. Orders
6. Inventory
7. Purchase
8. Profit and Expenses
9. Customers
10. Staff
11. Reports
12. Settings (include a Menu Management section here, per business-logic.md §4)

## Requirements:

### Responsiveness
- Must work cleanly on: desktop, laptop, tablet (portrait & landscape), and mobile.
- Sidebar navigation on desktop/tablet → collapses to a bottom nav bar or hamburger drawer on mobile.
- POS screen: on mobile/tablet, the "menu item grid" and "current order/cart" panel should stack
  vertically (cart becomes a collapsible bottom sheet) instead of the two-column desktop layout.
- Tables floor plan: should reflow from a grid layout on desktop to a scrollable list/card view on
  mobile, since a visual floor plan doesn't work well on small screens.
- Kitchen (KDS): station columns (Kitchen/Bar/Grill) side-by-side on desktop/tablet, but stack or
  become horizontally swipeable tabs on mobile — this screen is often used on a tablet mounted in
  the kitchen, so prioritize tablet experience here specifically.
- Use Tailwind's responsive utilities (sm:, md:, lg:, xl:) throughout — do not hardcode fixed widths
  that break on smaller viewports.

### Design system
- Before building, check /mnt/skills or any frontend-design guidance available to you for spacing,
  typography, and color palette conventions — keep one consistent design system across all 12 modules
  (same button styles, same card styles, same status-badge colors, same spacing scale).
- Use Alpine.js for interactivity (dropdowns, modals, tabs, collapsible panels) since no JS framework
  is in use yet.
- Represent every status from business-logic.md visually with consistent color-coded badges, e.g.:
  - Table status: Available (green), Occupied (red), Reserved (yellow), Cleaning (gray), Merged (blue)
  - Order status: Draft/Open, Sent to Kitchen, Preparing, Ready, Served, Billed, Paid, Closed, Voided
  - Kitchen ticket status: Pending, Preparing, Ready, Served
  - Use the SAME badge colors for the same status everywhere it appears across modules (e.g., "Ready"
    should look the same on Kitchen and on Orders).

### Module-specific UI must-haves (from business-logic.md):
- Dashboard: role-aware layout — build both an "Owner/all-branches" view and a "Branch Manager" view
  as two variants (can be a toggle for now since there's no auth yet). Include KPI cards, a top-selling
  items list, a low-stock alert widget, and a table-occupancy indicator.
- POS: build the full order flow UI — item grid with categories, cart panel, modifier selection modal,
  hold order button, split bill UI, discount entry (with a "manager approval required" modal state for
  discounts >15%), and multiple payment method selection at checkout.
- Tables: floor plan with all 6 statuses represented, a table-merge interaction, and a reservation
  indicator/countdown on reserved tables.
- Kitchen: ticket cards with the 4-stage status flow, a rush/priority flag visual, and elapsed-time
  display per ticket.
- Orders: a filterable list (by type: dine-in/takeaway/delivery/online, and by status) with an order
  detail view showing modification history.
- Inventory: stock list with reorder-level indicators (color-coded low stock), and a stock adjustment
  form UI (reason + quantity).
- Purchase: PO list, PO creation form, and a GRN (goods received) confirmation screen.
- Profit and Expenses: a P&L summary view (revenue, COGS, gross profit, operating expenses, net profit)
  and an expense entry form with recurring/one-time toggle.
- Customers: customer list/profile view showing loyalty tier/points and order history.
- Staff: staff list with role badges, a shift/attendance view, and a simple sales-performance widget.
- Reports: a reports landing page with report-type cards (Sales, Inventory, Financial, Staff, Customer),
  each linking to a filterable report view (branch + date range filters) with an export button
  (PDF/Excel — UI only, doesn't need to actually export yet).
- Settings: branch management, a Menu Management sub-section (categories/items/modifiers/pricing),
  tax config, payment methods, printers, and table/floor layout config — organize as tabs or a
  settings sidebar.

### Multi-branch UI
- Include a branch switcher in the top nav (dropdown) since this is multi-tenant — assume the logged-in
  user might have access to multiple branches or just one, and the UI should handle both cases.

### What NOT to do in this phase
- Do not create database migrations, models, or controllers with real logic yet.
- Do not wire up authentication yet — just design for role-based views using mock "current role" data.
- Do not worry about real-time updates (WebSockets) yet — just design the KDS/Dashboard screens as if
  they were real-time (we'll wire that up in the backend phase).

Please start by proposing the folder/component structure for the Blade views (e.g., layouts,
reusable components like status badges, KPI cards, modals), then build module by module. Show me
the plan first before generating all the files.
```
