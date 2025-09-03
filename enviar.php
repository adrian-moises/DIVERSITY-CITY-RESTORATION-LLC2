<?php
// enviar.php
declare(strict_types=1);
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=UTF-8');

// ======== CONFIG =========
// SMTP (cPanel) — usa SIEMPRE tu buzón creado en cPanel para autenticar
const SMTP_HOST   = 'mail.tu-dominio.com';          // <-- CAMBIA
const SMTP_PORT   = 465;                            // 465=SSL, 587=TLS
const SMTP_SECURE = 'ssl';                          // 'ssl' o 'tls'
const SMTP_USER   = 'notificaciones@tu-dominio.com'; // <-- CAMBIA (correo en tu dominio)
const SMTP_PASS   = 'TU_PASSWORD';                  // <-- CAMBIA

// Correo del destinatario final (fijo)
const MAIL_TO     = 'paulcanas7@gmail.com';         // <-- Aquí recibes tú
const MAIL_TO_N   = 'Paul';                         // Nombre opcional
// Adjuntos
const MAX_MB      = 5;
$ALLOWED_MIME = ['image/jpeg','image/png','application/pdf'];
// =========================

// PHPMailer
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

// Helper para errores
function bad($msg, $code = 400) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

// Sanitización básica
function clean($v) {
  return htmlspecialchars(trim((string)($v ?? '')), ENT_QUOTES, 'UTF-8');
}

// Solo permitimos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('Invalid method', 405);

// Campos del formulario
$nombre    = clean($_POST['nombre']  ?? '');
$email     = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$asunto    = clean($_POST['asunto']   ?? '');
$direccion = clean($_POST['direccion']?? '');
$mensaje   = clean($_POST['mensaje']  ?? '');

if (!$nombre || !$email || !$asunto || !$mensaje) {
  bad('Missing fields');
}

// Validar archivo (opcional)
$hasFile = isset($_FILES['archivo']) && is_uploaded_file($_FILES['archivo']['tmp_name']);
if ($hasFile) {
  $size = (int)$_FILES['archivo']['size'];
  $mime = mime_content_type($_FILES['archivo']['tmp_name']) ?: '';
  if ($size > MAX_MB*1024*1024) bad('File too large (max '.MAX_MB.'MB)');
  if (!in_array($mime, $ALLOWED_MIME, true)) bad('Unsupported file type');
}

try {
  $mail = new PHPMailer(true);
  $mail->CharSet  = 'UTF-8';
  $mail->isSMTP();
  $mail->Host     = SMTP_HOST;
  $mail->SMTPAuth = true;
  $mail->Username = SMTP_USER;   // SIEMPRE el correo de tu dominio
  $mail->Password = SMTP_PASS;
  $mail->SMTPSecure = SMTP_SECURE;
  $mail->Port     = SMTP_PORT;

  // *** IMPORTANTE ***
  // Aunque usamos SMTP_USER para autenticar, aquí mostramos que el remitente
  // es el correo que escribió el usuario:
  $mail->setFrom($email, $nombre);     // <- remitente real
  $mail->addReplyTo($email, $nombre);  // <- responderá al usuario
  $mail->addAddress(MAIL_TO, MAIL_TO_N); // <- destinatario fijo (tú)

  // Adjuntar archivo si existe
  if ($hasFile) {
    $mail->addAttachment($_FILES['archivo']['tmp_name'], $_FILES['archivo']['name']);
  }

  // Contenido del correo
  $mail->isHTML(true);
  $mail->Subject = "Free Quote Request: {$asunto}";
  $mail->Body = "
    <h2>New Free Quote Request</h2>
    <p><strong>Name:</strong> {$nombre}</p>
    <p><strong>Email:</strong> {$email}</p>
    <p><strong>Subject:</strong> {$asunto}</p>
    <p><strong>Address:</strong> {$direccion}</p>
    <p><strong>Message:</strong></p>
    <p style='white-space:pre-wrap'>{$mensaje}</p>
  ";
  $mail->AltBody = "Name: {$nombre}\nEmail: {$email}\nSubject: {$asunto}\nAddress: {$direccion}\nMessage:\n{$mensaje}";

  $mail->send();
  echo json_encode(['ok' => true]);
} catch (Exception $e) {
  bad('Mailer error: '.$e->getMessage(), 500);
}
