
<?php
// includes/mail_config.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

load_env(__DIR__ . '/.env');

function sendEmail($to, $subject, $body, $altBody = '')
{
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug  = 0;
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USERNAME') ?: '';
        $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
        $mail->Timeout    = 30;
        $mail->CharSet    = 'UTF-8';

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: $mail->Username;
        $fromName  = getenv('SMTP_FROM_NAME')  ?: 'Student Management System';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}

function sendWelcomeEmail($to, $firstName, $lastName, $admissionNumber)
{
    $subject = "🎓 Welcome to Student Management System, " . $firstName . "!";

    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: "Segoe UI", Arial, sans-serif; background: #f4f7f5; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 12px; overflow: hidden; }
            .header { background: #255F38; color: #ffffff; padding: 35px 30px; text-align: center; }
            .content { padding: 35px 30px; color: #2d3748; }
            .info-box { background: #f7fafc; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #255F38; }
            .info-box .value { color: #255F38; font-weight: 700; font-size: 20px; }
            .btn { display: inline-block; background: #255F38; color: #ffffff !important; padding: 14px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; }
            .footer { text-align: center; padding: 25px 30px; color: #718096; font-size: 13px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🎓 Welcome, ' . htmlspecialchars($firstName) . '!</h1>
            </div>
            <div class="content">
                <h2>✅ Registration Successful!</h2>
                <p>Dear <strong>' . htmlspecialchars($firstName . ' ' . $lastName) . '</strong>,</p>
                <div class="info-box">
                    <p><strong>📌 Your Admission Number:</strong></p>
                    <p class="value">' . htmlspecialchars($admissionNumber) . '</p>
                    <p><strong>📧 Email:</strong> ' . htmlspecialchars($to) . '</p>
                    <p><strong>📅 Registered:</strong> ' . date('F d, Y') . '</p>
                </div>
                <p style="text-align: center; margin: 30px 0;">
                    <a href="http://localhost/sms/student/index.php" class="btn">🔑 Login to Your Account</a>
                </p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Student Management System</p>
            </div>
        </div>
    </body>
    </html>
    ';

    return sendEmail($to, $subject, $body);
}
