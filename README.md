# TaskTrack — Task Management System

Ang TaskTrack ay web-based na sistema para pamahalaan ang mga empleyado, attendance, payroll, recruitment, production, at delivery. Gumagamit ito ng PHP at MySQL at maaari itong patakbuhin sa lokal na computer gamit ang XAMPP.

## Mga pangunahing bahagi

- **Dashboard at reports** — buod ng mga account at operasyon para sa administrator. Sa **Admin Dashboard** at **Admin → Reports**, maaaring pumili ng araw, kasalukuyang linggo (Lunes hanggang ngayon), huling 7 araw, kasalukuyang buwan/taon, o custom na petsa. Sakop ng napiling date range ang lahat ng dated operational totals, status breakdowns, at graphs sa dashboard: attendance date, payroll pay date, task submission date, production assignment/update timestamp, at delivery completion date (scheduled date kung hindi pa kumpleto). Ang employee profile at system account totals, pati accounts by role, ay kasalukuyang snapshot dahil walang maaasahang historical creation timestamp ang mga ito. May sariling pie chart para sa attendance statuses at bar chart para sa task/production/delivery totals ang Dashboard; hiwalay ang detalyadong report graphs at CSV export.
- **Mga account at access** — paggawa at pamamahala ng user accounts, pagtatalaga ng role, pag-enable o pag-disable ng account, pag-link ng account sa employee profile, at pamamahala ng profile picture.
- **Employee tasks** — paggawa at pagtatalaga ng office tasks, pag-update ng progress, at pagpapasa ng natapos na trabaho para sa review.
- **Attendance** — pag-record ng pagpasok at pag-uwi gamit ang Employee ID sa attendance page. Makikita ang mga record sa attendance reports; kasama sa attendance ang paid leave, unpaid leave, at half-day status.
- **Leave at payroll** — nagtatala ang HR ng face-to-face o ibang leave request para sa empleyado, kahit walang system account. Binibilang ng payroll ang eligible paid SIL, hindi binabayaran ang LWOP portion, at isinasaalang-alang ang kalahating araw.
- **Recruitment** — pagtanggap ng application at resume, pagtingin ng mga aplikante, pag-iskedyul ng interview, at pagproseso ng hiring.
- **Production** — pagtatala ng production task, pagtatalaga ng Cooking at Packaging stages, pagsubaybay sa progreso, at pag-review ng isinumiteng trabaho.
- **Logistics at delivery** — pagtatalaga ng shipment sa driver, pag-upload ng photo proof kapag minamarkahang na-deliver na, pagtingin sa delivery activity, at pag-apruba o pagpapaulit ng completion submission ng Supervisor.
- **Notifications at audit history** — mga task notification at tala ng mahahalagang system activity.

## Mga user role

| Role | Pangunahing gamit |
| --- | --- |
| Administrator | Pamahalaan ang accounts, roles, account status, employee links, reports, at system activity. |
| HR | Pamahalaan ang employee at recruitment workflows, kabilang ang application at interview processing. |
| Payroll | Gumawa at magproseso ng payroll records. |
| Supervisor | Magtalaga at mag-review ng employee, production, at delivery tasks. |
| Production | Tingnan at gawin ang production assignments. Ang Cooking at Packaging accounts ay para sa kani-kanilang production stage. |
| Logistics | Tingnan ang naka-assign na delivery at i-update ang delivery progress o completion. |
| Employee | Tingnan ang personal na office tasks, i-update ang progress, at isumite ang natapos na trabaho. |

Ang administrator ang gumagawa at nagtatalaga ng account roles. Hindi account registration page ang `register.php`; i-redirect nito ang naaangkop na administrator o HR user.

### Leave, attendance, at payroll

1. Sa **HR → Leave Management**, pumili ng empleyado, start at end date, paid SIL o unpaid leave/LWOP, at ilagay ang dahilan. Bawat weekday sa date range ay full leave day. Agad na nagiging aktibo ang HR-recorded leave; walang hiwalay na system approval.
2. Ang paid SIL ay limitado sa limang araw bawat service year at magagamit lamang kapag may hire date ang employee profile at nakumpleto na ang isang taon ng serbisyo. I-update ang hire date sa **HR → Employees → View → Edit Details** para sa mga lumang profile. Ang natitirang lagpas sa allowance ay maaaring i-record bilang LWOP.
3. Weekdays lamang ang binibilang; hindi pa isinasaalang-alang ang company holidays o ibang leave policy. Walang half-day option sa leave form; i-record ang bawat araw ng mahabang leave gamit ang date range. Sa regular attendance, bago mag-12:00 PM ang AM time-in/time-out at mula 12:00 PM ang PM time-in/time-out. Half Day ang isang kumpletong session (time-in at time-out), at kalahating araw ang bilang nito sa payroll; dalawang kumpletong session ang buong araw. Ang session na kulang ang punch ay Undertime at hindi awtomatikong binibilang bilang kalahating araw. Nananatili sa dating isang pares ng time-in/time-out ang logistics attendance.
4. Inilalagay ng system ang leave sa attendance. Hindi puwedeng mag-time-in ang empleyadong may full-day leave hangga't hindi kinakansela o itinatama ng HR ang leave.
5. Kinukuha ng bagong payroll calculation ang paid SIL mula sa HR leave records at daily rate. Hindi binabago ng payroll form ang leave amount. Nililimitahan ang bayad na trabaho at paid leave sa isang araw kada petsa; ibinabawas ng LWOP ang kaukulang bahagi ng paid workday. Ang payroll record na nagawa na bago naitala o kinansela ang leave ay hindi awtomatikong nire-recompute.
6. Hinaharang ng system ang office-task, production, at delivery assignment kung ang due/delivery date ay sakop ng aktibong leave.
7. Sa **HR → Attendance → Correct Attendance**, maaaring itama ng HR ang punch ng isang empleyado sa napiling petsa. Kailangan ang dahilan ng correction; itinatala sa audit history ang lumang at bagong punch at attendance status. Hindi maaaring baguhin ang punches na sakop ng aktibong leave.
8. Sa payroll masterlist, kinukumpirma ng Payroll user na na-review ang record bago ito markahang **Paid**. System review status lamang ito, hindi payout receipt o patunay na may aktuwal na perang nailipat. Maaaring itama ang Paid payroll record; kailangang magbigay ng dahilan at itinatala ang lumang/bagong halaga sa audit history.

### Pagwawasto at pag-verify

- Ang attendance submission ay sinusuri sa server ayon sa susunod na inaasahang punch. Pinipigilan ng per-employee/date database lock ang sabay na duplicate submission.
- Patakbuhin ang standalone regression tests mula sa project root gamit ang `php tests/attendance_calculations_test.php`. Saklaw nito ang AM/PM half-day, incomplete session, Logistics single shift, payroll work fraction, at punch sequence.
- Panatilihin ang audit history para sa HR attendance corrections, Payroll review confirmation, at paid payroll corrections. Huwag burahin o baguhin ang mga audit entry bilang bahagi ng normal na pagwawasto.

### Operational metrics para sa review

Gamitin ang sumusunod na pare-parehong depinisyon kapag nag-uulat ng operasyon; pumili ng iisang time window at iulat ang denominator:

- **Attendance exception rate** = attendance records na `Absent`, `Undertime`, o `Half Day` ÷ lahat ng attendance records sa napiling panahon. Ihiwalay ang approved leave sa denominator.
- **Payroll review completion rate** = payroll records na `Paid` (review confirmed) ÷ lahat ng payroll records na due na sa panahon. Hindi ito sukatan ng aktuwal na bank/cash payout.
- **Payroll correction rate** = audit entries na `paid_payroll_corrected` ÷ payroll records na sinubukang i-review sa parehong panahon.
- **Resume access success rate** = matagumpay na authorized resume requests ÷ lahat ng resume requests. Suriin ang Apache/PHP access logs; huwag ilagay ang pangalan o file path ng aplikante sa public metrics.
- **Recovery verification** = pinakahuling matagumpay na database-and-uploads restore drill, petsa at tagal. Ang pagkakaroon lang ng backup file ay hindi patunay na maibabalik ito.

### Backup at restore

1. Gumawa ng database dump at kopyahin ang `uploads/` directory sa parehong backup set. I-store ang mga ito sa lokasyong may access control at retention policy; huwag ilagay sa public web root.
2. I-back up bago mag-deploy ng code o schema migration. Itala ang code revision at oras ng backup.
3. Para sa aktuwal na Windows/XAMPP commands at restore verification checklist, sundin ang **Backup and restore** section ng [DEPLOYMENT.md](./DEPLOYMENT.md). Subukan muna ang restore sa hiwalay na test database at directory.

## Pag-install sa lokal na computer

### Mga kailangan

- XAMPP na may Apache, PHP, at MySQL/MariaDB
- Web browser
- PHP `mysqli` extension para sa database connection

### Fresh install

1. Ilagay ang project folder sa XAMPP `htdocs` directory, halimbawa `C:\xampp\htdocs\Task management system`.
2. Buksan ang XAMPP Control Panel at i-start ang **Apache** at **MySQL**.
3. Sa phpMyAdmin, gumawa ng database na `task_management_system`.
4. I-import ang `config/install_schema.sql` sa bagong database.
5. Itakda ang database configuration. Para sa lokal na XAMPP development, ang default ay `localhost`, database `task_management_system`, user `root`, at walang password. Ang mga setting na ito ay **hindi ligtas para sa production o network-accessible na deployment**.
6. Gumawa ng unang administrator sa pamamagitan ng CLI-only setup script. Itakda muna ang `TASKTRACK_INITIAL_ADMIN_NAME`, `TASKTRACK_INITIAL_ADMIN_EMAIL`, at `TASKTRACK_INITIAL_ADMIN_PASSWORD` bilang process environment variables, saka patakbuhin mula sa project root:

   ```text
   php config/create_initial_admin.php
   ```

   Dapat hindi bababa sa 8 character at may kahit isang numero ang admin password. Tumatakbo lamang ang bootstrap kapag walang laman ang `users` table. Alisin ang temporary environment variables pagkatapos gamitin.
7. Buksan sa browser ang `http://localhost/Task%20management%20system/` at mag-sign in gamit ang administrator account.

Para sa environment variables ng database, paggawa ng production configuration, at paglalapat ng migration sa umiiral nang database, sundin ang [DEPLOYMENT.md](./DEPLOYMENT.md). **Huwag** i-import ang install snapshot sa database na may laman na.

### Pagbabago ng umiiral na database

1. Gumawa muna ng backup ng database.
2. Alamin kung aling migrations na ang nailapat.
3. Ilapat lamang ang mga hindi pa nagagamit na file sa `config/migrations/`, ayon sa filename order.
4. Huwag ulitin ang migration at huwag ilapat ang migrations sa bagong database na ginawa na mula sa `config/install_schema.sql`; kasama na sa snapshot ang kasalukuyang schema.

## Pagbukas mula sa phone o ibang device sa parehong Wi‑Fi

1. Tiyaking tumatakbo ang Apache sa computer na may XAMPP.
2. Ikonekta sa iisang Wi‑Fi ang computer at phone.
3. Kunin ang IPv4 address ng computer gamit ang `ipconfig` sa Command Prompt. Halimbawa, kung `192.168.1.5` ang IPv4 address, buksan sa phone:

   ```text
   http://192.168.1.5/Task%20management%20system/
   ```

4. Kung hindi pa rin makakonekta, tiyaking gumagana muna sa mismong computer ang `http://localhost/Task%20management%20system/`. Payagan ang Apache sa Windows Firewall para sa Private network at tiyaking pareho ang Wi‑Fi ng dalawang device. Maaaring magbago ang IP address ng computer.

Ang LAN access na ito ay para lamang sa pinagkakatiwalaang lokal na network. Huwag ilantad ang development XAMPP server sa public internet.

## Pang-araw-araw na paggamit

1. Mag-sign in sa `index.php`; dadalhin ang user sa page para sa kaniyang role.
2. Gamitin ang navigation sa role page para buksan ang naaangkop na module.
3. Sa task modules, tingnan ang assignment at due date, i-update ang progress, at isumite ang completion. Maaaring manatiling pending ang submission hanggang aprubahan ng Supervisor, o ng HR/Supervisor para sa office tasks.
4. Mag-sign out gamit ang logout link kapag tapos na.

May hiwalay ding attendance page sa `attendance_form.php`. Ilagay ang Employee ID para tingnan at isumite ang susunod na AM o PM attendance action. Dahil walang PIN o login sa public kiosk, tiyaking ito ay nasa kontroladong lokasyon/network; Employee ID lamang ay hindi sapat na patunay kung sino ang nagpu-punch. Kung magbukas ang PM session habang bukas pa ang page, awtomatikong muling titingnan ng page ang susunod na action.

## Database at mahahalagang files

Ang install snapshot na `config/install_schema.sql` ay naglalaman ng mga pangunahing table:

`users`, `employee`, `attendance`, `payroll`, `applicant`, `supervisor`, `human_resource`, `production`, `production_assignments`, `logistics`, `delivery`, `office`, `task_notifications`, at mga activity/audit log table.

Ang leave workflow ay gumagamit din ng `employee_leave_requests` at `employee.hire_date`. Sa existing installation, ilapat ang leave migration sa deployment instructions o buksan ang HR Leave Management page para sa automatic schema setup.

- `config/connection.php` — koneksyon sa MySQL gamit ang environment variables o lokal na development defaults.
- `config/migrations/` — incremental database changes.
- `config/create_initial_admin.php` — ligtas na CLI setup para sa unang administrator.
- `config/*_API.php` — backend request handlers para sa mga module.
- `css/` — stylesheets.
- `js/` — browser-side interactions.
- `uploads/profile-pictures/` — uploaded account profile pictures; panatilihin ang `.htaccess` file nito kapag Apache ang gamit.
- `uploads/delivery-proofs/` — pribadong delivery proof photos na makikita lamang ng nakatalagang driver o Supervisor.

## Seguridad at backup

- Palitan ang default database credentials bago gamitin sa shared network o production. Gumamit ng hiwalay na MySQL account na may kinakailangang permissions lamang.
- Huwag ilagay ang database password o ibang secret sa repository.
- Gumamit ng malakas na password para sa bawat account at huwag magbahagi ng iisang login sa maraming tao.
- Limitahan ang access sa personal, attendance, payroll, at applicant information sa mga awtorisadong user.
- Regular na mag-backup ng database at mahahalagang uploaded files. Subukan ang restore procedure bago umasa sa backup.
- Ang `config/install_schema.sql` ay schema-only snapshot; hindi nito nire-restore ang aktuwal na application data.
