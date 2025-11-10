<?php
// Enable error reporting for debugging (remove in production!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Or your specific admin panel origin

// --- DB Configuration ---
$servername = "localhost";
$username = "root";
$password = ""; // <-- YOUR MYSQL PASSWORD HERE
$dbname = "strokbig_db";

$response = ['success' => false, 'message' => 'Cliente no encontrado.'];
$cliente_encontrado = null;

// Get identification from URL parameter
$identificacion = $_GET['id'] ?? null;

if (empty($identificacion)) {
    $response['message'] = 'No se proporcionó identificación.';
    echo json_encode($response);
    exit;
}

// Connect to DB
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $response['message'] = 'Error de conexión a la BD: ' . $conn->connect_error;
    // Log the error instead of showing it to the user in production
    error_log('DB Connection Error in buscar_cliente_simple: ' . $conn->connect_error);
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
    exit;
}
$conn->set_charset("utf8mb4");

// Prepare query using 'nombre' and 'tipo'
$sql = "SELECT nombre, tipo FROM clientes WHERE identificacion = ? LIMIT 1"; // <-- Corrected columns

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $response['message'] = 'Error al preparar la consulta: ' . $conn->error;
    error_log('SQL Prepare Error in buscar_cliente_simple: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
    $conn->close();
    exit;
}

// Bind parameter and execute
$stmt->bind_param("s", $identificacion);
$stmt->execute();
$result = $stmt->get_result();

// Check if client was found
if ($result->num_rows > 0) {
    $cliente_encontrado = $result->fetch_assoc();
    $response['success'] = true;
    $response['message'] = 'Cliente encontrado.';

    // Create the 'cliente' array using the correct column names ('nombre', 'tipo')
    // But keep the keys ('nombre_completo', 'tipo_cliente') that the JavaScript expects
    $response['cliente'] = [
        'nombre_completo' => $cliente_encontrado['nombre'], // <-- Use value from 'nombre' column
        'tipo_cliente' => $cliente_encontrado['tipo']      // <-- Use value from 'tipo' column
    ];

} else {
    // Not found, keep success = false and default message
}

// Close statement and connection
$stmt->close();
$conn->close();

// Send JSON response
echo json_encode($response);
?>