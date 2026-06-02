<?php
$page_title    = 'Netcrafter - Solutions Numériques Professionnelles au Niger';
$page_keywords = 'agence web Niger, développement web Niamey, agence digitale Niger, site internet Niger, création site web Niamey, Netcrafter, solutions numériques Niger, entreprise informatique Niamey';
include 'includes/header.php';
?>

<!-- ═══════════════════════════════════════
     HERO
═══════════════════════════════════════ -->
<section class="relative min-h-screen flex items-center overflow-hidden pt-20" id="home-hero">
    <canvas id="hero-canvas-home" class="absolute inset-0 w-full h-full pointer-events-none" style="z-index:1"></canvas>

    <!-- Blobs logo colors -->
    <div class="blob bg-nc-cyan"  style="width:700px;height:700px;top:-200px;left:-250px;opacity:0.18;"></div>
    <div class="blob bg-nc-blue"  style="width:600px;height:600px;bottom:-150px;right:-150px;animation-delay:2s;opacity:0.15;"></div>
    <div class="blob bg-nc-violet" style="width:400px;height:400px;top:30%;left:40%;opacity:0.04;animation-delay:4s;"></div>

    <!-- Circuit grid overlay -->
    <div class="absolute inset-0 pointer-events-none"
         style="background-image:
            linear-gradient(rgba(0,200,255,0.04) 1px,transparent 1px),
            linear-gradient(90deg,rgba(0,200,255,0.04) 1px,transparent 1px);
         background-size:55px 55px;">
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 py-20">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

            <!-- LEFT -->
            <div data-aos="fade-right">
                <!-- Badge disponibilité -->
                <div class="inline-flex items-center gap-2 glass rounded-full px-4 py-2 mb-6 text-sm font-medium" style="color:var(--accent-cyan)">
                    <span class="w-2 h-2 rounded-full bg-nc-green animate-pulse"></span>
                    <?= t('available') ?>
                </div>

                <h1 class="font-heading text-5xl md:text-6xl lg:text-7xl font-black leading-tight text-white mb-6">
                    <?= tr('home.hero_title') ?>
                </h1>

                <p class="text-gray-400 text-lg md:text-xl leading-relaxed mb-10 max-w-lg">
                    <?= t('home.hero_sub') ?>
                    <strong class="text-white"><?= t('results_guaranteed') ?></strong>
                </p>

                <div class="flex flex-wrap gap-4">
                    <a href="service.php" class="btn-primary text-base">
                        <i class="fas fa-rocket"></i> <?= t('btn.our_services') ?>
                    </a>
                    <a href="devis.php" class="btn-outline text-base">
                        <?= t('btn.free_quote') ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <!-- Stats avec compteurs animés -->
                <div class="grid grid-cols-3 gap-6 mt-12 pt-8" style="border-top:1px solid rgba(0,200,255,0.15)" data-counters>
                    <div class="anim-zoom delay-1">
                        <div class="text-3xl font-black gradient-text font-heading stat-number">
                            <span data-count="150" data-suffix="+">0+</span>
                        </div>
                        <div class="text-gray-500 text-sm mt-1"><?= t('home.stat_done') ?></div>
                    </div>
                    <div class="anim-zoom delay-3">
                        <div class="text-3xl font-black gradient-text font-heading stat-number">
                            <span data-count="84">0</span>
                        </div>
                        <div class="text-gray-500 text-sm mt-1"><?= t('home.stat_clients') ?></div>
                    </div>
                    <div class="anim-zoom delay-5">
                        <div class="text-3xl font-black gradient-text font-heading stat-number">
                            <span data-count="7" data-suffix=" ans">0 ans</span>
                        </div>
                        <div class="text-gray-500 text-sm mt-1"><?= t('home.stat_exp') ?></div>
                    </div>
                </div>
            </div>

            <!-- RIGHT — illustration + anneaux déco couleurs logo -->
            <div class="relative flex items-center justify-center" data-aos="fade-left" data-aos-delay="200">
                <!-- Anneaux animés -->
                <div class="absolute w-96 h-96 rounded-full" style="border:1px solid rgba(0,200,255,0.15);animation:spin 28s linear infinite;box-shadow:inset 0 0 40px rgba(0,200,255,0.03)"></div>
                <div class="absolute w-72 h-72 rounded-full" style="border:1px solid rgba(0,102,204,0.2);animation:spin 20s linear infinite reverse;"></div>
                <div class="absolute w-52 h-52 rounded-full" style="border:1px dashed rgba(0,200,255,0.12);animation:spin 14s linear infinite;"></div>
                <div class="absolute w-[440px] h-[440px] rounded-full" style="border:1px solid rgba(124,58,237,0.08);animation:spin 40s linear infinite reverse;"></div>

                <!-- Points décoratifs lumineux -->
                <div class="absolute w-96 h-96 rounded-full" style="animation:spin 28s linear infinite;">
                    <div class="absolute top-0 left-1/2 w-3.5 h-3.5 rounded-full -translate-x-1/2 -translate-y-1/2" style="background:#00c8ff;box-shadow:0 0 20px #00c8ff,0 0 8px #00c8ff;"></div>
                    <div class="absolute bottom-4 right-4 w-2 h-2 rounded-full" style="background:#4db8ff;box-shadow:0 0 10px #4db8ff;"></div>
                </div>
                <div class="absolute w-72 h-72 rounded-full" style="animation:spin 20s linear infinite reverse;">
                    <div class="absolute bottom-0 right-4 w-3 h-3 rounded-full" style="background:#0066cc;box-shadow:0 0 14px #0066cc;"></div>
                    <div class="absolute top-4 left-4 w-1.5 h-1.5 rounded-full" style="background:rgba(124,58,237,0.9);box-shadow:0 0 8px #7c3aed;"></div>
                </div>

                <!-- Logo central flottant -->
                <img src="image/logo-n.png" alt="Netcrafter"
                     class="relative z-10 w-48 h-48 hero-logo-holo drop-shadow-2xl"
                     style="filter:drop-shadow(0 0 50px rgba(0,200,255,0.55))">
            </div>
        </div>
    </div>

    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 text-gray-600 animate-bounce">
        <i class="fas fa-chevron-down"></i>
    </div>
</section>

<div class="section-divider max-w-7xl mx-auto"></div>

<!-- ═══════════════════════════════════════
     SERVICES
═══════════════════════════════════════ -->
<section id="services" class="py-24 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-16" data-aos="fade-up">
            <div class="badge mb-4"><i class="fas fa-star"></i> <?= t('home.what_we_do') ?></div>
            <h2 class="font-heading text-4xl md:text-5xl font-black text-white mb-4">
                <?= t('home.services_badge') ?>
            </h2>
            <p class="text-gray-400 text-lg max-w-2xl mx-auto"><?= t('home.services_sub') ?></p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
             data-grid-anim data-cols="3" data-cols-responsive='{"640":1,"768":2,"default":3}'>
            <?php
            $services = [
                ['icon'=>'fa-laptop-code',  'color'=>'#00c8ff', 'title'=>t('home.svc1_title'), 'desc'=>t('home.svc1_desc'), 'items'=>[t('home.svc1_i1'),t('home.svc1_i2'),t('home.svc1_i3')]],
                ['icon'=>'fa-bullhorn',     'color'=>'#0099dd', 'title'=>t('home.svc2_title'), 'desc'=>t('home.svc2_desc'), 'items'=>[t('home.svc2_i1'),t('home.svc2_i2'),t('home.svc2_i3')]],
                ['icon'=>'fa-palette',      'color'=>'#4db8ff', 'title'=>t('home.svc3_title'), 'desc'=>t('home.svc3_desc'), 'items'=>[t('home.svc3_i1'),t('home.svc3_i2'),t('home.svc3_i3')]],
                ['icon'=>'fa-shield-alt',   'color'=>'#00c8ff', 'title'=>t('home.svc4_title'), 'desc'=>t('home.svc4_desc'), 'items'=>[t('home.svc4_i1'),t('home.svc4_i2'),t('home.svc4_i3')]],
                ['icon'=>'fa-network-wired','color'=>'#0066cc', 'title'=>t('home.svc5_title'), 'desc'=>t('home.svc5_desc'), 'items'=>[t('home.svc5_i1'),t('home.svc5_i2'),t('home.svc5_i3')]],
                ['icon'=>'fa-tools',        'color'=>'#4db8ff', 'title'=>t('home.svc6_title'), 'desc'=>t('home.svc6_desc'), 'items'=>[t('home.svc6_i1'),t('home.svc6_i2'),t('home.svc6_i3')]],
            ];
            foreach ($services as $i => $svc): ?>
            <div class="service-card tilt-card p-6 relative overflow-hidden" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-5"
                     style="background:<?= $svc['color'] ?>18;border:1px solid <?= $svc['color'] ?>35;">
                    <i class="fas <?= $svc['icon'] ?> text-xl" style="color:<?= $svc['color'] ?>;"></i>
                </div>
                <h3 class="font-heading font-bold text-white text-lg mb-2"><?= $svc['title'] ?></h3>
                <p class="text-gray-400 text-sm leading-relaxed mb-4"><?= $svc['desc'] ?></p>
                <ul class="space-y-2">
                    <?php foreach ($svc['items'] as $item): ?>
                    <li class="flex items-center gap-2 text-gray-500 text-sm">
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:<?= $svc['color'] ?>;"></span>
                        <?= $item ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-10" data-aos="fade-up">
            <a href="service.php" class="btn-outline"><?= t('home.all_services') ?> <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
</section>

<div class="section-divider max-w-7xl mx-auto"></div>

<!-- ═══════════════════════════════════════
     EXPERTISES CLÉS
═══════════════════════════════════════ -->
<section id="expertises" class="py-24 relative overflow-hidden">
    <div class="blob bg-nc-blue" style="width:350px;height:350px;top:50%;left:-150px;opacity:0.07;animation-delay:1s;"></div>
    <div class="blob bg-nc-cyan" style="width:300px;height:300px;bottom:-50px;right:-100px;opacity:0.06;animation-delay:3s;"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16" data-aos="fade-up">
            <div class="badge mb-4"><i class="fas fa-bolt"></i> <?= t('home.what_we_master') ?></div>
            <h2 class="font-heading text-4xl md:text-5xl font-black text-white mb-4">
                <span class="gradient-text"><?= t('home.experts_title') ?></span>
            </h2>
            <p class="text-gray-400 text-lg max-w-3xl mx-auto">
                <?= t('home.hero_sub') ?>
            </p>
        </div>

        <?php
        $_d = fn($fr, $en) => ($GLOBALS['nc_lang'] ?? 'fr') === 'en' ? $en : $fr;
        $expertises = [
            [
                'icon'  => 'fa-laptop-code',
                'color' => '#00c8ff',
                'num'   => '01',
                'title' => $_d('Développement Web','Web Development'),
                'sub'   => $_d('Sites · Apps · E-commerce','Sites · Apps · E-commerce'),
                'desc'  => $_d('Sites vitrines impactants, applications web sur mesure, plateformes e-commerce et portails métier. Du design à la mise en ligne.','Impactful showcase sites, custom web applications, e-commerce platforms and business portals. From design to go-live.'),
                'items' => [$_d('Sites corporatifs & vitrines','Corporate & showcase sites'),$_d('Applications web complexes','Complex web applications'),$_d('E-commerce & paiement mobile','E-commerce & mobile payment'),$_d('APIs REST & intégrations','REST APIs & integrations')],
                'badge' => $_d('Expertise principale','Core expertise'),
            ],
            [
                'icon'  => 'fa-mobile-alt',
                'color' => '#0099dd',
                'num'   => '02',
                'title' => $_d('WebView & Apps Mobiles','WebView & Mobile Apps'),
                'sub'   => 'Android · iOS · PWA',
                'desc'  => $_d('Transformez votre site web en application mobile native grâce aux WebViews optimisées, avec notifications push et mode offline.','Transform your website into a native mobile app using optimised WebViews, with push notifications and offline mode.'),
                'items' => [$_d('Conversion web → app mobile','Web → mobile app conversion'),$_d('Publication Play Store / App Store','Play Store / App Store publishing'),$_d('Notifications push FCM','FCM push notifications'),'Progressive Web App (PWA)'],
                'badge' => $_d('Tendance 2025','2025 Trend'),
            ],
            [
                'icon'  => 'fa-robot',
                'color' => '#4db8ff',
                'num'   => '03',
                'title' => $_d('IA & Chatbots','AI & Chatbots'),
                'sub'   => 'OpenAI · Dialogflow · Custom',
                'desc'  => $_d('Déployez des assistants IA sur votre site, WhatsApp ou application. Support client automatisé, qualification de leads, réponses contextuelles.','Deploy AI assistants on your site, WhatsApp or app. Automated customer support, lead qualification and contextual responses.'),
                'items' => [$_d('Chatbots multi-canaux','Multi-channel chatbots'),$_d('Intégration OpenAI / LLMs','OpenAI / LLMs integration'),$_d('Base de connaissances IA','AI knowledge base'),$_d('Escalade humain intelligente','Intelligent human escalation')],
                'badge' => $_d('Innovation IA','AI Innovation'),
            ],
            [
                'icon'  => 'fa-comment-dots',
                'color' => '#25d366',
                'num'   => '04',
                'title' => $_d('Systèmes WhatsApp','WhatsApp Systems'),
                'sub'   => 'Business API · Bots · Notifications',
                'desc'  => $_d('Automatisez votre relation client via WhatsApp : bots de commande, notifications automatiques, catalogues produits et flux de vente.','Automate your customer relationship via WhatsApp: order bots, automatic notifications, product catalogues and sales flows.'),
                'items' => [$_d('API WhatsApp Business officielle','Official WhatsApp Business API'),$_d('Bots de prise de commande','Order-taking bots'),$_d('Notifications automatisées','Automated notifications'),$_d('Catalogues & paiements intégrés','Catalogues & integrated payments')],
                'badge' => $_d('ROI immédiat','Immediate ROI'),
            ],
            [
                'icon'  => 'fa-chart-bar',
                'color' => '#0066cc',
                'num'   => '05',
                'title' => $_d('Systèmes de Gestion','Management Systems'),
                'sub'   => 'ERP · CRM · POS · ' . $_d('Facturation','Invoicing'),
                'desc'  => $_d('Logiciels de gestion sur mesure : suivi des stocks, comptabilité, gestion RH, CRM client et facturation électronique adaptés à votre métier.','Custom management software: stock tracking, accounting, HR management, CRM and electronic invoicing tailored to your business.'),
                'items' => [$_d('ERP modulaires sur mesure','Custom modular ERP'),$_d('CRM & suivi commercial','CRM & sales tracking'),$_d('Facturation & comptabilité','Invoicing & accounting'),$_d('Gestion stocks & fournisseurs','Stock & supplier management')],
                'badge' => $_d('Productivité ×3','Productivity ×3'),
            ],
            [
                'icon'  => 'fa-map-marker-alt',
                'color' => '#4db8ff',
                'num'   => '06',
                'title' => $_d('Suivi & Tracking','Tracking & Monitoring'),
                'sub'   => $_d('Livraison · Assets · Reporting','Delivery · Assets · Reporting'),
                'desc'  => $_d('Plateformes de suivi en temps réel : colis, véhicules, équipements. Tableaux de bord analytiques avec alertes et rapports automatiques.','Real-time tracking platforms: parcels, vehicles, equipment. Analytical dashboards with alerts and automatic reports.'),
                'items' => [$_d('Tracking livraison en temps réel','Real-time delivery tracking'),$_d('QR codes & géolocalisation','QR codes & geolocation'),$_d('Dashboards analytiques','Analytical dashboards'),$_d('Alertes SMS / WhatsApp','SMS / WhatsApp alerts')],
                'badge' => $_d('Temps réel','Real time'),
            ],
        ];
        ?>

        <!-- Grille 3×2 avec cards impactantes -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
             data-grid-anim data-cols="3" data-cols-responsive='{"640":1,"768":2,"default":3}'>
            <?php foreach ($expertises as $i => $exp): ?>
            <div class="expertise-card tilt-card group relative rounded-2xl p-6 cursor-default overflow-hidden"
                 style="background:rgba(10,24,58,0.5);border:1px solid rgba(0,200,255,0.1);"
                 data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">

                <!-- Numéro déco en fond -->
                <div class="absolute top-4 right-5 font-black text-7xl leading-none pointer-events-none select-none"
                     style="color:<?= $exp['color'] ?>;opacity:0.04;"><?= $exp['num'] ?></div>

                <!-- Accent border top -->
                <div class="absolute top-0 left-0 right-0 h-0.5 rounded-t-2xl transition-all duration-500 group-hover:opacity-100 opacity-0"
                     style="background:linear-gradient(to right,<?= $exp['color'] ?>,transparent)"></div>

                <!-- Icon + badge -->
                <div class="flex items-start justify-between mb-5">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                         style="background:<?= $exp['color'] ?>15;border:1px solid <?= $exp['color'] ?>30;">
                        <i class="fas <?= $exp['icon'] ?> text-xl" style="color:<?= $exp['color'] ?>"></i>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full"
                          style="background:<?= $exp['color'] ?>12;border:1px solid <?= $exp['color'] ?>30;color:<?= $exp['color'] ?>;">
                        <?= $exp['badge'] ?>
                    </span>
                </div>

                <!-- Title -->
                <h3 class="font-heading font-black text-white text-xl mb-1 group-hover:text-nc-cyan transition-colors"><?= $exp['title'] ?></h3>
                <p class="text-xs font-semibold mb-3" style="color:<?= $exp['color'] ?>;opacity:0.8;"><?= $exp['sub'] ?></p>
                <p class="text-gray-400 text-sm leading-relaxed mb-5"><?= $exp['desc'] ?></p>

                <!-- Items -->
                <ul class="space-y-2">
                    <?php foreach ($exp['items'] as $item): ?>
                    <li class="flex items-center gap-2.5 text-sm text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:<?= $exp['color'] ?>"></span>
                        <?= $item ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- CTA vers devis -->
        <div class="text-center mt-12" data-aos="fade-up">
            <p class="text-gray-500 text-sm mb-4"><?= t('home.need_solution') ?></p>
            <div class="flex flex-wrap gap-4 justify-center">
                <a href="devis.php"   class="btn-primary"><i class="fas fa-paper-plane"></i> <?= t('home.devis_24h') ?></a>
                <a href="portfolio.php" class="btn-outline"><i class="fas fa-layer-group"></i> <?= t('home.see_our_work') ?></a>
            </div>
        </div>
    </div>
</section>

<div class="section-divider max-w-7xl mx-auto"></div>

<!-- ═══════════════════════════════════════
     VOLETS (compact)
═══════════════════════════════════════ -->
<section id="volets" class="py-20 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12" data-aos="fade-up">
            <div class="badge mb-4"><i class="fas fa-cubes"></i> <?= t('home.volets_badge') ?></div>
            <h2 class="font-heading text-3xl md:text-4xl font-black text-white mb-3">
                <?= tr('home.volets_title') ?>
            </h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6"
             data-grid-anim data-cols="3" data-cols-responsive='{"640":1,"768":2,"default":3}'>
            <?php foreach ([
                ['WORK','#00c8ff','fa-briefcase','Netcrafter Work',
                 'Projets informatiques, développement web, sécurité et réseaux sur mesure.',
                 'service.php','Découvrir nos services'],
                ['SHOP','#0066cc','fa-shopping-bag','Netcrafter Shop',
                 'Matériel informatique, équipements de surveillance et accessoires pro.',
                 'shop/shop.php','Voir la boutique'],
                ['FORMATION','#4db8ff','fa-graduation-cap','Netcrafter Formation',
                 'Programmes certifiants en informatique, développement et digital.',
                 'formation/formations.php','Voir les formations'],
            ] as $i => [$slug,$color,$icon,$title,$desc,$link,$cta]): ?>
            <a href="<?= $link ?>" class="volet-card tilt-card group flex flex-col gap-4 p-7 rounded-2xl"
               style="background:rgba(10,24,58,0.55);border:1px solid rgba(0,200,255,0.1);text-decoration:none;"
               data-aos="fade-up" data-aos-delay="<?= $i*100 ?>">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                         style="background:<?= $color ?>15;border:1px solid <?= $color ?>30;">
                        <i class="fas <?= $icon ?> text-xl" style="color:<?= $color ?>"></i>
                    </div>
                    <div>
                        <span class="text-xs font-black uppercase tracking-widest" style="color:<?= $color ?>"><?= $slug ?></span>
                        <h3 class="font-heading font-bold text-white text-lg leading-tight"><?= $title ?></h3>
                    </div>
                </div>
                <p class="text-gray-400 text-sm leading-relaxed"><?= $desc ?></p>
                <span class="text-sm font-semibold flex items-center gap-1.5 group-hover:gap-2.5 transition-all mt-auto" style="color:<?= $color ?>">
                    <?= $cta ?> <i class="fas fa-arrow-right text-xs"></i>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
/* ── 3D Tilt cards ──────────────────────────────────────── */
.tilt-card {
    transform-style: preserve-3d;
    will-change: transform;
    transition: box-shadow .4s ease, border-color .3s ease;
}
.tilt-card:hover {
    box-shadow: 0 30px 70px rgba(0,0,0,0.45), 0 0 40px rgba(0,200,255,0.1);
    border-color: rgba(0,200,255,0.35) !important;
}
.tilt-shine {
    position: absolute; inset: 0; border-radius: inherit;
    background: radial-gradient(circle at var(--mx,50%) var(--my,50%), rgba(255,255,255,0.05) 0%, transparent 60%);
    pointer-events: none; opacity: 0; transition: opacity .3s; z-index: 2;
}
.tilt-card:hover .tilt-shine { opacity: 1; }

/* ── Expertise card top-glow ────────────────────────────── */
.expertise-card { transition: transform .35s ease, box-shadow .35s ease, border-color .35s ease; }
.expertise-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 25px 60px rgba(0,200,255,0.08);
    border-color: rgba(0,200,255,0.28) !important;
}
.expertise-card .top-accent {
    position: absolute; top: 0; left: 0; right: 0; height: 2px; border-radius: 2px 2px 0 0;
    opacity: 0; transition: opacity .4s;
}
.expertise-card:hover .top-accent { opacity: 1; }

/* ── Infinite marquee ───────────────────────────────────── */
@keyframes marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
.marquee-track { animation: marquee 28s linear infinite; }
.marquee-container:hover .marquee-track { animation-play-state: paused; }
.tech-chip:hover {
    background: rgba(0,200,255,0.07) !important;
    border-color: rgba(0,200,255,0.2) !important;
    transform: translateY(-2px);
}

/* ── Typewriter cursor ──────────────────────────────────── */
.typewriter-cursor { display:inline-block;width:2px;height:1em;background:#00c8ff;vertical-align:text-bottom;animation:blink 1s step-end infinite;margin-left:2px; }

/* ── Hero logo holographic ──────────────────────────────── */
@keyframes holo-rotate { 0%{filter:drop-shadow(0 0 30px rgba(0,200,255,0.5)) hue-rotate(0deg)} 50%{filter:drop-shadow(0 0 50px rgba(0,200,255,0.7)) hue-rotate(20deg)} 100%{filter:drop-shadow(0 0 30px rgba(0,200,255,0.5)) hue-rotate(0deg)} }
.hero-logo-holo { animation: float 6s ease-in-out infinite, holo-rotate 4s ease-in-out infinite; }

/* ── Volet card hover ───────────────────────────────────── */
.volet-card {
    transition: transform .35s cubic-bezier(.16,1,.3,1), box-shadow .35s ease, border-color .35s ease;
}
.volet-card:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 30px 60px rgba(0,0,0,0.4);
}

/* ── Testimonial card ───────────────────────────────────── */
.testi-card {
    transition: transform .35s ease, box-shadow .35s ease;
    position: relative; overflow: hidden;
}
.testi-card:hover { transform: translateY(-5px); box-shadow: 0 25px 55px rgba(0,0,0,0.35); }
.testi-card::before {
    content: ''; position: absolute; top: -1px; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, var(--accent-cyan), var(--accent-blue));
    transform: scaleX(0); transition: transform .4s ease; transform-origin: left;
}
.testi-card:hover::before { transform: scaleX(1); }

/* ── Section reveal heading ─────────────────────────────── */
.section-heading-reveal {
    background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,0.5) 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.section-heading-reveal.aos-animate {
    background: linear-gradient(135deg, #ffffff 0%, #94a3b8 100%);
    -webkit-background-clip: text; background-clip: text;
}

/* ── Stats ring ─────────────────────────────────────────── */
@keyframes ring-pulse { 0%,100%{transform:scale(1);opacity:.3} 50%{transform:scale(1.15);opacity:.6} }
.stat-ring { animation: ring-pulse 3s ease-in-out infinite; border-radius:50%; position:absolute;inset:-8px;border:1px solid rgba(0,200,255,0.2);pointer-events:none; }
</style>

<!-- ═══ SOCIAL PROOF STRIP ═══════════════════════════════ -->
<div class="py-10 overflow-hidden" style="border-top:1px solid rgba(0,200,255,0.07);border-bottom:1px solid rgba(0,200,255,0.07);background:rgba(10,24,58,0.3)">
    <div class="max-w-7xl mx-auto px-4 flex flex-wrap justify-center gap-8 md:gap-16" data-aos="fade-up">
        <?php foreach ([
            ['150+', $_d('Projets livrés','Projects delivered'), 'fa-rocket', '#00c8ff'],
            ['98%',  $_d('Satisfaction client','Client satisfaction'), 'fa-heart', '#10b981'],
            ['7',    $_d('Ans d\'expérience','Years of experience'), 'fa-calendar-alt', '#0066cc'],
            ['24/7', $_d('Support disponible','Support available'), 'fa-headset', '#7c3aed'],
            ['Niger',  $_d('Basé à Niamey','Based in Niamey'), 'fa-map-marker-alt', '#4db8ff'],
        ] as [$val,$lbl,$icon,$col]): ?>
        <div class="flex items-center gap-3 group cursor-default">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background:<?= $col ?>12;border:1px solid <?= $col ?>22;transition:all .3s"
                 onmouseover="this.style.background='<?= $col ?>25';this.style.transform='scale(1.1)'"
                 onmouseout="this.style.background='<?= $col ?>12';this.style.transform=''">
                <i class="fas <?= $icon ?> text-sm" style="color:<?= $col ?>"></i>
            </div>
            <div>
                <div class="font-heading font-black text-xl text-white leading-none"><?= $val ?></div>
                <div class="text-gray-500 text-xs mt-0.5"><?= $lbl ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="section-divider max-w-7xl mx-auto"></div>

<!-- ═══════════════════════════════════════
     CTA BANNER
═══════════════════════════════════════ -->
<section class="py-24 relative overflow-hidden">
    <div class="absolute inset-0" style="background:linear-gradient(135deg,rgba(0,200,255,0.09) 0%,rgba(0,102,204,0.13) 50%,rgba(124,58,237,0.06) 100%);"></div>
    <div class="absolute inset-0" style="border-top:1px solid rgba(0,200,255,0.12);border-bottom:1px solid rgba(0,102,204,0.12);"></div>
    <!-- Animated orbs -->
    <div class="absolute w-64 h-64 rounded-full" style="background:radial-gradient(circle,rgba(0,200,255,0.12),transparent);top:-80px;left:-80px;animation:float 8s ease-in-out infinite;"></div>
    <div class="absolute w-48 h-48 rounded-full" style="background:radial-gradient(circle,rgba(124,58,237,0.1),transparent);bottom:-60px;right:-60px;animation:float 6s ease-in-out infinite reverse;"></div>
    <!-- Grid pattern -->
    <div class="absolute inset-0 pointer-events-none" style="background-image:linear-gradient(rgba(0,200,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,200,255,0.03) 1px,transparent 1px);background-size:40px 40px;"></div>

    <div class="max-w-5xl mx-auto px-4 text-center relative z-10" data-aos="zoom-in">
        <div class="badge mb-6 mx-auto"><i class="fas fa-bolt"></i> <?= t('home.cta_badge') ?></div>
        <h2 class="font-heading text-4xl md:text-5xl font-black text-white mb-4">
            <?= tr('home.cta_action') ?>
        </h2>
        <p class="text-gray-400 text-lg mb-10 max-w-2xl mx-auto">
            <?= t('home.cta_action_sub') ?>
        </p>
        <div class="flex flex-wrap gap-4 justify-center">
            <a href="devis.php" class="btn-primary text-base">
                <i class="fas fa-paper-plane"></i> <?= t('home.devis_24h') ?>
            </a>
            <a href="https://wa.me/22788672115" target="_blank" class="btn-outline text-base">
                <i class="fab fa-whatsapp text-nc-green"></i> <?= t('home.whatsapp_direct') ?>
            </a>
        </div>
    </div>
</section>

<div class="section-divider max-w-7xl mx-auto"></div>

<!-- ═══════════════════════════════════════
     RÉALISATIONS (depuis portfolio DB)
═══════════════════════════════════════ -->
<?php
$pf_colors = ['dev-web'=>'#00c8ff','webview'=>'#0099dd','ia-chatbot'=>'#4db8ff','whatsapp'=>'#25d366','gestion'=>'#0066cc','suivi'=>'#4db8ff','design'=>'#0099dd','securite'=>'#0066cc'];
$pf_labels = ['dev-web'=>'Dev Web','webview'=>'WebView','ia-chatbot'=>'IA & Chatbot','whatsapp'=>'WhatsApp','gestion'=>'Gestion','suivi'=>'Suivi','design'=>'Design','securite'=>'Sécurité'];
$pf_icons  = ['dev-web'=>'fa-laptop-code','webview'=>'fa-mobile-alt','ia-chatbot'=>'fa-robot','whatsapp'=>'fa-comment-dots','gestion'=>'fa-chart-bar','suivi'=>'fa-map-marker-alt','design'=>'fa-palette','securite'=>'fa-shield-alt'];
$pf_list   = [];
try {
    $pf_cfg = file_exists(__DIR__ . '/includes/db_config.php')
        ? include(__DIR__ . '/includes/db_config.php')
        : ['host'=>'localhost','user'=>'root','pass'=>'','db'=>'netcrafter'];
    $pf_db  = new mysqli($pf_cfg['host'], $pf_cfg['user'], $pf_cfg['pass'], $pf_cfg['db']);
    if (!$pf_db->connect_error) {
        $pf_db->set_charset('utf8mb4');
        $pf_res = $pf_db->query("SELECT * FROM portfolio_projects WHERE status='published' ORDER BY featured DESC, order_num ASC LIMIT 6");
        $pf_list = $pf_res ? $pf_res->fetch_all(MYSQLI_ASSOC) : [];
    }
} catch (Exception $e) { $pf_list = []; }
?>
<section id="realisations" class="py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-16" data-aos="fade-up">
            <div class="badge mb-4"><i class="fas fa-layer-group"></i> <?= t('portfolio.badge') ?></div>
            <h2 class="font-heading text-4xl md:text-5xl font-black text-white mb-4">
                <span class="gradient-text"><?= t('portfolio.title') ?></span>
            </h2>
            <p class="text-gray-400 text-lg max-w-2xl mx-auto"><?= t('portfolio.sub') ?></p>
        </div>

        <?php if (!empty($pf_list)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
             data-grid-anim data-cols="3" data-cols-responsive='{"640":1,"768":2,"default":3}'>
            <?php foreach ($pf_list as $i => $proj):
                $pcolor = $pf_colors[$proj['category']] ?? '#00c8ff';
                $plabel = $pf_labels[$proj['category']] ?? $proj['category'];
                $picon  = $pf_icons[$proj['category']]  ?? 'fa-folder';
                $tags   = $proj['tags'] ? array_slice(explode(',', $proj['tags']), 0, 3) : [];
            ?>
            <div class="group relative overflow-hidden rounded-2xl cursor-pointer"
                 style="border:1px solid rgba(0,200,255,0.1);"
                 onmouseover="this.style.borderColor='<?= $pcolor ?>40'"
                 onmouseout="this.style.borderColor='rgba(0,200,255,0.1)'"
                 data-aos="fade-up" data-aos-delay="<?= $i * 70 ?>">
                <!-- Image / Placeholder -->
                <div class="relative h-52 overflow-hidden" style="background:linear-gradient(135deg,<?= $pcolor ?>12,<?= $pcolor ?>04)">
                    <?php if ($proj['image']): ?>
                    <img src="<?= htmlspecialchars($proj['image']) ?>" alt="<?= htmlspecialchars($proj['title']) ?>"
                         class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center">
                        <i class="fas <?= $picon ?> text-6xl" style="color:<?= $pcolor ?>;opacity:0.18;"></i>
                    </div>
                    <?php endif; ?>
                    <div class="absolute inset-0" style="background:linear-gradient(to top,rgba(6,13,30,0.88),transparent 55%)"></div>
                    <!-- Hover overlay -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 p-5"
                         style="background:linear-gradient(135deg,<?= $pcolor ?>e0,<?= $pcolor ?>a0)">
                        <i class="fas <?= $picon ?> text-3xl text-white mb-3"></i>
                        <p class="text-center text-white text-sm leading-relaxed"><?= htmlspecialchars(substr($proj['short_desc'], 0, 100)) ?>...</p>
                        <a href="portfolio.php" class="mt-4 text-xs font-bold bg-white/20 px-3 py-1.5 rounded-full text-white hover:bg-white/30 transition-colors">
                            Voir le portfolio <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    <!-- Category badge -->
                    <div class="absolute top-3 left-3">
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full"
                              style="background:<?= $pcolor ?>22;border:1px solid <?= $pcolor ?>45;color:<?= $pcolor ?>;">
                            <i class="fas <?= $picon ?> mr-1 text-xs"></i><?= $plabel ?>
                        </span>
                    </div>
                    <?php if ($proj['featured']): ?>
                    <div class="absolute top-3 right-3">
                        <i class="fas fa-star text-yellow-400 text-sm"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Info -->
                <div class="p-4" style="background:rgba(10,24,58,0.65)">
                    <h3 class="font-heading font-bold text-white text-base mb-1 group-hover:text-nc-cyan transition-colors"><?= htmlspecialchars($proj['title']) ?></h3>
                    <div class="flex flex-wrap gap-1 mt-2">
                        <?php foreach ($tags as $tag): ?>
                        <span class="text-xs px-2 py-0.5 rounded-full" style="background:rgba(0,200,255,0.06);border:1px solid rgba(0,200,255,0.12);color:#64748b"><?= htmlspecialchars(trim($tag)) ?></span>
                        <?php endforeach; ?>
                        <?php if ($proj['year']): ?><span class="text-xs text-gray-600 ml-auto"><?= $proj['year'] ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-12 text-gray-600">
            <i class="fas fa-folder-open text-4xl mb-3 block"></i>
            <p>Portfolio bientôt disponible.</p>
        </div>
        <?php endif; ?>

        <div class="text-center mt-10" data-aos="fade-up">
            <a href="portfolio.php" class="btn-outline">
                <?= t('home.see_all_projects') ?> <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
</section>

<div class="section-divider max-w-7xl mx-auto"></div>

<!-- ═══════════════════════════════════════
     TÉMOIGNAGES
═══════════════════════════════════════ -->
<section id="temoignages" class="py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-16" data-aos="fade-up">
            <div class="badge mb-4"><i class="fas fa-quote-right"></i> <?= t('home.trust') ?></div>
            <h2 class="font-heading text-4xl md:text-5xl font-black text-white mb-4">
                <span class="gradient-text"><?= t('home.testimonials') ?></span>
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6"
             data-grid-anim data-cols="3" data-cols-responsive='{"640":1,"768":2,"default":3}'>
            <?php
            $testimonials = [
                ['name'=>'Ismael Ibrah', 'role'=>'Gérant, MOTATA',           'stars'=>5, 'text'=>'Netcrafter a transformé notre présence en ligne. Le site génère des leads de qualité. Équipe professionnelle, réactive et à l\'écoute.'],
                ['name'=>'Abdoul Majid','role'=>'Génie Civil',               'stars'=>5, 'text'=>'Installation parfaite de notre système de vidéosurveillance et réseau. Expertise technique impressionnante et service après-vente excellent.'],
                ['name'=>'Mari Moussa', 'role'=>'DRH',                       'stars'=>5, 'text'=>'La formation a permis à notre équipe de monter en compétences rapidement. Pédagogues, patients et très professionnels.'],
            ];
            $avatarColors = ['#00c8ff','#0066cc','#4db8ff'];
            foreach ($testimonials as $i => $t): ?>
            <div class="glass rounded-2xl p-6 relative overflow-hidden" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                <!-- Accent corner -->
                <div class="absolute top-0 right-0 w-16 h-16 opacity-10 rounded-bl-full"
                     style="background:<?= $avatarColors[$i] ?>"></div>

                <div class="flex gap-1 mb-4">
                    <?php for ($s = 0; $s < 5; $s++): ?>
                    <i class="fas fa-star text-sm <?= $s < $t['stars'] ? 'text-yellow-400' : 'text-gray-700' ?>"></i>
                    <?php endfor; ?>
                </div>
                <p class="text-gray-300 text-sm leading-relaxed mb-6 italic">"<?= $t['text'] ?>"</p>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm"
                         style="background:linear-gradient(135deg,<?= $avatarColors[$i] ?>,<?= $avatarColors[($i+1)%3] ?>)">
                        <?= strtoupper(substr($t['name'],0,1)) ?>
                    </div>
                    <div>
                        <div class="font-semibold text-white text-sm"><?= $t['name'] ?></div>
                        <div class="text-gray-500 text-xs"><?= $t['role'] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="section-divider max-w-7xl mx-auto"></div>

<!-- ═══════════════════════════════════════
     TECHNOLOGIES
═══════════════════════════════════════ -->
<section class="py-16 overflow-hidden">
    <div class="text-center mb-10" data-aos="fade-up">
        <h3 class="font-heading text-2xl font-bold text-gray-600"><?= t('home.tech_title') ?></h3>
    </div>
    <div class="marquee-container relative" style="mask-image:linear-gradient(90deg,transparent,black 15%,black 85%,transparent);-webkit-mask-image:linear-gradient(90deg,transparent,black 15%,black 85%,transparent)">
        <div class="marquee-track flex gap-12 w-max">
            <?php
            $techs = [
                ['fab fa-html5','HTML5','#e34f26'],['fab fa-css3-alt','CSS3','#264de4'],
                ['fab fa-js','JavaScript','#f7df1e'],['fab fa-php','PHP','#777bb4'],
                ['fab fa-react','React','#61dafb'],['fab fa-wordpress','WordPress','#21759b'],
                ['fas fa-database','MySQL','#00758f'],['fab fa-linux','Linux','#fcc624'],
                ['fab fa-git-alt','Git','#f05032'],['fab fa-docker','Docker','#2496ed'],
                ['fab fa-python','Python','#3572a5'],['fab fa-node-js','Node.js','#339933'],
                ['fab fa-figma','Figma','#f24e1e'],['fab fa-android','Android','#3ddc84'],
            ];
            // Repeat twice for seamless loop
            for ($r=0;$r<2;$r++) foreach ($techs as [$icon,$name,$color]): ?>
            <div class="tech-chip flex items-center gap-3 px-5 py-3 rounded-2xl flex-shrink-0 cursor-default transition-all duration-300"
                 style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                <i class="<?= $icon ?> text-2xl" style="color:<?= $color ?>"></i>
                <span class="text-gray-400 text-sm font-medium whitespace-nowrap"><?= $name ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══ Floating WhatsApp ═══ -->
<a href="https://wa.me/22788672115" target="_blank"
   class="fixed bottom-6 right-6 w-14 h-14 rounded-full flex items-center justify-center shadow-lg hover:scale-110 transition-all z-50"
   style="background:#25d366;box-shadow:0 0 25px rgba(37,211,102,0.45)">
    <i class="fab fa-whatsapp text-2xl text-white"></i>
</a>

<!-- Back to top -->
<button id="btt"
        onclick="window.scrollTo({top:0,behavior:'smooth'})"
        class="fixed bottom-24 right-6 w-10 h-10 glass rounded-full flex items-center justify-center text-nc-cyan hover:text-white transition-all z-50"
        style="opacity:0;transition:opacity .3s">
    <i class="fas fa-arrow-up text-sm"></i>
</button>
<script>
window.addEventListener('scroll', () => {
    const b = document.getElementById('btt');
    if (b) b.style.opacity = window.scrollY > 400 ? '1' : '0';
});
</script>


<script>
/* ═══ HERO PARTICLES ══════════════════════════════════════ */
(function(){
    const canvas = document.getElementById('hero-canvas-home');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let W, H, pts = [];
    function resize(){ W = canvas.width = canvas.offsetWidth; H = canvas.height = canvas.offsetHeight; }
    function Pt() {
        this.x  = Math.random()*W; this.y  = Math.random()*H;
        this.vx = (Math.random()-.5)*.5; this.vy = (Math.random()-.5)*.5-.15;
        this.r  = Math.random()*1.8+.4;
        this.a  = Math.random()*.5+.1; this.life = Math.random()*300+150; this.age = 0;
        this.hue = Math.random()>.6?195:220;
    }
    function init(){ pts=[]; for(let i=0;i<100;i++){const p=new Pt();p.age=Math.random()*p.life;pts.push(p);} }
    let af;
    function draw(){
        ctx.clearRect(0,0,W,H);
        pts.forEach(p=>{
            p.age++; if(p.age>p.life){Object.assign(p,new Pt());return;}
            const lr=p.age/p.life, al=p.a*Math.sin(lr*Math.PI);
            p.x+=p.vx; p.y+=p.vy;
            ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
            ctx.fillStyle=`hsla(${p.hue},100%,70%,${al})`; ctx.fill();
        });
        // Lines between close particles
        for(let i=0;i<pts.length;i++) for(let j=i+1;j<pts.length;j++){
            const dx=pts[i].x-pts[j].x, dy=pts[i].y-pts[j].y, d=Math.sqrt(dx*dx+dy*dy);
            if(d<90){ ctx.beginPath(); ctx.moveTo(pts[i].x,pts[i].y); ctx.lineTo(pts[j].x,pts[j].y);
                ctx.strokeStyle=`rgba(0,200,255,${.07*(1-d/90)})`; ctx.lineWidth=.5; ctx.stroke(); }
        }
        af=requestAnimationFrame(draw);
    }
    window.addEventListener('resize',()=>{resize();init();},{passive:true});
    document.addEventListener('visibilitychange',()=>{ if(document.hidden) cancelAnimationFrame(af); else draw(); });
    resize(); init(); draw();
})();

/* ═══ TYPEWRITER ══════════════════════════════════════════ */
(function(){
    const stats = document.querySelectorAll('[data-count]');
    if(!stats.length) return;
    // Add decorative rings to hero stats
    stats.forEach(el => {
        const wrap = el.closest('.anim-zoom');
        if (!wrap) return;
        const ring = document.createElement('div');
        ring.className = 'stat-ring';
        wrap.style.position='relative';
        wrap.appendChild(ring);
    });
})();

/* ═══ 3D TILT ══════════════════════════════════════════════ */
(function(){
    if(window.matchMedia('(pointer:coarse)').matches) return;
    document.querySelectorAll('.tilt-card').forEach(card=>{
        let shine = card.querySelector('.tilt-shine');
        if(!shine){ shine=document.createElement('div'); shine.className='tilt-shine'; card.appendChild(shine); }
        card.addEventListener('mousemove',function(e){
            const r=this.getBoundingClientRect();
            const x=(e.clientX-r.left)/r.width, y=(e.clientY-r.top)/r.height;
            this.style.transform=`perspective(900px) rotateX(${(y-.5)*-10}deg) rotateY(${(x-.5)*10}deg) translateY(-6px) scale(1.015)`;
            shine.style.setProperty('--mx',(x*100)+'%'); shine.style.setProperty('--my',(y*100)+'%');
        });
        card.addEventListener('mouseleave',function(){ this.style.transform=''; });
    });
})();

/* ═══ HERO LOGO HOLOGRAPHIC ════════════════════════════════ */
(function(){
    const logo = document.querySelector('.relative.z-10.w-48');
    if(logo) logo.classList.add('hero-logo-holo');
})();

/* ═══ TESTIMONIAL CARD CLASS ═══════════════════════════════ */
document.querySelectorAll('#temoignages .glass.rounded-2xl').forEach(el=>el.classList.add('testi-card'));

/* ═══ VOLET CARD CLASS ════════════════════════════════════ */
document.querySelectorAll('#volets a').forEach(el=>el.classList.add('volet-card'));

/* ═══ MAGNETIC CTA BUTTONS ════════════════════════════════ */
(function(){
    if(window.matchMedia('(pointer:coarse)').matches) return;
    document.querySelectorAll('.btn-primary,.btn-outline').forEach(btn=>{
        btn.addEventListener('mousemove',function(e){
            const r=this.getBoundingClientRect();
            const x=(e.clientX-r.left-r.width/2)*.3, y=(e.clientY-r.top-r.height/2)*.3;
            this.style.transform=`translate(${x}px,${y}px) translateY(-2px)`;
        });
        btn.addEventListener('mouseleave',function(){ this.style.transform=''; });
    });
})();

/* ═══ EXPERTISE CARD TOP ACCENT ════════════════════════════ */
document.querySelectorAll('.expertise-card').forEach(card=>{
    const col = card.style.getPropertyValue('--accent') ||
        (card.querySelector('[style*="color:"]')?.style.color) || '#00c8ff';
    const icon = card.querySelector('i[style]');
    const c = icon ? window.getComputedStyle(icon).color : '#00c8ff';
    const accent = document.createElement('div');
    accent.className = 'top-accent';
    accent.style.background = `linear-gradient(90deg,${c},transparent)`;
    card.insertBefore(accent, card.firstChild);
});

/* ═══ SCROLL REVEAL STATS (extra pulse) ═══════════════════ */
(function(){
    const statsSection = document.querySelector('[data-counters]');
    if(!statsSection) return;
    const obs = new IntersectionObserver(entries=>{
        entries.forEach(e=>{
            if(!e.isIntersecting) return;
            obs.disconnect();
            // Add shimmer to gradient text
            statsSection.querySelectorAll('.gradient-text').forEach((el,i)=>{
                setTimeout(()=>el.classList.add('anim-zoom'),i*150);
            });
        });
    },{threshold:.5});
    obs.observe(statsSection);
})();

/* ═══ ANIMATED SECTION HEADINGS ════════════════════════════ */
document.querySelectorAll('section h2 .gradient-text').forEach(h=>{
    h.classList.add('reveal-underline');
    new IntersectionObserver(entries=>{
        entries.forEach(e=>{ if(e.isIntersecting) h.classList.add('aos-animate'); });
    },{threshold:.5}).observe(h);
});
</script>

<?php include 'includes/footer.php'; ?>
