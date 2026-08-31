# Idea

This project is a native PHP restaurant management platform with two connected experiences:

- Admin panel for restaurant operations.
- Customer menu and ordering website.

The goal is to let a restaurant manage staff, menu, tables, inventory, invoices, and live orders from one control panel while customers order from QR codes at their tables.

## Main Concept

Each restaurant has its own data:

- Staff.
- Menu.
- Categories.
- Tables.
- Orders.
- Inventory.
- Invoices.
- Website settings.
- Activity logs.

The system is permission-based. Employees only see and edit the sections they are allowed to use.

Super admin can manage all restaurants. Restaurant employees can only manage their own restaurant.

## User Types

### Super Admin

The super admin controls the full platform.

They can:

- View all restaurants.
- Add restaurants.
- Edit restaurants.
- Delete restaurants.
- Enter a restaurant panel as the restaurant owner.

The super admin is the only user type that can see restaurant CRUD permissions.

### Restaurant Employee

Restaurant employees work inside one restaurant.

They can:

- See only sections allowed by their permissions.
- Edit only their restaurant data.
- Never change restaurant id or restaurant code.

## Permission Design

Permissions use CRUD names.

Examples:

- `orders.get`
- `orders.create`
- `orders.update`
- `orders.delete`
- `foods.get`
- `tables.update`
- `restaurant.update`
- `logs.get`

The `.get` permission controls section visibility.

Create, update, and delete permissions depend on the matching `.get` permission in the staff permissions modal.

The permission middleware protects API routes and filters output data through public-data definitions.

## Backend Structure

The backend is native PHP.

Important folders:

- `api/Controllers`
- `api/Models`
- `api/Validators`
- `api/Middleware`
- `api/Services`
- `routes`
- `database`
- `config`

Shared controller behavior belongs in:

`api/Controllers/helpers.php`

This helper contains shared functions such as:

- JSON input parsing.
- JSON responses.
- Header reading.
- Restaurant id reading.
- Permission-safe response data.
- Current employee lookup.
- Super admin checks.
- Image upload handling.
- Activity logging.
- Changed-field detection for logs.

## Admin Panel Idea

The admin panel is the main control room for restaurant work.

It includes:

- Dashboard.
- Restaurants.
- Orders.
- Foods.
- Categories.
- Tables.
- Inventory.
- Invoices.
- Staff.
- Settings.
- Activity Log.

The dashboard should show useful summaries and charts, not repeated full lists that already belong to other pages.

The header search helps users quickly jump to sections like orders, tables, staff, logs, inventory, settings, and invoices.

## Customer Website Idea

The customer website is opened from table QR codes.

QR links include:

`?r_code=restaurant_code&t_n=table_number`

The customer can:

- Choose language.
- View restaurant landing page.
- Browse menu.
- Open food details.
- Select addons.
- Add items to order.
- Submit an order for the table.
- Track order status.

The restaurant can customize the customer website from admin settings:

- Brand titles.
- Hero text.
- Descriptions.
- Hero image.
- Menu section titles.
- Colors.

## Orders Idea

Orders are table-based and live.

The staff order screen is designed for quick kitchen/cashier work:

- List orders.
- Filter by category and status.
- Open an order.
- See grouped food items.
- Update food statuses.
- Cancel part of a grouped quantity.
- Update full order status when needed.

Food status is more important than only order status because a kitchen may finish one food before another.

## Tables Idea

Tables are visual and floor-based.

Each floor has its own layout.

The table editor lets staff:

- Select a floor.
- Add tables.
- Drag tables.
- Save table positions.
- Print/show QR code per table.

Tables cannot overlap. The grid makes the layout easier to organize.

Table status is connected to orders:

- Free.
- Waiting order.
- Order done.

Payment is handled when an order-done table becomes free.

## Inventory Idea

Inventory links stock to menu sales.

The restaurant can create stock items like:

- Burger meat.
- Pepsi.
- Water.
- Cheese.

Each stock item has a unit:

- PCS.
- KGS.
- Liters.

Foods and addons consume inventory.

Example:

- Meat Burger consumes meat.
- Extra meat addon consumes more meat.
- Pepsi consumes one piece.
- Water consumes one liter.

This allows the system to reduce stock automatically when customers buy food.

Inventory also supports manual movements:

- Add stock.
- Decrease stock.
- Waste stock with reason.

Charts make stock health visible.

## Invoice Idea

Invoices connect order completion with restaurant accounting.

The system supports local invoice preview and printing.

Invoice print settings allow:

- Custom width.
- Custom height.
- Full-page mode.

Jordan JoFotara support is included for electronic invoicing.

Local testing can show and print invoices without JoFotara credentials. Real submission to JoFotara needs valid configuration.

## Activity Log Idea

The Activity Log is the restaurant audit trail.

It answers:

- Who changed something?
- What permission/action was used?
- Which record was affected?
- What fields changed?
- When did it happen?

The log is shown like a professional system console:

- Dark background.
- Light text.
- No rounded message bubbles.
- Right border color shows severity.

Examples:

- `alwalid - (ADD) Food - Added new food (Food 15)`
- `alwalid - (UPDATE) Order - Updated order food status (Order food 81)`
- `Ahmad - (UPDATE) Table - Updated the table status (Table 4)`

Clicking a log opens a modal with details.

For updates, the modal shows:

- Input name.
- Old value.
- New value.

This makes the log useful for management, debugging, and staff accountability.

## Real-Time Idea

The system uses lightweight polling for real-time behavior.

This keeps the native PHP system simple while still updating:

- Orders.
- Tables.
- Activity logs.

The UI refreshes live data without forcing full page reloads.

## Database Idea

The database setup is kept in two SQL files:

- `database/schema.sql`
- `database/seed.sql`

`schema.sql` creates the full database structure from scratch.

`seed.sql` inserts fake demo data for testing the admin panel, customer ordering, inventory, invoices, and activity logs.

Important tables/features include:

- Order food statuses.
- Payment fields.
- Inventory units, links, and movements.
- Jordan tax invoicing.
- Invoice print settings.
- Restaurant website settings.
- Activity logs.

## Design Direction

The admin panel should feel professional and operational.

Good direction:

- Clear tables.
- Compact controls.
- Useful charts.
- Clean modals.
- Fast filters.
- Permission-driven sidebar.
- Dark system-log style for audit logs.

The customer website should feel more visual and branded because customers use it directly.
