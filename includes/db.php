<?php
/**
 * اتصال به دیتابیس MySQL با PDO (Singleton)
 */

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    DB_HOST,
                    DB_NAME,
                    DB_CHARSET
                );

                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE             => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES    => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND  => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());

                // خطای اصلی به عنوان previous حمل می‌شود تا installState()
                // بتواند «دیتابیس ساخته نشده» را از «سرور در دسترس نیست»
                // تشخیص دهد، بدون آنکه جزئیات اتصال در پیام بیرونی درز کند
                throw new RuntimeException('خطا در اتصال به دیتابیس', 0, $e);
            }
        }

        return self::$instance;
    }

    /**
     * اتصال بدون انتخاب دیتابیس (برای setup)
     */
    public static function getBaseConnection(): PDO
    {
        try {
            $dsn = sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);

            return new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('Base database connection failed: ' . $e->getMessage());
            throw new RuntimeException('خطا در اتصال به سرور دیتابیس');
        }
    }

    /**
     * وضعیت نصب سایت
     *
     * «نصب نشده» و «دیتابیس در دسترس نیست» دو حالت کاملاً متفاوت‌اند:
     * قطعی موقت دیتابیس نباید بازدیدکننده را به نصب‌کننده بفرستد، چون
     * هم گمراه‌کننده است و هم می‌تواند به سوءاستفاده منجر شود.
     *
     * @return 'installed'|'not_installed'|'unavailable'
     */
    public static function installState(): string
    {
        try {
            $db = self::getConnection();
        } catch (Throwable $e) {
            // به سرور دیتابیس نمی‌توان وصل شد یا خود دیتابیس وجود ندارد
            return self::databaseMissing($e) ? 'not_installed' : 'unavailable';
        }

        try {
            $db->query('SELECT 1 FROM ' . tbl('options') . ' LIMIT 1');

            return 'installed';
        } catch (PDOException $e) {
            // 42S02 = جدول وجود ندارد؛ یعنی سایت هنوز نصب نشده است
            return ($e->getCode() === '42S02') ? 'not_installed' : 'unavailable';
        } catch (Throwable $e) {
            return 'unavailable';
        }
    }

    /**
     * آیا خطای اتصال به خاطر نبودِ خود دیتابیس است؟
     *
     * کد ۱۰۴۹ یعنی سرور در دسترس است اما دیتابیس ساخته نشده — این حالت
     * «نصب نشده» است، نه قطعی سرور. خطای اصلی PDO در previous قرار دارد.
     */
    private static function databaseMissing(Throwable $e): bool
    {
        $cause = $e->getPrevious() ?? $e;

        return str_contains($cause->getMessage(), '1049')
            || str_contains($cause->getMessage(), 'Unknown database');
    }

    /**
     * آیا سایت نصب شده است؟
     */
    public static function isInstalled(): bool
    {
        return self::installState() === 'installed';
    }

    // جلوگیری از clone و unserialize
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() { throw new RuntimeException('Cannot unserialize singleton'); }
}

/**
 * نام کامل جدول با پیشوند
 *
 * فقط نام‌های مجاز (حروف و آندرلاین) پذیرفته می‌شوند تا در کوئری‌ها
 * که نام جدول قابل bind شدن نیست، امکان تزریق وجود نداشته باشد.
 */
function tbl(string $name): string
{
    if (!preg_match('/^[a-z_]+$/', $name)) {
        throw new InvalidArgumentException('نام جدول نامعتبر است: ' . $name);
    }

    return '`' . DB_PREFIX . $name . '`';
}
