# Business Logic — Bar & Restaurant Management System (BMS)

## 1. System Overview

BMS is a multi-branch Bar & Restaurant Management System. It supports:
- Multi-branch (tenant) architecture with branch-level data isolation
- Role-based access: Owner (all branches), Branch Manager (single branch), Staff (limited access)
- Real-time operations for POS, Kitchen Display, and Dashboard

---

## 2. Modules & Workflows

### 2.1 Dashboard

**Two variants based on role:**

| View | Access | KPIs Shown |
|------|--------|------------|
| Owner / All Branches | Owner | Revenue per branch, total sales, cross-branch analytics, aggregated inventory alerts |
| Branch Manager | Manager | Single-branch KPIs: today's sales, orders, active tables, kitchen pending, stock alerts |

**Widgets:**
- KPI cards (Today's Sales, Orders, Active Tables, Kitchen Pending, Customers, Stock Alerts)
- Top-selling items list (weekly)
- Low-stock alert widget (items below reorder level)
- Table occupancy indicator (visual bar or grid)
- Recent activity feed (orders, payments, reservations)
- Daily/Weekly charts (ApexCharts)

---

### 2.2 POS (Point of Sale)

**Order Flow:**
1. Select table (or Walk-in/Takeaway)
2. Select customer (optional)
3. Browse menu by category → tap to add to cart
4. Apply modifiers (if applicable) via modal
5. Add order notes (special instructions, allergies)
6. Apply discount (see discount rules below)
7. Hold order (parks the order, clears cart)
8. Print KOT (Kitchen Order Ticket)
9. Proceed to payment

**Discount Rules:**
- Discount ≤ 15%: applied directly
- Discount > 15%: requires manager approval (modal with PIN/confirmation)
- Discount types: Percentage or Fixed amount

**Payment Methods:**
- Cash
- Credit/Debit Card
- Mobile Wallet
- Split payment (multiple methods)

**Bill Split:**
- Split evenly (by number of guests)
- Split by item (assign items to different payments)

**Order Types:**
- Dine-in (linked to table)
- Takeaway
- Delivery
- Online

---

### 2.3 Tables

**Statuses (6 total):**

| Status | Color | Description |
|--------|-------|-------------|
| Available | Green | Table is free and ready for guests |
| Occupied | Red | Guests are seated, order in progress |
| Reserved | Yellow/Amber | Table is reserved for a future time slot |
| Cleaning | Gray/Blue | Table is being cleaned/reset |
| Merged | Blue | Two or more tables combined for a large party |
| Blocked | Gray | Table is out of service (maintenance) |

**Features:**
- Floor plan view (desktop/tablet) with drag-positioned tables
- Grid/List view (mobile) — cards with status badges
- Table detail modal: change status, view current bill, assign waiter
- Table merge interaction (select 2+ tables → merge)
- Reservation indicator with countdown on reserved tables
- New table creation form

---

### 2.4 Kitchen (KDS — Kitchen Display System)

**Ticket Statuses (4-stage flow):**

| Status | Description |
|--------|-------------|
| New (Pending) | Order just received, awaiting preparation |
| Preparing | Kitchen is actively working on the order |
| Ready | Order is complete, awaiting pickup by server |
| Delivered | Server has picked up and delivered to table |

**Features:**
- Columns: New → Preparing → Ready → Delivered (Kanban-style)
- Drag-and-drop to move tickets between stages
- Rush/Priority flag (visual indicator — red badge or border)
- Elapsed time display per ticket (color-coded: green < 5min, amber < 10min, red > 10min)
- Station columns: Kitchen / Bar / Grill (can be filtered or shown as swim lanes)
- Tablet-optimized layout (landscape priority)

---

### 2.5 Orders

**Order Statuses:**

| Status | Description |
|--------|-------------|
| Draft/Open | Order created but not yet sent to kitchen |
| Sent to Kitchen | KOT printed, awaiting preparation |
| Preparing | Kitchen is working on it |
| Ready | Food ready for pickup |
| Served | Delivered to customer's table |
| Billed | Bill generated, awaiting payment |
| Paid | Payment received |
| Closed | Order fully completed and archived |
| Voided | Order cancelled (no payment) |

**Features:**
- Filterable list by type: Dine-in, Takeaway, Delivery, Online
- Filterable by status
- Order detail view with modification history
- Reservation management (list + creation form)

---

### 2.6 Inventory

**Features:**
- Stock list with color-coded reorder indicators:
  - Green: stock ≥ reorder level
  - Amber: stock > 0 but < reorder level (low stock)
  - Red: stock = 0 (out of stock)
- Stock adjustment form:
  - Reason (damage, count correction, write-off, return)
  - Quantity adjustment (+/-)
  - Staff member recording the adjustment
- Stock movement timeline (purchased, used, adjusted, returned)
- Category filtering
- Sort by name, stock level, or value

---

### 2.7 Purchase

**Purchase Order (PO) Lifecycle:**
1. Create PO (select supplier, add items, quantities, expected delivery)
2. Submit → Status: Pending
3. Manager approval → Status: Approved
4. Supplier delivers → Create GRN (Goods Received Note)
5. GRN confirmed → Status: Received
6. Stock automatically updated

**PO Statuses:**

| Status | Description |
|--------|-------------|
| Pending | Awaiting approval |
| Approved | Approved, awaiting delivery |
| Received | Goods received and stock updated |
| Cancelled | PO cancelled |

**Features:**
- PO list with status, supplier, total, ETA
- PO creation form (supplier selection, item line items)
- GRN confirmation screen (verify received quantities vs ordered)
- Supplier management cards

---

### 2.8 Profit & Expenses

**P&L Summary View:**
- Revenue (total sales)
- Cost of Goods Sold (COGS)
- Gross Profit (Revenue - COGS)
- Operating Expenses (rent, salaries, utilities, marketing, etc.)
- Net Profit (Gross Profit - Operating Expenses)

**Expense Categories:**
- Rent
- Salaries
- Utilities
- Marketing
- Repairs
- Transport
- Other

**Expense Entry Form:**
- Description
- Category
- Amount
- Payee
- Payment method
- Date
- Recurring / One-time toggle
- Recurring frequency (weekly, monthly, quarterly)

---

### 2.9 Customers

**Loyalty Tiers:**

| Tier | Points per $1 | Benefits |
|------|---------------|----------|
| New | 1 | Welcome bonus on 3rd visit |
| Bronze | 1 | Standard earning |
| Silver | 1.05 | 5% bonus points, birthday treat |
| Gold | 1.1 | 10% bonus points, priority reservations |

**Features:**
- Customer list with search and sort (name, spending, visits, points)
- Customer profile view:
  - Contact info
  - Loyalty tier and points balance
  - Visit history (date, table, guests, total, items)
  - Favourite items
  - Notes (preferences, allergies)
- Loyalty program management

---

### 2.10 Staff

**Roles:**
- Owner
- Branch Manager
- Waiter
- Cashier
- Kitchen Staff (Chef, Sous Chef, Line Cook, Prep Cook)

**Features:**
- Staff list with role badges, status (online/break/offline)
- Shift/attendance calendar view
- Sales performance widget (orders served, tips earned, ratings)
- Role-based filtering

---

### 2.11 Reports

**Report Types:**

| Report | Key Metrics |
|--------|-------------|
| Sales | Revenue trend, payment methods breakdown, sales by day, top items |
| Inventory | Stock value trend, distribution by category, low stock alerts |
| Financial (P&L) | Income vs expenses, profit margin trend, monthly summary |
| Staff | Performance ranking, tips earned, orders served |
| Customer | Demographics, visit frequency, spending patterns, loyalty metrics |

**Features:**
- Branch selector (for multi-branch owners)
- Date range filter (today, this week, this month, custom)
- Export button (PDF/Excel — UI only in this phase)

---

### 2.12 Rooms (Lodge)

**Guest stay workflow:**
1. View room availability and select an available room.
2. Check in a walk-in guest or record a reservation with guest contact, arrival date/time, expected sign-out date/time, room, rate, guests, and deposit.
3. Calculate the guest's stay duration from check-in to actual sign-out; use the number of days for room charges.
4. Manage the in-house stay, add or remove folio charges, and record partial or full guest payments.
5. Review the balance, record the checkout payment and actual sign-out date/time, then release the room to housekeeping.
6. Mark the room ready after cleaning so it becomes available again.

**Room statuses:** Available, Occupied, Reserved, Needs cleaning, and Maintenance.

**Features:**
- Room board with occupancy summary, status filters, rates, room type, and guest/reservation details.
- Reservation list with check-in action.
- Guest stay folio with room charges, deposits, and additional charges.
- Daily guest-payment income, room expenses, and automatically calculated net room profit.
- Room expense entry for supplies, utilities, repairs, laundry, and other costs.
- Checkout summary showing days stayed, sign-out time, and remaining balance before the room enters housekeeping.
- Housekeeping queue to mark cleaned rooms ready for the next guest.
- UI-only mock data and interactions during the front-end review phase.

### 2.13 Settings

**Sections (organized as tabs or sidebar):**

| Section | Configurations |
|---------|---------------|
| Restaurant Info | Name, address, logo, tax registration |
| Branch Management | Add/edit branches, assign managers |
| Menu Management | Categories, items, modifiers, pricing |
| Tax Configuration | Tax rate, scheme (inclusive/exclusive), service charge |
| Payment Methods | Enable/disable: Cash, Card, Mobile Wallet |
| Printers | POS printer, KOT printer, receipt printer setup |
| Table/Floor Layout | Configure floor plan, table positions |
| Theme | Light/Dark mode toggle |
| Receipt Settings | Header, footer, currency, locale |

**Menu Management (per business-logic.md §4):**
- Menu categories (add/edit/reorder)
- Menu items (name, description, price, category, image, availability toggle)
- Modifiers (size, add-ons, cooking preference)
- Pricing rules (happy hour, branch-specific pricing)

---

## 3. Multi-Branch Rules

- Owner can see all branches and switch between them via branch switcher
- Branch Manager sees only their branch data
- Staff sees only their assigned branch
- Branch switcher in top nav (dropdown)
- All data (orders, inventory, staff, etc.) is scoped to selected branch

---

## 4. Status Color Convention (Consistent Across All Modules)

| Status | Badge Color | Hex |
|--------|-------------|-----|
| Available / Active | Green | #10b981 |
| Occupied / Busy | Red | #ef4444 |
| Reserved / Pending | Amber | #f59e0b |
| Cleaning / In Progress | Blue | #3b82f6 |
| Merged / Info | Purple | #8b5cf6 |
| Completed / Success | Emerald | #10b981 |
| Cancelled / Voided | Red | #ef4444 |
| Ready | Green | #10b981 |
| Draft | Gray | #6b7280 |

---

## 5. UI Design Principles

- **Responsive**: Desktop (sidebar nav) → Tablet (sidebar or bottom nav) → Mobile (bottom nav)
- **Consistent**: Same badge colors, card styles, button styles, spacing scale across all modules
- **Interactive**: Alpine.js for dropdowns, modals, tabs, collapsible panels
- **Accessible**: Proper contrast ratios, focus states, semantic HTML
