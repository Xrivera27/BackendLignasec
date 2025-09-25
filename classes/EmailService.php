<?php
require_once __DIR__ . '/../config/config.php';

class EmailService {
    private $config;
    private $emailConfig;
    private $smtp_config;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $this->smtp_config = $this->config->getSmtpConfig();
        $this->emailConfig = $this->config->getEmailConfig();
        
        error_log("=== EmailService Inicializado ===");
        error_log("SMTP Host: " . $this->smtp_config['host']);
        error_log("SMTP Port: " . $this->smtp_config['port']);
        error_log("SMTP User: " . $this->smtp_config['user']);
        error_log("From Email: " . $this->smtp_config['from_email']);
        error_log("Email Ventas: " . $this->emailConfig['ventas']);
    }

    public function enviarNotificacionContacto($datos, $tipoContacto, $contactoId) {
        try {
            error_log("=== INICIANDO ENVIO NOTIFICACION CONTACTO ===");
            error_log("Contacto ID: $contactoId");
            error_log("Tipo: $tipoContacto");
            error_log("Datos: " . json_encode($datos));
            
            $asunto = "Nuevo contacto LIGNASEC - " . ($tipoContacto === 'popup' ? 'Formulario Popup' : 'Formulario Principal');
            $mensaje = $this->construirMensajeContacto($datos, $tipoContacto, $contactoId);
            
            error_log("Asunto: $asunto");
            error_log("Destinatario: " . $this->emailConfig['ventas']);
            
            $resultado = $this->enviarSMTP($this->emailConfig['ventas'], $asunto, $mensaje);
            
            error_log("Resultado envío notificación: " . ($resultado ? 'EXITO' : 'FALLO'));
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("ERROR enviando notificación: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    public function enviarBienvenidaNewsletter($email) {
        try {
            error_log("=== INICIANDO ENVIO BIENVENIDA NEWSLETTER ===");
            error_log("Email destinatario: $email");
            
            $asunto = "Bienvenido al Newsletter de LIGNASEC";
            $mensaje = $this->construirMensajeBienvenida($email);
            
            $resultado = $this->enviarSMTP($email, $asunto, $mensaje);
            
            error_log("Resultado envío bienvenida: " . ($resultado ? 'EXITO' : 'FALLO'));
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("ERROR enviando bienvenida: " . $e->getMessage());
            return false;
        }
    }

    public function enviarNotificacionSuscripcion($email, $suscriptorId) {
        try {
            error_log("=== INICIANDO ENVIO NOTIFICACION SUSCRIPCION ===");
            error_log("Nueva suscripción - ID: $suscriptorId, Email: $email");
            
            $asunto = "Nueva suscripcion al Newsletter - LIGNASEC";
            $mensaje = $this->construirMensajeNotificacionSuscripcion($email, $suscriptorId);
            
            $resultado = $this->enviarSMTP($this->emailConfig['ventas'], $asunto, $mensaje);
            
            error_log("Resultado notificación suscripción: " . ($resultado ? 'EXITO' : 'FALLO'));
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("ERROR enviando notificación suscripción: " . $e->getMessage());
            return false;
        }
    }

    private function enviarSMTP($destinatario, $asunto, $mensaje) {
        error_log("--- Iniciando conexión SMTP ---");
        error_log("Conectando a: {$this->smtp_config['host']}:{$this->smtp_config['port']}");
        
        $smtp = @fsockopen($this->smtp_config['host'], $this->smtp_config['port'], $errno, $errstr, 30);
        
        if (!$smtp) {
            error_log("ERROR: No se pudo conectar a SMTP: $errstr ($errno)");
            return false;
        }

        error_log("Conexión SMTP establecida exitosamente");

        try {
            // Leer respuesta inicial del servidor
            if (!$this->leerYValidarRespuesta($smtp, '220')) {
                throw new Exception("Error en respuesta inicial del servidor");
            }
            
            // EHLO
            if (!$this->enviarComandoYValidar($smtp, "EHLO " . $this->smtp_config['host'] . "\r\n", '250')) {
                throw new Exception("Error en comando EHLO");
            }
            
            // STARTTLS
            if (!$this->enviarComandoYValidar($smtp, "STARTTLS\r\n", '220')) {
                throw new Exception("Error en comando STARTTLS");
            }
            
            // Habilitar TLS
            if (!@stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("No se pudo habilitar TLS");
            }
            error_log("TLS habilitado exitosamente");
            
            // EHLO después de TLS
            if (!$this->enviarComandoYValidar($smtp, "EHLO " . $this->smtp_config['host'] . "\r\n", '250')) {
                throw new Exception("Error en EHLO post-TLS");
            }
            
            // AUTH LOGIN
            if (!$this->enviarComandoYValidar($smtp, "AUTH LOGIN\r\n", '334')) {
                throw new Exception("Error en AUTH LOGIN");
            }
            
            // Enviar usuario
            if (!$this->enviarComandoYValidar($smtp, base64_encode($this->smtp_config['user']) . "\r\n", '334')) {
                throw new Exception("Error enviando usuario");
            }
            
            // Enviar contraseña
            if (!$this->enviarComandoYValidar($smtp, base64_encode($this->smtp_config['password']) . "\r\n", '235')) {
                throw new Exception("Error de autenticación - credenciales incorrectas");
            }
            
            // MAIL FROM
            if (!$this->enviarComandoYValidar($smtp, "MAIL FROM: <{$this->smtp_config['from_email']}>\r\n", '250')) {
                throw new Exception("Error en MAIL FROM");
            }
            
            // RCPT TO
            if (!$this->enviarComandoYValidar($smtp, "RCPT TO: <$destinatario>\r\n", '250')) {
                throw new Exception("Error en RCPT TO");
            }
            
            // DATA
            if (!$this->enviarComandoYValidar($smtp, "DATA\r\n", '354')) {
                throw new Exception("Error en comando DATA");
            }
            
            // Enviar contenido del email
            $emailContent = $this->construirEmail($destinatario, $asunto, $mensaje);
            fwrite($smtp, $emailContent . "\r\n.\r\n");
            
            if (!$this->leerYValidarRespuesta($smtp, '250')) {
                throw new Exception("Error enviando contenido del email");
            }
            
            // QUIT
            fwrite($smtp, "QUIT\r\n");
            $this->leerRespuesta($smtp);
            
            error_log("--- Email enviado exitosamente ---");
            return true;
            
        } catch (Exception $e) {
            error_log("EXCEPCION en enviarSMTP: " . $e->getMessage());
            return false;
        } finally {
            if (is_resource($smtp)) {
                fclose($smtp);
                error_log("Conexión SMTP cerrada");
            }
        }
    }

    private function enviarComandoYValidar($smtp, $comando, $codigoEsperado) {
        fwrite($smtp, $comando);
        $response = $this->leerRespuesta($smtp);
        error_log("Comando: " . trim($comando) . " | Respuesta: $response");
        
        return $this->validarCodigo($response, $codigoEsperado);
    }

    private function leerYValidarRespuesta($smtp, $codigoEsperado) {
        $response = $this->leerRespuesta($smtp);
        error_log("Respuesta recibida: $response");
        
        return $this->validarCodigo($response, $codigoEsperado);
    }

    private function leerRespuesta($smtp) {
        $response = '';
        while ($line = fgets($smtp, 512)) {
            $response .= $line;
            // Si la línea no termina con '-', es la última línea de respuesta
            if (substr($line, 3, 1) !== '-') {
                break;
            }
        }
        return trim($response);
    }

    private function validarCodigo($response, $codigoEsperado) {
        $codigo = substr($response, 0, 3);
        $esValido = ($codigo === $codigoEsperado);
        
        if (!$esValido) {
            error_log("ERROR: Se esperaba código $codigoEsperado pero se recibió $codigo");
            error_log("Respuesta completa: $response");
        }
        
        return $esValido;
    }

    private function construirEmail($destinatario, $asunto, $mensaje) {
        $boundary = md5(uniqid(time()));
        
        $headers = "From: {$this->smtp_config['from_name']} <{$this->smtp_config['from_email']}>\r\n";
        $headers .= "To: $destinatario\r\n";
        $headers .= "Subject: $asunto\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "\r\n";
        
        return $headers . $mensaje;
    }

    private function construirMensajeContacto($datos, $tipo, $contactoId) {
        $siteConfig = $this->config->getSiteConfig();
        
        $mensaje = "<html><body>";
        $mensaje .= "<h2>Nuevo contacto en {$siteConfig['name']}</h2>";
        $mensaje .= "<p><strong>ID:</strong> $contactoId</p>";
        $mensaje .= "<p><strong>Tipo:</strong> " . ($tipo === 'popup' ? 'Formulario Popup' : 'Formulario Principal') . "</p>";
        $mensaje .= "<p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>";
        
        if ($tipo === 'popup') {
            $mensaje .= "<h3>Informacion Personal</h3>";
            $mensaje .= "<p><strong>Nombre:</strong> " . ($datos['firstName'] ?? '') . " " . ($datos['lastName'] ?? '') . "</p>";
            $mensaje .= "<p><strong>Email:</strong> " . ($datos['email'] ?? '') . "</p>";
            $mensaje .= "<p><strong>Telefono:</strong> " . ($datos['your-number'] ?? $datos['yournumber'] ?? '') . "</p>";
            $mensaje .= "<p><strong>Empresa:</strong> " . ($datos['companyName'] ?? 'No especificada') . "</p>";
        } else {
            $mensaje .= "<h3>Informacion Personal</h3>";
            $mensaje .= "<p><strong>Nombre:</strong> " . ($datos['name'] ?? '') . "</p>";
            $mensaje .= "<p><strong>Email:</strong> " . ($datos['email'] ?? '') . "</p>";
            $mensaje .= "<p><strong>Telefono:</strong> " . ($datos['phone'] ?? '') . "</p>";
            $mensaje .= "<p><strong>Direccion:</strong> " . ($datos['address'] ?? '') . "</p>";
            $mensaje .= "<p><strong>Mensaje:</strong> " . ($datos['message'] ?? 'Sin mensaje') . "</p>";
        }
        
        $mensaje .= "<hr>";
        $mensaje .= "<p><strong>Acciones sugeridas:</strong></p>";
        $mensaje .= "<ul>";
        $mensaje .= "<li>Responder al email: " . ($datos['email'] ?? '') . "</li>";
        $mensaje .= "<li>Llamar al telefono proporcionado</li>";
        $mensaje .= "<li>Revisar el dashboard de administracion</li>";
        $mensaje .= "</ul>";
        $mensaje .= "</body></html>";
        
        return $mensaje;
    }

    private function construirMensajeBienvenida($email) {
        $siteConfig = $this->config->getSiteConfig();
        
        $mensaje = "<html><body>";
        $mensaje .= "<h2>Bienvenido al Newsletter de {$siteConfig['name']}!</h2>";
        $mensaje .= "<p>Hola,</p>";
        $mensaje .= "<p>Gracias por suscribirte a nuestro newsletter. Te mantendremos informado sobre:</p>";
        $mensaje .= "<ul>";
        $mensaje .= "<li>Ultimas novedades en ciberseguridad</li>";
        $mensaje .= "<li>Consejos de seguridad para empresas</li>";
        $mensaje .= "<li>Servicios y soluciones de LIGNASEC</li>";
        $mensaje .= "<li>Eventos y capacitaciones</li>";
        $mensaje .= "</ul>";
        $mensaje .= "<p>Si tienes alguna pregunta, contactanos:</p>";
        $mensaje .= "<p><strong>Email:</strong> {$this->emailConfig['soporte']}<br>";
        $mensaje .= "<strong>Telefono:</strong> {$siteConfig['phone']}</p>";
        $mensaje .= "<p>Saludos,<br>Equipo LIGNASEC</p>";
        $mensaje .= "</body></html>";
        
        return $mensaje;
    }

    private function construirMensajeNotificacionSuscripcion($email, $suscriptorId) {
        $mensaje = "<html><body>";
        $mensaje .= "<h2>Nueva suscripcion al Newsletter - LIGNASEC</h2>";
        $mensaje .= "<p><strong>ID:</strong> $suscriptorId</p>";
        $mensaje .= "<p><strong>Email:</strong> $email</p>";
        $mensaje .= "<p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>";
        $mensaje .= "<p><strong>IP:</strong> " . ($_SERVER['REMOTE_ADDR'] ?? 'No disponible') . "</p>";
        $mensaje .= "</body></html>";
        
        return $mensaje;
    }
}
?>