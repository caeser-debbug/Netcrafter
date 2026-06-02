<?php
$page_title    = 'Netcrafter - Nos Volets d\'Activité';
$page_keywords = 'volets activité Netcrafter, domaines expertise Niger, web sécurité marketing formation Niamey, secteurs intervention agence numérique Niger';
include 'includes/header.php';
$_d = fn($fr, $en) => ($GLOBALS['nc_lang'] ?? 'fr') === 'en' ? $en : $fr;
?>

<!-- Hero -->
<section class="relative pt-32 pb-20 overflow-hidden">
    <div class="blob bg-nc-cyan" style="width:500px;height:500px;top:-150px;left:-200px;"></div>
    <div class="blob bg-nc-blue" style="width:400px;height:400px;bottom:-100px;right:-150px;animation-delay:2s;"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center" data-aos="fade-up">
        <div class="badge mb-6 mx-auto"><i class="fas fa-cubes"></i> <?= t('volets.our_org') ?></div>
        <h1 class="font-heading font-bold text-5xl md:text-7xl text-white mb-6">
            <span class="gradient-text"><?= t('volets.badge') ?></span>
        </h1>
        <p class="text-gray-400 text-xl max-w-3xl mx-auto">
            <?= t('volets.sub') ?>
        </p>
    </div>
</section>

<!-- Overview -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php
        $overviews = [
            ['href'=>'#work',     'icon'=>'fa-briefcase',      'color'=>'#00c8ff', 'num'=>'01', 'title'=>'Netcrafter Work',      'sub'=>$_d('Projets & Services','Projects & Services')],
            ['href'=>'#shop',     'icon'=>'fa-shopping-bag',   'color'=>'#0066cc', 'num'=>'02', 'title'=>'Netcrafter Shop',      'sub'=>$_d('Matériel & Équipements','Hardware & Equipment')],
            ['href'=>'#formation','icon'=>'fa-graduation-cap', 'color'=>'#4db8ff', 'num'=>'03', 'title'=>'Netcrafter Formation', 'sub'=>$_d('Formations & Certifications','Training & Certifications')],
        ];
        foreach ($overviews as $i => $ov): ?>
        <a href="<?= $ov['href'] ?>" class="group service-card p-6 rounded-2xl flex items-center gap-4" data-aos="fade-up" data-aos-delay="<?= $i*100 ?>">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-all"
                 style="background:<?= $ov['color'] ?>15;border:1px solid <?= $ov['color'] ?>35">
                <i class="fas <?= $ov['icon'] ?> text-xl" style="color:<?= $ov['color'] ?>"></i>
            </div>
            <div>
                <p class="text-xs uppercase tracking-widest mb-1" style="color:<?= $ov['color'] ?>">Division <?= $ov['num'] ?></p>
                <h3 class="font-heading font-semibold text-white text-lg"><?= $ov['title'] ?></h3>
                <p class="text-gray-500 text-sm mt-0.5"><?= $ov['sub'] ?></p>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ════════════════════════════
     DIVISION 1 — WORK
════════════════════════════ -->
<section id="work" class="scroll-mt-20 py-24 relative overflow-hidden">
    <div class="blob bg-nc-cyan" style="width:400px;height:400px;top:50%;right:-100px;opacity:0.06;"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <!-- Image -->
            <div class="relative" data-aos="fade-right">
                <div class="relative rounded-2xl overflow-hidden">
                    <img src="<?= BASE ?>/image/123456.png" alt="Netcrafter Work" class="w-full h-80 object-cover rounded-2xl">
                    <div class="absolute inset-0" style="background:linear-gradient(to top,rgba(6,13,30,0.6),transparent)"></div>
                </div>
                <div class="absolute -bottom-6 -right-6 glass px-6 py-4 rounded-2xl" style="box-shadow:0 0 30px rgba(0,200,255,0.3)">
                    <div class="font-heading font-bold text-4xl" style="color:#00c8ff">150+</div>
                    <div class="text-xs text-gray-400 uppercase tracking-widest mt-1"><?= $_d('Projets réalisés','Projects completed') ?></div>
                </div>
            </div>

            <!-- Content -->
            <div data-aos="fade-left">
                <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:#00c8ff">Division 01</p>
                <h2 class="font-heading font-bold text-4xl text-white mb-6">Netcrafter <span class="gradient-text">Work</span></h2>
                <div class="w-16 h-1 rounded-full mb-8" style="background:linear-gradient(to right,#00c8ff,#0066cc)"></div>

                <p class="text-gray-400 text-lg mb-10 leading-relaxed">
                    <?= $_d(
                        'Notre division <strong class="text-white">Netcrafter Work</strong> est dédiée à tous vos projets numériques et informatiques. Notre équipe met son expertise à votre service pour développer des solutions sur mesure.',
                        'Our <strong class="text-white">Netcrafter Work</strong> division is dedicated to all your digital and IT projects. Our team puts its expertise at your service to develop tailored solutions.'
                    ) ?>
                </p>

                <div class="grid grid-cols-2 gap-4 mb-10">
                    <?php foreach ([
                        ['fa-code',       $_d('Développement Web','Web Development'), $_d('Sites, apps, e-commerce','Sites, apps, e-commerce')],
                        ['fa-mobile-alt', $_d('Apps Mobiles','Mobile Apps'),           $_d('iOS et Android','iOS and Android')],
                        ['fa-server',     $_d('Infrastructure','Infrastructure'),      $_d('Installation, config, maint.','Setup, config, maintenance')],
                        ['fa-lock',       $_d('Sécurité Info.','IT Security'),         $_d('Audit & protection des données','Audit & data protection')],
                    ] as $f): ?>
                    <div class="service-card p-4 rounded-xl">
                        <i class="fas <?= $f[0] ?> text-xl mb-3 block" style="color:#00c8ff"></i>
                        <h4 class="font-semibold text-white text-sm mb-1"><?= $f[1] ?></h4>
                        <p class="text-gray-500 text-xs"><?= $f[2] ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="glass p-5 rounded-xl mb-8" style="border-left:3px solid #00c8ff">
                    <h4 class="font-semibold text-white mb-2"><?= $_d('Notre approche','Our Approach') ?></h4>
                    <p class="text-gray-400 text-sm leading-relaxed"><?= $_d('Méthodologie agile, collaboration étroite avec le client, livraison de qualité dans les délais impartis.','Agile methodology, close client collaboration, quality delivery on schedule.') ?></p>
                </div>

                <a href="<?= BASE ?>/devis.php" class="btn-primary"><i class="fas fa-briefcase"></i> <?= $_d('Démarrer un projet','Start a project') ?></a>
            </div>
        </div>

        <!-- Processus -->
        <div class="mt-20" data-aos="fade-up">
            <h3 class="font-heading font-bold text-2xl text-center text-white mb-12"><?= $_d('Notre processus de travail','Our Working Process') ?></h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach ([
                    ['1', $_d('Analyse','Analysis'),        $_d('Étude des besoins et cahier des charges.','Needs assessment and specifications.')],
                    ['2', $_d('Conception','Design'),        $_d('Architecture et design de la solution.','Architecture and solution design.')],
                    ['3', $_d('Développement','Development'),$_d('Codage selon les spécifications.','Coding to specifications.')],
                    ['4', $_d('Livraison','Delivery'),       $_d('Déploiement et maintenance.','Deployment and maintenance.')],
                ] as $s): ?>
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center text-white font-heading font-bold text-xl mx-auto mb-4"
                         style="background:linear-gradient(135deg,#00c8ff,#0066cc);box-shadow:0 0 20px rgba(0,200,255,0.3)">
                        <?= $s[0] ?>
                    </div>
                    <h4 class="font-semibold text-white mb-2"><?= $s[1] ?></h4>
                    <p class="text-gray-500 text-sm"><?= $s[2] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<div class="section-divider max-w-7xl mx-auto"></div>

<!-- ════════════════════════════
     DIVISION 2 — SHOP
════════════════════════════ -->
<section id="shop" class="scroll-mt-20 py-24 relative overflow-hidden">
    <div class="blob bg-nc-blue" style="width:400px;height:400px;top:50%;left:-100px;opacity:0.06;animation-delay:2s;"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <!-- Content -->
            <div data-aos="fade-right">
                <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:#0066cc">Division 02</p>
                <h2 class="font-heading font-bold text-4xl text-white mb-6">Netcrafter <span class="gradient-text">Shop</span></h2>
                <div class="w-16 h-1 rounded-full mb-8" style="background:linear-gradient(to right,#0066cc,#00c8ff)"></div>

                <p class="text-gray-400 text-lg mb-10 leading-relaxed">
                    <?= $_d(
                        'Notre division <strong class="text-white">Netcrafter Shop</strong> vous propose une large gamme d\'équipements informatiques professionnels. Produits de qualité, performance et fiabilité garanties.',
                        'Our <strong class="text-white">Netcrafter Shop</strong> division offers a wide range of professional IT equipment. Quality products, guaranteed performance and reliability.'
                    ) ?>
                </p>

                <div class="grid grid-cols-2 gap-4 mb-10">
                    <?php foreach ([
                        ['fa-laptop',        $_d('Matériel Informatique','IT Hardware'),     $_d('PCs, serveurs, périphériques','PCs, servers, peripherals')],
                        ['fa-network-wired', $_d('Équipements Réseaux','Network Equipment'), $_d('Routeurs, switches, câblage','Routers, switches, cabling')],
                        ['fa-video',         $_d('Vidéosurveillance','Video Surveillance'),   $_d('Caméras IP, enregistreurs','IP cameras, recorders')],
                        ['fa-phone-alt',     $_d('Téléphonie IP','IP Telephony'),             $_d('Postes & standards téléphoniques','Handsets & PBX systems')],
                    ] as $f): ?>
                    <div class="service-card p-4 rounded-xl">
                        <i class="fas <?= $f[0] ?> text-xl mb-3 block" style="color:#0066cc"></i>
                        <h4 class="font-semibold text-white text-sm mb-1"><?= $f[1] ?></h4>
                        <p class="text-gray-500 text-xs"><?= $f[2] ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="glass p-5 rounded-xl mb-8" style="border-left:3px solid #0066cc">
                    <h4 class="font-semibold text-white mb-2"><?= $_d('Notre engagement','Our Commitment') ?></h4>
                    <p class="text-gray-400 text-sm leading-relaxed"><?= $_d('Nous ne vendons pas seulement du matériel — nous vous conseillons sur les solutions les mieux adaptées et vous accompagnons dans la mise en place.','We don\'t just sell hardware — we advise you on the best-suited solutions and support you through implementation.') ?></p>
                </div>

                <a href="<?= BASE ?>/shop/shop.php" class="btn-primary"
                   style="background:linear-gradient(135deg,#0066cc,#00c8ff)">
                    <i class="fas fa-shopping-bag"></i> <?= $_d('Visiter la Boutique','Visit the Shop') ?>
                </a>
            </div>

            <!-- Image -->
            <div class="relative" data-aos="fade-left">
                <div class="relative rounded-2xl overflow-hidden">
                    <img src="<?= BASE ?>/image/1234567.png" alt="Netcrafter Shop" class="w-full h-80 object-cover rounded-2xl">
                    <div class="absolute inset-0" style="background:linear-gradient(to top,rgba(6,13,30,0.6),transparent)"></div>
                </div>
                <div class="absolute -bottom-6 -left-6 glass px-6 py-4 rounded-2xl" style="box-shadow:0 0 30px rgba(0,102,204,0.35)">
                    <div class="font-heading font-bold text-4xl" style="color:#0066cc">500+</div>
                    <div class="text-xs text-gray-400 uppercase tracking-widest mt-1"><?= $_d('Produits disponibles','Products available') ?></div>
                </div>
            </div>
        </div>

        <!-- Catégories -->
        <div class="mt-20" data-aos="fade-up">
            <h3 class="font-heading font-bold text-2xl text-center text-white mb-12"><?= $_d('Catégories de produits','Product Categories') ?></h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ([
                    [BASE.'/image/materiel.jpeg',         $_d('Ordinateurs','Computers'),    $_d('Desktops, laptops, all-in-one','Desktops, laptops, all-in-one')],
                    [BASE.'/image/Rack-informatique.jpg', $_d('Serveurs','Servers'),          $_d('Solutions datacenter','Datacenter solutions')],
                    [BASE.'/image/secu.png',              $_d('Sécurité','Security'),          $_d('Caméras, alarmes, accès','Cameras, alarms, access')],
                    [BASE.'/image/reseau.jpg',            $_d('Réseaux','Networks'),           $_d('Connexions fiables & performantes','Reliable & high-performance connections')],
                ] as $cat): ?>
                <div class="relative rounded-2xl overflow-hidden group cursor-pointer h-48">
                    <img src="<?= $cat[0] ?>" alt="<?= $cat[1] ?>" class="w-full h-full object-cover group-hover:scale-105 transition-all duration-500">
                    <div class="absolute inset-0" style="background:linear-gradient(to top,rgba(6,13,30,0.85),rgba(6,13,30,0.2) 50%,transparent)"></div>
                    <div class="absolute bottom-0 left-0 right-0 p-4">
                        <h4 class="font-heading font-bold text-white"><?= $cat[1] ?></h4>
                        <p class="text-gray-400 text-xs mt-1"><?= $cat[2] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<div class="section-divider max-w-7xl mx-auto"></div>

<!-- ════════════════════════════
     DIVISION 3 — FORMATION
════════════════════════════ -->
<section id="formation" class="scroll-mt-20 py-24 relative overflow-hidden">
    <div class="blob bg-nc-light" style="width:400px;height:400px;top:50%;right:-100px;opacity:0.06;animation-delay:4s;"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <!-- Image -->
            <div class="relative" data-aos="fade-right">
                <div class="relative rounded-2xl overflow-hidden">
                    <img src="<?= BASE ?>/image/12345678.png" alt="Netcrafter Formation" class="w-full h-80 object-cover rounded-2xl">
                    <div class="absolute inset-0" style="background:linear-gradient(to top,rgba(6,13,30,0.6),transparent)"></div>
                </div>
                <div class="absolute -bottom-6 -right-6 glass px-6 py-4 rounded-2xl" style="box-shadow:0 0 30px rgba(77,184,255,0.3)">
                    <div class="font-heading font-bold text-4xl" style="color:#4db8ff">30+</div>
                    <div class="text-xs text-gray-400 uppercase tracking-widest mt-1"><?= $_d('Formations disponibles','Trainings available') ?></div>
                </div>
            </div>

            <!-- Content -->
            <div data-aos="fade-left">
                <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:#4db8ff">Division 03</p>
                <h2 class="font-heading font-bold text-4xl text-white mb-6">Netcrafter <span class="gradient-text">Formation</span></h2>
                <div class="w-16 h-1 rounded-full mb-8" style="background:linear-gradient(to right,#4db8ff,#00c8ff)"></div>

                <p class="text-gray-400 text-lg mb-10 leading-relaxed">
                    <?= $_d(
                        'Notre division <strong class="text-white">Netcrafter Formation</strong> vous accompagne dans le développement de vos compétences numériques. Programmes adaptés à tous les niveaux.',
                        'Our <strong class="text-white">Netcrafter Formation</strong> division supports you in developing your digital skills. Programmes adapted to all levels.'
                    ) ?>
                </p>

                <div class="grid grid-cols-2 gap-4 mb-10">
                    <?php foreach ([
                        ['fa-code',        $_d('Développement Web','Web Development'),    $_d('HTML, CSS, JS, PHP, Python','HTML, CSS, JS, PHP, Python')],
                        ['fa-server',      $_d('Administration Systèmes','System Admin'),  $_d('Windows Server, Linux, Cloud','Windows Server, Linux, Cloud')],
                        ['fa-shield-alt',  $_d('Cybersécurité','Cybersecurity'),           $_d('Protection, audit, RGPD','Protection, audit, GDPR')],
                        ['fa-paint-brush', $_d('Design Numérique','Digital Design'),       $_d('UX/UI, graphisme, prototypage','UX/UI, graphic design, prototyping')],
                    ] as $f): ?>
                    <div class="service-card p-4 rounded-xl">
                        <i class="fas <?= $f[0] ?> text-xl mb-3 block" style="color:#4db8ff"></i>
                        <h4 class="font-semibold text-white text-sm mb-1"><?= $f[1] ?></h4>
                        <p class="text-gray-500 text-xs"><?= $f[2] ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="glass p-5 rounded-xl mb-8" style="border-left:3px solid #4db8ff">
                    <h4 class="font-semibold text-white mb-2"><?= $_d('Notre pédagogie','Our Pedagogy') ?></h4>
                    <p class="text-gray-400 text-sm leading-relaxed"><?= $_d('Théorie + pratique, petits groupes, projets concrets, suivi personnalisé. Programmes adaptés à vos besoins spécifiques.','Theory + practice, small groups, real-world projects, personalised follow-up. Programmes tailored to your specific needs.') ?></p>
                </div>

                <a href="<?= BASE ?>/formation/formations.php" class="btn-primary"
                   style="background:linear-gradient(135deg,#4db8ff,#0066cc)">
                    <i class="fas fa-graduation-cap"></i> <?= $_d('Voir les Formations','View Trainings') ?>
                </a>
            </div>
        </div>

        <!-- Formats -->
        <div class="mt-20" data-aos="fade-up">
            <h3 class="font-heading font-bold text-2xl text-center text-white mb-12"><?= $_d('Formats de formations','Training Formats') ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ([
                    ['fa-users',        $_d('En Groupe','Group'),        $_d('Sessions collectives, apprentissage collaboratif.','Group sessions, collaborative learning.'),                     $_d('À partir de 350 FCFA/jour','From 350 FCFA/day')],
                    ['fa-user-graduate',$_d('Individuelle','Individual'), $_d('Parcours personnalisé, formateur dédié, suivi optimal.','Personalised path, dedicated trainer, optimal follow-up.'), $_d('À partir de 550 FCFA/jour','From 550 FCFA/day'), true],
                    ['fa-laptop-house', $_d('À Distance','Remote'),       $_d('E-learning flexible, sessions live, accompagnement perso.','Flexible e-learning, live sessions, personal support.'),  $_d('À partir de 250 FCFA/jour','From 250 FCFA/day')],
                ] as $fmt): ?>
                <div class="service-card p-8 rounded-2xl text-center"
                     <?= !empty($fmt[4]) ? 'style="border-color:rgba(0,200,255,0.4);box-shadow:0 0 30px rgba(0,200,255,0.1)"' : '' ?>>
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-6"
                         style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.25)">
                        <i class="fas <?= $fmt[0] ?> text-2xl" style="color:#00c8ff"></i>
                    </div>
                    <h4 class="font-heading font-bold text-white text-lg mb-4"><?= $_d('Formation','Training') ?> <?= $fmt[1] ?></h4>
                    <p class="text-gray-400 text-sm leading-relaxed mb-6"><?= $fmt[2] ?></p>
                    <div class="font-bold gradient-text"><?= $fmt[3] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-12 text-center" data-aos="zoom-in">
            <a href="<?= BASE ?>/devis.php" class="btn-primary"
               style="background:linear-gradient(135deg,#4db8ff,#0066cc)">
                <i class="fas fa-paper-plane"></i> <?= $_d('Demander un programme','Request a programme') ?>
            </a>
        </div>
    </div>
</section>

<!-- CTA final -->
<section class="relative py-20 overflow-hidden">
    <div class="absolute inset-0" style="background:linear-gradient(135deg,rgba(0,200,255,0.07),rgba(0,102,204,0.10))"></div>
    <div class="absolute inset-0" style="border-top:1px solid rgba(0,200,255,0.1);border-bottom:1px solid rgba(0,102,204,0.1)"></div>
    <div class="max-w-4xl mx-auto px-4 text-center relative z-10" data-aos="zoom-in">
        <h2 class="font-heading font-bold text-4xl text-white mb-4"><?= t('volets.cta_title') ?></h2>
        <p class="text-gray-400 text-lg mb-8"><?= t('volets.cta_sub') ?></p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="<?= BASE ?>/devis.php" class="btn-primary"><i class="fas fa-paper-plane"></i> <?= t('btn.quote') ?></a>
            <a href="https://wa.me/22788672115" target="_blank" class="btn-outline"><i class="fab fa-whatsapp text-nc-green"></i> WhatsApp</a>
        </div>
    </div>
</section>

<!-- Floating WhatsApp -->
<a href="https://wa.me/22788672115" target="_blank"
   class="fixed bottom-6 right-6 z-50 w-14 h-14 rounded-full flex items-center justify-center hover:scale-110 transition-all"
   style="background:#25d366;box-shadow:0 0 25px rgba(37,211,102,0.4)">
    <i class="fab fa-whatsapp text-white text-2xl"></i>
</a>

<?php include 'includes/footer.php'; ?>
