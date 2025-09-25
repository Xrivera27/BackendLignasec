<?php
// test_endpoint_newsletter.php
echo "=== PROBANDO ENDPOINT NEWSLETTER.PHP ===\n\n";

// Simular datos POST
$_POST = [
    'email' => 'test@newsletter.com'
];

// Simular método POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_USER_AGENT'] = 'Test Script';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

echo "Datos POST simulados:\n";
print_r($_POST);
echo "\n";

echo "Ejecutando api/newsletter.php...\n";

// Capturar la salida del endpoint
ob_start();
include 'api/newsletter.php';
$output = ob_get_clean();

echo "Respuesta del endpoint:\n";
echo $output . "\n";

echo "\n=== FIN TEST NEWSLETTER ENDPOINT ===\n";
?>