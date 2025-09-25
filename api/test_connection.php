<?php
header('Content-Type: application/json; charset=utf-8');

// AGREGAR ESTAS LÍNEAS QUE FALTAN:
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../classes/EmailService.php';

try {
    $config = Config::getInstance();
    $database = new Database();
    $emailService = new EmailService();
    
    // Probar conexion a base de datos
    $dbInfo = $database->getDbInfo();
    $dbConectada = $database->testConnection();
    
    // Probar configuracion de email
    $emailConfig = $emailService->probarConfiguracion();
    
    $resultado = [
        'success' => true,
        'message' => 'Prueba de conexiones completada',
        'data' => [
            'database' => [
                'conectada' => $dbConectada,
                'info' => $dbInfo
            ],
            'config' => [
                'env_cargado' => $config->has('DB_HOST'),
                'debug_mode' => $config->isDebugMode(),
                'site_config' => $config->getSiteConfig()
            ],
            'email' => $emailConfig
        ]
    ];
    
    enviarRespuestaJson(true, 'Conexiones probadas exitosamente', $resultado['data']);
    
} catch (Exception $e) {
    enviarRespuestaJson(false, 'Error en las pruebas: ' . $e->getMessage(), [], 500);
}
?>