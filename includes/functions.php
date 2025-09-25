<?php
/**
 * Funciones helper generales - LIGNASEC
 */

require_once __DIR__ . '/../config/config.php';

function limpiarEntrada($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validarTelefonoHondureno($telefono) {
    $telefonoLimpio = preg_replace('/[^0-9+]/', '', $telefono);
    
    $patrones = [
        '/^\+504[0-9]{8}$/',           // +50412345678
        '/^504[0-9]{8}$/',             // 50412345678
        '/^[0-9]{8}$/',                // 12345678
        '/^[2,3,7,8,9][0-9]{7}$/'      // Numeros que empiecen con 2,3,7,8,9
    ];
    
    foreach ($patrones as $patron) {
        if (preg_match($patron, $telefonoLimpio)) {
            return true;
        }
    }
    
    return false;
}

function obtenerIpCliente() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function registrarActividad($tipo, $mensaje, $db) {
    try {
        $query = "INSERT INTO logs_sistema (tipo_log, mensaje, ip_cliente, user_agent) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $tipo,
            $mensaje,
            obtenerIpCliente(),
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        return true;
    } catch(Exception $e) {
        $config = Config::getInstance();
        if ($config->shouldLogErrors()) {
            error_log("Error registrando actividad: " . $e->getMessage());
        }
        return false;
    }
}

function verificarRateLimiting($ip, $tabla, $db) {
    try {
        $config = Config::getInstance();
        $securityConfig = $config->getSecurityConfig();
        
        $tiempoLimite = date('Y-m-d H:i:s', time() - $securityConfig['rate_limit_window']);
        
        $query = "SELECT COUNT(*) as intentos FROM $tabla 
                 WHERE ip_cliente = ? AND fecha_creacion > ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$ip, $tiempoLimite]);
        $resultado = $stmt->fetch();
        
        return $resultado['intentos'] < $securityConfig['max_attempts'];
    } catch (Exception $e) {
        return true; // En caso de error, permitir el envio
    }
}

function enviarRespuestaJson($success, $mensaje, $datos = [], $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $mensaje,
        'data' => $datos
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function validarCamposRequeridos($datos, $camposRequeridos) {
    $errores = [];
    
    foreach ($camposRequeridos as $campo) {
        if (!isset($datos[$campo]) || empty(trim($datos[$campo]))) {
            $errores[$campo] = "El campo $campo es requerido";
        }
    }
    
    return $errores;
}

function generarToken($longitud = 32) {
    return bin2hex(random_bytes($longitud));
}

function formatearTelefono($telefono) {
    $telefonoLimpio = preg_replace('/[^0-9]/', '', $telefono);
    
    if (strlen($telefonoLimpio) == 8) {
        return '+504 ' . substr($telefonoLimpio, 0, 4) . '-' . substr($telefonoLimpio, 4, 4);
    }
    
    return $telefono;
}

function logError($mensaje, $contexto = []) {
    $config = Config::getInstance();
    if ($config->shouldLogErrors()) {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] $mensaje";
        if (!empty($contexto)) {
            $logMessage .= " Contexto: " . json_encode($contexto);
        }
        error_log($logMessage);
    }
}
?>