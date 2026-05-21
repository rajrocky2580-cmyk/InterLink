<?php
// =========================================================
// InterLink — Lightweight SMTP Mailer (no Composer needed)
// =========================================================
require_once __DIR__ . '/config.php';

class Mailer {

    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;

    public function __construct() {
        $this->host      = SMTP_HOST;
        $this->port      = SMTP_PORT;
        $this->username  = SMTP_USERNAME;
        $this->password  = SMTP_PASSWORD;
        $this->fromEmail = SMTP_FROM_EMAIL;
        $this->fromName  = SMTP_FROM_NAME;
    }

    /**
     * Send an HTML email.
     * @throws Exception on any SMTP failure
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        // Port 465 = implicit SSL; port 587 = STARTTLS (handled separately)
        $scheme = ($this->port === 465) ? 'ssl' : 'tcp';
        $socket = @stream_socket_client(
            "{$scheme}://{$this->host}:{$this->port}",
            $errno, $errstr, 30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new Exception("SMTP connection failed ({$this->host}:{$this->port}): $errstr ($errno)");
        }

        stream_set_timeout($socket, 15);

        $this->readResponse($socket);                                   // 220 greeting

        // EHLO
        $this->sendCmd($socket, "EHLO " . (gethostname() ?: 'localhost'));

        // STARTTLS upgrade for port 587
        if ($this->port === 587) {
            $this->sendCmd($socket, "STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->sendCmd($socket, "EHLO " . (gethostname() ?: 'localhost'));
        }

        // AUTH LOGIN
        $this->sendCmd($socket, "AUTH LOGIN");
        $this->sendCmd($socket, base64_encode($this->username));
        $this->sendCmd($socket, base64_encode($this->password));

        // Envelope
        $this->sendCmd($socket, "MAIL FROM:<{$this->fromEmail}>");
        $this->sendCmd($socket, "RCPT TO:<$toEmail>");
        $this->sendCmd($socket, "DATA");

        // Headers + body
        $boundary = md5(uniqid());
        $headers  = implode("\r\n", [
            "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromEmail}>",
            "To: =?UTF-8?B?" . base64_encode($toName ?: $toEmail) . "?= <$toEmail>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"$boundary\"",
            "X-Mailer: InterLink-Mailer/1.0",
        ]);

        $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($plainText)) . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $body .= "--$boundary--";

        fwrite($socket, "$headers\r\n\r\n$body\r\n.\r\n");
        $this->readResponse($socket);   // 250 OK

        $this->sendCmd($socket, "QUIT");
        fclose($socket);

        return true;
    }

    private function sendCmd($socket, string $cmd): string {
        fwrite($socket, "$cmd\r\n");
        return $this->readResponse($socket);
    }

    private function readResponse($socket): string {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // end of multi-line response
        }
        $code = (int)substr($response, 0, 3);
        if ($code >= 400) {
            throw new Exception("SMTP error: " . trim($response));
        }
        return $response;
    }
}


/**
 * Build the OTP email HTML template.
 */
function buildOtpEmail(string $otp, string $name, int $expiryMins): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>InterLink — Password Reset OTP</title>
</head>
<body style="margin:0;padding:0;background:#0a0e1a;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0e1a;padding:40px 16px;">
  <tr><td align="center">
    <table width="100%" style="max-width:520px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:40px;color:#e2e8f0;">
      <tr><td align="center" style="padding-bottom:24px;">
        <div style="display:inline-flex;align-items:center;gap:10px;">
          <div style="width:44px;height:44px;background:linear-gradient(135deg,#4f8ef7,#8b5cf6);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;">💬</div>
          <span style="font-size:1.5rem;font-weight:800;color:#fff;">Inter<span style="color:#4f8ef7;">Link</span></span>
        </div>
      </td></tr>
      <tr><td align="center" style="padding-bottom:8px;">
        <h1 style="margin:0;font-size:1.4rem;color:#fff;">Password Reset Request</h1>
      </td></tr>
      <tr><td align="center" style="padding-bottom:28px;">
        <p style="margin:8px 0 0;color:#94a3b8;font-size:.9rem;">Hi <strong style="color:#e2e8f0;">{$name}</strong>, use the OTP below to reset your password.</p>
      </td></tr>
      <tr><td align="center" style="padding-bottom:28px;">
        <div style="background:rgba(79,142,247,0.12);border:2px dashed rgba(79,142,247,0.4);border-radius:16px;padding:24px 40px;display:inline-block;">
          <p style="margin:0 0 6px;font-size:.75rem;letter-spacing:.12em;text-transform:uppercase;color:#64748b;">Your One-Time Password</p>
          <p style="margin:0;font-size:2.8rem;font-weight:900;letter-spacing:.25em;color:#4f8ef7;font-family:monospace;">{$otp}</p>
        </div>
      </td></tr>
      <tr><td align="center" style="padding-bottom:28px;">
        <p style="margin:0;color:#94a3b8;font-size:.85rem;">
          ⏱️ This OTP is valid for <strong style="color:#fbbf24;">{$expiryMins} minutes</strong> only.<br>
          Do not share this code with anyone.
        </p>
      </td></tr>
      <tr><td style="border-top:1px solid rgba(255,255,255,0.08);padding-top:20px;" align="center">
        <p style="margin:0;color:#475569;font-size:.78rem;">If you did not request a password reset, ignore this email.<br>Your account remains secure.</p>
      </td></tr>
    </table>
    <p style="color:#334155;font-size:.75rem;margin-top:20px;">© 2025 InterLink · Secure Messaging Platform</p>
  </td></tr>
</table>
</body>
</html>
HTML;
}
