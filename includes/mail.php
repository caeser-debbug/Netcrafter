<?php
/**
 * Netcrafter — Email helper
 * Uses PHP mail() (requires a local MTA or SMTP relay configured in php.ini).
 * Returns true on success, false on failure.
 */

if (!defined('NC_MAIL_LOADED')) {
    define('NC_MAIL_LOADED', true);
    define('NC_MAIL_FROM',      'noreply@netcrafterniger.com');
    define('NC_MAIL_FROM_NAME', 'Netcrafter');
    define('NC_MAIL_ADMIN',     'contact@netcrafterniger.com');
}

/**
 * Send an HTML email.
 *
 * @param string $to      Recipient address
 * @param string $subject Subject line
 * @param string $html    HTML body
 * @param string $text    Plain-text fallback (auto-generated if empty)
 * @return bool
 */
function nc_send_mail(string $to, string $subject, string $html, string $text = ''): bool
{
    if (!$text) {
        $text = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));
    }

    $boundary  = '----=_Part_' . md5(microtime());
    $headers   = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . NC_MAIL_FROM_NAME . ' <' . NC_MAIL_FROM . '>',
        'Reply-To: ' . NC_MAIL_ADMIN,
        'X-Mailer: Netcrafter PHP Mailer',
    ]);

    $body  = '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($text)) . "\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode(nc_mail_wrap($html, $subject))) . "\r\n";
    $body .= '--' . $boundary . "--\r\n";

    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

/**
 * Wrap HTML content in a branded email template.
 */
function nc_mail_wrap(string $content, string $title = ''): string
{
    return <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>{$title}</title></head>
<body style="margin:0;padding:0;background:#060d1e;font-family:Inter,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#060d1e;padding:30px 0">
<tr><td align="center">
  <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%">
    <!-- Header -->
    <tr><td style="background:linear-gradient(135deg,#0a1835,#060d1e);border-radius:16px 16px 0 0;padding:28px 32px;border:1px solid rgba(0,200,255,0.15);border-bottom:none;text-align:center">
      <span style="font-size:22px;font-weight:800;color:#fff;letter-spacing:-0.5px">
        NET<span style="color:#00c8ff">CRAFTER</span>
      </span>
    </td></tr>
    <!-- Body -->
    <tr><td style="background:#0a1835;border:1px solid rgba(0,200,255,0.12);border-top:none;border-bottom:none;padding:32px">
      {$content}
    </td></tr>
    <!-- Footer -->
    <tr><td style="background:rgba(6,13,30,0.8);border:1px solid rgba(0,200,255,0.1);border-top:none;border-radius:0 0 16px 16px;padding:18px 32px;text-align:center">
      <p style="color:#475569;font-size:12px;margin:0">
        &copy; <?= date('Y') ?> Netcrafter &mdash; Niamey, Niger &mdash;
        <a href="https://netcrafterniger.com" style="color:#00c8ff;text-decoration:none">netcrafterniger.com</a>
      </p>
    </td></tr>
  </table>
</td></tr></table>
</body></html>
HTML;
}

/**
 * Pre-built email templates.
 */

function nc_mail_devis(string $to, array $data): bool
{
    $name  = htmlspecialchars($data['prenom'] . ' ' . $data['nom']);
    $ref   = htmlspecialchars($data['devis_id'] ?? '—');
    $html  = <<<HTML
    <h2 style="color:#00c8ff;margin:0 0 16px">Demande de devis reçue</h2>
    <p style="color:#94a3b8;line-height:1.7">Bonjour <strong style="color:#fff">{$name}</strong>,</p>
    <p style="color:#94a3b8;line-height:1.7">
        Nous avons bien reçu votre demande de devis <strong style="color:#fff">#{$ref}</strong>.
        Notre équipe vous contactera dans les plus brefs délais.
    </p>
    <div style="background:rgba(0,200,255,0.07);border:1px solid rgba(0,200,255,0.2);border-radius:12px;padding:16px;margin:20px 0">
        <p style="color:#94a3b8;font-size:13px;margin:0">
            <strong style="color:#00c8ff">Référence :</strong> {$ref}<br>
            <strong style="color:#00c8ff">Email :</strong> {$data['email']}<br>
            <strong style="color:#00c8ff">Téléphone :</strong> {$data['telephone']}
        </p>
    </div>
    <p style="color:#94a3b8;line-height:1.7">
        Cordialement,<br><strong style="color:#fff">L'équipe Netcrafter</strong>
    </p>
HTML;
    return nc_send_mail($to, 'Votre demande de devis — Netcrafter', $html);
}

function nc_mail_stock_alert(string $admin_email, string $product_name, string $subscriber_email): bool
{
    $prod  = htmlspecialchars($product_name);
    $email = htmlspecialchars($subscriber_email);
    $html  = <<<HTML
    <h2 style="color:#00c8ff;margin:0 0 16px">Nouvelle alerte stock</h2>
    <p style="color:#94a3b8">Un utilisateur souhaite être alerté du retour en stock :</p>
    <div style="background:rgba(0,200,255,0.07);border:1px solid rgba(0,200,255,0.2);border-radius:12px;padding:16px;margin:16px 0">
        <p style="color:#fff;margin:0"><strong>Produit :</strong> {$prod}</p>
        <p style="color:#94a3b8;margin:6px 0 0"><strong>Email :</strong> {$email}</p>
    </div>
HTML;
    return nc_send_mail($admin_email, "Alerte stock — $product_name", $html);
}

function nc_mail_formation_inscription(string $to, string $student_name, string $formation_title): bool
{
    $name  = htmlspecialchars($student_name);
    $form  = htmlspecialchars($formation_title);
    $html  = <<<HTML
    <h2 style="color:#00c8ff;margin:0 0 16px">Inscription confirmée</h2>
    <p style="color:#94a3b8;line-height:1.7">Bonjour <strong style="color:#fff">{$name}</strong>,</p>
    <p style="color:#94a3b8;line-height:1.7">
        Votre inscription à la formation <strong style="color:#fff">{$form}</strong> a bien été enregistrée.
        Vous pouvez dès maintenant accéder à votre espace formation.
    </p>
    <div style="text-align:center;margin:24px 0">
        <a href="https://netcrafterniger.com/formation/dashboard.php"
           style="background:linear-gradient(135deg,#00c8ff,#0066cc);color:#fff;text-decoration:none;padding:12px 28px;border-radius:10px;font-weight:700;font-size:14px">
            Accéder à ma formation
        </a>
    </div>
    <p style="color:#94a3b8;line-height:1.7">
        Cordialement,<br><strong style="color:#fff">L'équipe Netcrafter</strong>
    </p>
HTML;
    return nc_send_mail($to, "Inscription confirmée — $formation_title", $html);
}
