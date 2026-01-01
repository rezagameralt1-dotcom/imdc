Chat Identifier:

IMDC — M05.9

Project Snapshot — Version: v12

1. وضعیت پروژه تا این لحظه

پروژه IMDC (Immanent Digital City) با استاندارد Level 9 (Production-Ready) به‌صورت تجمعی توسعه یافته و این Snapshot مرجع نهایی (Source of Truth) کل تاریخچه است.

معماری کلی پروژه:

Backend: Laravel 12 / PHP 8.2

Multi-DB PostgreSQL (core, products, orders, inventory, nfts)

Redis 7

Docker Compose + Nginx

API-First با پاسخ استاندارد { success, data | error, trace_id }

Milestoneهای قبلی:

M00: Repo + Docker + ENV Skeleton (قفل شده)

M01: Auth (Sanctum, Login/Register/Logout/Me) (قفل شده)

M02: Roles & Permissions (Spatie RBAC، Guardrailها) (قفل شده)

M03: Marketplace Base (Products, Orders, Inventory, Accounting Sync) (قفل شده)

M04: NFT Ownership (ERC-721، Transfer، WORM Log) (قفل شده)

Milestone M05 (DID / Linking):

پیاده‌سازی کامل DID

پیاده‌سازی Linking بین DID ↔ Order ↔ NFT

Idempotency برای تمام عملیات Linking

Guardrail مستقل برای Linking با Feature Flag (FEATURE_LINKING)

Route-level RBAC فقط با Role (بدون وابستگی به guard_name)

Controllerهای Query برای:

GET by DID

GET by Order

GET by NFT

Service Layer برای Create و Query Linking با Validation سخت‌گیرانه UUID

Logging رویدادهای Linking به‌صورت Append-Only

Scripts رسمی Verify:

verify-rbac.sh

verify-marketplace.sh

verify-linking.sh

verify-lock.sh

تمام Verify Scriptها در حالت پایدار PASS شده‌اند

اشکال شناسایی‌شده و تثبیت‌شده:

مشکل خروجی verify-rbac.sh به‌دلیل read روی EOF تحت set -e شناسایی شد

تصمیم تثبیت شد که این رفتار به‌عنوان باگ اسکریپتی در M05 ثبت و قفل شود

Branch پایدار:

pr/m05-linking

Working tree پاک و همگام با remote

Snapshotهای مستندات:

PROJECT_SNAPSHOT.md

PR_M04.md

M04-NFT.md
به‌روزرسانی و همگام با وضعیت فعلی پروژه هستند

2. وضعیت فعلی پروژه

پروژه در انتهای Milestone M05 قرار دارد

شواهد:

تمام Verify Scriptهای رسمی اجرا و PASS شده‌اند

Linking به‌صورت عملی ایجاد، خواندن و Idempotency را با موفقیت انجام می‌دهد

Guardrailها (RBAC، Marketplace، NFT، Linking، Lock) همگی پایدار هستند

Milestone هنوز به عدد صحیح بعدی ارتقا داده نشده زیرا:

M05 به‌صورت رسمی در Snapshot به‌عنوان «نزدیک به تکمیل نهایی» (اعشاری) ثبت شده است

3. مرحله بعدی

مرحله بعدی: M06 — DAO / Voting

دلیل:

زیرساخت هویت (User + DID) و مالکیت (NFT + Order) اکنون تثبیت شده‌اند

DAO نیازمند هویت، نقش و اتصال دامنه‌ای است که همگی آماده‌اند

پیش‌نیازها:

استفاده از DID به‌عنوان Identity رأی‌دهنده

استفاده از RBAC موجود

ثبت رویدادها به‌صورت Append-Only مشابه Linking

4. مسیر کامل تا پایان پروژه

M06 — DAO / Voting

Voting Proposals

Multi-role Voting

Guardrail و Idempotency

خروجی: DAO عملیاتی

M07 — Pharma Advisor

ماژول مشاوره دارویی با Disclaimer

خروجی: API پایدار + Guardrail

M08 — VR / 3D + Map

اتصال داده‌ها به مکان و NFT

خروجی: لایه فضایی شهر

M09 — Training & Skill NFT

Courses، Enrollment، Skill NFT

خروجی: سیستم آموزش رسمی

M10 — Reports / Admin Panel

داشبورد مدیریتی

Audit Logs

M11 — Final Security Audit & Packaging

Audit نهایی

ZIP نهایی

PDF فارسی راهنما

قفل کامل پروژه

5. قوانین اجرایی اجباری برای چت‌های بعدی

توضیح، تحلیل یا تفسیر ممنوع

هرگونه ویرایش کد از طریق ترمینال ممنوع

هر تغییر کد فقط از طریق پرامپت دقیق برای Cursor

اگر نیاز به بررسی است فقط دستور تست ترمینالی

خروجی‌ها باید به‌ترتیب اولویت اجرا ارائه شوند

در پایان هر چت:

الزاماً Snapshot جدید

با Chat Identifier و Version جدید

6. رخدادها و تغییرات ثبت‌شده در چرخهٔ گفتگو (افزوده‌های v12)

6.1) خطای قبلی verify-rbac.sh در مرحله Config Values (رفع شد)

خطای قبلی:

Fatal error: Call to undefined function env() in /var/www/html/config/permission.php

علت:

verify-rbac.sh برای بررسی config/permission.php از plain PHP (بدون Laravel bootstrap) استفاده می‌کرد و env() در آن کانتکست در دسترس نبود.

رفع:

اسکریپت verify-rbac.sh اصلاح شد تا بررسی config values بدون وابستگی به env() در plain PHP انجام شود و خروجی مرحله 8 پایدار گردد.

خروجی جدید مرحله 8:

✓ defaults.guard_name: sanctum
✓ connection: core
✓ Config values look correct

6.2) وضعیت Git و شاخه پایدار (تثبیت شد)

ریشه Git داخل مسیر:

/home/hp/Desktop/IMDC/backend

شاخه فعال:

pr/m05-linking

آخرین Commitها:

787c624 (HEAD -> pr/m05-linking, origin/pr/m05-linking)
fix(rbac-guardrail): make permission config check work in verify-rbac without env()

65a040b
docs: update snapshots and M04/M05 notes after linking stabilization

Working tree:

Clean و همگام با remote (origin/pr/m05-linking)

6.3) نتیجه Verify نهایی پس از Commit (PASS کامل)

پس از اعمال Commit و اجرای رسمی داخل کانتینر:

verify-rbac.sh: PASS
verify-marketplace.sh: PASS
verify-linking.sh: PASS
verify-lock.sh: PASS

نشانگر نهایی:

ALL_GUARDRAILS_OK
