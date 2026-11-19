<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Production Database Connection using PDO Prepared Statements
 * Fully configured for MilesWeb MariaDB Hosting (Unix Socket & TCP Fallback)
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;
    private static ?string $connectionMethod = null;

    /**
     * Get or create PDO singleton connection
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // 1. If explicitly set to sqlite
            if (DB_TYPE === 'sqlite') {
                self::$instance = self::getSqliteConnection($options);
                self::$connectionMethod = 'sqlite';
                return self::$instance;
            }

            $dbName   = DB_NAME;
            $dbUser   = DB_USER;
            $dbPass   = DB_PASS;
            $dbSocket = defined('DB_SOCKET') ? DB_SOCKET : '/run/mysqld/mysqld.sock';
            $dbHost   = DB_HOST;
            $dbPort   = DB_PORT;

            // 2. Primary: MilesWeb MariaDB Unix Socket connection
            // Recommended by MilesWeb for maximum local performance and lowest latency
            if (!empty($dbSocket) && (file_exists($dbSocket) || PHP_OS_FAMILY !== 'Windows')) {
                try {
                    $socketDsn = sprintf(
                        "mysql:unix_socket=%s;dbname=%s;charset=utf8mb4",
                        $dbSocket,
                        $dbName
                    );
                    self::$instance = new PDO($socketDsn, $dbUser, $dbPass, $options);
                    self::$connectionMethod = 'unix_socket';
                    return self::$instance;
                } catch (PDOException $e) {
                    error_log("MilesWeb Unix socket connection failed: " . $e->getMessage() . ". Trying TCP fallback.");
                }
            }

            // 3. Secondary: TCP host connection (MilesWeb 103.108.220.222:3306 or configured host)
            try {
                $tcpDsn = sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                    $dbHost,
                    $dbPort,
                    $dbName
                );
                self::$instance = new PDO($tcpDsn, $dbUser, $dbPass, $options);
                self::$connectionMethod = 'tcp';
                return self::$instance;
            } catch (PDOException $e) {
                error_log("MilesWeb TCP connection failed: " . $e->getMessage());

                // 4. Safe Local Development / Offline Testing SQLite Fallback
                // Allows local test suites to run without crashing when remote MariaDB is unreachable
                $isLocalEnv = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) 
                    || php_sapi_name() === 'cli'
                    || (getenv('ALLOW_SQLITE_FALLBACK') === 'true');

                if ($isLocalEnv) {
                    error_log("Notice: Falling back to local SQLite for development / test environment.");
                    self::$instance = self::getSqliteConnection($options);
                    self::$connectionMethod = 'sqlite_fallback';
                    return self::$instance;
                }

                // In live production, throw a clean, secure PDOException (never leak credentials)
                throw new PDOException("Production database connection could not be established. Please check hosting credentials.");
            }
        }

        return self::$instance;
    }

    /**
     * Return active connection method ('unix_socket', 'tcp', 'sqlite', 'sqlite_fallback')
     */
    public static function getConnectionMethod(): ?string {
        return self::$connectionMethod;
    }

    /**
     * Create or retrieve SQLite connection with automatic schema init
     */
    private static function getSqliteConnection(array $options): PDO {
        $sqliteFile = DB_SQLITE_PATH;
        $isNew = !file_exists($sqliteFile) || filesize($sqliteFile) === 0;
        $pdo = new PDO("sqlite:" . $sqliteFile, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        if ($isNew) {
            self::initSqliteSchema($pdo);
        }
        return $pdo;
    }

    /**
     * Initialize schema for SQLite environments
     */
    public static function initSqliteSchema(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                name TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME NULL
            );

            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT NOT NULL UNIQUE,
                setting_value TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS players (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                registration_id TEXT NOT NULL UNIQUE,
                full_name TEXT NOT NULL,
                photo_path TEXT NOT NULL,
                playing_position TEXT NOT NULL,
                club_name TEXT NOT NULL,
                jersey_size TEXT NOT NULL,
                primary_contact TEXT NOT NULL,
                alternative_contact TEXT NULL,
                full_address TEXT NOT NULL,
                registration_fee NUMERIC NOT NULL DEFAULT 100.00,
                registration_status TEXT NOT NULL DEFAULT 'PENDING_PAYMENT',
                payment_status TEXT NOT NULL DEFAULT 'PENDING',
                razorpay_order_id TEXT NULL,
                razorpay_payment_id TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                registration_id TEXT NOT NULL,
                razorpay_order_id TEXT NOT NULL,
                razorpay_payment_id TEXT NULL,
                razorpay_signature TEXT NULL,
                amount NUMERIC NOT NULL,
                currency TEXT NOT NULL DEFAULT 'INR',
                status TEXT NOT NULL DEFAULT 'CREATED',
                payment_method TEXT NULL,
                error_code TEXT NULL,
                error_description TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS webhook_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id TEXT NOT NULL UNIQUE,
                event_type TEXT NOT NULL,
                order_id TEXT NULL,
                payment_id TEXT NULL,
                payload TEXT NULL,
                status TEXT NOT NULL DEFAULT 'PROCESSED',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                username TEXT NOT NULL,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed default admin if missing
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $defaultHash = password_hash('Admin@BPL2026', PASSWORD_BCRYPT);
            $insAdmin = $pdo->prepare("INSERT INTO admins (username, email, password_hash, name) VALUES ('admin', 'admin@buddilane.org', ?, 'Tournament Director')");
            $insAdmin->execute([$defaultHash]);
        }

        // Seed default settings if empty
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $defaults = [
                'tournament_name' => 'BUDDILANE PREMIER LEAGUE',
                'tournament_season' => 'SEASON 3 — 2026–27',
                'organised_by' => 'BUDDILANE FRIENDS GROUP',
                'registration_fee' => '100',
                'registration_open' => '1',
                'registration_deadline' => '2027-01-31',
                'contact_phone' => '+91 98450 12345',
                'contact_email' => 'contact@buddilanepremierleague.com',
                'tournament_venue' => 'Buddilane Football Ground',
                'razorpay_mode' => 'test',
                'razorpay_key_id' => 'rzp_test_51a2b3c4d5e6f7',
                'razorpay_key_secret' => 'sk_test_1a2b3c4d5e6f7g8h9i0j',
                'razorpay_webhook_secret' => 'whsec_test_secret_9988776655'
            ];
            $insert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            foreach ($defaults as $k => $v) {
                $insert->execute([$k, $v]);
            }
        }
    }
}

/**
 * Global helper function to retrieve database instance
 */
function getDB(): PDO {
    return Database::getConnection();
}
