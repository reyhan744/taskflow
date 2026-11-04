# مستند معماری TaskFlow

نسخه: 1.0.0 · آخرین به‌روزرسانی: ۱۴۰۵/۰۸/۱۳

## ۱. نمای کلی

TaskFlow یک افزونهٔ مستقل مدیریت وظایف تیمی است که کاملًا از قالب مستقل است و برای امکانات اصلی به Zephyr/Impreza یا افزونهٔ دیگری وابسته نیست. تمام منطق وظایف، کنترل دسترسی و ذخیره‌سازی در سمت سرور (PHP + MySQL از طریق `wpdb`) انجام می‌شود و رابط Vue.js فقط یک مصرف‌کنندهٔ امنِ REST API است.

```
┌─────────────────────────── پیشخوان وردپرس ───────────────────────────┐
│  Vue.js 3 SPA (بدون Build)                                           │
│  نماها: داشبورد · وظایف · Kanban · پروژه‌ها · تاریخچه                 │
│         پرداخت (Mock) · ایمیل (Mock) · تنظیمات                        │
└──────────────────────────────┬───────────────────────────────────────┘
                               │  fetch + X-WP-Nonce (Cookie Auth)
┌──────────────────────────────▼───────────────────────────────────────┐
│  REST API — فضای‌نام taskflow/v1                                     │
│  Controllers: Rest_Tasks · Rest_Admin · Rest_Mocks                   │
│  هر مسیر: permission_callback (قابلیت + مالکیت)                      │
└──────────────────────────────┬───────────────────────────────────────┘
                               │
┌──────────────────────────────▼───────────────────────────────────────┐
│  لایهٔ سرویس (منطق کسب‌وکار)                                          │
│  Task_Service · Dashboard · Comments · Time_Tracker                  │
│  Mock_Payment (Adapter) · Mock_Mailer (Adapter)                      │
└──────────────────────────────┬───────────────────────────────────────┘
                               │
┌──────────────────────────────▼───────────────────────────────────────┐
│  لایهٔ مخزن (SQL خالص)                                                │
│  Task_Repository · Project_Repository · Tag_Repository               │
│  Activity · Notifications — همه با $wpdb->prepare()                  │
└──────────────────────────────┬───────────────────────────────────────┘
                               │
┌──────────────────────────────▼───────────────────────────────────────┐
│  MySQL — ۱۱ جدول اختصاصی با پیشوند {prefix}taskflow_                 │
└───────────────────────────────────────────────────────────────────────┘
```

**چرا این لایه‌بندی؟** چالش، جداسازی «منطق وظایف / ذخیره‌سازی / API / سرویس‌های Mock» و امکان تعویض Mock با سرویس واقعی را الزامی کرده است. با این ساختار:

- کنترلرهای REST نازک هستند (فقط دریافت ورودی، مجوز، پاسخ).
- سرویس‌ها منطق را نگه می‌دارند (اعتبارسنجی، تاریخچه، اعلان، ایمیل) و در تست‌ها با تزریق وابستگی قابل تعویض‌اند (سازندهٔ `TaskFlow_Task_Service` هر چهار وابستگی را تزریق می‌پذیرد).
- مخزن‌ها فقط SQL می‌دانند و هیچ چیز دیگری — تست‌پذیری مستقیم با شِم SQLite بدون وردپرس.
- Mock‌ها یک قرارداد (Contract) مشخص دارند: `create()/finalize()/find()/all()` برای پرداخت و `send()/outbox()/inbox()` برای ایمیل. یک درگاه واقعی فقط باید همین متدها را پیاده‌سازی و جایگزین شود؛ هیچ کد دیگری تغییر نمی‌کند.

## ۲. تصمیم‌های کلیدی طراحی

### ۲-۱. جدول اختصاصی به‌جای Custom Post Type

وظایف یک دامنهٔ ساخت‌یافته با روابط چند‌به‌چند (برچسب)، مرتب‌سازی Kanban، شمارش‌های تجمعی و فیلترهای مرکب هستند. CPT + meta برای این کوئری‌ها ناکارآمد و شکننده است (`meta_query` تودرتو). جدول اختصاصی ایندکس‌های واقعی (`status`, `priority`, `assignee_id`, `due_date`, ...) و JOIN تمیز می‌دهد و همچنان از `wpdb` + `prepare` استفاده می‌کند — دقیقاً طبق الزام «MySQL از طریق wpdb».

### ۲-۲. امنیت در سه لایه

1. **مسیر:** هر مسیر REST یک `permission_callback` دارد که قابلیت لازم (`taskflow_manage` / `taskflow_edit_others_tasks` / `taskflow_edit_tasks`) و در صورت نیاز مالکیت وظیفه را بررسی می‌کند. فراخوانی بدون Nonce معتبر توسط هستهٔ وردپرس رد می‌شود.
2. **کوئری:** کاربران غیرممتاز هرگز «همه» را از API نمی‌گیرند؛ محدودیت دید مستقیماً در SQL تزریق می‌شود (`TaskFlow_Capabilities::visibility_sql()` → `AND ( created_by = me OR assignee_id = me )`). به‌روزرسانی/حذف تک‌رکوردی نیز قبل از عملیات مالکیت را بازبینی می‌کند.
3. **ورودی/خروجی:** همهٔ ورودی‌ها با کلاس `TaskFlow_Validation` (whitelist وضعیت/اولویت، نرمال‌سازی تاریخ، `absint`، `sanitize_*`) پاک می‌شوند؛ ORDER BY فقط از ستون‌های whitelist؛ خروجی SPA به‌صورت پیش‌فرض توسط Vue escape می‌شود و هیچ `v-html` در پروژه وجود ندارد.

### ۲-۳. Kanban و position

هر ستون (وضعیت) موقعیت‌های پیوستهٔ `0..n-1` دارد. پس از هر جابه‌جایی، ستون مقصد دوباره ترتیب‌دهی می‌شود (`resequence`)؛ نتیجه: بدون رقابت شماره‌ها، بدون gap. عبور به «تکمیل‌شده» `completed_at` را مهر می‌زند و بازگشت، پاکش می‌کند.

### ۲-۴. تاریخچه و اعلان‌ها به‌صورت عوارض جانبی سرویس

هر تغییر معنادار از طریق `TaskFlow_Activity::log()` با کاربر، زمان، نوع رویداد و پیامِ از پیش ترجمه‌شده ثبت می‌شود؛ اعلان‌ها و ایمیل‌های Mock نیز از همان نقطه صادر می‌شوند (`notify_assignment`, `notify_status_change`). این یعنی تاریخچه و اعلان‌ها هرگز از مسیر UI رد نمی‌شوند و برای هر کلاینتی (حتی مستقیم روی REST) برقرارند.

### ۲-۵. Mock و مرز «MOCK/DEMO»

- درگاه پرداخت Mock: ایجاد تراکنش با نتیجهٔ انتخابی (success/failed/pending)، شناسهٔ درگاه `TFMOCK-xxxx`، نهایی‌سازی تراکنش‌های pending، جمع‌های وضعیت. هیچ پول، کارت یا سرویس بیرونی.
- ایمیل Mock: ارسال فقط رکورد Outbox می‌سازد (`status=sent`, `sent_at`)؛ ایمیل ورودی با فرم شبیه‌سازی به Inbox می‌رود. فیلتر `taskflow_mock_mailer_passthrough` برای آیندهٔ واقعی‌سازی وجود دارد ولی به‌صورت پیش‌فرض خاموش است.
- همهٔ رکوردها، پیام‌ها و نوارهای UI این دو بخش با عبارت صریح **MOCK/DEMO** مشخص‌اند تا با سرویس واقعی اشتباه گرفته نشوند.

### ۲-۶. فرانت‌اند بدون Build

Vue.js 3 (نسخهٔ کامل همراه کامپایلر تمپلیت: `vue.global.prod.js`) داخل بسته جاسازی شده است؛ نصب روی هر هاستی بدون Node/npm انجام می‌شود. ماژول‌ها با `wp_enqueue_script` به‌ترتیب وابستگی لود می‌شوند و پیکربندی (REST URL، Nonce، کاربر، مجوزها، تنظیمات و دیکشنری ترجمه) به‌صورت امن داخل صفحه تزریق می‌گردد (`window.TaskFlowBoot`). ترجمه سمت PHP انجام و به SPA منتقل می‌شود؛ در نتیجه یک منبع حقیقت برای رشته‌ها داریم. RTL به‌کمک CSS Logical Properties خودکار است و تاریخ‌ها با `Intl.DateTimeFormat` و تقویم شمسی نمایش داده می‌شوند.

## ۳. مدل داده

| جدول | ستون‌های کلیدی | نقش |
|------|----------------|-----|
| `taskflow_tasks` | title, description, status, priority, due_date, project_id, assignee_id, created_by, position, time_spent, comments_count, completed_at | وظایف |
| `taskflow_projects` | name, description, color, created_by | پروژه‌ها |
| `taskflow_tags` | name, slug (UNIQUE), color | برچسب‌ها |
| `taskflow_task_tags` | task_id, tag_id (UNIQUE ترکیبی) | رابط چند‌به‌چند |
| `taskflow_activity` | task_id, user_id, type, meta (JSON), message | تاریخچه |
| `taskflow_comments` | task_id, user_id, content, mentions (JSON) | نظرات (خلاقانه) |
| `taskflow_notifications` | user_id, task_id, type, message, is_read | اعلان‌ها |
| `taskflow_email_outbox` | to_email, subject, body, status, sent_at | ایمیل خروجی Mock |
| `taskflow_email_inbox` | from_email, from_name, subject, body, is_read | ایمیل ورودی Mock |
| `taskflow_payments` | order_ref, amount, currency, status, gateway_ref, message | تراکنش‌های Mock |
| `taskflow_time_logs` | task_id, user_id, started_at, ended_at, duration | ثبت زمان (خلاقانه) |

- نصب با `dbDelta` و ثبت `taskflow_db_version` برای ارتقاهای آینده.
- **رفتار حذف افزونه (مستندشده و قابل تنظیم):** پیش‌فرض داده‌ها باقی می‌مانند (نصب مجدد بدون از دست دادن تاریخچه). اگر مدیر گزینهٔ «حذف کامل داده‌ها» را فعال کند، `uninstall.php` تمام جدول‌ها + گزینه‌ها + قابلیت‌های نقش‌ها را پاک می‌کند.

## ۴. نقشهٔ REST API (`taskflow/v1`)

| متد و مسیر | مجوز لازم | شرح |
|------------|-----------|-----|
| GET /context | edit | بوت‌استرپ SPA (کاربر، مجوزها، تنظیمات) |
| GET /tasks | edit | فهرست + جستجو/فیلتر/مرتب‌سازی/صفحه‌بندی (دید نقش‌محور) |
| POST /tasks | edit | ایجاد وظیفه (+tag_ids) |
| GET /tasks/{id} | view-task | جزئیات + تاریخچه + نظرات + زمان‌ها |
| PUT /tasks/{id} | edit-task | ویرایش جزئی (مالک/ویرایشگر/مدیر) |
| DELETE /tasks/{id} | edit-task | حذف (+پاک‌سازی وابسته‌ها) |
| POST /tasks/{id}/status | change-status | جابه‌جایی Kanban (مسئول هم مجاز است) |
| GET/POST /tasks/{id}/comments | view/change-status | نظرات و منشن |
| GET /tasks/{id}/time · POST /time/start · /time/stop · GET /time/active | view/change-status/edit | تایمر ثبت زمان |
| GET /projects · POST/PUT/DELETE | edit / edit-others | پروژه‌ها |
| GET /tags · POST/PUT/DELETE | edit / edit-others | برچسب‌ها |
| GET /users | edit | کاربران قابل تخصیص |
| GET /dashboard | edit | آمار زنده |
| GET /activity | edit | تاریخچه (غیرممتاز فقط رویدادهای خودش) |
| GET /notifications · POST /notifications/read | edit | اعلان‌ها (فقط مالک) |
| GET/POST /settings | manage | تنظیمات |
| GET /export · POST /import · GET /import/sample | manage | خروجی/ورود داده |
| POST /seed · GET /system | manage | دادهٔ نمایشی / اطلاعات سیستم |
| GET/POST /mock/payments · POST /mock/payments/{id}/finalize | manage | پرداخت Mock |
| GET /mock/email/outbox · POST /mock/email/send | manage یا edit-others | ایمیل خروجی Mock |
| GET/POST /mock/email/inbox · POST /mock/email/inbox/{id}/read | manage یا edit-others | ایمیل ورودی Mock |

## ۵. جریان‌های مهم

**ایجاد وظیفه:** `POST /tasks` → اعتبارسنجی → `Task_Service::create_task` → insert + sync برچسب + resequence → فعالیت «created» → اگر مسئول تعیین شده: اعلان + ایمیل Mock → پاسخ 201 با وظیفهٔ تزئین‌شده (پروژه، مسئول، سازنده، برچسب‌ها، `can_edit` و ...).

**جابه‌جایی Kanban:** Drag&Drop → `POST /tasks/{id}/status` → `perm_change_status` (مالک یا مسئول یا ویرایشگر) → `move_task` → مهر `completed_at` + resequence → فعالیت `status_changed` → اعلان طرف مقابل → به‌روزرسانی بهینه در UI.

**پرداخت آزمایشی:** فرم → `POST /mock/payments {amount, simulate}` → رکورد با وضعیت انتخابی → برای pending دو دکمهٔ نهایی‌سازی (`/finalize`) → فعالیت `mock_payment`. همه‌جا برچسب MOCK.

## ۶. تست‌پذیری

- `tests/bootstrap.php` یک شِم `wpdb` روی SQLite + استاب‌های حداقلی وردپرس (options، کاربران/نقش‌ها، i18n) فراهم می‌کند؛ در نتیجه **همان کلاس‌های واقعی افزونه** در تست‌ها اجرا می‌شوند و SQL و رفتار مجوزها واقعاً پوشش داده می‌شوند.
- `TaskFlow_Schema::get_sqlite_schema()` معادل DDL آزمایشی است (فقط برای تست).
- تست‌های E2E (`tests/e2e.php`) روی وردپرس زنده از `rest_get_server()->dispatch()` استفاده می‌کنند و کل مسیر HTTP-LESS را با تعویض کاربر می‌پیمایند.

## ۷. نقشهٔ فایل‌ها

```
taskflow.php                         بوت‌لودر: ثابت‌ها، autoloader، هوک‌ها
uninstall.php                        حذف انتخابی داده‌ها
includes/
  class-taskflow-schema.php          DDL + نصب/حذف جدول‌ها
  class-taskflow-activator.php       فعال‌سازی/غیرفعال‌سازی
  class-taskflow-capabilities.php    قابلیت‌ها + مالکیت + visibility_sql
  class-taskflow-validation.php      پاکسازی/اعتبارسنجی متمرکز
  class-taskflow-settings.php        تنظیمات با whitelist
  class-taskflow-task-repository.php SQL وظایف
  class-taskflow-project-repository.php · class-taskflow-tag-repository.php
  class-taskflow-task-service.php    منطق وظایف + عوارض جانبی
  class-taskflow-activity.php        ثبت/خواندن تاریخچه
  class-taskflow-notifications.php   اعلان‌های درون‌افزونه‌ای
  class-taskflow-mock-payment.php    Adapter درگاه Mock
  class-taskflow-mock-mailer.php     Adapter ایمیل Mock
  class-taskflow-dashboard.php       آمار داشبورد
  class-taskflow-exporter.php        CSV/JSON
  class-taskflow-importer.php        ایمپورت CSV
  class-taskflow-comments.php        نظرات + منشن
  class-taskflow-time-tracker.php    تایمر
  class-taskflow-rest*.php           کنترلرهای REST (Tasks/Admin/Mocks + Utils)
  class-taskflow-assets.php          منو + enqueue + تزریق بوت‌استرپ
  class-taskflow-i18n.php            دیکشنری رشته‌های SPA
assets/js/*.js                       i18n · api · store · ui · views · app
assets/css/taskflow.css              استایل SPA
assets/vendor/vue.global.prod.js     Vue 3.5.13
templates/admin-app*.php             قالب‌های x-template
languages/                           taskflow.pot · fa_IR po/mo
tests/                               PHPUnit + e2e
```
