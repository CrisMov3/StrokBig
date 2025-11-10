<?php
// Iniciar encabezados para JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

// --- Configuración de la Base de Datos ---
$servername = "localhost";
$username = "root";
$password = ""; // <-- PON TU CONTRASEÑA DE MYSQL AQUÍ
$dbname = "strokbig_db";

// Respuesta por defecto
$response = ['success' => false, 'message' => 'No se encontraron datos.'];
$identificacion_buscada = isset($_GET['id']) ? trim(strtoupper($_GET['id'])) : '';

if (empty($identificacion_buscada)) {
    $response['message'] = 'Identificación o referencia no proporcionada.';
    echo json_encode($response);
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $response['message'] = 'Error de conexión a la base de datos.';
    echo json_encode($response);
    exit;
}
$conn->set_charset("utf8mb4");

// Prepara la búsqueda
// Primero, intentamos buscar por ID de cliente
$sql_cliente = "SELECT id, identificacion, nombre, tipo FROM clientes WHERE identificacion = ?"; // <-- AÑADIDO 'identificacion'
$stmt_cliente = $conn->prepare($sql_cliente);
$stmt_cliente->bind_param("s", $identificacion_buscada);
$stmt_cliente->execute();
$result_cliente = $stmt_cliente->get_result();

$cliente_id = null;
$user_data = null;

if ($result_cliente->num_rows > 0) {
    // --- Encontrado por ID de Usuario ---
    $cliente = $result_cliente->fetch_assoc();
    $cliente_id = $cliente['id'];
    $user_data = [
        'identificacion' => $cliente['identificacion'], // <-- AÑADIDO
        'name' => $cliente['nombre'],
        'type' => $cliente['tipo'],
        'invoices' => []
    ];
} else {
    // --- No se encontró por ID, buscar por ID de Factura ---
    $sql_factura = "SELECT f.*, c.id AS cliente_id_encontrado, c.identificacion, c.nombre, c.tipo 
                    FROM facturas f 
                    JOIN clientes c ON f.cliente_id = c.id
                    WHERE f.factura_id_visible = ?"; // <-- AÑADIDO 'c.identificacion'
    $stmt_factura_unica = $conn->prepare($sql_factura);
    $stmt_factura_unica->bind_param("s", $identificacion_buscada);
    $stmt_factura_unica->execute();
    $result_factura_unica = $stmt_factura_unica->get_result();
    
    if ($result_factura_unica->num_rows > 0) {
        $factura_encontrada = $result_factura_unica->fetch_assoc();
        $cliente_id = $factura_encontrada['cliente_id_encontrado'];
        $user_data = [
            'identificacion' => $factura_encontrada['identificacion'], // <-- AÑADIDO
            'name' => $factura_encontrada['nombre'],
            'type' => $factura_encontrada['tipo'],
            'invoices' => []
        ];
        $user_data['invoices'][] = [
            'id' => $factura_encontrada['factura_id_visible'],
            'concept' => $factura_encontrada['concepto'],
            'amount' => (float)$factura_encontrada['monto_total'],
            'dueDate' => $factura_encontrada['fecha_base_vencimiento'],
            'totalInstallments' => (int)$factura_encontrada['total_cuotas'],
            'paidInstallments' => (int)$factura_encontrada['cuotas_pagadas']
        ];
    }
    $stmt_factura_unica->close();
}
$stmt_cliente->close();

if ($cliente_id && empty($user_data['invoices'])) {
    $sql_facturas = "SELECT factura_id_visible, concepto, monto_total, total_cuotas, cuotas_pagadas, fecha_base_vencimiento 
                     FROM facturas 
                     WHERE cliente_id = ?";
    $stmt_facturas = $conn->prepare($sql_facturas);
    $stmt_facturas->bind_param("i", $cliente_id);
    $stmt_facturas->execute();
    $result_facturas = $stmt_facturas->get_result();
    
    while ($row = $result_facturas->fetch_assoc()) {
        $user_data['invoices'][] = [
            'id' => $row['factura_id_visible'],
            'concept' => $row['concepto'],
            'amount' => (float)$row['monto_total'],
            'dueDate' => $row['fecha_base_vencimiento'],
            'totalInstallments' => (int)$row['total_cuotas'],
            'paidInstallments' => (int)$row['cuotas_pagadas']
        ];
    }
    $stmt_facturas->close();
}

if ($user_data && !empty($user_data['invoices'])) {
    $response['success'] = true;
    $response['user'] = $user_data;
    $response['message'] = 'Datos encontrados.';
} else if ($user_data && empty($user_data['invoices'])) {
     $response['success'] = true;
     $response['user'] = $user_data;
     $response['message'] = 'No se encontraron facturas pendientes.';
} else {
     $response['message'] = 'Número de identificación o referencia inválido.';
}

$conn->close();
echo json_encode($response);
exit;
?>