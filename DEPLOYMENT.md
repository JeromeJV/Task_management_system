# TaskTrack deployment notes

## Database connection

The application reads these process environment variables:

- `TASKTRACK_DB_HOST`
- `TASKTRACK_DB_NAME`
- `TASKTRACK_DB_USER`
- `TASKTRACK_DB_PASSWORD`
- `TASKTRACK_DB_PORT` (optional; defaults to `3306`)

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
```

Database connection failures are written to the PHP error log; the application response does not include connection credentials.

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

The account-link migration adds a unique `users.employee_id` mapping and backfills only unambiguous email matches. After applying it, an administrator should check **Admin → Account management → Employee profile** and manually link any remaining unmatched accounts to the correct employee profile. New accounts created from an employee profile are linked automatically.

The office-task review migration adds a separate review state to office tasks. Employee-submitted work stays pending until a Supervisor or HR account reviews it from the employee task assignment page. Approval marks it completed; requesting changes sends the employee's explanation back to My Tasks.

The production and delivery approval migration adds review fields and delivery assigner tracking. Production stages remain pending until the assigning Supervisor approves them; delivered shipments remain uncompleted until the assigning Supervisor approves them. A rejection asks the worker/driver to make changes and resubmit.

Task details in Employee, Production, and Logistics views show the recorded assigner and, after a decision, the reviewer who approved or requested changes and the review time. Existing assignments without a recorded assigner or review actor are shown as not recorded; the system does not infer missing names.
