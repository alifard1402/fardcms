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
                throw new RuntimeException('خطا در اتصال به دیتابیس');
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
     * آیا دیتابیس نصب شده است؟ (برای هدایت به نصب‌کننده)
     */
    public static function isInstalled(): bool
    {
        try {
            $db = self::getConnection();
            $db->query('SELECT 1 FROM ' . tbl('options') . ' LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
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
