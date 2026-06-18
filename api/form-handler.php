<?php
header('Content-Type: application/json');

// ===== CARGAR VARIABLES DE ENTORNO =====
require_once __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ===== RECIBIR DATOS =====
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// ===== VALIDAR CAMPOS OBLIGATORIOS =====
$required = ['nombre', 'whatsapp', 'rubro'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "El campo '$field' es obligatorio"]);
        exit;
    }
}

// ===== VALIDAR honeypot =====
if (!empty($input['website'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bot detectado']);
    exit;
}

// ===== VALIDAR TIMESTAMP (mínimo 2 segundos) =====
$timestamp = (int)($input['timestamp'] ?? 0);
$elapsed = time() - ($timestamp / 1000);
if ($elapsed < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Envío demasiado rápido']);
    exit;
}

// ===== VALIDAR reCAPTCHA =====
$recaptchaToken = $input['recaptcha_token'] ?? '';
$secretKey = $_ENV['RECAPTCHA_SECRET_KEY'] ?? '';
if ($secretKey && $recaptchaToken) {
    $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secretKey,
        'response' => $recaptchaToken
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($verifyUrl, false, $context);
    $response = json_decode($result, true);
    if (!$response['success'] || $response['score'] < 0.5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Error de verificación de seguridad']);
        exit;
    }
} else {
    // Si no hay clave, omitimos (pero en producción debe estar configurada)
    error_log('reCAPTCHA no configurado');
}

// ===== SANITIZAR DATOS =====
$nombre = htmlspecialchars(strip_tags($input['nombre']));
$whatsapp = htmlspecialchars(strip_tags($input['whatsapp']));
$email = isset($input['email']) ? htmlspecialchars(strip_tags($input['email'])) : '';
$rubro = htmlspecialchars(strip_tags($input['rubro']));
$mensaje = isset($input['mensaje']) ? htmlspecialchars(strip_tags($input['mensaje'])) : '';

// ===== GUARDAR EN GOOGLE SHEETS =====
$sheetsWebhook = $_ENV['GOOGLE_SHEETS_WEBHOOK_URL'] ?? '';
if ($sheetsWebhook) {
    $data = [
        'nombre' => $nombre,
        'whatsapp' => $whatsapp,
        'email' => $email,
        'rubro' => $rubro,
        'mensaje' => $mensaje,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data)
        ]
    ];
    $context = stream_context_create($options);
    file_get_contents($sheetsWebhook, false, $context);
    // No verificamos respuesta para no bloquear
}

// ===== ENVIAR CORREO CON PHPMailer =====
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'];
    $mail->Password   = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = $_ENV['SMTP_SECURE'] ?? 'tls';
    $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;

    $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME'] ?? 'NOWA Web');
    $mail->addAddress($_ENV['SMTP_TO'] ?? 'contacto@gruponowa.com.ar');
    $mail->addReplyTo($email ?: $_ENV['SMTP_FROM'], $nombre);

    $mail->isHTML(true);
    $mail->Subject = 'Nueva consulta desde el sitio NOWA';
    $mail->Body = "
        <h2>Nuevo mensaje de contacto</h2>
        <p><strong>Nombre / Empresa:</strong> $nombre</p>
        <p><strong>WhatsApp:</strong> $whatsapp</p>
        <p><strong>Email:</strong> " . ($email ?: 'No especificado') . "</p>
        <p><strong>Rubro:</strong> $rubro</p>
        <p><strong>Mensaje:</strong><br>" . nl2br($mensaje ?: 'Sin mensaje adicional') . "</p>
    ";
    $mail->AltBody = "Nueva consulta:\nNombre: $nombre\nWhatsApp: $whatsapp\nEmail: $email\nRubro: $rubro\nMensaje: $mensaje";

    $mail->send();
} catch (Exception $e) {
    error_log("Error al enviar correo: {$mail->ErrorInfo}");
    // No detenemos el proceso, solo registramos error
}

// ===== RESPUESTA EXITOSA =====
echo json_encode(['success' => true, 'message' => 'Consulta enviada correctamente']);