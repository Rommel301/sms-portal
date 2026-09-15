
<?php
// includes/EmailHelper.php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';

load_env(__DIR__ . '/.env');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailHelper
{
    public static function sendResetEmail($email, $name, $token)
    {
        $reset_link = "http://localhost/sms/student/reset_password.php?token=" . trim($token);

        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('SMTP_USERNAME') ?: '';
            $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);

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
            $mail->addAddress($email, $name);
            $mail->addReplyTo($fromEmail, $fromName);

            $mail->isHTML(true);
            $mail->Subject = "Password Reset Request - Student Management System";
            $mail->Body    = self::getEmailTemplate($name, $reset_link, $token);
            $mail->AltBody = strip_tags($mail->Body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email failed: " . $mail->ErrorInfo);
            return false;
        }
    }

    private static function getEmailTemplate($name, $reset_link, $token)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); overflow: hidden; }
                .header { background: #1b5e20; color: white; padding: 30px 20px; text-align: center; }
                .header h2 { margin: 0; }
                .content { padding: 30px 25px; }
                .button { display: inline-block; padding: 14px 30px; background: #1b5e20; color: white !important; text-decoration: none; border-radius: 8px; font-weight: 600; }
                .button-container { text-align: center; margin: 25px 0; }
                .link-box { background: #f5f5f5; padding: 12px 15px; border: 1px solid #e0e0e0; border-radius: 8px; word-break: break-all; margin: 10px 0; font-size: 14px; color: #1b5e20; }
                .token-box { background: #f0f0f0; padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; word-break: break-all; margin: 10px 0; }
                .warning { background: #fff8e1; padding: 15px 20px; border-left: 4px solid #ffc107; margin: 20px 0; border-radius: 4px; }
                .warning ul { margin: 10px 0 0 0; padding-left: 20px; }
                .footer { text-align: center; padding: 20px; color: #888; font-size: 12px; border-top: 1px solid #e0e0e0; background: #fafafa; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔐 Password Reset Request</h2>
                    <div>Student Management System</div>
                </div>
                <div class='content'>
                    <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    <p>We received a request to reset your password.</p>
                    <div class='button-container'>
                        <a href='" . trim($reset_link) . "' class='button'>Reset Password</a>
                    </div>
                    <p style='text-align:center;font-size:14px;color:#888;'>Or copy and paste this link:</p>
                    <div class='link-box'>" . trim($reset_link) . "</div>
                    <div class='token-box'><strong>🔑 Token:</strong> " . trim($token) . "</div>
                    <div class='warning'>
                        <p><strong>⚠️ Important Security Information:</strong></p>
                        <ul>
                            <li>This link will expire in <strong>1 hour</strong></li>
                            <li>If you didn't request this, please ignore this email</li>
                            <li>Never share this link with anyone</li>
                        </ul>
                    </div>
                    <p>Thank you,<br><strong style='color:#1b5e20;'>Student Management System Team</strong></p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply.</p>
                    <p>&copy; " . date('Y') . " Student Management System</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
