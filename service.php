<?php
$page_title    = 'Netcrafter - Nos Services Professionnels';
$page_keywords = 'services web Niger, développement web Niamey, sécurité informatique Niger, cybersécurité Niamey, marketing digital Niger, SEO Niger, application mobile Niger, Netcrafter services';
include 'includes/header.php';
?>

<!-- Hero -->
<section class="relative pt-32 pb-20 overflow-hidden">
    <div class="blob bg-nc-cyan"  style="width:500px;height:500px;top:-150px;left:-200px;"></div>
    <div class="blob bg-nc-blue"  style="width:400px;height:400px;bottom:-100px;right:-150px;animation-delay:2s;"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <!-- Quick nav -->
        <div class="flex flex-wrap justify-center gap-3 mb-10" data-aos="fade-down">
            <a href="#dev-web"      class="glass px-4 py-2 rounded-full text-sm font-medium hover:bg-white/10 transition-all" style="color:#00c8ff"><i class="fas fa-code mr-2"></i><?= t('services.dev_web') ?></a>
            <a href="#marketing"   class="glass px-4 py-2 rounded-full text-sm font-medium hover:bg-white/10 transition-all" style="color:#4db8ff"><i class="fas fa-bullhorn mr-2"></i><?= t('services.marketing') ?></a>
            <a href="#design"      class="glass px-4 py-2 rounded-full text-sm font-medium hover:bg-white/10 transition-all" style="color:#0099dd"><i class="fas fa-palette mr-2"></i><?= t('services.design') ?></a>
            <a href="#securite"    class="glass px-4 py-2 rounded-full text-sm font-medium hover:bg-white/10 transition-all" style="color:#0066cc"><i class="fas fa-shield-alt mr-2"></i><?= t('services.security') ?></a>
            <a href="#informatique"class="glass px-4 py-2 rounded-full text-sm font-medium hover:bg-white/10 transition-all" style="color:#4db8ff"><i class="fas fa-server mr-2"></i><?= t('services.it') ?></a>
        </div>

        <h1 class="font-heading font-bold text-5xl md:text-7xl text-white mb-6" data-aos="fade-up">
            <span class="gradient-text"><?= t('services.badge') ?></span>
        </h1>
        <p class="text-gray-400 text-xl max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="100">
            <?= t('services.hero_sub') ?>
        </p>
    </div>
</section>

<?php
$_d = fn($fr, $en) => ($GLOBALS['nc_lang'] ?? 'fr') === 'en' ? $en : $fr;
$services = [
    [
        'id'    => 'dev-web',
        'icon'  => 'fa-laptop-code',
        'color' => '#00c8ff',
        'num'   => '01',
        'label' => $_d('Développement Web', 'Web Development'),
        'desc'  => $_d('Nous concevons et développons des solutions web sur mesure. De la simple vitrine au système complexe, notre équipe crée des expériences web optimisées et performantes.', 'We design and develop custom web solutions. From simple showcase sites to complex systems, our team creates optimised and high-performing web experiences.'),
        'cards' => [
            ['icon'=>'fa-browser',       'title'=>$_d('Sites Vitrines & Corporatifs','Showcase & Corporate Websites'),  'desc'=>$_d('Sites professionnels responsives qui représentent votre image de marque.','Responsive professional websites that represent your brand image.'),           'items'=>[$_d('Design responsive multi-appareils','Multi-device responsive design'),$_d('SEO intégré dès la conception','SEO built in from the start'),$_d('Interface admin intuitive','Intuitive admin interface'),$_d('Hébergement haute performance','High-performance hosting')]],
            ['icon'=>'fa-code',          'title'=>$_d('Applications Web Sur Mesure','Custom Web Applications'),         'desc'=>$_d('Applications personnalisées pour automatiser vos processus métier.','Custom applications to automate your business processes.'),                          'items'=>[$_d('Analyse approfondie des besoins','In-depth requirements analysis'),$_d('Technologies modernes (React, PHP)','Modern technologies (React, PHP)'),$_d('APIs et intégrations tierces','APIs and third-party integrations'),$_d('Tests rigoureux & déploiement sécurisé','Rigorous testing & secure deployment')]],
            ['icon'=>'fa-shopping-cart', 'title'=>$_d('Sites E-commerce','E-commerce Websites'),                        'desc'=>$_d('Boutiques en ligne performantes et sécurisées pour vendre sur internet.','High-performing and secure online stores to sell on the internet.'),             'items'=>['WooCommerce / Prestashop',$_d('Passerelles de paiement sécurisées','Secure payment gateways'),$_d('Gestion stocks et commandes','Stock and order management'),$_d('Optimisation pour la conversion','Conversion optimisation')]],
        ],
    ],
    [
        'id'    => 'marketing',
        'icon'  => 'fa-bullhorn',
        'color' => '#4db8ff',
        'num'   => '02',
        'label' => $_d('Marketing Digital', 'Digital Marketing'),
        'desc'  => $_d('Amplifiez votre présence en ligne et atteignez efficacement votre public cible grâce à nos stratégies adaptées à vos objectifs commerciaux.', 'Amplify your online presence and effectively reach your target audience with our strategies tailored to your business goals.'),
        'cards' => [
            ['icon'=>'fa-facebook',  'title'=>$_d('Campagnes Facebook Ads','Facebook Ads Campaigns'),    'desc'=>$_d('Campagnes ciblées sur Facebook et Instagram pour maximiser votre ROI.','Targeted campaigns on Facebook and Instagram to maximise your ROI.'),   'items'=>[$_d("Ciblage précis de l'audience",'Precise audience targeting'),$_d('Création de visuels impactants','Creation of impactful visuals'),$_d('Optimisation continue des performances','Continuous performance optimisation'),$_d("Rapports d'analyse détaillés",'Detailed analytics reports')]],
            ['icon'=>'fa-edit',      'title'=>$_d('Stratégie de Contenu','Content Strategy'),            'desc'=>$_d('Stratégies de contenu pertinentes pour engager votre audience.','Relevant content strategies to engage your audience.'),                    'items'=>[$_d('Planification éditoriale','Editorial planning'),$_d("Rédaction d'articles de blog",'Blog article writing'),$_d('Création de contenus multimédias','Multimedia content creation'),$_d('Optimisation SEO du contenu','Content SEO optimisation')]],
            ['icon'=>'fa-comments',  'title'=>$_d('Gestion Réseaux Sociaux','Social Media Management'), 'desc'=>$_d('Gestion professionnelle de vos comptes pour une présence active.','Professional management of your accounts for an active presence.'),        'items'=>[$_d('Création & programmation de publications','Post creation & scheduling'),$_d('Modération et engagement communautaire','Community moderation & engagement'),$_d('Veille concurrentielle','Competitive monitoring'),$_d('Analyse des performances','Performance analysis')]],
        ],
    ],
    [
        'id'    => 'design',
        'icon'  => 'fa-palette',
        'color' => '#0099dd',
        'num'   => '03',
        'label' => $_d('Design Graphique', 'Graphic Design'),
        'desc'  => $_d('Des créations graphiques impactantes pour renforcer votre identité visuelle et communiquer efficacement, en ligne ou sur supports imprimés.', 'Impactful graphic designs to strengthen your visual identity and communicate effectively, online or in print.'),
        'cards' => [
            ['icon'=>'fa-file-alt', 'title'=>$_d('Flyers & Dépliants','Flyers & Leaflets'),    'desc'=>$_d('Supports imprimés attractifs pour vos campagnes promotionnelles.','Attractive print materials for your promotional campaigns.'),          'items'=>[$_d('Design moderne et accrocheur','Modern and eye-catching design'),$_d('Mise en page professionnelle','Professional layout'),$_d("Optimisation pour l'impression",'Print optimisation'),$_d('Respect de votre charte graphique','Compliance with your brand guidelines')]],
            ['icon'=>'fa-image',    'title'=>$_d('Bâches & Vinyles','Banners & Vinyl Prints'), 'desc'=>$_d('Supports grand format pour événements et signalétique commerciale.','Large-format materials for events and commercial signage.'),         'items'=>[$_d('Design adapté aux grands formats','Design adapted for large formats'),$_d('Haute résolution optimale','Optimal high resolution'),$_d('Compatibilité multi-supports','Multi-support compatibility'),$_d('Résistance aux conditions extérieures','Outdoor weather resistance')]],
            ['icon'=>'fa-id-card',  'title'=>$_d('Identité Visuelle','Visual Identity'),       'desc'=>$_d('Création ou refonte de votre identité pour vous démarquer.','Creation or redesign of your identity to make you stand out.'),            'items'=>[$_d('Création de logo distinctif','Distinctive logo creation'),$_d('Définition de la charte graphique','Brand guidelines definition'),$_d('Templates pour documents commerciaux','Templates for business documents'),$_d('Déclinaison sur tous supports','Application across all media')]],
        ],
    ],
    [
        'id'    => 'securite',
        'icon'  => 'fa-shield-alt',
        'color' => '#0066cc',
        'num'   => '04',
        'label' => $_d('Sécurité & Surveillance', 'Security & Surveillance'),
        'desc'  => $_d('Protégez vos locaux, vos biens et vos données avec nos solutions de sécurité et surveillance adaptées aux professionnels.', 'Protect your premises, assets and data with our professional security and surveillance solutions.'),
        'cards' => [
            ['icon'=>'fa-video',  'title'=>$_d('Vidéosurveillance','Video Surveillance'),  'desc'=>$_d('Systèmes professionnels pour sécuriser et surveiller à distance.','Professional systems to secure and monitor remotely.'),             'items'=>[$_d('Caméras HD/4K intérieures & extérieures','HD/4K indoor & outdoor cameras'),$_d('Enregistrement DVR/NVR','DVR/NVR recording'),$_d('Accès à distance via smartphone','Remote access via smartphone'),$_d('Détection de mouvement intelligente','Intelligent motion detection')]],
            ['icon'=>'fa-bell',   'title'=>$_d("Systèmes d'Alarme",'Alarm Systems'),       'desc'=>$_d('Alarmes performantes pour protéger contre les intrusions.','High-performance alarms to protect against intrusions.'),               'items'=>[$_d("Détecteurs de mouvement et d'ouverture",'Motion and opening detectors'),$_d('Sirènes intérieures et extérieures','Indoor and outdoor sirens'),$_d('Alertes sur smartphone','Smartphone alerts'),$_d('Télésurveillance optionnelle','Optional remote monitoring')]],
            ['icon'=>'fa-lock',   'title'=>$_d("Contrôle d'Accès",'Access Control'),       'desc'=>$_d("Solutions pour sécuriser l'entrée de vos locaux.",'Solutions to secure the entry of your premises.'),                         'items'=>[$_d('Badges, claviers à code, biométrie','Badges, keypads, biometrics'),$_d('Gestion des droits utilisateurs','User rights management'),$_d('Historique des accès','Access history logs'),$_d('Intégration avec systèmes existants','Integration with existing systems')]],
        ],
    ],
    [
        'id'    => 'informatique',
        'icon'  => 'fa-server',
        'color' => '#00c8ff',
        'num'   => '05',
        'label' => $_d('Services Informatiques', 'IT Services'),
        'desc'  => $_d("Optimisez votre infrastructure et garantissez la continuité de votre activité grâce à nos services de maintenance et d'assistance technique.", 'Optimise your infrastructure and guarantee business continuity with our maintenance and technical support services.'),
        'cards' => [
            ['icon'=>'fa-tools',         'title'=>$_d('Maintenance Informatique','IT Maintenance'),    'desc'=>$_d('Maintenance préventive et corrective pour votre parc informatique.','Preventive and corrective maintenance for your IT equipment.'),         'items'=>[$_d('Maintenance sur site ou à distance','On-site or remote maintenance'),$_d('Contrats adaptés à vos besoins','Contracts tailored to your needs'),$_d('Interventions rapides','Fast response times'),$_d('Rapports de maintenance détaillés','Detailed maintenance reports')]],
            ['icon'=>'fa-network-wired', 'title'=>$_d('Infrastructure Réseau','Network Infrastructure'),'desc'=>$_d('Conception, installation et maintenance de réseaux performants.','Design, installation and maintenance of high-performance networks.'),      'items'=>[$_d('Câblage structuré','Structured cabling'),$_d('Installation de serveurs','Server installation'),$_d('Configuration de pare-feu','Firewall configuration'),$_d('Solutions VPN sécurisées','Secure VPN solutions')]],
            ['icon'=>'fa-cloud',         'title'=>$_d('Solutions Cloud','Cloud Solutions'),            'desc'=>$_d('Migration et gestion de vos apps dans le cloud pour plus de flexibilité.','Migration and management of your apps in the cloud for greater flexibility.'),'items'=>[$_d('Migration vers AWS, Azure, Google','Migration to AWS, Azure, Google'),$_d('Solutions de sauvegarde cloud','Cloud backup solutions'),$_d("Hébergement d'applications",'Application hosting'),$_d('Optimisation des coûts cloud','Cloud cost optimisation')]],
        ],
    ],
];
?>

<!-- Services -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20 space-y-28">
    <?php foreach ($services as $i => $svc): ?>
    <section id="<?= $svc['id'] ?>" class="scroll-mt-24">

        <!-- Header section -->
        <div class="flex items-center gap-4 mb-8" data-aos="fade-right">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center"
                 style="background:<?= $svc['color'] ?>18;border:1px solid <?= $svc['color'] ?>35">
                <i class="fas <?= $svc['icon'] ?> text-2xl" style="color:<?= $svc['color'] ?>"></i>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:<?= $svc['color'] ?>">Service <?= $svc['num'] ?></p>
                <h2 class="font-heading font-bold text-3xl text-white"><?= $svc['label'] ?></h2>
            </div>
            <div class="hidden sm:block flex-1 ml-6 h-px" style="background:linear-gradient(to right,<?= $svc['color'] ?>30,transparent)"></div>
        </div>

        <p class="text-gray-400 text-lg max-w-4xl mb-10" data-aos="fade-up"><?= $svc['desc'] ?></p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6"
             data-grid-anim data-cols="3" data-cols-responsive='{"640":1,"768":2,"default":3}'>
            <?php foreach ($svc['cards'] as $j => $card): ?>
            <div class="service-card p-6 rounded-2xl" data-aos="fade-up" data-aos-delay="<?= ($j+1)*100 ?>">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-5"
                     style="background:<?= $svc['color'] ?>15">
                    <i class="fas <?= $card['icon'] ?>" style="color:<?= $svc['color'] ?>"></i>
                </div>
                <h3 class="font-heading font-semibold text-white text-lg mb-3"><?= $card['title'] ?></h3>
                <p class="text-gray-500 text-sm mb-5 leading-relaxed"><?= $card['desc'] ?></p>
                <ul class="space-y-2">
                    <?php foreach ($card['items'] as $item): ?>
                    <li class="flex items-start gap-2 text-sm text-gray-400">
                        <i class="fas fa-check mt-0.5 flex-shrink-0 text-xs" style="color:<?= $svc['color'] ?>"></i>
                        <?= $item ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
</div>

<!-- CTA -->
<section class="relative py-20 overflow-hidden">
    <div class="absolute inset-0" style="background:linear-gradient(135deg,rgba(0,200,255,0.08),rgba(0,102,204,0.12))"></div>
    <div class="absolute inset-0" style="border-top:1px solid rgba(0,200,255,0.12);border-bottom:1px solid rgba(0,102,204,0.12)"></div>
    <div class="max-w-4xl mx-auto px-4 text-center relative z-10" data-aos="zoom-in">
        <div class="badge mb-6 mx-auto"><i class="fas fa-paper-plane"></i> <?= t('services.cta_badge') ?></div>
        <h2 class="font-heading font-bold text-4xl md:text-5xl text-white mb-6">
            <?= tr('services.cta_title') ?>
        </h2>
        <p class="text-gray-400 text-lg mb-10"><?= t('services.cta_sub') ?></p>
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
