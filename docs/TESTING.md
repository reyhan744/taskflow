# راهنمای تست و گزارش نتایج TaskFlow

## ۱. سه سطح تست

| سطح | ابزار | محیط لازم | نتیجهٔ ثبت‌شده |
|-----|-------|-----------|----------------|
| سینتکس | `php -l` روی همهٔ فایل‌ها | PHP | ✅ بدون خطا |
| واحد/یکپارچگی | PHPUnit 10.5 (+ شِم SQLite مستقل) | فقط PHP | ✅ **۶۱ تست / ۲۱۳ اظهار — همه سبز** |
| انتها-به-انتها | `tests/e2e.php` با WP-CLI روی وردپرس زنده | وردپرس نصب‌شده | ✅ **۵۶/۵۶ بررسی سبز + debug.log خالی** |

## ۲. اجرای تست‌های PHPUnit (بدون نیاز به وردپرس/MySQL)

تست‌ها **کلاس‌های واقعی افزونه** را روی یک پایگاه دادهٔ SQLite درون‌حافظه اجرا می‌کنند (tests/bootstrap.php شِم سازگار با `wpdb` است). هیچ وابستگی خارجی (composer/وردپرس) لازم نیست — فقط PHP 8.1+.

```bash
# ۱) دریافت PHPUnit (یک بار)
curl -sSL -o phpunit.phar https://phar.phpunit.de/phpunit-10.phar

# ۲) اجرا
php phpunit.phar -c phpunit.xml.dist
```

یا در صورت استفاده از Composer:

```bash
composer install
composer test
```

خروجی واقعی اجرای این مخزن:

```
PHPUnit 10.5.66 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.32
Configuration: phpunit.xml.dist

............................................................   60 / 60 (100%)

OK (61 tests, 213 assertions)
Time: 00:00.030, Memory: 25.04 MB
```

## ۳. پوشش تست‌ها

| فایل تست | محدوده |
|----------|--------|
| `TaskRepositoryTest` (۱۲ تست) | CRUD، جستجوی عنوان، فیلتر وضعیت/اولویت/مسئول/پروژه/overdue، مرتب‌سازی priority، **تزریق در ORDER BY (whitelist)**، resequence کانبان، مهر completed_at، sync برچسب‌ها، **idempotent بودن first_or_create** |
| `CapabilitiesTest` (۷ تست) | نگاشت نقش‌ها، اعطا/سلب نقش اضافی از تنظیمات، مالکیت (مالک/مسئول/غریبه/ویرایشگر/مدیر)، visibility_sql |
| `TaskServiceTest` (۶ تست) | ثبت فعالیت created/diff/status/deleted، اعلان + ایمیل Mock هنگام تخصیص، عدم اعلان برای خودتخصیصی |
| `MockPaymentTest` (۶ تست) | ایجاد success/failed/pending، شناسهٔ TFMOCK، finalize فقط روی pending، جمع‌ها، فیلتر و جستجو |
| `MockMailerTest` (۴ تست) | رکورد Outbox با sent_at، شبیه‌سازی Inbox + خوانده‌شدن، بدنهٔ استاندارد MOCK، mark_read اعلان‌ها |
| `SettingsTest` (۳ تست) | پیش‌فرض‌ها، whitelist و clamp و پاکسازی ورودی، سقف/کف per_page |
| `ValidationTest` (۵ تست) | وضعیت/اولویت whitelist، نرمال‌سازی تاریخ، اعتبارسنجی مبلغ/ایمیل/رنگ/id |
| `ExportImportTest` (۷ تست) | BOM CSV، ساختار JSON، ایمپورت با ساخت پروژه/برچسب/مسئول، ردیف‌های خراب، **قالب نمونهٔ round-trip** |
| `CommentsTimeTest` (۶ تست) | ذخیرهٔ نظر + شمارنده، منشن @user → اعلان + ایمیل Mock، رد نظر خالی، start/stop تایمر + جمع time_spent، توقف تایمر قبلی، فعالیت time_logged |
| `DashboardTest` (۲ تست) | شمارش‌های زنده (باز/تکمیل/عقب‌افتاده/نرخ)، رعایت دید نقش در آمار |

## ۴. تست‌های End-to-End روی وردپرس واقعی

`tests/e2e.php` با WP-CLI اجرا می‌شود و همهٔ ۲۵+ مسیر REST را با تعویض کاربر (admin/editor/author/subscriber) می‌پیماید: CRUD کامل، مجوزهای 403، جریان تخصیص تا اعلان و ایمیل Mock، Kanban، جستجو/فیلتر، تایمر، اکسپورت/ایمپورت، پرداخت Mock، تنظیمات و حذف.

```bash
# روی یک نصب وردپرس با افزونهٔ فعال‌شده:
wp user create editor editor@example.com --role=editor
wp user create author author@example.com --role=author
wp user create subscriber subscriber@example.com --role=subscriber
wp eval-file tests/e2e.php
```

نتیجهٔ واقعی روی محیط آزمایش (WordPress 6.9 + SQLite drop-in):

```
== TaskFlow E2E on live WordPress ==
  [PASS] ×۵۶ همهٔ بررسی‌ها
== RESULT: 0 failed ==
--- debug.log --- (خالی؛ بدون هیچ Warning/Notice)
```

## ۵. محیط آزمایش رسمی

| مؤلفه | نسخه |
|-------|------|
| PHP | 8.3.32 (CLI استاتیک) |
| WordPress | 6.9 |
| پایگاه دادهٔ تست E2E | SQLite از طریق افزونهٔ رسمی sqlite-database-integration 2.1.15 (همان لایهٔ wpdb) |
| PHPUnit | 10.5.66 |
| Vue.js (bundled) | 3.5.13 |

تست‌ها همچنین روی PHP 8.1/8.2 در CI (GitHub Actions — `.github/workflows/ci.yml`) اجرا می‌شوند: php -l روی همهٔ فایل‌ها + کل مجموعهٔ PHPUnit.

## ۶. سناریوهای دستی (چک‌لیست سریع ارزیاب)

۱. با admin یک وظیفه با پروژه/برچسب/مسئول/سررسید بسازید → در فهرست و Kanban ظاهر شود.
۲. کارت را در Kanban به «Completed» بکشید → بعد از Refresh سر جایش بماند (ماندگاری MySQL).
۳. با author وارد شوید → فقط وظایف خودش را ببیند؛ ویرایش وظیفهٔ دیگری با REST → 403.
۴. در داشبورد شمارش‌ها را با حذف/تکمیل وظیفه تطبیق دهید → به‌روزرسانی زنده.
۵. وظیفه را به کاربری تخصیص دهید → اعلان زنگ + ایمیل MOCK در Outbox همان کاربر.
۶. پرداخت آزمایشی pending بسازید و Finalize کنید → وضعیت و جمع‌ها تغییر کند.
۷. خروجی CSV را در Excel باز کنید → فارسی بدون به‌هم‌ریختگی (BOM).
۸. زبان پروفایل را فارسی کنید → کل رابط RTL با تقویم شمسی.
