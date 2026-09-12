<?php
declare(strict_types=1);

/**
 * SMTP Mailer Helper using PHPMailer (bundled)
 * 
 * Required environment variables:
 * - SMTP_HOST: SMTP server hostname (e.g., smtp.gmail.com)
 * - SMTP_PORT: SMTP port (e.g., 587)
 * - SMTP_USER: SMTP username (email address)
 * - SMTP_PASS: SMTP password (Gmail app password)
 * - SMTP_FROM: From email address
 * - SMTP_FROM_NAME: From name (e.g., "Steiner Maris Academy")
 * - SMTP_SECURE: Encryption method (tls, ssl, or empty for none)
 * 
 * All credentials must be set via environment variables. Never hardcode credentials.
 */

// Simple PHPMailer-like class for SMTP sending
class SMTPMailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $secure;
    private $timeout = 30;
    
    public function __construct() {
        $this->host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $this->port = (int) (getenv('SMTP_PORT') ?: 587);
        $this->username = getenv('SMTP_USER') ?: '';
        $this->password = getenv('SMTP_PASS') ?: '';
        $this->fromEmail = getenv('SMTP_FROM') ?: $this->username;
        $this->fromName = getenv('SMTP_FROM_NAME') ?: 'Steiner Maris Academy';
        $this->secure = getenv('SMTP_SECURE') ?: 'tls';
    }
    
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
        if (!$this->username || !$this->password) {
            error_log('SMTP credentials not configured');
            return false;
        }
        
        try {
            $socket = $this->connect();
            if (!$socket) {
                return false;
            }
            
            $this->sendCommand($socket, 'EHLO ' . gethostname());
            $this->readResponse($socket);
            
            if ($this->secure === 'tls' || $this->port === 587) {
                $this->sendCommand($socket, 'STARTTLS');
                $this->readResponse($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCommand($socket, 'EHLO ' . gethostname());
                $this->readResponse($socket);
            }
            
            $this->sendCommand($socket, 'AUTH LOGIN');
            $this->readResponse($socket);
            $this->sendCommand($socket, base64_encode($this->username));
            $this->readResponse($socket);
            $this->sendCommand($socket, base64_encode($this->password));
            $this->readResponse($socket);
            
            $this->sendCommand($socket, 'MAIL FROM:<' . $this->fromEmail . '>');
            $this->readResponse($socket);
            $this->sendCommand($socket, 'RCPT TO:<' . $toEmail . '>');
            $this->readResponse($socket);
            $this->sendCommand($socket, 'DATA');
            $this->readResponse($socket);
            
            $headers = [
                'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
                'To: ' . $toName . ' <' . $toEmail . '>',
                'Subject: ' . $subject,
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="OTP_BOUNDARY"',
                '',
            ];
            
            $message = implode("\r\n", $headers) . "\r\n";
            $message .= '--OTP_BOUNDARY\r\n';
            $message .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n\r\n";
            $message .= $textBody ?: strip_tags($htmlBody) . "\r\n";
            $message .= '--OTP_BOUNDARY\r\n';
            $message .= 'Content-Type: text/html; charset=UTF-8' . "\r\n\r\n";
            $message .= $htmlBody . "\r\n";
            $message .= '--OTP_BOUNDARY--\r\n';
            $message .= '.\r\n';
            
            fwrite($socket, $message);
            $this->readResponse($socket);
            
            $this->sendCommand($socket, 'QUIT');
            fclose($socket);
            
            return true;
        } catch (Throwable $e) {
            error_log('SMTP send error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function connect(): ?\resource {
        $host = $this->host;
        $port = $this->port;
        $secure = $this->secure;
        
        if ($secure === 'ssl') {
            $host = 'ssl://' . $host;
        }
        
        $socket = @stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
        );
        
        if (!$socket) {
            error_log("SMTP connection failed: $errstr ($errno)");
            return null;
        }
        
        stream_set_timeout($socket, $this->timeout);
        $this->readResponse($socket);
        return $socket;
    }
    
    private function sendCommand(\resource $socket, string $command): void {
        fwrite($socket, $command . "\r\n");
    }
    
    private function readResponse(\resource $socket): string {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            $response .= $line;
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        return $response;
    }
}

/**
 * Send OTP email for account activation or login verification
 * 
 * @param string $toEmail Recipient email
 * @param string $toName Recipient name
 * @param string $code 6-digit OTP code
 * @param string $purpose 'activation' or 'login'
 * @return bool True on success, false on failure
 */
function send_otp_email(string $toEmail, string $toName, string $code, string $purpose): bool {
    $mailer = new SMTPMailer();
    
    if ($purpose === 'activation') {
        $subject = 'Your Steiner Maris Academy Account Activation Code';
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #0f5132, #059669); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0;">
                <h1 style="margin: 0; font-size: 24px;">Steiner Maris Academy</h1>
                <p style="margin: 10px 0 0; opacity: 0.9;">Account Activation Required</p>
            </div>
            <div style="background: #f8faf9; border: 1px solid #dce8e2; border-top: none; padding: 30px; border-radius: 0 0 12px 12px;">
                <h2 style="color: #0f5132; margin-top: 0;">Welcome to Steiner Maris Academy!</h2>
                <p>Your student account has been created. To activate your account and set your password, please use the following one-time activation code:</p>
                <div style="background: #f0f5f3; border: 2px solid #059669; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0;">
                    <span style="font-size: 32px; font-weight: 700; color: #0f5132; letter-spacing: 8px; font-family: monospace;">' . htmlspecialchars($code) . '</span>
                </div>
                <p style="color: #666; font-size: 14px;">This code will expire in <strong>5 minutes</strong>. If you did not request this, please contact the admissions office.</p>
                <hr style="border: none; border-top: 1px solid #dce8e2; margin: 24px 0;">
                <p style="color: #91a49b; font-size: 12px; margin: 0;">Steiner Maris Academy &mdash; SMA Portal</p>
            </div>
        </body>
        </html>';
        $textBody = "Welcome to Steiner Maris Academy!\n\nYour activation code is: $code\n\nThis code expires in 5 minutes. Visit /activate.php to complete your account setup.\n\nSteiner Maris Academy - SMA Portal";
    } else {
        $subject = 'Your Steiner Maris Academy Login Verification Code';
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #0f5132, #059669); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0;">
                <h1 style="margin: 0; font-size: 24px;">Steiner Maris Academy</h1>
                <p style="margin: 10px 0 0; opacity: 0.9;">Login Verification Code</p>
            </div>
            <div style="background: #f8faf9; border: 1px solid #dce8e2; border-top: none; padding: 30px; border-radius: 0 0 12px 12px;">
                <h2 style="color: #0f5132; margin-top: 0;">Login Verification Required</h2>
                <p>Someone attempted to sign in to your Steiner Maris Academy account. Use the following one-time verification code to complete your login:</p>
                <div style="background: #f0f5f3; border: 2px solid #059669; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0;">
                    <span style="font-size: 32px; font-weight: 700; color: #0f5132; letter-spacing: 8px; font-family: monospace;">' . htmlspecialchars($code) . '</span>
                </div>
                <p style="color: #666; font-size: 14px;">This code will expire in <strong>5 minutes</strong>. If you did not attempt to sign in, please contact the registrar\'s office immediately.</p>
                <hr style="border: none; border-top: 1px solid #dce8e2; margin: 24px 0;">
                <p style="color: #91a49b; font-size: 12px; margin: 0;">Steiner Maris Academy &mdash; SMA Portal</p>
            </div>
        </body>
        </html>';
        $textBody = "Steiner Maris Academy Login Verification\n\nYour login code is: $code\n\nThis code expires in 5 minutes. Enter this code at /verify_otp.php to complete your login.\n\nIf you did not attempt to sign in, contact the registrar's office immediately.\n\nSteiner Maris Academy - SMA Portal";
    }
    
    return $mailer->send($toEmail, $toName, $subject, $htmlBody);
}