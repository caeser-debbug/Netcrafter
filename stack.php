<?php
$page_title = 'Stack Technique — Netcrafter';
require_once __DIR__ . '/includes/header.php';
$_d = fn($fr, $en) => ($GLOBALS['nc_lang'] ?? 'fr') === 'en' ? $en : $fr;
?>

<!-- ═══════════════════════════════════════
     HERO
═══════════════════════════════════════ -->
<section class="relative pt-32 pb-16 overflow-hidden">
    <div class="blob bg-nc-cyan"  style="width:550px;height:550px;top:-180px;left:-180px;"></div>
    <div class="blob bg-nc-blue"  style="width:450px;height:450px;bottom:-120px;right:-150px;animation-delay:2s;"></div>
    <div class="blob bg-nc-violet" style="width:300px;height:300px;top:40%;left:55%;animation-delay:4s;opacity:0.06;"></div>

    <!-- Grid overlay -->
    <div class="absolute inset-0 pointer-events-none"
         style="background-image:linear-gradient(rgba(0,200,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(0,200,255,0.04) 1px,transparent 1px);background-size:60px 60px;"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <div class="inline-flex items-center gap-2 badge mb-6" data-aos="fade-down">
            <i class="fas fa-layer-group"></i> <?= $_d('Technologies','Technologies') ?>
        </div>
        <h1 class="font-heading font-black text-5xl md:text-7xl text-white mb-6" data-aos="fade-up">
            <?= $_d('Notre','Our') ?> <span class="gradient-text"><?= $_d('Stack Technique','Tech Stack') ?></span>
        </h1>
        <p class="text-gray-400 text-xl max-w-2xl mx-auto leading-relaxed" data-aos="fade-up" data-aos-delay="100">
            <?= $_d('Les technologies que nous maîtrisons pour construire des solutions robustes et modernes.','The technologies we master to build robust and modern solutions.') ?>
        </p>

        <!-- Stats row -->
        <div class="flex flex-wrap justify-center gap-8 mt-12" data-aos="fade-up" data-aos-delay="200">
            <?php
            $stats = [
                ['30+', $_d('Technologies','Technologies'),      'fa-microchip'],
                ['7',   $_d("Ans d'expérience",'Years of experience'), 'fa-clock'],
                ['150+', $_d('Projets livrés','Projects delivered'),   'fa-rocket'],
                ['5',   $_d('Catégories','Categories'),          'fa-th-large'],
            ];
            foreach ($stats as $s): ?>
            <div class="glass rounded-2xl px-6 py-4 text-center hover-glow" style="border-color:rgba(0,200,255,0.15);">
                <div class="flex items-center gap-2 justify-center mb-1">
                    <i class="fas <?= $s[2] ?> text-nc-cyan text-sm"></i>
                    <span class="font-heading font-black text-2xl gradient-text"><?= $s[0] ?></span>
                </div>
                <div class="text-gray-500 text-xs"><?= $s[1] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════
     FILTER TABS
═══════════════════════════════════════ -->
<div class="sticky top-16 z-40 py-4" style="background:rgba(6,13,30,0.92);backdrop-filter:blur(20px);border-bottom:1px solid rgba(0,200,255,0.1);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap justify-center gap-2">
            <?php
            $filters = [
                ['all',       $_d('Tous','All'),               'fa-th'],
                ['frontend',  'Frontend',                       'fa-palette'],
                ['backend',   'Backend',                        'fa-server'],
                ['database',  $_d('Base de données','Database'),'fa-database'],
                ['tools',     $_d('Outils','Tools'),            'fa-wrench'],
                ['mobile',    'Mobile/PWA',                     'fa-mobile-alt'],
            ];
            foreach ($filters as $i => [$val, $label, $icon]): ?>
            <button class="filter-btn flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold transition-all duration-300 <?= $i === 0 ? 'active' : '' ?>"
                    data-filter="<?= $val ?>"
                    style="<?= $i === 0 ? 'background:rgba(0,200,255,0.2);color:#00c8ff;border:1px solid rgba(0,200,255,0.5);' : 'background:rgba(255,255,255,0.04);color:#94a3b8;border:1px solid rgba(255,255,255,0.08);' ?>">
                <i class="fas <?= $icon ?> text-xs"></i> <?= $label ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     TECHNOLOGY GRID
═══════════════════════════════════════ -->
<section class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <?php
        $categories = [
            [
                'key'   => 'frontend',
                'label' => 'Frontend',
                'color' => '#00c8ff',
                'icon'  => 'fa-palette',
                'techs' => [
                    ['HTML5 / CSS3',    'fab fa-html5',     'Expert',        $_d('Sémantique, accessibilité, animations CSS avancées','Semantics, accessibility, advanced CSS animations')],
                    ['JavaScript ES6+', 'fab fa-js',        'Expert',        $_d('Vanilla JS, DOM, Fetch API, modules ESM','Vanilla JS, DOM, Fetch API, ESM modules')],
                    ['TypeScript',      'fab fa-js',        'Advanced',      $_d('Typage fort pour projets scalables','Strong typing for scalable projects')],
                    ['Tailwind CSS',    'fa-wind',          'Expert',        $_d('Utility-first, thèmes personnalisés','Utility-first, custom themes')],
                    ['Bootstrap',       'fab fa-bootstrap', 'Advanced',      $_d('Grilles responsives, composants UI','Responsive grids, UI components')],
                    ['React.js',        'fab fa-react',     'Advanced',      $_d('SPA, hooks, context, Next.js','SPA, hooks, context, Next.js')],
                    ['Vue.js',          'fab fa-vuejs',     'Intermediate',  $_d('Composition API, Pinia, Nuxt','Composition API, Pinia, Nuxt')],
                    ['GSAP / AOS',      'fa-magic',         'Advanced',      $_d('Animations performantes et fluides','Smooth, high-performance animations')],
                ],
            ],
            [
                'key'   => 'backend',
                'label' => 'Backend',
                'color' => '#0066cc',
                'icon'  => 'fa-server',
                'techs' => [
                    ['PHP 8.x',   'fab fa-php',      'Expert',        $_d('OOP, PDO, REST API, Composer','OOP, PDO, REST API, Composer')],
                    ['MySQL',     'fa-database',     'Expert',        $_d('Requêtes complexes, optimisation, indexation','Complex queries, optimisation, indexing')],
                    ['Node.js',   'fab fa-node-js',  'Advanced',      $_d('Express, API REST, WebSockets','Express, REST API, WebSockets')],
                    ['Python',    'fab fa-python',   'Intermediate',  $_d('Scripts, automatisation, Flask','Scripts, automation, Flask')],
                    ['Laravel',   'fa-code',         'Advanced',      $_d('MVC, Eloquent ORM, Artisan','MVC, Eloquent ORM, Artisan')],
                    ['REST API',  'fa-plug',         'Expert',        $_d('Architecture RESTful, JSON, OAuth','RESTful architecture, JSON, OAuth')],
                ],
            ],
            [
                'key'   => 'database',
                'label' => $_d('Base de données','Database'),
                'color' => '#7c3aed',
                'icon'  => 'fa-database',
                'techs' => [
                    ['MySQL / MariaDB', 'fa-database',   'Expert',        $_d('SGBDR principal de nos projets','Main RDBMS for our projects')],
                    ['PostgreSQL',      'fa-server',     'Advanced',      $_d('Pour projets à fort volume de données','For high-volume data projects')],
                    ['Redis',           'fa-memory',     'Intermediate',  $_d("Cache, sessions, files d'attente",'Cache, sessions, message queues')],
                    ['Firebase',        'fa-fire',       'Intermediate',  $_d('Realtime database, auth, hosting','Realtime database, auth, hosting')],
                    ['SQLite',          'fa-file-code',  'Advanced',      $_d('Apps légères et embarquées','Lightweight and embedded apps')],
                ],
            ],
            [
                'key'   => 'tools',
                'label' => $_d('Outils','Tools'),
                'color' => '#10b981',
                'icon'  => 'fa-wrench',
                'techs' => [
                    ['Git / GitHub',     'fab fa-github',     'Expert',        $_d('Versioning, branches, CI/CD','Versioning, branches, CI/CD')],
                    ['Docker',           'fab fa-docker',     'Advanced',      $_d('Containerisation, environnements reproductibles','Containerisation, reproducible environments')],
                    ['VS Code',          'fa-code',           'Expert',        $_d('IDE principal, extensions custom','Main IDE, custom extensions')],
                    ['Figma',            'fa-pencil-ruler',   'Advanced',      $_d('Maquettes, prototypes, design system','Mockups, prototypes, design system')],
                    ['Linux / cPanel',   'fab fa-linux',      'Expert',        $_d('Déploiement, administration serveur','Deployment, server administration')],
                    ['Webpack / Vite',   'fa-box',            'Intermediate',  $_d('Build tools, bundling, HMR','Build tools, bundling, HMR')],
                    ['Postman',          'fa-paper-plane',    'Expert',        $_d('Tests API, collections, mocking','API testing, collections, mocking')],
                ],
            ],
            [
                'key'   => 'mobile',
                'label' => 'Mobile/PWA',
                'color' => '#f59e0b',
                'icon'  => 'fa-mobile-alt',
                'techs' => [
                    ['Progressive Web Apps', 'fa-mobile-alt',       'Expert',        $_d('Manifest, Service Worker, offline-first','Manifest, Service Worker, offline-first')],
                    ['React Native',         'fab fa-react',         'Intermediate',  $_d('Apps cross-platform iOS/Android','Cross-platform iOS/Android apps')],
                    ['Responsive Design',    'fa-laptop-mobile',     'Expert',        $_d('Mobile-first, toutes résolutions','Mobile-first, all resolutions')],
                    ['WebView Apps',         'fa-window-maximize',   'Expert',        $_d('Apps Android via WebView','Android apps via WebView')],
                ],
            ],
        ];

        $levelColors = [
            'Expert'       => ['bg' => 'rgba(16,185,129,0.15)',  'text' => '#10b981', 'border' => 'rgba(16,185,129,0.3)'],
            'Advanced'     => ['bg' => 'rgba(0,200,255,0.12)',   'text' => '#00c8ff', 'border' => 'rgba(0,200,255,0.3)'],
            'Intermediate' => ['bg' => 'rgba(245,158,11,0.12)',  'text' => '#f59e0b', 'border' => 'rgba(245,158,11,0.3)'],
        ];

        $levelLabels = [
            'Expert'       => $_d('Expert','Expert'),
            'Advanced'     => $_d('Avancé','Advanced'),
            'Intermediate' => $_d('Intermédiaire','Intermediate'),
        ];

        foreach ($categories as $catIdx => $cat):
        ?>
        <!-- Category Header -->
        <div class="flex items-center gap-4 mb-8 mt-12 first:mt-0" data-aos="fade-right">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background:<?= $cat['color'] ?>22;border:1px solid <?= $cat['color'] ?>44;">
                <i class="fas <?= $cat['icon'] ?> text-sm" style="color:<?= $cat['color'] ?>"></i>
            </div>
            <h2 class="font-heading font-bold text-2xl text-white"><?= $cat['label'] ?></h2>
            <div class="flex-1 h-px" style="background:linear-gradient(90deg,<?= $cat['color'] ?>44,transparent)"></div>
            <span class="text-sm font-medium px-3 py-1 rounded-full" style="background:<?= $cat['color'] ?>15;color:<?= $cat['color'] ?>;border:1px solid <?= $cat['color'] ?>33;">
                <?= count($cat['techs']) ?> <?= count($cat['techs']) > 1 ? $_d('technologies','technologies') : $_d('technologie','technology') ?>
            </span>
        </div>

        <!-- Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-4">
            <?php foreach ($cat['techs'] as $tIdx => $tech):
                $lc = $levelColors[$tech[2]] ?? $levelColors['Expert'];
            ?>
            <div class="tech-card group relative glass rounded-2xl p-5 hover-glow cursor-default overflow-hidden"
                 data-category="<?= $cat['key'] ?>"
                 data-aos="fade-up" data-aos-delay="<?= min($tIdx * 50, 300) ?>"
                 style="border-color:<?= $cat['color'] ?>22;">

                <!-- Top accent line -->
                <div class="absolute top-0 left-0 right-0 h-0.5 rounded-t-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                     style="background:linear-gradient(90deg,transparent,<?= $cat['color'] ?>,transparent);"></div>

                <!-- Icon + Level -->
                <div class="flex items-start justify-between mb-4">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-lg flex-shrink-0"
                         style="background:<?= $cat['color'] ?>18;border:1px solid <?= $cat['color'] ?>33;">
                        <i class="<?= $tech[1] ?>" style="color:<?= $cat['color'] ?>"></i>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                          style="background:<?= $lc['bg'] ?>;color:<?= $lc['text'] ?>;border:1px solid <?= $lc['border'] ?>;">
                        <?= $levelLabels[$tech[2]] ?? $tech[2] ?>
                    </span>
                </div>

                <!-- Name & Description -->
                <h3 class="font-heading font-bold text-white text-base mb-1 leading-tight"><?= $tech[0] ?></h3>
                <p class="text-gray-500 text-xs leading-relaxed"><?= $tech[3] ?></p>

                <!-- Hover detail -->
                <div class="absolute inset-x-0 bottom-0 h-0.5 scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left"
                     style="background:linear-gradient(90deg,<?= $cat['color'] ?>,transparent);"></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

    </div>
</section>

<!-- ═══════════════════════════════════════
     CTA
═══════════════════════════════════════ -->
<section class="py-20">
    <div class="section-divider mb-16"></div>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="glass rounded-3xl p-12 relative overflow-hidden" data-aos="zoom-in">
            <div class="blob bg-nc-cyan" style="width:300px;height:300px;top:-100px;right:-80px;opacity:0.07;"></div>
            <div class="blob bg-nc-violet" style="width:250px;height:250px;bottom:-80px;left:-60px;opacity:0.06;animation-delay:2s;"></div>

            <div class="relative z-10">
                <div class="inline-flex items-center gap-2 badge mb-6">
                    <i class="fas fa-comments"></i> <?= $_d('Parlons de votre projet','Let\'s talk about your project') ?>
                </div>
                <h2 class="font-heading font-black text-4xl md:text-5xl text-white mb-4">
                    <?= $_d("Besoin d'une technologie <span class=\"gradient-text\">spécifique ?</span>",'Need a <span class="gradient-text">specific technology?</span>') ?>
                </h2>
                <p class="text-gray-400 text-lg mb-8 max-w-2xl mx-auto">
                    <?= $_d("Vous avez des exigences techniques précises ? Discutons ensemble de votre stack idéal et nous vous proposerons la solution la plus adaptée.",'Do you have specific technical requirements? Let\'s discuss your ideal stack and we\'ll propose the best-suited solution.') ?>
                </p>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="<?= BASE ?>/devis.php" class="btn-primary text-base">
                        <i class="fas fa-rocket"></i> <?= $_d('Demander un devis gratuit','Request a free quote') ?>
                    </a>
                    <a href="<?= BASE ?>/service.php" class="btn-outline text-base">
                        <i class="fas fa-th-large"></i> <?= t('btn.our_services') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<style>
.filter-btn.active {
    background: rgba(0,200,255,0.2) !important;
    color: #00c8ff !important;
    border-color: rgba(0,200,255,0.5) !important;
}
.tech-card { transition: all 0.3s ease; }
.tech-card:hover { border-color: rgba(0,200,255,0.4) !important; }
</style>

<script>
// Filter logic
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const filter = this.dataset.filter;
        document.querySelectorAll('.tech-card').forEach(card => {
            card.style.display = (filter === 'all' || card.dataset.category === filter) ? '' : 'none';
        });
        // Show/hide category headers
        document.querySelectorAll('[data-category-key]').forEach(header => {
            header.style.display = (filter === 'all' || header.dataset.categoryKey === filter) ? '' : 'none';
        });
    });
});
</script>
