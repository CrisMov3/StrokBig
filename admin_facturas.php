<?php
// --- Configuración de la Base de Datos ---
$servername = "localhost";
$username = "root";
$password = "";        // <-- TU CONTRASEÑA MYSQL AQUÍ
$dbname = "strokbig_db";

// Variable para mensajes
$mensaje = "";
$error = "";

// --- Lógica para procesar el formulario ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        $error = "Error de conexión a la base de datos: " . $conn->connect_error;
        error_log("DB Connection Error in admin_facturas: " . $conn->connect_error);
    } else {
        $conn->set_charset("utf8mb4");

        // Datos del Cliente
        $identificacion = isset($_POST['identificacion']) ? $conn->real_escape_string($_POST['identificacion']) : null;
        $nombre = isset($_POST['nombre']) ? $conn->real_escape_string($_POST['nombre']) : null;
        $tipo = isset($_POST['tipo']) ? $conn->real_escape_string($_POST['tipo']) : null; // Correct variable used

        // Datos de la Factura
        $concepto = isset($_POST['concepto']) ? $conn->real_escape_string($_POST['concepto']) : null;
        $monto_total = isset($_POST['monto_total']) ? floatval($_POST['monto_total']) : 0;
        $total_cuotas = isset($_POST['total_cuotas']) ? intval($_POST['total_cuotas']) : 1;
        $fecha_base_vencimiento = isset($_POST['fecha_base_vencimiento']) ? $conn->real_escape_string($_POST['fecha_base_vencimiento']) : null;
        $cuotas_pagadas = 0;

        if (!$identificacion || !$nombre || !$tipo || !$concepto || $monto_total <= 0 || $total_cuotas < 1 || !$fecha_base_vencimiento) {
             $error = "Error: Faltan datos requeridos o son inválidos.";
             // Log detailed info for debugging missing fields
             error_log("Missing data error. POST data: " . print_r($_POST, true));
        } else {
            // --- Lógica de ID de Factura Automático ---
            $factura_id_visible = "";
            $is_id_unique = false;
            $max_tries = 10;
            $tries = 0;

            while (!$is_id_unique && $tries < $max_tries) {
                $factura_id_visible = "SB-" . rand(100000, 999999);
                $sql_check_id = "SELECT 1 FROM facturas WHERE factura_id_visible = ?";
                $stmt_check = $conn->prepare($sql_check_id);
                 if ($stmt_check) {
                    $stmt_check->bind_param("s", $factura_id_visible);
                    $stmt_check->execute();
                    $stmt_check->store_result();
                    if ($stmt_check->num_rows == 0) {
                        $is_id_unique = true;
                    }
                    $stmt_check->close();
                 } else {
                     $error = "Error preparando verificación de ID factura: " . $conn->error;
                     break;
                 }
                $tries++;
            }

            if (!$is_id_unique && !$error) {
                 $error = "No se pudo generar un ID de factura único después de $max_tries intentos.";
            }

            if (!$error && $is_id_unique) {
                // 1. Insertar o actualizar el cliente (UPSERT)
                $sql_cliente = "INSERT INTO clientes (identificacion, nombre, tipo)
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), tipo = VALUES(tipo)"; // Using 'nombre' and 'tipo'

                $stmt_cliente = $conn->prepare($sql_cliente);

                if(!$stmt_cliente){
                     $error = "Error al preparar consulta cliente: " . $conn->error;
                } else {
                    $stmt_cliente->bind_param("sss", $identificacion, $nombre, $tipo);

                    if ($stmt_cliente->execute()) {
                        $cliente_id = $conn->insert_id;
                        if ($cliente_id == 0) {
                            $result = $conn->query("SELECT id FROM clientes WHERE identificacion = '$identificacion'");
                             if ($result && $result->num_rows > 0) {
                                $cliente_id = $result->fetch_assoc()['id'];
                             } else {
                                 $error = "Error crítico: No se pudo obtener el ID del cliente '$identificacion' después de guardar.";
                                 $cliente_id = null;
                             }
                        }

                        if ($cliente_id) {
                            $sql_factura = "INSERT INTO facturas (cliente_id, factura_id_visible, concepto, monto_total, total_cuotas, cuotas_pagadas, fecha_base_vencimiento)
                                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $stmt_factura = $conn->prepare($sql_factura);
                            if(!$stmt_factura){
                                 $error = "Error al preparar consulta factura: " . $conn->error;
                            } else {
                                $stmt_factura->bind_param("isssdis", $cliente_id, $factura_id_visible, $concepto, $monto_total, $total_cuotas, $cuotas_pagadas, $fecha_base_vencimiento);
                                if ($stmt_factura->execute()) {
                                    $mensaje = "¡Cliente registrado/actualizado y factura '$factura_id_visible' creada exitosamente!";
                                } else {
                                    $error = "Error al registrar la factura: " . $stmt_factura->error;
                                }
                                $stmt_factura->close();
                            }
                        }
                    } else {
                        $error = "Error al registrar/actualizar el cliente: " . $stmt_cliente->error;
                    }
                    $stmt_cliente->close();
                }
            }
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Registrar Facturas</title>
    <style>
        body { font-family: sans-serif; background: #f0f4f8; margin: 0; padding: 20px; }
        .admin-container { max-width: 800px; margin: 20px auto; }
        .admin-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        h2 { color: #042a63; border-bottom: 2px solid #042a63; padding-bottom: 10px; margin-bottom: 20px; }
        .form-section { margin-bottom: 25px; border-bottom: 1px solid #e2e8f0; padding-bottom: 25px; }
        .form-section:last-of-type { border-bottom: none; }
        .form-section h3 { margin-bottom: 15px; color: #334155; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 5px; font-weight: 600; color: #475569; }
        .form-group input, .form-group select { padding: 10px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 1rem; box-sizing: border-box; width: 100%; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #042a63; box-shadow: 0 0 0 2px rgba(4, 42, 99, 0.2); }
        .form-group input:read-only, .form-group select:disabled { background-color: #f1f5f9; cursor: not-allowed; opacity: 0.7; }
        #cliente-status { font-size: 0.85em; margin-top: 5px; padding: 4px 8px; border-radius: 3px; display: inline-block; }
        .btn-submit { background: #042a63; color: white; border: 0; padding: 12px 25px; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: background 0.3s ease; margin-top: 20px; display: inline-block; }
        .btn-submit:hover { background: #03306b; }
        .mensaje { padding: 15px; background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 5px; margin-bottom: 20px; }
        .error { padding: 15px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-card">
            <h2>Panel de Administración de Facturas</h2>

            <?php if ($mensaje): ?>
                <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form id="factura-form" action="admin_facturas.php" method="POST">

                <div class="form-section">
                    <h3>Datos del Cliente</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="identificacion_cliente">Identificación (ID de Usuario)</label>
                            <input type="text" id="identificacion_cliente" name="identificacion" required>
                            <small id="cliente-status" style="display: none;"></small>
                        </div>
                        <div class="form-group">
                             <label for="tipo_cliente">Tipo de Cliente</label>
                             <select id="tipo_cliente" name="tipo" required>
                                 <option value="">Seleccione...</option>
                                 <option value="natural">Persona Natural</option>
                                 <option value="juridica">Persona Jurídica</option>
                             </select>
                        </div>
                         <div class="form-group" style="grid-column: span 2;">
                            <label for="nombre_cliente">Nombre Completo</label>
                            <input type="text" id="nombre_cliente" name="nombre" required>
                        </div>
                    </div>
                     <small style="display: block; margin-top: 10px; color: #64748b;">Si la identificación ya existe, se cargarán y bloquearán el nombre y tipo. De lo contrario, ingréselos.</small>
                </div>

                <div class="form-section">
                    <h3>Datos de la Factura</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="concepto">Concepto</label>
                            <select id="concepto" name="concepto" required>
                                <option value="" disabled selected>Selecciona un concepto...</option>
                                <option value="Licencia Básico">Licencia Básico</option>
                                <option value="Licencia Profesional">Licencia Profesional</option>
                                <option value="Licencia Avanzada">Licencia Avanzada</option>
                                <option value="Mantenimiento de Computadores">Mantenimiento de Computadores</option>
                                <option value="Soporte tecnico">Soporte tecnico</option>
                                <option value="Desarrollo Web">Desarrollo Web</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="monto_total">Monto Total (Ej: 1500000)</label>
                            <input type="number" step="0.01" id="monto_total" name="monto_total" required>
                        </div>
                        <div class="form-group">
                            <label for="total_cuotas">Total de Cuotas</label>
                            <input type="number" id="total_cuotas" name="total_cuotas" min="1" value="1" required>
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="fecha_base_vencimiento">Fecha Vencimiento (1ra Cuota)</label>
                            <input type="date" id="fecha_base_vencimiento" name="fecha_base_vencimiento" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Registrar Factura</button>
            </form>
        </div>
    </div>

    <script src="Script/admin_facturas.js" defer></script>

</body>
</html>