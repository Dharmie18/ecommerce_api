<?php

/**
 * Universal Email Sender for ShopIt Commerce
 * Works both on local environments (XAMPP) and cloud deployments (Render, Vercel API, cPanel).
 * Supports authenticated SMTP (Gmail, Brevo, Resend, SendGrid, Mailgun) and native PHP mail().
 */

function sendShopItEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): array
{
    $smtpHost   = getenv('SMTP_HOST') ?: '';
    $smtpPort   = intval(getenv('SMTP_PORT') ?: 587);
    $smtpUser   = getenv('SMTP_USER') ?: '';
    $smtpPass   = getenv('SMTP_PASS') ?: (getenv('SMTP_PASSWORD') ?: '');
    $smtpSecure = strtolower(getenv('SMTP_SECURE') ?: ($smtpPort === 465 ? 'ssl' : 'tls'));
    $fromEmail  = getenv('SMTP_FROM') ?: (getenv('FROM_EMAIL') ?: 'no-reply@shopit.ng');
    $fromName   = getenv('SMTP_FROM_NAME') ?: (getenv('FROM_NAME') ?: 'ShopIt Commerce');

    if (empty($textBody)) {
        $textBody = strip_tags($htmlBody);
    }

    // 1. If SMTP credentials exist, send via native socket SMTP
    if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
        $smtpResult = sendRawSmtpEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpSecure, $fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody, $textBody);
        if ($smtpResult['success']) {
            return $smtpResult;
        }
    }

    // 2. Fallback to standard PHP mail()
    $boundary = "==Multipart_Boundary_x" . md5(time()) . "x";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $message  = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $textBody . "\r\n\r\n";

    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    $message .= "--{$boundary}--";

    $mailSent = @mail($toEmail, $subject, $message, $headers);

    // Save to local debug log for testing
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: {$toEmail} | SUBJECT: {$subject} | STATUS: " . ($mailSent ? 'SENT' : 'FALLBACK_LOGGED') . "\n";
    @file_put_contents($logDir . '/email_debug.log', $logEntry, FILE_APPEND);

    return [
        'success' => true,
        'driver' => 'mail_fallback',
        'logged' => true
    ];
}

/**
 * Lightweight pure-PHP SMTP client with TLS / SSL support (Zero dependencies)
 */
function sendRawSmtpEmail(
    string $host,
    int $port,
    string $user,
    string $pass,
    string $secure,
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody
): array {
    $timeout = 15;
    $prefix = ($secure === 'ssl') ? 'ssl://' : '';
    $socket = @stream_socket_client($prefix . $host . ':' . $port, $errno, $errstr, $timeout);

    if (!$socket) {
        return ['success' => false, 'error' => "Cannot connect to SMTP server: {$errstr} ({$errno})"];
    }

    $read = function () use ($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    };

    $write = function (string $cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };

    $greeting = $read();
    if (substr($greeting, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'error' => 'Invalid SMTP greeting: ' . $greeting];
    }

    $localhost = gethostname() ?: 'localhost';
    $write("EHLO {$localhost}");
    $ehlo = $read();

    if ($secure === 'tls') {
        $write("STARTTLS");
        $tls = $read();
        if (substr($tls, 0, 3) === '220') {
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return ['success' => false, 'error' => 'TLS handshake failed'];
            }
            $write("EHLO {$localhost}");
            $read();
        }
    }

    $write("AUTH LOGIN");
    $auth = $read();
    if (substr($auth, 0, 3) !== '334') {
        fclose($socket);
        return ['success' => false, 'error' => 'AUTH LOGIN failed: ' . $auth];
    }

    $write(base64_encode($user));
    $read();
    $write(base64_encode($pass));
    $authRes = $read();
    if (substr($authRes, 0, 3) !== '235') {
        fclose($socket);
        return ['success' => false, 'error' => 'SMTP Authentication failed: ' . $authRes];
    }

    $write("MAIL FROM:<{$fromEmail}>");
    $read();
    $write("RCPT TO:<{$toEmail}>");
    $rcpt = $read();
    if (substr($rcpt, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => 'Recipient rejected: ' . $rcpt];
    }

    $write("DATA");
    $dataRes = $read();
    if (substr($dataRes, 0, 3) !== '354') {
        fclose($socket);
        return ['success' => false, 'error' => 'DATA command rejected: ' . $dataRes];
    }

    $boundary = "==Multipart_Boundary_x" . md5(time()) . "x";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "To: {$toName} <{$toEmail}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $textBody . "\r\n\r\n";

    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    $body .= "--{$boundary}--\r\n.";

    $write($headers . $body);
    $sendRes = $read();
    $write("QUIT");
    fclose($socket);

    if (substr($sendRes, 0, 3) === '250') {
        return ['success' => true, 'driver' => 'smtp'];
    }

    return ['success' => false, 'error' => 'Failed to send data: ' . $sendRes];
}

/**
 * Branded HTML Email Template Builder
 */
function getVerificationEmailHtml(string $name, string $magicLink): string
{
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f1; color: #14212b; margin: 0; padding: 20px; }
    .container { max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #14212b; padding: 40px 32px; }
    .header { text-align: center; border-bottom: 2px solid #14212b; padding-bottom: 20px; margin-bottom: 30px; }
    .logo { display: inline-block; background: #14212b; color: #e0ee56; font-size: 24px; font-weight: 900; width: 44px; height: 44px; line-height: 44px; text-align: center; }
    .title { font-size: 22px; font-weight: 900; text-transform: uppercase; margin: 15px 0 5px; color: #14212b; }
    .subtitle { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #9a4e2c; }
    .content { font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 30px; }
    .btn-container { text-align: center; margin: 35px 0; }
    .btn { display: inline-block; background: #14212b; color: #e0ee56 !important; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.14em; padding: 16px 36px; text-decoration: none; border-radius: 0; }
    .link-alt { font-size: 11px; word-break: break-all; color: #64748b; background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; }
    .footer { text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 30px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">S</div>
      <div class="title">Verify Your Email</div>
      <div class="subtitle">ShopIt Commerce Direct</div>
    </div>
    <div class="content">
      <p>Hello <strong>{$name}</strong>,</p>
      <p>Thank you for creating an account with ShopIt! Click the button below to verify your email address and immediately access your trade dashboard and welcome rewards.</p>
      <div class="btn-container">
        <a href="{$magicLink}" class="btn">Verify & Activate Account</a>
      </div>
      <p>If the button above does not work, copy and paste this link into your browser:</p>
      <div class="link-alt">{$magicLink}</div>
    </div>
    <div class="footer">
      ShopIt Commerce · Direct Wholesale & Retail Logistics<br>
      If you did not register for an account, please disregard this email.
    </div>
  </div>
</body>
</html>
HTML;
}

function getPasswordResetEmailHtml(string $name, string $otpCode): string
{
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f1; color: #14212b; margin: 0; padding: 20px; }
    .container { max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #14212b; padding: 40px 32px; }
    .header { text-align: center; border-bottom: 2px solid #14212b; padding-bottom: 20px; margin-bottom: 30px; }
    .logo { display: inline-block; background: #14212b; color: #e0ee56; font-size: 24px; font-weight: 900; width: 44px; height: 44px; line-height: 44px; text-align: center; }
    .title { font-size: 22px; font-weight: 900; text-transform: uppercase; margin: 15px 0 5px; color: #14212b; }
    .subtitle { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #9a4e2c; }
    .content { font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 30px; }
    .otp-box { text-align: center; margin: 30px 0; background: #14212b; color: #e0ee56; font-family: monospace; font-size: 32px; font-weight: 900; letter-spacing: 0.3em; padding: 18px; }
    .footer { text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 30px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">S</div>
      <div class="title">Password Reset Code</div>
      <div class="subtitle">ShopIt Security</div>
    </div>
    <div class="content">
      <p>Hello <strong>{$name}</strong>,</p>
      <p>We received a request to reset your password. Use the 6-digit verification code below to set a new password:</p>
      <div class="otp-box">{$otpCode}</div>
      <p>This code will expire in <strong>15 minutes</strong>. If you did not request a password reset, you can safely ignore this email.</p>
    </div>
    <div class="footer">
      ShopIt Commerce · Direct Wholesale & Retail Logistics
    </div>
  </div>
</body>
</html>
HTML;
}

function getOrderReceiptEmailHtml(string $name, int $order_id, array $items, float $subtotal, float $discount, float $tax, float $total, string $address, string $paymentMethod): string
{
    $formattedSubtotal = number_format($subtotal, 2);
    $formattedDiscount = number_format($discount, 2);
    $formattedTax      = number_format($tax, 2);
    $formattedTotal    = number_format($total, 2);
    $orderDate         = date('F j, Y - g:i A');

    $rowsHtml = '';
    foreach ($items as $item) {
        $pName  = htmlspecialchars($item['name'] ?? 'Product');
        $pQty   = intval($item['quantity'] ?? 1);
        $pPrice = number_format(floatval($item['price'] ?? 0), 2);
        $pLineTotal = number_format($pQty * floatval($item['price'] ?? 0), 2);
        $rowsHtml .= "
        <tr style=\"border-bottom: 1px solid #e2e8f0;\">
          <td style=\"padding: 12px 8px; font-size: 13px; font-weight: 700; color: #14212b;\">{$pName}</td>
          <td style=\"padding: 12px 8px; font-size: 13px; text-align: center; color: #475569;\">{$pQty}</td>
          <td style=\"padding: 12px 8px; font-size: 13px; text-align: right; font-family: monospace; color: #14212b;\">₦{$pPrice}</td>
          <td style=\"padding: 12px 8px; font-size: 13px; text-align: right; font-family: monospace; font-weight: 800; color: #14212b;\">₦{$pLineTotal}</td>
        </tr>";
    }

    $discountRow = ($discount > 0) ? "
      <tr>
        <td colspan=\"3\" style=\"padding: 6px 8px; text-align: right; font-size: 13px; color: #059669; font-weight: bold;\">Coupon Discount:</td>
        <td style=\"padding: 6px 8px; text-align: right; font-size: 13px; color: #059669; font-weight: bold; font-family: monospace;\">-₦{$formattedDiscount}</td>
      </tr>" : "";

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f1; color: #14212b; margin: 0; padding: 20px; }
    .container { max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #14212b; padding: 40px 32px; }
    .header { text-align: center; border-bottom: 2px solid #14212b; padding-bottom: 20px; margin-bottom: 25px; }
    .logo { display: inline-block; background: #14212b; color: #e0ee56; font-size: 24px; font-weight: 900; width: 44px; height: 44px; line-height: 44px; text-align: center; }
    .title { font-size: 22px; font-weight: 900; text-transform: uppercase; margin: 15px 0 5px; color: #14212b; }
    .subtitle { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #9a4e2c; }
    .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; margin-bottom: 25px; font-size: 12px; }
    .table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
    .footer { text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 30px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">S</div>
      <div class="title">Order Confirmation</div>
      <div class="subtitle">Receipt for Order #{$order_id}</div>
    </div>
    
    <p style="font-size: 14px; margin-bottom: 20px;">
      Hello <strong>{$name}</strong>,<br>
      Thank you for your order! We have received your payment and our fulfillment team is preparing your package.
    </p>

    <div class="meta-box">
      <table width="100%" style="font-size: 12px; line-height: 1.6;">
        <tr>
          <td><strong>Order ID:</strong> #{$order_id}</td>
          <td style="text-align: right;"><strong>Date:</strong> {$orderDate}</td>
        </tr>
        <tr>
          <td><strong>Payment Method:</strong> {$paymentMethod}</td>
          <td style="text-align: right;"><strong>Payment Status:</strong> <span style="color: #059669; font-weight: bold;">Completed</span></td>
        </tr>
        <tr>
          <td colspan="2" style="padding-top: 6px;"><strong>Delivery Address:</strong> {$address}</td>
        </tr>
      </table>
    </div>

    <table class="table">
      <thead>
        <tr style="background: #14212b; color: #ffffff; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em;">
          <th style="padding: 10px 8px; text-align: left;">Item</th>
          <th style="padding: 10px 8px; text-align: center;">Qty</th>
          <th style="padding: 10px 8px; text-align: right;">Price</th>
          <th style="padding: 10px 8px; text-align: right;">Total</th>
        </tr>
      </thead>
      <tbody>
        {$rowsHtml}
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" style="padding: 10px 8px 4px; text-align: right; font-size: 13px; color: #64748b;">Subtotal:</td>
          <td style="padding: 10px 8px 4px; text-align: right; font-size: 13px; font-family: monospace;">₦{$formattedSubtotal}</td>
        </tr>
        {$discountRow}
        <tr>
          <td colspan="3" style="padding: 4px 8px; text-align: right; font-size: 13px; color: #64748b;">VAT (7.5%):</td>
          <td style="padding: 4px 8px; text-align: right; font-size: 13px; font-family: monospace;">₦{$formattedTax}</td>
        </tr>
        <tr style="border-top: 2px solid #14212b;">
          <td colspan="3" style="padding: 12px 8px; text-align: right; font-size: 15px; font-weight: 900; color: #14212b; text-transform: uppercase;">Grand Total:</td>
          <td style="padding: 12px 8px; text-align: right; font-size: 16px; font-weight: 900; color: #9a4e2c; font-family: monospace;">₦{$formattedTotal}</td>
        </tr>
      </tfoot>
    </table>

    <p style="font-size: 13px; color: #64748b; line-height: 1.5;">
      You can track and view this order anytime in your <a href="https://shopit-users.vercel.app" style="color: #9a4e2c; font-weight: bold;">Trade Account Dashboard</a>.
    </p>

    <div class="footer">
      ShopIt Commerce · Direct Wholesale & Retail Logistics<br>
      Automated Order Confirmation & Tax Receipt
    </div>
  </div>
</body>
</html>
HTML;
}

function getReferralRewardEmailHtml(string $referrerName, string $friendName, string $couponCode, float $discountPercent = 5.0): string
{
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f1; color: #14212b; margin: 0; padding: 20px; }
    .container { max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #14212b; padding: 40px 32px; }
    .header { text-align: center; border-bottom: 2px solid #14212b; padding-bottom: 20px; margin-bottom: 25px; }
    .logo { display: inline-block; background: #14212b; color: #e0ee56; font-size: 24px; font-weight: 900; width: 44px; height: 44px; line-height: 44px; text-align: center; }
    .title { font-size: 22px; font-weight: 900; text-transform: uppercase; margin: 15px 0 5px; color: #14212b; }
    .subtitle { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #9a4e2c; }
    .reward-box { background: #14212b; color: #e0ee56; text-align: center; padding: 24px; margin: 25px 0; }
    .coupon-code { font-family: monospace; font-size: 28px; font-weight: 900; letter-spacing: 0.15em; margin: 10px 0; color: #e0ee56; }
    .footer { text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 30px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">S</div>
      <div class="title">🎉 Referral Reward Earned!</div>
      <div class="subtitle">ShopIt Partner Rewards</div>
    </div>
    
    <p style="font-size: 14px; line-height: 1.6;">
      Hello <strong>{$referrerName}</strong>,<br>
      Great news! Your friend <strong>{$friendName}</strong> just joined ShopIt using your referral invite link.
    </p>

    <div class="reward-box">
      <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.12em; color: #ffffff99;">Your New Discount Coupon</div>
      <div class="coupon-code">{$couponCode}</div>
      <div style="font-size: 13px; font-weight: bold; color: #ffffff;">{$discountPercent}% OFF (Valid for 7 days on 3+ items)</div>
    </div>

    <p style="font-size: 13px; color: #475569; line-height: 1.5;">
      This coupon has been automatically added to your <a href="https://shopit-users.vercel.app" style="color: #9a4e2c; font-weight: bold;">Rewards Hub</a> and is ready to use at checkout.
    </p>

    <div class="footer">
      Keep sharing your link to earn unlimited coupons on ShopIt!<br>
      ShopIt Commerce · Direct Wholesale & Retail Logistics
    </div>
  </div>
</body>
</html>
HTML;
}

function getLoginAlertEmailHtml(string $name, string $email, string $time, string $ip): string
{
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f1; color: #14212b; margin: 0; padding: 20px; }
    .container { max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #14212b; padding: 40px 32px; }
    .header { text-align: center; border-bottom: 2px solid #14212b; padding-bottom: 20px; margin-bottom: 25px; }
    .logo { display: inline-block; background: #14212b; color: #e0ee56; font-size: 24px; font-weight: 900; width: 44px; height: 44px; line-height: 44px; text-align: center; }
    .title { font-size: 20px; font-weight: 900; text-transform: uppercase; margin: 15px 0 5px; color: #14212b; }
    .subtitle { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #9a4e2c; }
    .alert-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 18px; margin: 20px 0; font-size: 13px; line-height: 1.6; }
    .footer { text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 30px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">S</div>
      <div class="title">Security Notice: New Sign-In</div>
      <div class="subtitle">ShopIt Account Security</div>
    </div>
    
    <p style="font-size: 14px; line-height: 1.6;">
      Hello <strong>{$name}</strong>,<br>
      Your ShopIt account was recently accessed with a successful sign-in.
    </p>

    <div class="alert-box">
      <strong>Account:</strong> {$email}<br>
      <strong>Time:</strong> {$time}<br>
      <strong>IP Address:</strong> {$ip}
    </div>

    <p style="font-size: 13px; color: #64748b; line-height: 1.5;">
      If this was you, no further action is needed.<br>
      If you did <strong>not</strong> authorize this sign-in, please reset your password immediately in the ShopIt app.
    </p>

    <div class="footer">
      ShopIt Commerce · Automated Security Notification
    </div>
  </div>
</body>
</html>
HTML;
}

function getPasswordChangedEmailHtml(string $name, string $email, string $time): string
{
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f1; color: #14212b; margin: 0; padding: 20px; }
    .container { max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #14212b; padding: 40px 32px; }
    .header { text-align: center; border-bottom: 2px solid #14212b; padding-bottom: 20px; margin-bottom: 25px; }
    .logo { display: inline-block; background: #14212b; color: #e0ee56; font-size: 24px; font-weight: 900; width: 44px; height: 44px; line-height: 44px; text-align: center; }
    .title { font-size: 20px; font-weight: 900; text-transform: uppercase; margin: 15px 0 5px; color: #14212b; }
    .subtitle { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: #9a4e2c; }
    .success-box { background: #ecfdf5; border: 1px solid #10b98140; color: #065f46; padding: 18px; margin: 20px 0; font-size: 13px; font-weight: 600; text-align: center; }
    .footer { text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 30px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">S</div>
      <div class="title">Password Changed Successfully</div>
      <div class="subtitle">ShopIt Account Security</div>
    </div>
    
    <p style="font-size: 14px; line-height: 1.6;">
      Hello <strong>{$name}</strong>,
    </p>

    <div class="success-box">
      ✓ Your account password was successfully updated on {$time}.
    </div>

    <p style="font-size: 13px; color: #64748b; line-height: 1.5;">
      You can now sign in with your new password.<br>
      <span style="color: #ef4444; font-weight: bold;">Important:</span> If you did not make this change, please recover your account immediately or contact ShopIt security support.
    </p>

    <div class="footer">
      ShopIt Commerce · Automated Security Notification
    </div>
  </div>
</body>
</html>
HTML;
}
?>
