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

// ===== GUARDAR EN GOOGLE SHEETS (con timeout) =====
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

    $ch = curl_init($sheetsWebhook);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 segundos máximo
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // si tienes problemas de SSL

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("Error en Google Sheets: " . curl_error($ch));
    }
    curl_close($ch);
}

// ===== ENVIAR CORREO CON PHPMailer =====
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'];
    $mail->Password   = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;

    $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME'] ?? 'NOWA Web');
    $mail->addAddress($_ENV['SMTP_TO'] ?? 'contacto@gruponowa.com.ar');
    $mail->addReplyTo($email ?: $_ENV['SMTP_FROM'], $nombre);

    $mail->isHTML(true);
    $mail->Subject = 'Nueva consulta desde el sitio NOWA';

    // Logo (ajusta la URL al logo que tengas en tu servidor)
    $logoUrl = 'https://gruponowa.com.ar/img/logo/logo_nowa_w.png';

    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Nueva consulta NOWA</title>
        <style>
            body {
                font-family: Arial, Helvetica, sans-serif;
                background-color: #f4f1ec;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 20px auto;
                background-color: #ffffff;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            }
            .header {
                background-color: #0F1F2B;
                padding: 30px 20px;
                text-align: center;
            }
            .header img {
                max-width: 180px;
                height: auto;
            }
            .content {
                padding: 30px 30px 20px;
            }
            .content h1 {
                font-size: 24px;
                color: #0F1F2B;
                margin-top: 0;
                margin-bottom: 8px;
                font-weight: 700;
            }
            .content p {
                color: #4A5568;
                font-size: 16px;
                line-height: 1.6;
                margin: 0 0 20px 0;
            }
            .divider {
                border: none;
                border-top: 2px solid #eae6df;
                margin: 24px 0;
            }
            .field-group {
                margin-bottom: 16px;
            }
            .field-label {
                font-weight: 600;
                color: #0F1F2B;
                font-size: 14px;
                display: block;
                margin-bottom: 2px;
            }
            .field-value {
                color: #2E6DA4;
                font-size: 16px;
                padding: 8px 12px;
                background-color: #f8f6f3;
                border-radius: 6px;
                display: inline-block;
                width: 100%;
                box-sizing: border-box;
            }
            .field-value-multiline {
                color: #4A5568;
                font-size: 15px;
                padding: 10px 12px;
                background-color: #f8f6f3;
                border-radius: 6px;
                display: block;
                white-space: pre-wrap;
                word-wrap: break-word;
            }
            .footer {
                background-color: #f8f6f3;
                padding: 20px 30px;
                text-align: center;
                border-top: 1px solid #eae6df;
            }
            .footer p {
                font-size: 13px;
                color: #8A96A3;
                margin: 4px 0;
            }
            .footer a {
                color: #2E6DA4;
                text-decoration: none;
            }
            .footer a:hover {
                text-decoration: underline;
            }
            .badge {
                display: inline-block;
                background-color: #2E6DA4;
                color: #ffffff;
                font-size: 12px;
                font-weight: 600;
                padding: 4px 12px;
                border-radius: 20px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            @media (max-width: 480px) {
                .content {
                    padding: 20px 16px;
                }
                .header {
                    padding: 20px 16px;
                }
                .header img {
                    max-width: 140px;
                }
                .field-value {
                    font-size: 14px;
                    padding: 6px 10px;
                }
                .field-value-multiline {
                    font-size: 14px;
                    padding: 8px 10px;
                }
                .footer {
                    padding: 16px 16px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <!-- HEADER -->
            <div class="header">
                <img src="' . $logoUrl . '" alt="Grupo NOWA">
            </div>

            <!-- CONTENIDO -->
            <div class="content">
                <h1>📩 Nueva consulta recibida</h1>
                <p>Un usuario ha completado el formulario de contacto en el sitio web de NOWA.</p>
                <hr class="divider">

                <div class="field-group">
                    <span class="field-label">👤 Nombre / Empresa</span>
                    <div class="field-value">' . $nombre . '</div>
                </div>

                <div class="field-group">
                    <span class="field-label">📱 WhatsApp</span>
                    <div class="field-value"><a href="https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsapp) . '" style="color:#2E6DA4;text-decoration:none;">' . $whatsapp . '</a></div>
                </div>

                <div class="field-group">
                    <span class="field-label">📧 Email</span>
                    <div class="field-value">' . ($email ? '<a href="mailto:' . $email . '" style="color:#2E6DA4;text-decoration:none;">' . $email . '</a>' : 'No especificado') . '</div>
                </div>

                <div class="field-group">
                    <span class="field-label">🏷️ Rubro</span>
                    <div class="field-value"><span class="badge">' . $rubro . '</span></div>
                </div>';

    // Si hay mensaje adicional, lo añadimos
    if (!empty($mensaje)) {
        $mail->Body .= '
                <div class="field-group">
                    <span class="field-label">💬 Mensaje</span>
                    <div class="field-value-multiline">' . nl2br(htmlspecialchars($mensaje)) . '</div>
                </div>';
    }

    $mail->Body .= '
                <hr class="divider">
                <p style="font-size:14px;color:#8A96A3;text-align:center;margin-top:10px;">
                    Esta consulta fue enviada desde el formulario de contacto de <strong>NOWA</strong>.
                </p>
            </div>

            <!-- FOOTER -->
            <div class="footer">
                <p><strong>Grupo NOWA</strong> — Conectamos fábricas del mundo con Latinoamérica</p>
                <p>📧 <a href="mailto:contacto@gruponowa.com.ar">contacto@gruponowa.com.ar</a> &nbsp;·&nbsp; 📱 <a href="https://wa.me/5491122894924">+54 9 11 2289-4924</a></p>
                <p style="margin-top:8px;">🌎 Argentina · Uruguay · Brasil · USA</p>
                <p style="margin-top:12px;font-size:11px;color:#B0B0B0;">
                    Este mensaje fue generado automáticamente. Por favor, no responder a este correo.<br>
                    <a href="https://gruponowa.com.ar" style="color:#2E6DA4;">gruponowa.com.ar</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ';
    $mail->AltBody = "Nueva consulta:\nNombre: $nombre\nWhatsApp: $whatsapp\nEmail: $email\nRubro: $rubro\nMensaje: $mensaje";

    $mail->send();
} catch (Exception $e) {
    error_log("Error al enviar correo: {$mail->ErrorInfo}");
    // No detenemos el proceso, solo registramos error
}

// ===== RESPUESTA EXITOSA =====
echo json_encode(['success' => true, 'message' => 'Consulta enviada correctamente']);