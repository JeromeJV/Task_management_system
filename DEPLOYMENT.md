# TaskTrack deployment notes

## Database connection

The application reads these process environment variables:

- `TASKTRACK_DB_HOST`
- `TASKTRACK_DB_NAME`
- `TASKTRACK_DB_USER`
- `TASKTRACK_DB_PASSWORD`
- `TASKTRACK_DB_PORT` (optional; defaults to `3306`)
- `TASKTRACK_SMTP_HOST` (optional; defaults to `smtp.gmail.com`)
- `TASKTRACK_SMTP_PORT` (optional; defaults to `587`)
- `TASKTRACK_SMTP_USERNAME`
- `TASKTRACK_SMTP_PASSWORD`
- `TASKTRACK_SMTP_FROM` (optional; defaults to the configured SMTP username)
- `TASKTRACK_BASE_URL` (recommended; public base URL used in password setup/reset links)

## Appearance preference

Attendance, application, task-entry, resume-view, and authenticated role pages provide a compact light/dark mode switch; the login page does not. The selection is saved in that browser's local storage, so it follows the browser on the same device but is not synced between devices or accounts. No database migration is needed.

For a local XAMPP development install, the existing `localhost` / `root` / empty-password / `task_management_system` defaults remain available. Do not use those defaults in a network-accessible or production deployment. Configure a dedicated MySQL user with only the permissions the application needs, set the environment variables in the web server process, and keep the password out of the repository.

For Apache, configure the variables in the relevant VirtualHost or server environment, then restart Apache. For example, replace the values with the deployment's actual settings:

```apache
SetEnv TASKTRACK_DB_HOST 127.0.0.1
SetEnv TASKTRACK_DB_NAME task_management_system
SetEnv TASKTRACK_DB_USER tasktrack_app
SetEnv TASKTRACK_DB_PASSWORD replace-with-a-secret
SetEnv TASKTRACK_DB_PORT 3306
SetEnv TASKTRACK_SMTP_HOST smtp.gmail.com
SetEnv TASKTRACK_SMTP_PORT 587
SetEnv TASKTRACK_SMTP_USERNAME replace-with-mailbox
SetEnv TASKTRACK_SMTP_PASSWORD replace-with-a-secret
SetEnv TASKTRACK_SMTP_FROM replace-with-mailbox
SetEnv TASKTRACK_BASE_URL https://tasktrack.example.com
```

Use the mail provider's recommended SMTP credentials (for example, a dedicated app password where supported) rather than an interactive account password.

Database connection failures are written to the PHP error log; the application response does not include connection credentials.

Applicant status emails, password setup/reset emails, and office-task review emails require the SMTP username and password in the Apache process environment. A task completion submission emails its assigned supervisor; a review decision emails the linked employee account. In-app notifications remain available if SMTP delivery fails, and the user who triggered the event is shown a delivery warning. Do not put credentials in PHP source or commit them. If an SMTP credential was ever present in source control or shared outside the server, revoke/rotate it with the mail provider and configure the replacement only in the server environment.

In **Admin → Account management**, an administrator can update another account's email from its linked employee profile using **Use profile email**, or set/replace its password using **Set password**. Setting a password also clears any pending setup/reset link and login lockout. When creating an account, leave both initial-password fields blank to send a one-time setup link, or provide and confirm a password of at least eight characters to create it without email delivery. The manual-password option is intended for controlled test accounts; use the setup link for normal employee accounts, and share any test password through a private channel.

## Database installation and migrations

### Fresh installation

1. Create an empty MySQL database.
2. Import `config/install_schema.sql` into that database.
3. Configure the database environment variables above.
4. Set `TASKTRACK_INITIAL_ADMIN_NAME`, `TASKTRACK_INITIAL_ADMIN_EMAIL`, and `TASKTRACK_INITIAL_ADMIN_PASSWORD` in the local process environment, then run `php config/create_initial_admin.php` from the project root. Remove those temporary variables afterward.

The install snapshot includes the current schema, including account profile pictures, the employee-account link, and task completion-review fields. It is schema-only and was generated from the current local TaskTrack database. Review it for deployment-specific requirements before using it in another environment; it is not a substitute for environment-specific configuration or production data.

Do not apply the incremental migrations to a database that has already been created from this snapshot: those changes are already included.
The CLI-only bootstrap refuses to create an account unless the `users` table is empty, and does not accept credentials through command-line arguments or write them to the repository.

### Existing installation

Back up the database first. Apply each unapplied migration in `config/migrations/` in filename order, and confirm that the existing database is based on the application's matching schema. Do not apply a migration twice.

The 2026-10-14 workflow migrations:

1. `config/migrations/20261014_add_office_task_completion_review.sql`
2. `config/migrations/20261014_add_supervisor_approval_for_production_and_delivery.sql`
3. `config/migrations/20261014_add_user_employee_link.sql`

The 2026-10-14 account profile-picture migration:

4. `config/migrations/20261014_add_user_profile_picture.sql`

This adds an optional profile-picture filename to each user account. Administrators can upload JPG, PNG, or WebP images (up to 2 MB) from **Admin → Account management**. Uploaded images are stored under `uploads/profile-pictures/`; retain the `.htaccess` file in that directory when deploying to Apache.

The 2026-10-15 delivery-proof migration:

5. `config/migrations/20261015_add_delivery_proof_image.sql`

This adds an optional delivery-proof image filename to delivery records. Logistics users must attach a JPG, PNG, or WebP photo (up to 5 MB and 6000 × 6000 pixels) when submitting a delivery for supervisor approval. Images are saved under `uploads/delivery-proofs/`; retain its `.htaccess` file when deploying to Apache. Supervisors can view the proof in the delivery review dialog. The image endpoint checks the active account and assigned supervisor or driver before serving a photo. On an existing XAMPP installation, the delivery schema initializer adds this column automatically before the page queries it; otherwise apply the migration manually in phpMyAdmin. The database account needs permission to alter the `delivery` table for automatic setup.

The account-link migration adds a unique `users.employee_id` mapping and backfills only unambiguous email matches. After applying it, an administrator should check **Admin → Account management → Employee profile** and manually link any remaining unmatched accounts to the correct employee profile. New accounts created from an employee profile are linked automatically.

The office-task review migration adds a separate review state to office tasks. Employee-submitted work stays pending until a Supervisor or HR account reviews it from the employee task assignment page. Approval marks it completed; requesting changes sends the employee's explanation back to My Tasks.

The production and delivery approval migration adds review fields and delivery assigner tracking. Production stages remain pending until the assigning Supervisor approves them; delivered shipments remain uncompleted until the assigning Supervisor approves them. A rejection asks the worker/driver to make changes and resubmit. Delivery proof photos are added separately by the 2026-10-15 migration above.

Task details in Employee, Production, and Logistics views show the recorded assigner and, after a decision, the reviewer who approved or requested changes and the review time. Existing assignments without a recorded assigner or review actor are shown as not recorded; the system does not infer missing names.

The leave-management migration, `config/migrations/20261015_add_employee_leave_records.sql`, adds an employee hire date, paid/unpaid leave attendance statuses, and the `employee_leave_requests` table. Apply it after the other 2026-10-15 migration. HR can record leave for any employee profile, including employees without an account. The default paid SIL policy allows up to five weekdays per service year after one year of service; HR must populate legacy employees' hire dates in **HR → Employees → View → Edit Details** before recording paid SIL. The daily attendance roster and automatic Absent finalization use the hire date, so employees with a future hire date are excluded until their start date; employees with no recorded hire date retain the existing roster behavior. Leave requests cover full weekdays in the selected date range; weekends are excluded and there is no company-holiday calendar or partial-day leave option. For regular (non-logistics) attendance, the AM session is selected before 12:00 PM and the PM session from 12:00 PM; a complete time-in/time-out pair earns half a day, and both complete pairs earn a full day. An incomplete pair is marked Undertime and does not count as a half day in payroll. Logistics attendance retains its existing single-shift punch flow. The system does not manage a separate government benefit claim. Payroll reads paid SIL and LWOP records automatically, and office, production, and delivery assignments are checked against active leave dates.

The public attendance kiosk uses Employee ID without a second factor. Restrict access to a controlled kiosk location/network; an Employee ID alone does not verify who is submitting a punch.

The HR, attendance, payroll, office-task, production, and delivery pages also initialize this schema automatically on existing installations. If the automatic initializer has already added the hire-date column/status values and created the leave table, do not apply the migration afterward. The MySQL application account needs permission to add the employee hire-date column, modify the attendance status enum, and create the leave table; otherwise apply the migration manually with an appropriately privileged database account before using those pages. Payroll records created before a leave change are stored snapshots and are not automatically recalculated.

## Backup and restore

### Backup

1. Pause writes or take the backup during a quiet maintenance window so the database and uploaded files represent a consistent point in time.
2. From PowerShell, run `mysqldump` from the XAMPP MySQL `bin` directory or add that directory to `PATH`. Provide the database password only at the interactive prompt:

   ```powershell
   mysqldump --host=127.0.0.1 --user=tasktrack_backup --password --single-transaction --routines --triggers task_management_system > tasktrack-db-backup.sql
   ```

3. Copy the entire `uploads/` directory into a separate, access-controlled backup location. It contains applicant resumes and other private files; do not put backups under the web root.
4. Protect database and file backups with restricted permissions and encryption at rest. Keep a retention schedule and record the backup timestamp and deployed code revision.

### Restore and verification

1. Restore to a separate test database first. Create an empty database with the expected character set, then import the dump:

   ```powershell
   mysql --host=127.0.0.1 --user=tasktrack_restore -p task_management_system_restore < tasktrack-db-backup.sql
   ```

2. Restore the matching `uploads/` backup into the test deployment's `uploads/` directory. Confirm that `uploads/.htaccess` is present and denies direct HTTP access.
3. Configure the test deployment's database and SMTP environment variables; do not reuse public or production credentials in a test environment.
4. Sign in with a test account and verify employee, attendance, payroll, applicant and resume records, plus the audit history. Make an authorized resume request through `resume.php`; verify direct `/uploads/<file>` requests are denied.
5. Run `php tests/attendance_calculations_test.php`, inspect the PHP/Apache/MySQL logs, and confirm the application can read the restored files and database. Record the restore date, duration, and result.
6. Only after the test restore succeeds should an approved production restore be performed. Coordinate downtime, preserve a pre-restore backup, and verify the deployed code/schema compatibility before reopening access.

The backup account needs only the privileges required to read the schema/data and dump triggers/routines; use a separately controlled restore account for imports. Never test restore commands against the live database.

## Regression checks and operational measures

Run `php tests/attendance_calculations_test.php` after attendance calculation changes. Before release, lint every modified PHP file with `php -l` and run `git diff --check`.

For operational reporting, use the definitions in the README and state each reporting window and denominator. In particular, `Paid` is the Payroll user's review confirmation status; it is not evidence of an external payout. Track recovery verification by completing a database-and-uploads restore drill rather than only checking that backup files exist.

Resume documents are served through the authenticated `resume.php` endpoint for HR accounts. Keep the root `uploads/.htaccess` access-deny rule on Apache deployments; the endpoint supports PDF preview and protected downloads for supported document formats.
