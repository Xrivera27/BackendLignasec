<?php
/**
 * Manejador de Newsletter - LIGNASEC
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/EmailService.php';

class NewsletterHandler {
    private $conn;
    private $tabla = "suscriptores_newsletter";
    private $config;
    private $emailService;

    public function __construct($db) {
        $this->conn = $db;
        $this->config = Config::getInstance();
        $this->emailService = new EmailService();
    }

    // Función para limpiar nombres de campos malformateados
    private function limpiarNombresCampos($datos) {
        $datosLimpios = [];
        foreach ($datos as $key => $value) {
            $keyLimpia = trim(str_replace([':', '_'], '', $key));
            $datosLimpios[$keyLimpia] = is_string($value) ? trim($value) : $value;
        }
        return $datosLimpios;
    }

    public function procesarSuscripcion($emailInput) {
        try {
            // Si es un array (viene de $_POST), limpiar y extraer el email
            if (is_array($emailInput)) {
                $datosLimpios = $this->limpiarNombresCampos($emailInput);
                $email = $datosLimpios['email'] ?? '';
            } else {
                // Si es string directo
                $email = $emailInput;
            }

            if (empty($email) || !validarEmail($email)) {
                return [
                    'success' => false,
                    'message' => 'Por favor, ingrese un email valido.'
                ];
            }

            $email = limpiarEntrada($email);

            if ($this->emailYaSuscrito($email)) {
                return [
                    'success' => false,
                    'message' => 'Este email ya esta suscrito a nuestro newsletter.'
                ];
            }

            $ip = obtenerIpCliente();
            if (!verificarRateLimiting($ip, $this->tabla, $this->conn)) {
                return [
                    'success' => false,
                    'message' => 'Demasiados intentos. Por favor, espere un momento antes de intentar nuevamente.'
                ];
            }

            $query = "INSERT INTO " . $this->tabla . " 
                     (email, ip_cliente, user_agent) 
                     VALUES (?, ?, ?)";

            $stmt = $this->conn->prepare($query);
            $resultado = $stmt->execute([
                $email,
                $ip,
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            if ($resultado) {
                $suscriptorId = $this->conn->lastInsertId();
                
                registrarActividad('newsletter', "Nueva suscripcion ID: $suscriptorId, Email: $email", $this->conn);
                
                // Enviar email de bienvenida
                $this->emailService->enviarBienvenidaNewsletter($email);
                
                // Notificar al admin
                $this->emailService->enviarNotificacionSuscripcion($email, $suscriptorId);
                
                return [
                    'success' => true,
                    'message' => 'Gracias por suscribirte! Te mantendremos informado sobre las ultimas novedades en ciberseguridad.',
                    'subscriber_id' => $suscriptorId
                ];
            } else {
                throw new Exception("Error al guardar la suscripcion");
            }

        } catch (Exception $e) {
            logError("Error en procesarSuscripcion: " . $e->getMessage(), ['email' => $email ?? 'no-email']);
            registrarActividad('error', "Error procesando suscripcion: " . $e->getMessage(), $this->conn);
            
            return [
                'success' => false,
                'message' => 'Error interno. Por favor, intente nuevamente mas tarde.'
            ];
        }
    }

    private function emailYaSuscrito($email) {
        try {
            $query = "SELECT COUNT(*) as total FROM " . $this->tabla . " 
                     WHERE email = ? AND estado = 'activo'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$email]);
            $resultado = $stmt->fetch();
            
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            logError("Error verificando email: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerTodosSuscriptores($limite = 50, $offset = 0) {
        try {
            $query = "SELECT * FROM " . $this->tabla . " 
                     WHERE estado = 'activo'
                     ORDER BY fecha_suscripcion DESC 
                     LIMIT ? OFFSET ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$limite, $offset]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            logError("Error obteniendo suscriptores: " . $e->getMessage());
            return [];
        }
    }

    public function darDeBajaSuscriptor($email) {
        try {
            $query = "UPDATE " . $this->tabla . " 
                     SET estado = 'inactivo', fecha_baja = NOW() 
                     WHERE email = ?";
            $stmt = $this->conn->prepare($query);
            
            return $stmt->execute([$email]);
        } catch (Exception $e) {
            logError("Error dando de baja: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerEstadisticas() {
        try {
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
                        SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) as inactivos,
                        COUNT(CASE WHEN fecha_suscripcion >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as ultimos_30_dias
                      FROM " . $this->tabla;
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (Exception $e) {
            logError("Error obteniendo estadisticas de newsletter: " . $e->getMessage());
            return null;
        }
    }
}
?>