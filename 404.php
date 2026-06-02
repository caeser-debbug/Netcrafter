<?php
http_response_code(404);
if (!function_exists('t')) require_once __DIR__ . '/includes/lang.php';
$page_title = '404 — Page introuvable';
include __DIR__ . '/includes/header.php';
?>
<section style="min-height:80vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:40px 20px">
  <div data-aos="fade-up">
    <div style="font-size:8rem;font-weight:900;line-height:1;background:linear-gradient(135deg,#00c8ff,#0066cc);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">404</div>
    <h1 style="font-size:1.8rem;font-weight:700;color:#fff;margin:16px 0 10px">
      <?= $GLOBALS['nc_lang']==='en' ? 'Page not found' : 'Page introuvable' ?>
    </h1>
    <p style="color:#94a3b8;max-width:440px;margin:0 auto 32px">
      <?= $GLOBALS['nc_lang']==='en'
        ? 'The page you are looking for has been moved, deleted or never existed.'
        : 'La page que vous cherchez a été déplacée, supprimée ou n\'a jamais existé.' ?>
    </p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="<?= BASE ?>/" style="background:linear-gradient(135deg,#00c8ff,#0066cc);color:#fff;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:8px">
        <i class="fas fa-home"></i> <?= $GLOBALS['nc_lang']==='en' ? 'Home' : 'Accueil' ?>
      </a>
      <a href="javascript:history.back()" style="background:rgba(0,200,255,0.08);border:1px solid rgba(0,200,255,0.25);color:#00c8ff;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:8px">
        <i class="fas fa-arrow-left"></i> <?= $GLOBALS['nc_lang']==='en' ? 'Go back' : 'Retour' ?>
      </a>
    </div>
    <!-- Animated glitch blobs -->
    <div style="position:absolute;top:20%;left:10%;width:300px;height:300px;border-radius:50%;background:#00c8ff;filter:blur(120px);opacity:.04;pointer-events:none"></div>
    <div style="position:absolute;bottom:20%;right:10%;width:250px;height:250px;border-radius:50%;background:#0066cc;filter:blur(100px);opacity:.06;pointer-events:none"></div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
