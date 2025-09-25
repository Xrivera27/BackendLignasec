<?php
/**
 * Configuracion de Base de Datos - LIGNASEC
 */

require_once 'config.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    public $conn;

    public function __construct() {
        $config = Config::getInstance();
        $dbConfig = $config->getDbConfig();
        
        $this->host = $dbConfig['host'];
        $this->db_name = $dbConfig['name'];
        $this->username = $dbConfig['user'];
        $this->password = $dbConfig['pass'];
        $this->charset = $dbConfig['charset'];
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE {$this->charset}_unicode_ci"
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            $config = Config::getInstance();
            if ($config->shouldLogErrors()) {
                error_log("Error de conexion DB: " . $exception->getMessage());
            }
            throw new Exception("Error de conexion a la base de datos");
        }

        return $this->conn;
    }
    
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT 1");
            return $stmt !== false;
        } catch(Exception $e) {
            return false;
        }
    }

    public function getDbInfo() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT VERSION() as version, DATABASE() as database_name");
            return $stmt->fetch();
        } catch(Exception $e) {
            return null;
        }
    }
}
?>