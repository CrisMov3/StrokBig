<?php
// Iniciar encabezados para JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// --- Configuración de la Base de Datos ---
$servername = "localhost";
$username = "root";
$password = ""; // <-- ¡¡¡PON TU CONTRASEÑA DE MYSQL AQUÍ!!!
$dbname = "strokbig_db";

$response = ['success' => false, 'message' => 'Error desconocido.'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $response['message'] = 'Error de conexión a la BD: ' . $conn->connect_error;
    echo json_encode($response);
    exit;
}
$conn->set_charset("utf8mb4");

try {
    // Consulta para obtener todos los pagos y el nombre del cliente asociado
    $sql = "SELECT pr.id, c.nombre AS cliente_nombre, pr.referencia, pr.monto_pagado, 
                   pr.metodo_pago, pr.fecha_pago, pr.cuotas_pagadas_desc
            FROM pagos_recibidos pr
            JOIN clientes c ON pr.cliente_id = c.id
            ORDER BY pr.fecha_pago DESC"; // Ordena por fecha de pago, los más recientes primero

    $result = $conn->query($sql);

    if ($result) {
        $pagos = [];
        while ($row = $result->fetch_assoc()) {
            $pagos[] = $row;
        }
        $response['success'] = true;
        $response['message'] = 'Pagos obtenidos exitosamente.';
        $response['data'] = $pagos;
    } else {
        throw new Exception("Error al ejecutar la consulta: " . $conn->error);
    }

} catch (Exception $e) {
    $response['message'] = 'Error al obtener los pagos: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>