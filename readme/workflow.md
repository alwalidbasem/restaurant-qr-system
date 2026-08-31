# Workflow

This document explains how the restaurant management system is used from login to daily operations.

## 1. Admin Login

The admin panel login requires three fields:

- Username
- Password
- Restaurant code

The restaurant code is checked against the employee restaurant. This prevents an employee from using a valid username and password to enter the wrong restaurant control panel.

After login:

- A super admin enters the global admin area.
- A restaurant employee enters only their own restaurant area.
- If a super admin selects a restaurant, the admin panel behaves like the owner panel for that restaurant.

## 2. Super Admin Workflow

The super admin can see the Restaurants section.

Main actions:

- View all restaurants.
- Add a new restaurant.
- Edit restaurant information.
- Delete a restaurant after confirmation.
- Open a restaurant control panel as that restaurant owner.

When the super admin opens a restaurant, the URL contains the selected restaurant id. From there, all restaurant sections use that selected restaurant scope.

## 3. Restaurant Employee Workflow

Restaurant employees can only work inside their own restaurant.

The sidebar is controlled by permissions:

- If the employee has a `.get` permission, the section appears.
- If the employee does not have the permission, the section is hidden.
- If the employee manually opens a page without permission, the admin panel redirects back to an allowed page.

Employees can never change their restaurant id or restaurant code.

## 4. Staff Management

Required permissions:

- `employees.get` to view staff.
- `employees.create` to add staff.
- `employees.update` to edit staff.
- `employees.delete` to delete staff.

The staff modal includes:

- Profile image upload.
- Name.
- Username.
- Password.
- Role.
- Restaurant id.
- Description.
- Permissions accordion.

Permission behavior:

- Read permission must be checked first.
- Create, update, and delete permissions stay locked until the related `.get` permission is selected.
- Restaurant CRUD permissions are visible only to the super admin.

Passwords are hashed with:

`config/security/hash.php`

## 5. Restaurant Settings

Required permission:

`restaurant.update`

Employees with this permission can update restaurant configuration, including:

- Restaurant public details.
- Website titles.
- Hero image.
- Hero text.
- Menu titles.
- Theme colors.
- Tax and invoice settings.
- Invoice print size.

Restaurant employees cannot update restaurant id or restaurant code.

## 6. Menu Workflow

Food categories and foods have separate sections.

Categories:

- `categories.get` to view.
- `categories.create` to add.
- `categories.update` to edit.
- `categories.delete` to delete.

Foods:

- `foods.get` to view.
- `foods.create` to add foods and addons.
- `foods.update` to edit foods and addons.
- `foods.delete` to delete foods and addons.

Food images are uploaded as files, not URL-only input.

Foods can have:

- Arabic and English names.
- Arabic and English descriptions.
- Category.
- Image.
- Price.
- Tax settings.
- Addons.

## 7. Tables Workflow

Required permissions:

- `tables.get` to view tables.
- `tables.create` to add and move tables.
- `tables.update` to update table layout, status, and payment.
- `tables.delete` to delete tables.

Tables are grouped by floors.

Workflow:

- Select a floor.
- Show only tables for that floor.
- Add a table to the selected floor.
- Move tables on the grid.
- Tables cannot overlap.
- Save layout to the database.
- QR code is shown only when clicking QR code for a table.

QR code URL format:

`weburl/?r_code=restaurant_code&t_n=table_number`

## 8. Orders Workflow

Required permissions:

- `orders.get` to view orders.
- `orders.create` to create orders from admin.
- `orders.update` to update orders or order food statuses.
- `orders.delete` to delete orders.

Orders update in near real time.

Orders page layout:

- Order list/table on one side.
- Order food details on the other side.
- Filter by one or more food categories.
- Filter by status.
- Open order details by clicking an order.

Food status is updated per food item, not only for the whole order.

Example:

- 10 Coca-Cola items are grouped as quantity 10.
- If 2 are canceled, the order shows:
  - Coca-Cola, quantity 8.
  - Coca-Cola, quantity 2, canceled.

Canceled food is subtracted from the order total.

## 9. Table Status And Payment Flow

Manual table status rules:

- A table cannot be manually changed to `waiting_order`.
- If the table is `waiting_order`, it can become:
  - `order_done`
  - `free` by canceling the linked order
- If the table is `order_done`, it can only become `free` after payment.

Payment modal options:

- Cash.
- Credit card.
- Cash and credit card.

Cash payment:

- `payment_method = cash`
- `payment_status = paid`
- cash total is saved

Credit payment:

- `payment_method = credit`
- `payment_status = paid`
- credit total is saved

Cash and credit:

- Employee enters cash amount and credit amount.
- Paid total must be equal to or higher than the order total.
- If paid total is higher, the system shows the extra amount charged.

## 10. Inventory Workflow

Required permissions:

- `inventory.get` to view inventory.
- `inventory.create` to add stock items.
- `inventory.update` to edit stock, links, and movements.
- `inventory.delete` to delete stock items.

Inventory units:

- PCS
- KGS
- Liters

Inventory items can be linked to foods and addons.

Example:

- Burger meat stock is measured by KGS.
- Meat Burger consumes `0.25 KGS`.
- Extra meat addon consumes another `0.25 KGS`.
- Pepsi consumes `1 PCS`.
- Water consumes `1 Liter`.

Inventory movements:

- Add stock.
- Decrease stock.
- Waste stock with a reason.

Charts show:

- Added stock in green.
- Waste in red.
- Decreased stock in gray.
- Stock by item with warning colors based on quantity.

## 11. Invoices Workflow

Invoices are generated from finished orders.

The system supports:

- Local invoice preview.
- Print/download through browser print.
- Invoice size settings.
- Full-page invoice mode.
- Jordan JoFotara configuration.

For testing, invoices can be previewed and printed locally without JoFotara credentials. JoFotara submission requires valid client id, secret key, and tax settings.

## 12. Activity Log Workflow

Required permission:

`logs.get`

Employees with this permission can open the Activity Log section.

The log shows restaurant actions in real time.

Logged actions include:

- Restaurant updates.
- Staff create/update/delete.
- Food create/update/delete.
- Food addon create/update/delete.
- Category create/update/delete.
- Order create/update/delete.
- Order food status updates.
- Table create/update/status/delete.
- Inventory create/update/delete/movement.
- Tax and invoice settings updates.

Log behavior:

- New logs appear at the bottom.
- The page polls for new logs every few seconds.
- Scrolling up loads previous logs 25 at a time.
- Permission filter supports multiple selections.
- Staff filter supports multiple selections.
- Time filter supports last hour, 24 hours, week, month, and 3 months.

Log display format:

`Username - (UPDATE / DELETE / ADD) Permission group - Action (Entity id)`

Clicking a log opens an information modal.

For update actions, the modal shows only edited fields:

- Old value.
- New value.

For create/delete actions, the modal shows available entity information.

