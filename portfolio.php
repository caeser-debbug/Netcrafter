<?php
$page_title    = 'Netcrafter - Portfolio & Réalisations';
$page_keywords = 'portfolio agence web Niger, réalisations Netcrafter, projets web Niamey, exemples sites internet Niger, références agence digitale Niger';

// DB avec détection environnement
$projects   = [];
$counts     = [];
$total      = 0;
$filter_cat = isset($_GET['cat']) ? preg_replace('/[^a-z-]/', '', $_GET['cat']) : 'all';
$conn       = null;

try {
    $cfg  = file_exists(__DIR__ . '/includes/db_config.php')
        ? include(__DIR__ . '/includes/db_config.php')
        : ['host'=>'localhost','user'=>'root','pass'=>'','db'=>'netcrafter'];
    $conn = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db']);
    if ($conn->connect_error) throw new Exception($conn->connect_error);
    $conn->set_charset('utf8mb4');

    $where = "WHERE status = 'published'";
    if ($filter_cat !== 'all') {
        $safe_cat = $conn->real_escape_string($filter_cat);
        $where   .= " AND category = '$safe_cat'";
    }
    $res      = $conn->query("SELECT * FROM portfolio_projects $where ORDER BY featured DESC, order_num ASC, created_at DESC");
    $projects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    $res2 = $conn->query("SELECT category, COUNT(*) c FROM portfolio_projects WHERE status='published' GROUP BY category");
    if ($res2) while ($r = $res2->fetch_assoc()) $counts[$r['category']] = $r['c'];
    $total = array_sum($counts);
} catch (Exception $e) {
    $projects = []; $counts = []; $total = 0;
}

include 'includes/header.php';

$cats = [
    'all'       => ['label'=> t('portfolio.filter_all'), 'icon'=>'fa-th-large', 'color'=>'#00c8ff'],
    'dev-web'   => ['label'=>'Dev Web',           'icon'=>'fa-laptop-code',    'color'=>'#00c8ff'],
    'webview'   => ['label'=>'WebView',           'icon'=>'fa-mobile-alt',     'color'=>'#0099dd'],
    'ia-chatbot'=> ['label'=>'IA & Chatbot',      'icon'=>'fa-robot',          'color'=>'#4db8ff'],
    'whatsapp'  => ['label'=>'WhatsApp',          'icon'=>'fa-comment-dots',   'color'=>'#25d366'],
    'gestion'   => ['label'=>'Gestion',           'icon'=>'fa-chart-bar',      'color'=>'#0066cc'],
    'suivi'     => ['label'=>'Suivi & Tracking',  'icon'=>'fa-map-marker-alt', 'color'=>'#4db8ff'],
    'design'    => ['label'=>'Design',            'icon'=>'fa-palette',        'color'=>'#0099dd'],
    'securite'  => ['label'=>'Sécurité',          'icon'=>'fa-shield-alt',     'color'=>'#0066cc'],
];
?>

<!-- Hero -->
<section class="relative pt-32 pb-16 overflow-hidden" id="portfolio-hero">
    <canvas id="hero-canvas" class="absolute inset-0 w-full h-full pointer-events-none" style="z-index:1"></canvas>
    <div class="blob bg-nc-cyan"  style="width:600px;height:600px;top:-200px;left:-200px;opacity:0.15;"></div>
    <div class="blob bg-nc-blue"  style="width:500px;height:500px;bottom:-100px;right:-150px;animation-delay:2s;opacity:0.12;"></div>
    <div class="absolute inset-0 pointer-events-none"
         style="background-image:linear-gradient(rgba(0,200,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(0,200,255,0.04) 1px,transparent 1px);background-size:50px 50px;"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative text-center" style="z-index:2">
        <div class="badge mb-6 mx-auto" data-aos="fade-down" data-aos-duration="800">
            <i class="fas fa-layer-group"></i> <?= t('portfolio.badge') ?>
        </div>
        <h1 class="font-heading font-black text-5xl md:text-7xl text-white mb-6" data-aos="fade-up" data-aos-duration="900">
            <span class="gradient-text glitch-text"><?= t('portfolio.title') ?></span>
        </h1>
        <p class="text-gray-400 text-xl max-w-3xl mx-auto mb-4" data-aos="fade-up" data-aos-delay="100" data-aos-duration="800">
            <?= t('portfolio.sub') ?>
        </p>
        <!-- Animated Stats -->
        <div class="flex flex-wrap justify-center gap-10 mt-12" data-aos="fade-up" data-aos-delay="200">
            <?php
            $stats = [
                [$total, t('portfolio.stat_done'), '+', '#00c8ff'],
                ['100', t('portfolio.stat_sat'), '%', '#10b981'],
                ['6', t('portfolio.stat_tech'), '', '#7c3aed'],
            ];
            foreach ($stats as $s):
            ?>
            <div class="text-center stat-block group cursor-default" data-aos="zoom-in" data-aos-delay="<?= (array_search($s,$stats)*80)+200 ?>">
                <div class="relative w-28 h-28 mx-auto mb-3 flex items-center justify-center rounded-2xl"
                     style="background:rgba(<?= $s[3]==='#10b981'?'16,185,129':($s[3]==='#7c3aed'?'124,58,237':'0,200,255') ?>,0.07);border:1px solid rgba(<?= $s[3]==='#10b981'?'16,185,129':($s[3]==='#7c3aed'?'124,58,237':'0,200,255') ?>,0.2);transition:all .4s ease">
                    <div>
                        <div class="text-4xl font-black font-heading" style="color:<?= $s[3] ?>">
                            <span class="count-num" data-target="<?= $s[0] ?>" data-suffix="<?= $s[2] ?>"><?= $s[0] ?><?= $s[2] ?></span>
                        </div>
                        <div class="text-gray-500 text-xs mt-1"><?= $s[1] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Before / After Section -->
<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
    <div class="text-center mb-10" data-aos="fade-up">
        <div class="badge mb-4 mx-auto"><i class="fas fa-exchange-alt"></i> Avant → Après</div>
        <h2 class="font-heading font-bold text-3xl text-white">Nos Transformations</h2>
        <p class="text-gray-400 mt-2">Glissez le curseur pour comparer l'avant et l'après de nos refontes.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php
        $ba_items = [
            ['Site Vitrine', 'Refonte complète — +180% de conversions',
             'linear-gradient(135deg,#e2e8f0,#cbd5e1)', 'linear-gradient(135deg,#060d1e,#0a1835)',
             '#1e293b', '#00c8ff', 'fa-globe'],
            ['E-Commerce', 'Modernisation boutique — +240% de ventes',
             'linear-gradient(135deg,#fef3c7,#fde68a)', 'linear-gradient(135deg,#0a1835,#0d1f48)',
             '#1e293b', '#10b981', 'fa-shopping-cart'],
            ['Application Web', 'Refonte UX — -60% de taux de rebond',
             'linear-gradient(135deg,#dbeafe,#bfdbfe)', 'linear-gradient(135deg,#060d1e,#0a1835)',
             '#1e293b', '#7c3aed', 'fa-laptop-code'],
        ];
        foreach ($ba_items as $i => [$title, $stat, $bgBefore, $bgAfter, $textBefore, $accentAfter, $icon]):
        ?>
        <div class="ba-wrapper rounded-2xl overflow-hidden" style="border:1px solid rgba(0,200,255,0.12)" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
            <div class="ba-slider relative select-none" style="height:220px;cursor:col-resize" data-index="<?= $i ?>">
                <!-- After (full width, clipped by JS) -->
                <div class="ba-after absolute inset-0 flex flex-col items-center justify-center gap-3"
                     style="background:<?= $bgAfter ?>">
                    <i class="fas <?= $icon ?> text-5xl" style="color:<?= $accentAfter ?>;opacity:0.7"></i>
                    <span class="text-xs font-bold uppercase tracking-widest" style="color:<?= $accentAfter ?>">Après • Netcrafter</span>
                    <div class="w-12 h-0.5 rounded" style="background:<?= $accentAfter ?>"></div>
                    <span class="text-white text-xs font-semibold"><?= $title ?></span>
                </div>
                <!-- Before (clipped) -->
                <div class="ba-before absolute inset-0 overflow-hidden flex flex-col items-center justify-center gap-3"
                     style="background:<?= $bgBefore ?>;clip-path:inset(0 50% 0 0)">
                    <i class="fas <?= $icon ?> text-5xl opacity-30" style="color:<?= $textBefore ?>"></i>
                    <span class="text-xs font-bold uppercase tracking-widest" style="color:<?= $textBefore ?>;opacity:0.5">Avant</span>
                    <div class="w-12 h-0.5 rounded bg-gray-400 opacity-40"></div>
                    <span class="text-xs font-semibold" style="color:<?= $textBefore ?>;opacity:0.6"><?= $title ?></span>
                </div>
                <!-- Divider handle -->
                <div class="ba-handle absolute top-0 bottom-0 flex items-center justify-center" style="left:50%;transform:translateX(-50%);width:3px;background:rgba(255,255,255,0.9);cursor:col-resize;z-index:10">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center shadow-lg" style="background:#fff;position:absolute">
                        <i class="fas fa-arrows-left-right text-gray-700 text-sm"></i>
                    </div>
                </div>
                <!-- Labels -->
                <div class="absolute bottom-2 left-3 text-xs font-bold px-2 py-0.5 rounded" style="background:rgba(0,0,0,0.4);color:rgba(255,255,255,0.6)">Avant</div>
                <div class="absolute bottom-2 right-3 text-xs font-bold px-2 py-0.5 rounded" style="background:rgba(0,200,255,0.2);color:#00c8ff">Après</div>
            </div>
            <div class="px-4 py-3" style="background:rgba(10,24,58,0.8)">
                <p class="text-sm text-gray-300 font-medium"><?= $title ?></p>
                <p class="text-xs mt-1" style="color:<?= $accentAfter ?>"><?= $stat ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
(function(){
    document.querySelectorAll('.ba-slider').forEach(slider => {
        let dragging = false;
        const before = slider.querySelector('.ba-before');
        const handle = slider.querySelector('.ba-handle');

        function setPos(x) {
            const rect = slider.getBoundingClientRect();
            const pct = Math.max(5, Math.min(95, ((x - rect.left) / rect.width) * 100));
            before.style.clipPath = `inset(0 ${100 - pct}% 0 0)`;
            handle.style.left = pct + '%';
        }

        slider.addEventListener('mousedown', e => { dragging = true; setPos(e.clientX); });
        slider.addEventListener('touchstart', e => { dragging = true; setPos(e.touches[0].clientX); }, {passive:true});
        window.addEventListener('mousemove', e => { if (dragging) setPos(e.clientX); });
        window.addEventListener('touchmove', e => { if (dragging) setPos(e.touches[0].clientX); }, {passive:true});
        window.addEventListener('mouseup',  () => { dragging = false; });
        window.addEventListener('touchend', () => { dragging = false; });
    });
})();
</script>

<!-- Filter tabs -->
<div class="sticky top-16 z-30 py-4" style="background:rgba(6,13,30,0.92);backdrop-filter:blur(20px);border-bottom:1px solid rgba(0,200,255,0.1);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap gap-2 justify-center" id="filter-tabs">
            <?php foreach ($cats as $key => $cat):
                $count = $key === 'all' ? $total : ($counts[$key] ?? 0);
                $active = $filter_cat === $key || ($key === 'all' && $filter_cat === 'all');
            ?>
            <a href="?cat=<?= $key ?>"
               class="filter-tab flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all <?= $active ? 'active-tab' : '' ?>"
               style="<?= $active ? "background:{$cat['color']}22;border:1px solid {$cat['color']}55;color:{$cat['color']};" : 'background:rgba(10,24,58,0.5);border:1px solid rgba(0,200,255,0.1);color:#94a3b8;' ?>">
                <i class="fas <?= $cat['icon'] ?> text-xs"></i>
                <?= $cat['label'] ?>
                <?php if ($count > 0): ?>
                <span class="px-1.5 py-0.5 rounded-full text-xs font-bold" style="background:rgba(0,200,255,0.12)"><?= $count ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Projects Grid -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

    <?php if (empty($projects)): ?>
    <div class="text-center py-24">
        <i class="fas fa-folder-open text-5xl text-gray-700 mb-4"></i>
        <p class="text-gray-500"><?= t('portfolio.no_cat') ?></p>
    </div>
    <?php else: ?>

    <!-- Featured projects -->
    <?php $featured = array_filter($projects, fn($p) => $p['featured']); ?>
    <?php if (!empty($featured) && $filter_cat === 'all'): ?>
    <div class="mb-12">
        <div class="flex items-center gap-3 mb-6" data-aos="fade-right">
            <span class="w-8 h-px" style="background:#00c8ff"></span>
            <span class="text-xs font-bold uppercase tracking-widest" style="color:#00c8ff"><?= t('portfolio.featured') ?></span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8"
             data-grid-anim data-cols="2" data-cols-responsive='{"640":1,"default":2}'>
            <?php foreach (array_slice($featured, 0, 4) as $j => $p):
                $cat  = $cats[$p['category']] ?? $cats['dev-web'];
                $tags = $p['tags'] ? explode(',', $p['tags']) : [];
            ?>
            <div class="group portfolio-card rounded-2xl overflow-hidden cursor-pointer"
                 style="border:1px solid rgba(0,200,255,0.12);"
                 onclick="openModal(<?= $p['id'] ?>)"
                 data-aos="fade-up" data-aos-delay="<?= $j*100 ?>">

                <!-- Image -->
                <div class="relative h-56 overflow-hidden" style="background:linear-gradient(135deg,<?= $cat['color'] ?>15,<?= $cat['color'] ?>05)">
                    <?php if ($p['image']): ?>
                    <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center">
                        <i class="fas <?= $cat['icon'] ?> text-6xl opacity-20" style="color:<?= $cat['color'] ?>"></i>
                    </div>
                    <?php endif; ?>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent group-hover:opacity-70 transition-opacity"></div>
                    <!-- Badges -->
                    <div class="absolute top-3 left-3 flex gap-2">
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full" style="background:<?= $cat['color'] ?>25;border:1px solid <?= $cat['color'] ?>55;color:<?= $cat['color'] ?>">
                            <i class="fas <?= $cat['icon'] ?> mr-1 text-xs"></i><?= $cat['label'] ?>
                        </span>
                    </div>
                    <div class="absolute top-3 right-3">
                        <span class="text-xs font-bold px-2 py-1 rounded-full" style="background:rgba(0,200,255,0.2);border:1px solid rgba(0,200,255,0.4);color:#00c8ff">
                            <i class="fas fa-star text-xs mr-1"></i>Featured
                        </span>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6" style="background:rgba(10,24,58,0.7)">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <h3 class="font-heading font-bold text-white text-lg leading-snug group-hover:text-nc-cyan transition-colors"><?= htmlspecialchars($p['title']) ?></h3>
                        <?php if ($p['year']): ?>
                        <span class="text-gray-600 text-xs flex-shrink-0"><?= $p['year'] ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-400 text-sm leading-relaxed mb-4 line-clamp-2"><?= htmlspecialchars($p['short_desc']) ?></p>
                    <div class="flex flex-wrap gap-1.5 mb-4">
                        <?php foreach (array_slice($tags, 0, 4) as $tag): ?>
                        <span class="text-xs px-2 py-0.5 rounded-full" style="background:rgba(0,200,255,0.07);border:1px solid rgba(0,200,255,0.15);color:#94a3b8"><?= htmlspecialchars(trim($tag)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex items-center justify-between">
                        <?php if ($p['client']): ?>
                        <span class="text-gray-600 text-xs"><i class="fas fa-building mr-1"></i><?= htmlspecialchars($p['client']) ?></span>
                        <?php endif; ?>
                        <span class="text-xs font-medium ml-auto" style="color:<?= $cat['color'] ?>">
                            Voir détails <i class="fas fa-arrow-right ml-1"></i>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- All projects -->
    <?php $others = ($filter_cat === 'all') ? array_filter($projects, fn($p) => !$p['featured']) : $projects; ?>
    <?php if (!empty($others)): ?>
    <?php if ($filter_cat === 'all' && !empty($featured)): ?>
    <div class="flex items-center gap-3 mb-6" data-aos="fade-right">
        <span class="w-8 h-px" style="background:rgba(0,200,255,0.3)"></span>
        <span class="text-xs font-bold uppercase tracking-widest text-gray-600"><?= t('portfolio.all_proj') ?></span>
    </div>
    <?php endif; ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
         data-grid-anim data-cols="3" data-cols-responsive='{"640":1,"768":2,"default":3}'>
        <?php foreach ($others as $j => $p):
            $cat  = $cats[$p['category']] ?? $cats['dev-web'];
            $tags = $p['tags'] ? explode(',', $p['tags']) : [];
        ?>
        <div class="group portfolio-card rounded-2xl overflow-hidden cursor-pointer"
             style="border:1px solid rgba(0,200,255,0.10);"
             onclick="openModal(<?= $p['id'] ?>)"
             data-aos="fade-up" data-aos-delay="<?= ($j % 3) * 80 ?>">

            <div class="relative h-44 overflow-hidden" style="background:linear-gradient(135deg,<?= $cat['color'] ?>12,<?= $cat['color'] ?>04)">
                <?php if ($p['image']): ?>
                <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                <?php else: ?>
                <div class="w-full h-full flex items-center justify-center">
                    <i class="fas <?= $cat['icon'] ?> text-5xl opacity-15" style="color:<?= $cat['color'] ?>"></i>
                </div>
                <?php endif; ?>
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                <div class="absolute top-3 left-3">
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full" style="background:<?= $cat['color'] ?>20;border:1px solid <?= $cat['color'] ?>45;color:<?= $cat['color'] ?>">
                        <i class="fas <?= $cat['icon'] ?> mr-1 text-xs"></i><?= $cat['label'] ?>
                    </span>
                </div>
            </div>

            <div class="p-5" style="background:rgba(10,24,58,0.65)">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <h3 class="font-heading font-semibold text-white text-base leading-snug group-hover:text-nc-cyan transition-colors line-clamp-2"><?= htmlspecialchars($p['title']) ?></h3>
                    <?php if ($p['year']): ?>
                    <span class="text-gray-600 text-xs flex-shrink-0"><?= $p['year'] ?></span>
                    <?php endif; ?>
                </div>
                <p class="text-gray-400 text-sm leading-relaxed mb-3 line-clamp-2"><?= htmlspecialchars($p['short_desc']) ?></p>
                <div class="flex flex-wrap gap-1 mb-3">
                    <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                    <span class="text-xs px-2 py-0.5 rounded-full" style="background:rgba(0,200,255,0.06);border:1px solid rgba(0,200,255,0.12);color:#64748b"><?= htmlspecialchars(trim($tag)) ?></span>
                    <?php endforeach; ?>
                </div>
                <span class="text-xs font-medium" style="color:<?= $cat['color'] ?>">
                    Voir le projet <i class="fas fa-arrow-right ml-1"></i>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Project Modal -->
<div id="project-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.85);backdrop-filter:blur(6px)">
    <div class="relative w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-2xl" style="background:#0a1835;border:1px solid rgba(0,200,255,0.2);">
        <button onclick="closeModal()" class="absolute top-4 right-4 z-10 w-9 h-9 rounded-full flex items-center justify-center text-gray-400 hover:text-white transition-colors" style="background:rgba(0,200,255,0.1)">
            <i class="fas fa-times"></i>
        </button>
        <div id="modal-content" class="p-8">
            <div class="animate-pulse space-y-4">
                <div class="h-6 bg-gray-700 rounded w-3/4"></div>
                <div class="h-4 bg-gray-700 rounded"></div>
                <div class="h-4 bg-gray-700 rounded w-5/6"></div>
            </div>
        </div>
    </div>
</div>

<!-- CTA -->
<section class="relative py-20 overflow-hidden">
    <div class="absolute inset-0" style="background:linear-gradient(135deg,rgba(0,200,255,0.07),rgba(0,102,204,0.10));border-top:1px solid rgba(0,200,255,0.10);border-bottom:1px solid rgba(0,102,204,0.10)"></div>
    <div class="max-w-4xl mx-auto px-4 text-center relative z-10" data-aos="zoom-in">
        <div class="badge mb-6 mx-auto"><i class="fas fa-rocket"></i> <?= t('portfolio.cta_badge') ?></div>
        <h2 class="font-heading font-black text-4xl md:text-5xl text-white mb-6">
            <?= tr('portfolio.cta_title') ?>
        </h2>
        <p class="text-gray-400 text-lg mb-10"><?= t('portfolio.cta_sub') ?></p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="<?= BASE ?>/devis.php" class="btn-primary"><i class="fas fa-paper-plane"></i> <?= t('btn.quote') ?></a>
            <a href="https://wa.me/22788672115" target="_blank" class="btn-outline"><i class="fab fa-whatsapp text-nc-green"></i> WhatsApp</a>
        </div>
    </div>
</section>

<a href="https://wa.me/22788672115" target="_blank"
   class="fixed bottom-6 right-6 z-50 w-14 h-14 rounded-full flex items-center justify-center hover:scale-110 transition-all"
   style="background:#25d366;box-shadow:0 0 25px rgba(37,211,102,0.4)">
    <i class="fab fa-whatsapp text-white text-2xl"></i>
</a>

<!-- Inline project data for modal -->
<script>
<?php
$modalData = [];
if ($conn && !$conn->connect_error) {
    $res_modal = $conn->query("SELECT * FROM portfolio_projects WHERE status='published'");
    if ($res_modal) while ($row = $res_modal->fetch_assoc()) {
        $modalData[(int)$row['id']] = [
            'id'         => (int)$row['id'],
            'title'      => $row['title'],
            'category'   => $row['category'],
            'short_desc' => $row['short_desc'],
            'full_desc'  => $row['full_desc'],
            'image'      => $row['image'],
            'tags'       => $row['tags'],
            'client'     => $row['client'],
            'year'       => $row['year'],
            'live_url'   => $row['live_url'],
        ];
    }
}
?>
const projectsData = <?php echo json_encode($modalData, JSON_UNESCAPED_UNICODE); ?>;
const catsData = <?php echo json_encode($cats, JSON_UNESCAPED_UNICODE); ?>;

function openModal(id) {
    const p    = projectsData[id];
    const cat  = catsData[p.category] || catsData['dev-web'];
    const tags = p.tags ? p.tags.split(',').map(t => t.trim()) : [];
    const modal = document.getElementById('project-modal');

    document.getElementById('modal-content').innerHTML = `
        <div class="space-y-6">
            ${p.image ? `<img src="${p.image}" alt="${p.title}" class="w-full h-64 object-cover rounded-xl">` : `
            <div class="w-full h-52 rounded-xl flex items-center justify-center" style="background:${cat.color}10;border:1px solid ${cat.color}25">
                <i class="fas ${cat.icon} text-7xl" style="color:${cat.color};opacity:0.25"></i>
            </div>`}
            <div>
                <div class="flex flex-wrap items-center gap-3 mb-3">
                    <span class="text-xs font-bold px-3 py-1 rounded-full" style="background:${cat.color}20;border:1px solid ${cat.color}45;color:${cat.color}">
                        <i class="fas ${cat.icon} mr-1"></i>${cat.label}
                    </span>
                    ${p.year ? `<span class="text-gray-500 text-sm">${p.year}</span>` : ''}
                    ${p.client ? `<span class="text-gray-500 text-sm"><i class="fas fa-building mr-1"></i>${p.client}</span>` : ''}
                </div>
                <h2 class="font-heading font-black text-2xl text-white mb-4">${p.title}</h2>
                ${p.full_desc
                    ? `<p class="text-gray-400 leading-relaxed mb-4">${p.full_desc}</p>`
                    : `<p class="text-gray-400 leading-relaxed mb-4">${p.short_desc}</p>`}
                ${tags.length ? `
                <div class="flex flex-wrap gap-2 mb-6">
                    ${tags.map(t => `<span class="text-xs px-2.5 py-1 rounded-full" style="background:rgba(0,200,255,0.08);border:1px solid rgba(0,200,255,0.18);color:#94a3b8">${t}</span>`).join('')}
                </div>` : ''}
                <div class="flex flex-wrap gap-3">
                    ${p.live_url ? `<a href="${p.live_url}" target="_blank" class="btn-primary text-sm py-2"><i class="fas fa-external-link-alt"></i> Voir le projet</a>` : ''}
                    <a href="<?= BASE ?>/devis.php" class="btn-outline text-sm py-2"><i class="fas fa-paper-plane"></i> Projet similaire ?</a>
                </div>
            </div>
        </div>`;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('project-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

document.getElementById('project-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>

<style>
/* ── Portfolio cards — 3D tilt ──────────────────────────── */
.portfolio-card {
    transition: box-shadow .4s ease, border-color .35s ease;
    transform-style: preserve-3d;
    will-change: transform;
    cursor: pointer;
}
.portfolio-card:hover {
    box-shadow: 0 35px 80px rgba(0,0,0,0.55), 0 0 50px rgba(0,200,255,0.18);
    border-color: rgba(0,200,255,0.45) !important;
}
.portfolio-card .card-shine {
    position: absolute; inset: 0; border-radius: inherit;
    background: radial-gradient(circle at var(--mx,50%) var(--my,50%), rgba(255,255,255,0.06) 0%, transparent 60%);
    pointer-events: none; z-index: 5; transition: opacity .3s;
    opacity: 0;
}
.portfolio-card:hover .card-shine { opacity: 1; }

/* ── Glitch text ────────────────────────────────────────── */
@keyframes glitch1 {
    0%,100%{clip-path:inset(0 0 98% 0);transform:translate(-2px,0)}
    25%{clip-path:inset(30% 0 50% 0);transform:translate(2px,0)}
    50%{clip-path:inset(60% 0 20% 0);transform:translate(-1px,0)}
    75%{clip-path:inset(80% 0 0 0);transform:translate(1px,0)}
}
@keyframes glitch2 {
    0%,100%{clip-path:inset(0 0 95% 0);transform:translate(2px,0)}
    25%{clip-path:inset(50% 0 30% 0);transform:translate(-2px,0)}
    50%{clip-path:inset(10% 0 70% 0);transform:translate(1px,0)}
    75%{clip-path:inset(90% 0 5% 0);transform:translate(-1px,0)}
}
.glitch-text { position: relative; display: inline-block; }
.glitch-text::before, .glitch-text::after {
    content: attr(data-text);
    position: absolute; inset: 0; pointer-events: none;
}
.glitch-text::before {
    color: #00ffff; animation: glitch1 4s infinite linear;
    text-shadow: -2px 0 #ff00ff; mix-blend-mode: screen;
}
.glitch-text::after {
    color: #ff00ff; animation: glitch2 4s infinite linear .15s;
    text-shadow: 2px 0 #00ffff; mix-blend-mode: screen;
}

/* cursor glow is handled globally by header.php */

/* ── Reveal line ────────────────────────────────────────── */
@keyframes revealLine { from{width:0;opacity:0} to{width:48px;opacity:1} }
.reveal-line { animation: revealLine .6s ease forwards; }

/* ── Stat block hover ───────────────────────────────────── */
.stat-block:hover > div { transform: translateY(-4px) scale(1.05); }

/* ── Filter tab active animation ────────────────────────── */
.filter-tab { position: relative; overflow: hidden; }
.filter-tab::after {
    content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%);
    height: 2px; width: 0; background: currentColor; transition: width .3s ease; border-radius: 1px;
}
.filter-tab:hover::after { width: 60%; }
.active-tab::after { width: 60% !important; }

/* ── Modal open animation ───────────────────────────────── */
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.88) translateY(30px); }
    to   { opacity: 1; transform: scale(1)    translateY(0); }
}
#project-modal > div { animation: none; }
#project-modal.flex > div { animation: modalIn .45s cubic-bezier(.16,1,.3,1) forwards; }

/* ── Tag hover ──────────────────────────────────────────── */
.tag-chip { transition: all .2s ease; }
.tag-chip:hover { background: rgba(0,200,255,0.15) !important; color: #00c8ff !important; transform: translateY(-1px); }

/* ── Line clamp ─────────────────────────────────────────── */
.line-clamp-2 {
    display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
}

/* ── Floating badge ─────────────────────────────────────── */
@keyframes floatBadge { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-5px)} }
.hero-badge { animation: floatBadge 3s ease-in-out infinite; }
</style>

<script>
/* ═══ PARTICLES ════════════════════════════════════════════ */
(function() {
    const canvas = document.getElementById('hero-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let W, H, particles = [], animId;

    function resize() {
        W = canvas.width  = canvas.offsetWidth;
        H = canvas.height = canvas.offsetHeight;
    }

    function Particle() {
        this.reset = function() {
            this.x  = Math.random() * W;
            this.y  = Math.random() * H;
            this.vx = (Math.random() - .5) * 0.4;
            this.vy = (Math.random() - .5) * 0.4 - 0.2;
            this.r  = Math.random() * 2 + 0.5;
            this.a  = Math.random() * 0.6 + 0.1;
            this.life = Math.random() * 200 + 100;
            this.age  = 0;
            this.hue  = Math.random() > 0.7 ? 195 : 220;
        };
        this.reset();
        this.y = Math.random() * H;
    }

    function init() {
        particles = [];
        for (let i = 0; i < 80; i++) { const p = new Particle(); particles.push(p); }
    }

    function draw() {
        ctx.clearRect(0, 0, W, H);
        particles.forEach(p => {
            p.age++;
            if (p.age > p.life) { p.reset(); return; }
            const lifeRatio = p.age / p.life;
            const alpha = p.a * Math.sin(lifeRatio * Math.PI);
            p.x += p.vx; p.y += p.vy;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = `hsla(${p.hue},100%,70%,${alpha})`;
            ctx.fill();
        });
        // Connect nearby particles
        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const dx = particles[i].x - particles[j].x;
                const dy = particles[i].y - particles[j].y;
                const d  = Math.sqrt(dx*dx+dy*dy);
                if (d < 80) {
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.strokeStyle = `rgba(0,200,255,${0.08*(1-d/80)})`;
                    ctx.lineWidth = 0.5;
                    ctx.stroke();
                }
            }
        }
        animId = requestAnimationFrame(draw);
    }

    window.addEventListener('resize', () => { resize(); init(); }, {passive:true});
    resize(); init(); draw();

    // Pause when tab hidden (perf)
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) cancelAnimationFrame(animId);
        else draw();
    });
})();

/* cursor glow is now global (header.php) */

/* ═══ 3D TILT ON CARDS ════════════════════════════════════ */
(function() {
    function applyTilt(card) {
        const shine = document.createElement('div');
        shine.className = 'card-shine';
        if (card.querySelector('.relative')) card.querySelector('.relative').appendChild(shine);

        card.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width;
            const y = (e.clientY - rect.top)  / rect.height;
            const rx = (y - .5) * -14;
            const ry = (x - .5) *  14;
            this.style.transform = `perspective(900px) rotateX(${rx}deg) rotateY(${ry}deg) translateY(-10px) scale(1.02)`;
            shine.style.setProperty('--mx', (x*100)+'%');
            shine.style.setProperty('--my', (y*100)+'%');
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    }
    document.querySelectorAll('.portfolio-card').forEach(applyTilt);
})();

/* ═══ COUNT-UP STATS ═══════════════════════════════════════ */
(function() {
    const nums = document.querySelectorAll('.count-num');
    if (!nums.length) return;
    const obs = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            obs.unobserve(entry.target);
            const el     = entry.target;
            const target = parseInt(el.dataset.target) || 0;
            const suffix = el.dataset.suffix || '';
            const dur    = 1600;
            let start = null;
            function step(ts) {
                if (!start) start = ts;
                const p = Math.min((ts - start) / dur, 1);
                const eased = 1 - Math.pow(1 - p, 4);
                el.textContent = Math.round(target * eased) + suffix;
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        });
    }, {threshold: 0.5});
    nums.forEach(n => obs.observe(n));
})();

/* ═══ GLITCH TEXT ══════════════════════════════════════════ */
(function() {
    const el = document.querySelector('.glitch-text');
    if (el) el.setAttribute('data-text', el.textContent);
})();

/* ═══ MAGNETIC BUTTONS ════════════════════════════════════ */
(function() {
    if (window.matchMedia('(pointer:coarse)').matches) return;
    document.querySelectorAll('.btn-primary, .btn-outline').forEach(btn => {
        btn.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = (e.clientX - rect.left - rect.width/2)  * 0.35;
            const y = (e.clientY - rect.top  - rect.height/2) * 0.35;
            this.style.transform = `translate(${x}px,${y}px) translateY(-2px)`;
        });
        btn.addEventListener('mouseleave', function() { this.style.transform = ''; });
    });
})();

/* ═══ STAGGERED CARD REVEAL ════════════════════════════════ */
(function() {
    const grids = document.querySelectorAll('[data-grid-anim]');
    if (!grids.length) return;
    grids.forEach(grid => {
        const cards = grid.querySelectorAll('.portfolio-card');
        cards.forEach((c, i) => {
            c.style.opacity = '0';
            c.style.transform = 'translateY(30px)';
            c.style.transition = `opacity .5s ease ${i*70}ms, transform .5s cubic-bezier(.16,1,.3,1) ${i*70}ms`;
        });
        const obs = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (!e.isIntersecting) return;
                obs.unobserve(e.target);
                e.target.style.opacity = '1';
                e.target.style.transform = '';
            });
        }, {threshold: 0.1, rootMargin:'0px 0px -40px 0px'});
        cards.forEach(c => obs.observe(c));
    });
})();

/* ═══ TAG CHIPS ════════════════════════════════════════════ */
document.querySelectorAll('[style*="background:rgba(0,200,255,0.0"]').forEach(el => {
    el.classList.add('tag-chip');
});

</script>

<?php include 'includes/footer.php'; ?>
