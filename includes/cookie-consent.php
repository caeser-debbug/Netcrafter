<?php
$lang = $GLOBALS['nc_lang'] ?? 'fr';
$txt = [
  'fr' => [
    'msg'    => 'Nous utilisons des cookies pour améliorer votre expérience. En continuant, vous acceptez notre',
    'policy' => 'politique de confidentialité',
    'accept' => 'Accepter',
    'decline'=> 'Refuser',
  ],
  'en' => [
    'msg'    => 'We use cookies to improve your experience. By continuing, you agree to our',
    'policy' => 'privacy policy',
    'accept' => 'Accept',
    'decline'=> 'Decline',
  ],
];
$t = $txt[$lang] ?? $txt['fr'];
?>
<!-- Cookie Consent -->
<div id="cookie-banner" style="
  display:none; position:fixed; bottom:0; left:0; right:0; z-index:9999;
  background:rgba(6,13,30,0.97); border-top:1px solid rgba(0,200,255,0.2);
  backdrop-filter:blur(16px); padding:16px 24px;
  flex-wrap:wrap; align-items:center; gap:12px;
">
  <p style="margin:0;color:#cbd5e1;font-size:.9rem;flex:1;min-width:220px">
    <?= $t['msg'] ?>
    <a href="<?= BASE ?>/privacy.php" style="color:#00c8ff;text-decoration:underline"><?= $t['policy'] ?></a>.
  </p>
  <div style="display:flex;gap:10px;flex-shrink:0">
    <button onclick="cookieChoice(false)" style="
      background:transparent;border:1px solid rgba(0,200,255,0.3);
      color:#94a3b8;padding:8px 18px;border-radius:8px;cursor:pointer;font-size:.85rem
    "><?= $t['decline'] ?></button>
    <button onclick="cookieChoice(true)" style="
      background:linear-gradient(135deg,#00c8ff,#0066cc);color:#fff;border:none;
      padding:8px 22px;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:600
    "><?= $t['accept'] ?></button>
  </div>
</div>
