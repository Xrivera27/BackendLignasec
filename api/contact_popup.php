<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// AGREGAR ESTA LÍNEA:
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespuestaJson(false, 'Metodo no permitido', [], 405);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ContactHandler.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $contactHandler = new ContactHandler($db);
    
    $datos = $_POST;
    $resultado = $contactHandler->procesarContactoPopup($datos);
    
    enviarRespuestaJson($resultado['success'], $resultado['message'], $resultado);
    
} catch (Exception $e) {
    logError("Error en contact_popup API: " . $e->getMessage());
    enviarRespuestaJson(false, 'Error del servidor', [], 500);
}
?>