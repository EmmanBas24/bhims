# Barangay Health Inventory System (BHIS)

Local PHP/MySQL application for XAMPP.

Installation:
1. Put these files into `C:\xampp\htdocs\bhis` or a folder inside htdocs.
2. Import `database.sql` into your MySQL (phpMyAdmin) to create the `bhis` database and tables.
3. Edit `config.php` if needed (DB credentials).
4. Start Apache and MySQL in XAMPP.
5. Open
Default admin: username `admin` password `admin123`

Notes:
- Uses prepared statements and sessions.
- Minimal implementation provided: lists, add/edit/delete for inventory, issuance, user management, logs, basic reports.
- This is a local-only starter system. Customize and harden before production use.
