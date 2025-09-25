<?php
/**
 * Configuracion principal - LIGNASEC
 * Carga variables del archivo .env
 */

class Config {
    private static $instance = null;
    private $config = [];

    private function __construct() {
        $this->loadEnv();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadEnv() {
        $envFile = dirname(__DIR__) . '/.env';
        
        if (!file_exists($envFile)) {
            throw new Exception('Archivo .env no encontrado');
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue; // Ignorar comentarios
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remover comillas si existen
                $value = trim($value, '"\'');
                
                $this->config[$key] = $value;
                
                // También establecer como variable de entorno
                putenv("$key=$value");
            }
        }
    }

    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    public function has($key) {
        return isset($this->config[$key]);
    }

    public function all() {
        return $this->config;
    }

    // Métodos de conveniencia para configuraciones comunes
    public function getDbConfig() {
        return [
            'host' => $this->get('DB_HOST'),
            'name' => $this->get('DB_NAME'),
            'user' => $this->get('DB_USER'),
            'pass' => $this->get('DB_PASS'),
            'charset' => $this->get('DB_CHARSET', 'utf8mb4')
        ];
    }

    public function getSmtpConfig() {
        return [
            'host' => $this->get('SMTP_HOST'),
            'port' => $this->get('SMTP_PORT'),
            'user' => $this->get('SMTP_USER'),
            'password' => $this->get('SMTP_PASSWORD'),
            'from_email' => $this->get('SMTP_FROM_EMAIL'),
            'from_name' => $this->get('SMTP_FROM_NAME')
        ];
    }

    public function getEmailConfig() {
        return [
            'soporte' => $this->get('EMAIL_SOPORTE'),
            'ventas' => $this->get('EMAIL_VENTAS')
        ];
    }

    public function getSiteConfig() {
        return [
            'name' => $this->get('SITE_NAME'),
            'phone' => $this->get('COMPANY_PHONE'),
            'address' => $this->get('COMPANY_ADDRESS')
        ];
    }

    public function getSecurityConfig() {
        return [
            'max_attempts' => (int)$this->get('MAX_ATTEMPTS', 5),
            'rate_limit_window' => (int)$this->get('RATE_LIMIT_WINDOW', 3600),
            'min_name_length' => (int)$this->get('MIN_NAME_LENGTH', 2),
            'max_name_length' => (int)$this->get('MAX_NAME_LENGTH', 100),
            'max_message_length' => (int)$this->get('MAX_MESSAGE_LENGTH', 2000),
            'max_company_length' => (int)$this->get('MAX_COMPANY_LENGTH', 255)
        ];
    }

    public function isDebugMode() {
        return $this->get('DEBUG_MODE', 'false') === 'true';
    }

    public function shouldLogErrors() {
        return $this->get('LOG_ERRORS', 'false') === 'true';
    }
}
?>