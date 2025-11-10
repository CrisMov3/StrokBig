<?php
// Habilitar errores para depuración (¡quitar en producción!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Cargar PHPMailer ---
// Ajusta esta ruta si tu carpeta 'vendor' está en otro lugar respecto a 'api'
require_once __DIR__ . '/../vendor/autoload.php'; // Asumiendo que 'vendor' está un nivel arriba de 'api'

// --- Usar Clases de PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// --- Headers para Respuesta JSON ---
header('Content-Type: application/json');
// header('Access-Control-Allow-Origin: *'); // Descomenta si es necesario
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// --- CONFIGURACIÓN ---
$destinatario = "cristian.lp2305@gmail.com"; // <-- CORREO DONDE RECIBIRÁS LOS MENSAJES
$asunto_prefijo = "Contacto interesado STB";

// --- Configuración SMTP (Gmail - Usa Contraseña de Aplicación) ---
$smtp_host = 'smtp.gmail.com';
$smtp_username = 'strokbigofficial@gmail.com'; // TU CORREO DE GMAIL/WORKSPACE
$smtp_password = 'eycvxtrtvhpyqlrv';        // TU CONTRASEÑA DE APLICACIÓN DE 16 DÍGITOS
$smtp_secure = PHPMailer::ENCRYPTION_SMTPS; // O ENCRYPTION_STARTTLS
$smtp_port = 465;                           // 465 para SMTPS, 587 para STARTTLS

// --- Configuración del Remitente Fijo ---
$nombre_remitente_fijo = "Notificaciones Strokbig";
// Usa un correo VERIFICADO en tu cuenta SMTP o uno genérico si tu servidor lo permite
$correo_remitente_fijo = "strokbigofficial@gmail.com"; // Puede ser el mismo que el de autenticación

// --- URL del Logo (¡REEMPLAZA ESTA URL con la URL pública!) ---
$url_logo = "URL_COMPLETA_DE_TU_LOGO"; // Ejemplo: https://www.strokbig.com/logo_email.png

// --- Respuesta por defecto ---
$response = ['success' => false, 'message' => 'Error desconocido.'];

// --- Validar Método POST ---
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    $response['message'] = 'Método no permitido.';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// --- Obtener y limpiar datos del formulario ---
$nombre = isset($_POST['nombre_completo']) ? htmlspecialchars($_POST['nombre_completo'], ENT_QUOTES, 'UTF-8') : null;
$correo = filter_input(INPUT_POST, 'correo_electronico', FILTER_SANITIZE_EMAIL);
$asunto_usuario = isset($_POST['asunto']) ? htmlspecialchars($_POST['asunto'], ENT_QUOTES, 'UTF-8') : null;
$mensaje_usuario = isset($_POST['mensaje']) ? htmlspecialchars($_POST['mensaje'], ENT_QUOTES, 'UTF-8') : null;

// --- Validaciones básicas ---
if (empty($nombre) || empty($correo) || empty($asunto_usuario) || empty($mensaje_usuario)) {
    $response['message'] = 'Por favor, complete todos los campos requeridos.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Por favor, ingrese una dirección de correo electrónico válida.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// --- Preparar Asunto y Cuerpos (HTML y Texto Plano) ---
$asunto_final = "$asunto_prefijo $asunto_usuario";

// --- Cuerpo HTML Mejorado ---
$cuerpo_mensaje_html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nuevo Mensaje de Contacto - Strokbig</title>
<style>
  body { font-family: \'Poppins\', Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; }
  .email-wrapper { background-color: #f4f7f6; padding: 20px; }
  .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
  .email-header { background-color: #0F172A; padding: 30px 20px; text-align: center; }
  .email-header img { max-width: 180px; height: auto; margin-bottom: 15px; }
  .email-header h1 { margin: 0; font-size: 22px; color: #ffffff; font-weight: 600;}
  .email-body { padding: 35px 30px; color: #334155; font-size: 15px; line-height: 1.6; }
  .email-body .data-section { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 25px; }
  .email-body .data-item { margin-bottom: 15px; }
  .email-body .data-item:last-child { margin-bottom: 0; }
  .email-body .label { font-weight: 600; color: #1e3a8a; display: block; margin-bottom: 4px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;}
  .email-body .value { color: #111827; font-weight: 500; }
  .email-body .message-label { margin-bottom: 8px; }
  .email-body .message-content { white-space: pre-wrap; word-wrap: break-word; font-size: 15px; color: #334155; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px;}
  .email-footer { background-color: #e2e8f0; padding: 20px; text-align: center; font-size: 12px; color: #475569; border-top: 1px solid #cbd5e1;}
  a { color: #1e6091; text-decoration: none; } a:hover { text-decoration: underline; }
</style>
</head>
<body style="font-family: \'Poppins\', Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; -webkit-font-smoothing: antialiased;">
  <div class="email-wrapper" style="background-color: #f4f7f6; padding: 20px;">
    <div class="email-container" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
      <div class="email-header" style="background-color: #28306e; padding: 30px 20px; text-align: center;">
        ' . (!empty($url_logo) && $url_logo !== "URL_COMPLETA_DE_TU_LOGO" ? '<img src="' . $url_logo . '" alt="Strokbig Logo" style="max-width: 180px; height: auto; margin-bottom: 15px;"><br>' : '') . '
        <h1 style="margin: 0; font-size: 22px; color: #ffffff; font-weight: 600;">Nuevo Mensaje de Contacto</h1>
      </div>
      <div class="email-body" style="padding: 35px 30px; color: #334155; font-size: 15px; line-height: 1.6;">
        <p style="margin: 0 0 25px 0;">Has recibido un nuevo mensaje desde el formulario web:</p>
        <div class="data-section" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 25px;">
          <div class="data-item" style="margin-bottom: 15px;"><span class="label" style="font-weight: 600; color: #1e3a8a; display: block; margin-bottom: 4px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Nombre:</span> <span class="value" style="color: #111827; font-weight: 500;">' . $nombre . '</span></div>
          <div class="data-item" style="margin-bottom: 15px;"><span class="label" style="font-weight: 600; color: #1e3a8a; display: block; margin-bottom: 4px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Correo Electrónico:</span> <span class="value" style="color: #111827; font-weight: 500;"><a href="mailto:' . $correo . '" style="color: #1e6091; text-decoration: none;">' . $correo . '</a></span></div>
          <div class="data-item" style="margin-bottom: 0;"><span class="label" style="font-weight: 600; color: #1e3a8a; display: block; margin-bottom: 4px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Asunto:</span> <span class="value" style="color: #111827; font-weight: 500;">' . $asunto_usuario . '</span></div>
        </div>
        <div><span class="label message-label" style="font-weight: 600; color: #1e3a8a; display: block; margin-bottom: 8px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Mensaje:</span><div class="message-content" style="white-space: pre-wrap; word-wrap: break-word; font-size: 15px; color: #334155; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px;">' . nl2br($mensaje_usuario) . '</div></div>
      </div>
      <div class="email-footer" style="background-color: #e2e8f0; padding: 20px; text-align: center; font-size: 12px; color: #475569; border-top: 1px solid #cbd5e1;">Enviado desde el formulario de contacto web de Strokbig.</div>
    </div>
  </div>
</body>
</html>';

// --- Cuerpo Texto Plano (Alternativo) ---
$cuerpo_mensaje_plain = "Has recibido un nuevo mensaje desde el formulario web de Strokbig:\n\n";
$cuerpo_mensaje_plain .= "Nombre: " . $nombre . "\n";
$cuerpo_mensaje_plain .= "Correo: " . $correo . "\n";
$cuerpo_mensaje_plain .= "Asunto: " . $asunto_usuario . "\n\n";
$cuerpo_mensaje_plain .= "Mensaje:\n" . $mensaje_usuario . "\n\n";
$cuerpo_mensaje_plain .= "---\nEnviado desde el formulario de contacto web.";


// --- Instanciar y Configurar PHPMailer ---
$mail = new PHPMailer(true);

try {
    // Configuración del servidor SMTP (tomada de tus variables)
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Descomenta para depuración detallada
    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_username;
    $mail->Password   = $smtp_password; // Contraseña de aplicación
    $mail->SMTPSecure = $smtp_secure;
    $mail->Port       = $smtp_port;
    $mail->CharSet    = 'UTF-8';

    // Remitente y Destinatarios
    $mail->setFrom($correo_remitente_fijo, $nombre_remitente_fijo); // Quién envía (configurado arriba)
    $mail->addAddress($destinatario);                               // A quién le llega (tu correo)
    $mail->addReplyTo($correo, $nombre);                            // A quién responder (el usuario)

    // Contenido
    $mail->isHTML(true);                                  // Establecer formato HTML
    $mail->Subject = $asunto_final;
    $mail->Body    = $cuerpo_mensaje_html;                // Cuerpo en HTML
    $mail->AltBody = $cuerpo_mensaje_plain;               // Cuerpo alternativo en texto plano

    // Enviar
    $mail->send();

    // Éxito
    $response['success'] = true;
    $response['message'] = '¡Mensaje enviado con éxito! Nos pondremos en contacto pronto.';
    http_response_code(200);

} catch (Exception $e) {
    // Error
    $response['message'] = "No se pudo enviar el mensaje. Error: {$mail->ErrorInfo}";
    error_log("Error PHPMailer: {$mail->ErrorInfo}. Datos: " . print_r($_POST, true));
    http_response_code(500);
}

// --- Enviar Respuesta JSON ---
echo json_encode($response);
?>