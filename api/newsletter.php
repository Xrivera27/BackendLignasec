<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespuestaJson(false, 'Metodo no permitido', [], 405);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/NewsletterHandler.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $newsletterHandler = new NewsletterHandler($db);
    
    // Pasar todo el $_POST para que maneje los campos malformateados
    $resultado = $newsletterHandler->procesarSuscripcion($_POST);
    
    enviarRespuestaJson($resultado['success'], $resultado['message'], $resultado);
    
} catch (Exception $e) {
    logError("Error en newsletter API: " . $e->getMessage());
    enviarRespuestaJson(false, 'Error del servidor', [], 500);
}
?>