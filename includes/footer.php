<?php if (!defined('BASE')) { $host = $_SERVER['HTTP_HOST'] ?? ''; define('BASE', (strpos($host,'localhost')!==false || strpos($host,'127.0.0.1')!==false) ? '/netcrafter' : ''); } ?>
<!-- ═══ FOOTER ═══ -->
<footer style="background:linear-gradient(180deg,#060d1e 0%,#030810 100%); border-top:1px solid rgba(0,200,255,0.12);" class="mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

        <!-- Top grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-10">

            <!-- Brand -->
            <div>
                <a href="<?= BASE ?>/index.php" class="flex items-center gap-3 mb-5">
                    <img src="<?= BASE ?>/image/logo-n.png" alt="Netcrafter" class="h-10">
                    <span class="font-heading font-bold text-xl text-white">NET<span class="gradient-text">CRAFTER</span></span>
                </a>
                <p class="text-gray-400 text-sm leading-relaxed mb-5"><?= t('footer.desc') ?></p>
                <div class="flex gap-3">
                    <a href="https://facebook.com" target="_blank"
                       class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-400 hover:text-nc-cyan transition-all"
                       style="background:rgba(0,200,255,0.07);border:1px solid rgba(0,200,255,0.15)">
                        <i class="fab fa-facebook-f text-sm"></i>
                    </a>
                    <a href="https://instagram.com" target="_blank"
                       class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-400 hover:text-nc-cyan transition-all"
                       style="background:rgba(0,200,255,0.07);border:1px solid rgba(0,200,255,0.15)">
                        <i class="fab fa-instagram text-sm"></i>
                    </a>
                    <a href="https://wa.me/22788672115" target="_blank"
                       class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-400 hover:text-nc-green transition-all"
                       style="background:rgba(16,185,129,0.07);border:1px solid rgba(16,185,129,0.2)">
                        <i class="fab fa-whatsapp text-sm"></i>
                    </a>
                </div>
            </div>

            <!-- Navigation -->
            <div>
                <h3 class="font-heading font-semibold text-white mb-5 flex items-center gap-2">
                    <span class="w-1 h-4 rounded-full" style="background:linear-gradient(180deg,#00c8ff,#0066cc)"></span>
                    <?= t('footer.nav') ?>
                </h3>
                <ul class="space-y-3 text-gray-400 text-sm">
                    <li><a href="<?= BASE ?>/service.php"              class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-chevron-right text-xs text-nc-cyan/50"></i><?= t('nav.services') ?></a></li>
                    <li><a href="<?= BASE ?>/shop/shop.php"            class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-chevron-right text-xs text-nc-cyan/50"></i><?= t('nav.shop') ?></a></li>
                    <li><a href="<?= BASE ?>/formation/formations.php" class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-chevron-right text-xs text-nc-cyan/50"></i><?= t('nav.training') ?></a></li>
                    <li><a href="<?= BASE ?>/volet.php"                class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-chevron-right text-xs text-nc-cyan/50"></i><?= t('nav.panels') ?></a></li>
                    <li><a href="<?= BASE ?>/portfolio.php"            class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-chevron-right text-xs text-nc-cyan/50"></i><?= t('nav.portfolio') ?></a></li>
                    <li><a href="<?= BASE ?>/blog/index.php"           class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-chevron-right text-xs text-nc-cyan/50"></i><?= t('nav.blog') ?></a></li>
                    <li><a href="<?= BASE ?>/devis.php"                class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-chevron-right text-xs text-nc-cyan/50"></i><?= t('nav.quote') ?></a></li>
                </ul>
            </div>

            <!-- Outils -->
            <div class="hidden xl:block">
                <h3 class="font-heading font-semibold text-white mb-5 flex items-center gap-2">
                    <span class="w-1 h-4 rounded-full" style="background:linear-gradient(180deg,#00c8ff,#0066cc)"></span>
                    <?= t('nav.tools') ?>
                </h3>
                <ul class="space-y-3 text-gray-400 text-sm">
                    <li><a href="<?= BASE ?>/configurateur.php"      class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-sliders-h text-xs text-nc-cyan/50"></i><?= t('nav.configurator') ?></a></li>
                    <li><a href="<?= BASE ?>/processus.php"          class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-sitemap text-xs text-nc-cyan/50"></i><?= t('nav.process') ?></a></li>
                    <li><a href="<?= BASE ?>/stack.php"              class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-layer-group text-xs text-nc-cyan/50"></i><?= t('nav.stack') ?></a></li>
                    <li><a href="<?= BASE ?>/outils/audit.php"       class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-search-plus text-xs text-nc-cyan/50"></i><?= t('nav.audit') ?></a></li>
                    <li><a href="<?= BASE ?>/outils/pentest.php"     class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-user-secret text-xs text-nc-cyan/50"></i><?= ($GLOBALS['nc_lang']??'fr')==='en' ? 'Security Audit' : 'Audit Sécurité' ?></a></li>
                    <li><a href="<?= BASE ?>/outils/palette.php"     class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-palette text-xs text-nc-cyan/50"></i><?= t('nav.palette') ?></a></li>
                    <li><a href="<?= BASE ?>/outils/seo-preview.php" class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-eye text-xs text-nc-cyan/50"></i><?= t('nav.seo_preview') ?></a></li>
                </ul>
            </div>

            <!-- Services -->
            <div>
                <h3 class="font-heading font-semibold text-white mb-5 flex items-center gap-2">
                    <span class="w-1 h-4 rounded-full" style="background:linear-gradient(180deg,#00c8ff,#0066cc)"></span>
                    <?= t('footer.services') ?>
                </h3>
                <ul class="space-y-3 text-gray-400 text-sm">
                    <li><a href="<?= BASE ?>/service.php" class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-code       text-xs text-nc-blue/70"></i><?= t('footer.dev_web') ?></a></li>
                    <li><a href="<?= BASE ?>/service.php" class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-bullhorn   text-xs text-nc-blue/70"></i><?= t('footer.marketing') ?></a></li>
                    <li><a href="<?= BASE ?>/service.php" class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-shield-alt text-xs text-nc-blue/70"></i><?= t('footer.cctv') ?></a></li>
                    <li><a href="<?= BASE ?>/service.php" class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-network-wired text-xs text-nc-blue/70"></i><?= t('footer.network') ?></a></li>
                    <li><a href="<?= BASE ?>/service.php" class="hover:text-nc-cyan transition-colors flex items-center gap-2"><i class="fas fa-tools      text-xs text-nc-blue/70"></i><?= t('footer.maintenance') ?></a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h3 class="font-heading font-semibold text-white mb-5 flex items-center gap-2">
                    <span class="w-1 h-4 rounded-full" style="background:linear-gradient(180deg,#00c8ff,#0066cc)"></span>
                    <?= t('footer.contact') ?>
                </h3>
                <ul class="space-y-4 text-gray-400 text-sm">
                    <li class="flex items-start gap-3">
                        <i class="fas fa-map-marker-alt text-nc-cyan mt-0.5"></i>
                        <span>Niamey, Niger</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-phone text-nc-cyan mt-0.5"></i>
                        <a href="tel:+22788672115" class="hover:text-nc-cyan transition-colors">+227 88 67 21 15</a>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-envelope text-nc-cyan mt-0.5"></i>
                        <a href="mailto:contact@netcrafterniger.com" class="hover:text-nc-cyan transition-colors">contact@netcrafterniger.com</a>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fab fa-whatsapp text-nc-green mt-0.5"></i>
                        <a href="https://wa.me/22788672115" target="_blank" class="hover:text-nc-green transition-colors"><?= t('footer.whatsapp') ?></a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Divider -->
        <div class="section-divider my-10"></div>

        <!-- Bottom -->
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 text-gray-600 text-sm">
            <p>
                &copy; 2026 <span class="gradient-text font-semibold">Netcrafter</span>. <?= t('footer.rights') ?>
            </p>
            <div class="flex gap-6">
                <a href="<?= BASE ?>/confidentialite.php" class="hover:text-gray-300 transition-colors"><?= t('footer.privacy') ?></a>
                <a href="<?= BASE ?>/cgu.php" class="hover:text-gray-300 transition-colors"><?= t('footer.tos') ?></a>
                <a href="<?= BASE ?>/mentions-legales.php" class="hover:text-gray-300 transition-colors"><?= t('footer.legal') ?></a>
            </div>
        </div>
    </div>
</footer>

<!-- AOS JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
<!-- Netcrafter JS (consolidated) -->
<script src="<?= BASE ?>/js/netcrafter.js" defer></script>

<?php include __DIR__ . '/whatsapp-float.php'; ?>
<?php include __DIR__ . '/back-to-top.php'; ?>
<?php include __DIR__ . '/cookie-consent.php'; ?>
</body>
</html>
