<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // SOLO PARA DESARROLLO

// Iniciar encabezados para JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// --- Configuración de la Base de Datos ---
$servername = "localhost";
$username = "stro_userroot";
$password = "StrokbigDB2025!"; // Revisa si esta sigue siendo tu contraseña
$dbname = "stro_strokbig_db";

$response = ['success' => false, 'message' => 'Error desconocido.'];

// Decodificar el JSON enviado desde JavaScript
$data = json_decode(file_get_contents('php://input'), true);
error_log("Datos recibidos: " . print_r($data, true)); // LOG 1: Ver datos crudos

if (!$data) {
    $response['message'] = 'No se recibieron datos (JSON nulo).';
    error_log($response['message']); // LOG Error
    echo json_encode($response);
    exit;
}

// Datos necesarios del JS
$identificacion = $data['identificacion'] ?? null; 
$uids_cuotas = $data['uids_cuotas'] ?? []; 
$amountPaid = $data['amountPaid'] ?? 0;
$method = $data['method'] ?? 'Desconocido';
$reference = $data['reference'] ?? 'SinReferencia';
$invoicesText = $data['invoicesText'] ?? '';
$wompiTransactionId = $data['wompiId'] ?? null; // Recibimos el ID de Wompi

if (empty($identificacion) || empty($uids_cuotas) || $amountPaid <= 0 || empty($wompiTransactionId)) {
    $response['message'] = 'Faltan datos para registrar el pago (ID, cuotas, monto o Wompi ID).';
    error_log($response['message'] . " Datos: " . print_r($data, true)); // LOG Error
    echo json_encode($response);
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $response['message'] = 'Error de conexión a la BD: ' . $conn->connect_error;
    error_log($response['message']); // LOG Error
    echo json_encode($response);
    exit;
}
$conn->set_charset("utf8mb4");

// --- VERIFICACIÓN DE PAGO EN WOMPI (SERVIDOR A SERVIDOR) ---
$WOMPI_PRIVATE_KEY = "prv_test_4PtwNcCtxxVYywvQ4Prr5cyf4szxxQmQ"; // Tu llave privada
$WOMPI_CHECK_URL = "https://api-sandbox.wompi.co/v1/transactions/" . $wompiTransactionId;

$ch = curl_init($WOMPI_CHECK_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $WOMPI_PRIVATE_KEY
]);

$wompi_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$wompi_result = json_decode($wompi_response, true);
error_log("Respuesta Verificación Wompi (HTTP $http_code): " . $wompi_response); // LOG 2: Ver respuesta Wompi

// Si Wompi no confirma el pago APROBADO, rechazamos el registro
if ($http_code != 200 || !isset($wompi_result['data']['status']) || $wompi_result['data']['status'] !== 'APPROVED') {
    $response['message'] = 'Verificación de Wompi fallida. El pago no fue aprobado o no se encontró la transacción.';
    $response['wompi_status'] = $wompi_result['data']['status'] ?? 'ERROR';
    error_log($response['message'] . " Wompi Status: " . $response['wompi_status']); // LOG Error Wompi
    echo json_encode($response);
    $conn->close();
    exit;
}
// --- FIN VERIFICACIÓN DE WOMPI ---

// Si la verificación es exitosa, procedemos a registrar en nuestra BD

// 1. Obtener el ID numérico del cliente
$cliente_id = null;
$stmt_cliente = $conn->prepare("SELECT id FROM clientes WHERE identificacion = ?");
if (!$stmt_cliente) {
     $response['message'] = 'Error preparando consulta cliente: ' . $conn->error;
     error_log($response['message']); // LOG Error SQL Prepare
     echo json_encode($response);
     $conn->close();
     exit;
}
$stmt_cliente->bind_param("s", $identificacion);
$stmt_cliente->execute();
$result_cliente = $stmt_cliente->get_result();
if ($result_cliente->num_rows > 0) {
    $cliente_id = $result_cliente->fetch_assoc()['id'];
    error_log("Cliente ID encontrado: $cliente_id para identificación: $identificacion"); // LOG 3: Cliente ID
} else {
     error_log("Cliente no encontrado para identificación: $identificacion"); // LOG Error Cliente no encontrado
}
$stmt_cliente->close();

if (!$cliente_id) {
    $response['message'] = 'No se pudo verificar el cliente (ID no encontrado en BD).';
    echo json_encode($response);
    $conn->close();
    exit;
}

// 2. Iniciar Transacción
$conn->begin_transaction();
error_log("Iniciando transacción para referencia: $reference"); // LOG 4: Inicio TX

try {
    // 3. Registrar el pago principal en la nueva tabla 'pagos_recibidos'
    $sql_pago = "INSERT INTO pagos_recibidos (cliente_id, referencia, monto_pagado, metodo_pago, cuotas_pagadas_desc) 
                 VALUES (?, ?, ?, ?, ?)";
    $stmt_pago = $conn->prepare($sql_pago);
    if (!$stmt_pago) { throw new Exception("Error preparando INSERT pagos_recibidos: " . $conn->error); }
    
    $stmt_pago->bind_param("isdss", $cliente_id, $reference, $amountPaid, $method, $invoicesText);
    $stmt_pago->execute();
    
    if ($stmt_pago->affected_rows <= 0) {
        // Importante: Chequear error específico si affected_rows es 0 o -1
        $insert_error = $stmt_pago->error ? "Error: " . $stmt_pago->error : "Affected rows fue 0.";
        throw new Exception("No se pudo registrar el pago principal. " . $insert_error);
    }
    error_log("Pago registrado en pagos_recibidos. ID: " . $stmt_pago->insert_id); // LOG 5: Pago Insertado
    $stmt_pago->close();

    // 4. Actualizar las 'cuotas_pagadas' en la tabla 'facturas'
    $cuotas_por_factura = [];
    foreach ($uids_cuotas as $uid) {
        $parts = explode('-', $uid); 
        // ¡Importante! Asegurarse de que el formato del ID sea consistente
        // Si puede haber IDs como SB-INV-MA01-1, necesitamos ajustar esto
        if (count($parts) < 3) {
             error_log("UID de cuota inválido recibido: $uid"); // LOG Error UID inválido
             continue; // Saltar este UID
        }
        // Asumiendo formato TIPO-CODIGO-NUMCUOTA (ej. SB-MA01-1)
        $factura_id_visible = $parts[0] . '-' . $parts[1]; 
        $cuota_num = (int)end($parts); // Tomar el último elemento como número de cuota
        
        error_log("Procesando UID: $uid -> Factura visible: $factura_id_visible, Cuota #: $cuota_num"); // LOG 6: Procesando UID

        if (!isset($cuotas_por_factura[$factura_id_visible])) {
            $cuotas_por_factura[$factura_id_visible] = [];
        }
        $cuotas_por_factura[$factura_id_visible][] = $cuota_num;
    }

    error_log("Cuotas agrupadas por factura: " . print_r($cuotas_por_factura, true)); // LOG 7: Cuotas agrupadas

    foreach ($cuotas_por_factura as $factura_id => $cuotas) {
        if (empty($cuotas)) continue; // Si no hay cuotas válidas para esta factura

        $max_cuota_pagada = max($cuotas); 
        error_log("Actualizando factura $factura_id (Cliente $cliente_id). Nueva max_cuota_pagada: $max_cuota_pagada"); // LOG 8: Intentando actualizar

        // Obtener cuotas_pagadas actual antes de actualizar
        $current_paid_installments = 0;
        $stmt_get_current = $conn->prepare("SELECT cuotas_pagadas FROM facturas WHERE factura_id_visible = ? AND cliente_id = ?");
        if ($stmt_get_current) {
            $stmt_get_current->bind_param("si", $factura_id, $cliente_id);
            $stmt_get_current->execute();
            $result_current = $stmt_get_current->get_result();
            if ($row_current = $result_current->fetch_assoc()) {
                $current_paid_installments = $row_current['cuotas_pagadas'];
            }
            $stmt_get_current->close();
            error_log("Factura $factura_id tiene actualmente cuotas_pagadas = $current_paid_installments"); // LOG 9: Cuotas actuales
        } else {
             error_log("Error preparando SELECT cuotas_pagadas: " . $conn->error);
        }


        // Actualizar SOLO si la nueva cuota máxima es MAYOR que la actual
        if ($max_cuota_pagada > $current_paid_installments) {
            $sql_update = "UPDATE facturas SET cuotas_pagadas = ? 
                           WHERE factura_id_visible = ? AND cliente_id = ?"; 
                           // Quitamos AND cuotas_pagadas < ? porque ya lo validamos arriba
            
            $stmt_update = $conn->prepare($sql_update);
            if (!$stmt_update) { throw new Exception("Error preparando UPDATE facturas ($factura_id): " . $conn->error); }
            
            $stmt_update->bind_param("isi", $max_cuota_pagada, $factura_id, $cliente_id);
            $stmt_update->execute();

            $affected_rows_update = $stmt_update->affected_rows;
            error_log("Resultado UPDATE factura $factura_id: Affected Rows = $affected_rows_update. Error: " . $stmt_update->error); // LOG 10: Resultado UPDATE
            
            // Aunque affected_rows sea 0, no necesariamente es un error fatal si la condición WHERE no coincidió (ya estaba actualizada?)
            // Pero si hay un error ($stmt_update->error), sí lanzamos excepción
             if ($stmt_update->error) {
                  throw new Exception("Error ejecutando UPDATE facturas ($factura_id): " . $stmt_update->error);
             }

            $stmt_update->close();
        } else {
             error_log("No se actualiza factura $factura_id porque max_cuota_pagada ($max_cuota_pagada) no es mayor que current_paid_installments ($current_paid_installments)."); // LOG 11: No se actualiza
        }
    }

    // 5. Confirmar la transacción
    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Pago registrado exitosamente.';
    $response['newReference'] = $reference;
    error_log("Transacción COMMIT exitosa para referencia: $reference"); // LOG 12: Commit

} catch (Exception $e) {
    // 6. Si algo falló, revertir
    $conn->rollback();
    $response['message'] = 'Error al registrar el pago en la BD: ' . $e->getMessage();
    error_log("Transacción ROLLBACK para referencia: $reference. Error: " . $e->getMessage()); // LOG Error Rollback
}

$conn->close();
error_log("Respuesta final enviada: " . json_encode($response)); // LOG 13: Respuesta final
echo json_encode($response);

?>
