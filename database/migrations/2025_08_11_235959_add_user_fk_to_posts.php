<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // اگر جدول‌ها موجود نیستند، خطا می‌گیریم تا ریشه‌ای حل شود (چشم‌پوشی نمی‌کنیم)
        if (!Schema::hasTable('posts')) {
            throw new RuntimeException("Table 'posts' does not exist. Create it before adding user_id.");
        }
        if (!Schema::hasTable('users')) {
            throw new RuntimeException("Table 'users' does not exist. Create it before adding FK from posts.");
        }

        // 1) اگر user_id نداریم، اضافه‌اش می‌کنیم (nullable موقت برای داده‌های فعلی)
        if (!Schema::hasColumn('posts', 'user_id')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            });
        }

        // 2) اگر author_id داریم و user_id هم (الان) داریم، مقادیر رو منتقل کن
        if (Schema::hasColumn('posts', 'author_id') && Schema::hasColumn('posts', 'user_id')) {
            // فقط nullهایی که user_id ندارن را از author_id پر می‌کنیم
            DB::statement("
                UPDATE posts
                SET user_id = CASE
                    WHEN user_id IS NULL THEN author_id
                    ELSE user_id
                END
            ");
        }

        // 3) اگر ستون user_id هنوز null-های بدون مرجع دارد و نیاز داری اجباری باشد،
        // این بخش را بعد از تصفیهٔ داده‌ها می‌توانی فعال کنی تا NOT NULL شود.
        // اگر مطمئن هستی همهٔ ردیف‌ها باید owner داشته باشند، این دو خط را باز کن:
        // DB::statement('UPDATE posts SET user_id = 1 WHERE user_id IS NULL'); // نمونهٔ پیش‌فرض
        // Schema::table('posts', function (Blueprint $table) { $table->foreignId('user_id')->nullable(false)->change(); });

        // 4) اگر author_id اضافه بود، حذفش کن (بعد از انتقال)
        if (Schema::hasColumn('posts', 'author_id')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn('author_id');
            });
        }

        // 5) اطمینان از وجود ایندکس؛ اسم استاندارد ایندکس را می‌سازیم
        // (foreignId به‌صورت پیش‌فرض ایندکس می‌سازد، ولی اگر سفارشی می‌خواهی:)
        // Schema::table('posts', function (Blueprint $table) {
        //     $table->index('user_id', 'posts_user_id_idx');
        // });
    }

    public function down(): void
    {
        // حذف FK و ستون user_id در صورت وجود
        if (Schema::hasTable('posts') && Schema::hasColumn('posts', 'user_id')) {
            Schema::table('posts', function (Blueprint $table) {
                // نام کلید خارجی را به‌صورت خودکار پیدا/حذف می‌کنیم
                // لاراول معمولاً نامی مثل posts_user_id_foreign می‌سازد:
                $table->dropForeign(['user_id']);
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
