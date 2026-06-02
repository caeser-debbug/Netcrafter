<?php
$wa_number = '22788672115';
$wa_message = urlencode($GLOBALS['nc_lang'] === 'en'
    ? 'Hello, I would like more information about your services.'
    : 'Bonjour, je souhaite avoir plus d\'informations sur vos services.');
?>
<!-- WhatsApp Floating Button -->
<a href="https://wa.me/<?= $wa_number ?>?text=<?= $wa_message ?>"
   target="_blank" rel="noopener"
   id="wa-float"
   aria-label="WhatsApp"
   title="<?= $GLOBALS['nc_lang'] === 'en' ? 'Chat on WhatsApp' : 'Discuter sur WhatsApp' ?>"
   style="
     position:fixed; bottom:24px; right:24px; z-index:9998;
     width:58px; height:58px; border-radius:50%;
     background:linear-gradient(135deg,#25d366,#128c7e);
     display:flex; align-items:center; justify-content:center;
     box-shadow:0 4px 20px rgba(37,211,102,0.45);
     text-decoration:none; transition:transform .2s, box-shadow .2s;
   "
   onmouseenter="this.style.transform='scale(1.12)';this.style.boxShadow='0 6px 28px rgba(37,211,102,0.6)'"
   onmouseleave="this.style.transform='scale(1)';this.style.boxShadow='0 4px 20px rgba(37,211,102,0.45)'">
  <i class="fab fa-whatsapp" style="font-size:28px;color:#fff"></i>
  <span id="wa-pulse" style="
    position:absolute; top:0; right:0; width:14px; height:14px;
    background:#ff4444; border-radius:50%; border:2px solid #060d1e;
    animation:waPulse 2s infinite;
  "></span>
</a>
<style>
@keyframes waPulse {
  0%,100%{transform:scale(1);opacity:1}
  50%{transform:scale(1.4);opacity:.7}
}
</style>
