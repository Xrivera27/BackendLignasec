<?php
/**
 * Manejador de Contactos - LIGNASEC
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/EmailService.php';

class ContactHandler {
    private $conn;
    private $tabla = "contactos";
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

    public function procesarContactoPopup($datos) {
        try {
            // DEBUG con error_log
            error_log("=== PROCESANDO CONTACTO POPUP ===");
            error_log("Datos recibidos: " . json_encode($datos));
            
            $datosLimpios = $this->limpiarNombresCampos($datos);
            error_log("Datos limpios: " . json_encode($datosLimpios));
            
            $validacion = $this->validarDatosPopup($datosLimpios);
            if (!$validacion['valido']) {
                error_log("Validación fallida: " . json_encode($validacion['errores']));
                return [
                    'success' => false,
                    'message' => $validacion['mensaje'],
                    'errors' => $validacion['errores']
                ];
            }

            error_log("Validación exitosa");

            $ip = obtenerIpCliente();
            if (!verificarRateLimiting($ip, $this->tabla, $this->conn)) {
                error_log("Rate limit excedido para IP: $ip");
                return [
                    'success' => false,
                    'message' => 'Demasiados intentos. Por favor, espere un momento antes de enviar otro mensaje.'
                ];
            }

            error_log("Guardando en base de datos...");

            $query = "INSERT INTO " . $this->tabla . " 
                     (nombre, apellido, email, telefono, nombre_empresa, tipo_contacto, ip_cliente, user_agent) 
                     VALUES (?, ?, ?, ?, ?, 'popup', ?, ?)";

            $stmt = $this->conn->prepare($query);
            $resultado = $stmt->execute([
                limpiarEntrada($datosLimpios['firstName'] ?? ''),
                limpiarEntrada($datosLimpios['lastName'] ?? ''),
                limpiarEntrada($datosLimpios['email'] ?? ''),
                limpiarEntrada($datosLimpios['your-number'] ?? $datosLimpios['yournumber'] ?? ''),
                limpiarEntrada($datosLimpios['companyName'] ?? ''),
                $ip,
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            if ($resultado) {
                $contactoId = $this->conn->lastInsertId();
                
                error_log("Contacto guardado con ID: $contactoId");
                error_log("Iniciando envío de email...");
                
                registrarActividad('contacto', "Nuevo contacto popup ID: $contactoId", $this->conn);
                
                // Enviar notificacion por email
                try {
                    error_log("Llamando a emailService->enviarNotificacionContacto()");
                    $emailEnviado = $this->emailService->enviarNotificacionContacto($datosLimpios, 'popup', $contactoId);
                    error_log("Email resultado: " . ($emailEnviado ? 'ENVIADO' : 'FALLO'));
                } catch (Exception $e) {
                    error_log("Error enviando email: " . $e->getMessage());
                    $emailEnviado = false;
                }
                
                return [
                    'success' => true,
                    'message' => 'Gracias por contactarnos. Nuestro equipo se comunicara contigo muy pronto.',
                    'contact_id' => $contactoId,
                    'email_enviado' => $emailEnviado
                ];
            } else {
                throw new Exception("Error al guardar el contacto");
            }

        } catch (Exception $e) {
            error_log("Error en ContactHandler: " . $e->getMessage());
            logError("Error en procesarContactoPopup: " . $e->getMessage(), $datos);
            registrarActividad('error', "Error procesando contacto popup: " . $e->getMessage(), $this->conn);
            
            $siteConfig = $this->config->getSiteConfig();
            return [
                'success' => false,
                'message' => 'Error interno. Por favor, intente nuevamente o llamanos al ' . $siteConfig['phone']
            ];
        }
    }

    public function procesarContactoPrincipal($datos) {
        try {
            // DEBUG con error_log en lugar de console.log
            error_log("=== PROCESANDO CONTACTO PRINCIPAL ===");
            error_log("Datos recibidos: " . json_encode($datos));
            
            $datosLimpios = $this->limpiarNombresCampos($datos);
            error_log("Datos limpios: " . json_encode($datosLimpios));
            
            $validacion = $this->validarDatosContactoPrincipal($datosLimpios);
            if (!$validacion['valido']) {
                error_log("Validación fallida: " . json_encode($validacion['errores']));
                return [
                    'success' => false,
                    'message' => $validacion['mensaje'],
                    'errors' => $validacion['errores']
                ];
            }

            error_log("Validación exitosa");

            $ip = obtenerIpCliente();
            if (!verificarRateLimiting($ip, $this->tabla, $this->conn)) {
                error_log("Rate limit excedido para IP: $ip");
                return [
                    'success' => false,
                    'message' => 'Demasiados intentos. Por favor, espere un momento antes de enviar otro mensaje.'
                ];
            }

            error_log("Guardando contacto principal en BD...");

            $query = "INSERT INTO " . $this->tabla . " 
                     (nombre, email, telefono, direccion, mensaje, tipo_contacto, ip_cliente, user_agent) 
                     VALUES (?, ?, ?, ?, ?, 'pagina_contacto', ?, ?)";

            $stmt = $this->conn->prepare($query);
            $resultado = $stmt->execute([
                limpiarEntrada($datosLimpios['name'] ?? ''),
                limpiarEntrada($datosLimpios['email'] ?? ''),
                limpiarEntrada($datosLimpios['phone'] ?? ''),
                limpiarEntrada($datosLimpios['address'] ?? ''),
                limpiarEntrada($datosLimpios['message'] ?? ''),
                $ip,
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            if ($resultado) {
                $contactoId = $this->conn->lastInsertId();
                
                error_log("Contacto principal guardado con ID: $contactoId");
                error_log("Iniciando envío de email para contacto principal...");
                
                registrarActividad('contacto', "Nuevo contacto principal ID: $contactoId", $this->conn);
                
                // Enviar notificacion por email
                try {
                    error_log("Llamando a emailService para contacto principal...");
                    $emailEnviado = $this->emailService->enviarNotificacionContacto($datosLimpios, 'pagina_contacto', $contactoId);
                    error_log("Email contacto principal resultado: " . ($emailEnviado ? 'ENVIADO' : 'FALLO'));
                } catch (Exception $e) {
                    error_log("Error enviando email contacto principal: " . $e->getMessage());
                    $emailEnviado = false;
                }
                
                return [
                    'success' => true,
                    'message' => 'Tu mensaje ha sido enviado exitosamente. Nos pondremos en contacto contigo pronto.',
                    'contact_id' => $contactoId,
                    'email_enviado' => $emailEnviado
                ];
            } else {
                throw new Exception("Error al guardar el contacto");
            }

        } catch (Exception $e) {
            error_log("Error en ContactHandler Principal: " . $e->getMessage());
            logError("Error en procesarContactoPrincipal: " . $e->getMessage(), $datos);
            registrarActividad('error', "Error procesando contacto principal: " . $e->getMessage(), $this->conn);
            
            $siteConfig = $this->config->getSiteConfig();
            return [
                'success' => false,
                'message' => 'Error interno. Por favor, intente nuevamente o llamanos al ' . $siteConfig['phone']
            ];
        }
    }

    private function validarDatosPopup($datos) {
        $securityConfig = $this->config->getSecurityConfig();
        $errores = [];

        if (empty($datos['firstName']) || strlen(trim($datos['firstName'])) < $securityConfig['min_name_length']) {
            $errores['firstName'] = 'El nombre es requerido y debe tener al menos ' . $securityConfig['min_name_length'] . ' caracteres.';
        }

        if (!empty($datos['lastName']) && strlen(trim($datos['lastName'])) < $securityConfig['min_name_length']) {
            $errores['lastName'] = 'El apellido debe tener al menos ' . $securityConfig['min_name_length'] . ' caracteres.';
        }

        if (empty($datos['email']) || !validarEmail($datos['email'])) {
            $errores['email'] = 'Se requiere un email valido.';
        }

        $telefono = $datos['your-number'] ?? $datos['yournumber'] ?? $datos['phone'] ?? '';
        if (empty($telefono) || !validarTelefonoHondureno($telefono)) {
            $errores['phone'] = 'Se requiere un numero de telefono hondureno valido.';
        }

        if (!empty($datos['companyName']) && strlen(trim($datos['companyName'])) > $securityConfig['max_company_length']) {
            $errores['companyName'] = 'El nombre de la empresa es demasiado largo.';
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores,
            'mensaje' => empty($errores) ? 'Validacion exitosa' : 'Por favor, corrija los errores en el formulario.'
        ];
    }

    private function validarDatosContactoPrincipal($datos) {
        $securityConfig = $this->config->getSecurityConfig();
        $errores = [];

        if (empty($datos['name']) || strlen(trim($datos['name'])) < $securityConfig['min_name_length']) {
            $errores['name'] = 'El nombre completo es requerido.';
        }

        if (empty($datos['email']) || !validarEmail($datos['email'])) {
            $errores['email'] = 'Se requiere un email valido.';
        }

        if (empty($datos['phone']) || !validarTelefonoHondureno($datos['phone'])) {
            $errores['phone'] = 'Se requiere un numero de telefono valido.';
        }

        if (empty($datos['address'])) {
            $errores['address'] = 'La direccion es requerida.';
        }

        if (!empty($datos['message']) && strlen(trim($datos['message'])) > $securityConfig['max_message_length']) {
            $errores['message'] = 'El mensaje es demasiado largo.';
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores,
            'mensaje' => empty($errores) ? 'Validacion exitosa' : 'Por favor, corrija los errores en el formulario.'
        ];
    }

    public function obtenerTodosContactos($limite = 50, $offset = 0) {
        try {
            $query = "SELECT * FROM " . $this->tabla . " 
                     ORDER BY fecha_creacion DESC 
                     LIMIT ? OFFSET ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$limite, $offset]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            logError("Error obteniendo contactos: " . $e->getMessage());
            return [];
        }
    }

    public function actualizarEstadoContacto($id, $estado) {
        try {
            $query = "UPDATE " . $this->tabla . " SET estado = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            return $stmt->execute([$estado, $id]);
        } catch (Exception $e) {
            logError("Error actualizando estado: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerEstadisticas() {
        try {
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                        SUM(CASE WHEN estado = 'contactado' THEN 1 ELSE 0 END) as contactados,
                        SUM(CASE WHEN estado = 'resuelto' THEN 1 ELSE 0 END) as resueltos,
                        SUM(CASE WHEN tipo_contacto = 'popup' THEN 1 ELSE 0 END) as popup,
                        SUM(CASE WHEN tipo_contacto = 'pagina_contacto' THEN 1 ELSE 0 END) as pagina_contacto
                      FROM " . $this->tabla;
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (Exception $e) {
            logError("Error obteniendo estadisticas de contactos: " . $e->getMessage());
            return null;
        }
    }

    public function obtenerContacto($id) {
        try {
            $query = "SELECT * FROM " . $this->tabla . " WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id]);
            
            return $stmt->fetch();
        } catch (Exception $e) {
            logError("Error obteniendo contacto: " . $e->getMessage());
            return null;
        }
    }
}
?>