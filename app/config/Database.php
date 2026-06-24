<?php

/**
 * Conexão PDO com MySQL - Singleton
 * PHP 7.3+
 */

require_once __DIR__ . '/Config.php';

class Database
{
    /** @var PDO|null */
    private static $instance = null;

    /**
     * @return PDO
     */
    public static function getConnection()
    {
        if (self::$instance === null) {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $dbNames = [Config::DB_NAME];
            // Compatibilidade com ambientes antigos que ainda usam web_dashbi_2.
            if (Config::DB_NAME !== 'web_dashbi_2') {
                $dbNames[] = 'web_dashbi_2';
            }

            $lastError = null;
            foreach ($dbNames as $dbName) {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    Config::DB_HOST,
                    $dbName,
                    Config::DB_CHARSET
                );
                try {
                    self::$instance = new PDO($dsn, Config::DB_USER, Config::DB_PASS, $options);
                    break;
                } catch (PDOException $e) {
                    $lastError = $e;
                    if (stripos($e->getMessage(), 'Unknown database') === false) {
                        throw $e;
                    }
                }
            }

            if (self::$instance === null && $lastError !== null) {
                throw $lastError;
            }
        }

        return self::$instance;
    }

    /**
     * Evita clonagem
     */
    private function __clone() {}

    /**
     * Reset para testes
     */
    public static function reset()
    {
        self::$instance = null;
    }
}
