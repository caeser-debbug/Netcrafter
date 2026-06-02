<?php
$page_title = 'Netcrafter - Notre Processus de Développement';
require_once __DIR__ . '/includes/header.php';
$_d = fn($fr, $en) => ($GLOBALS['nc_lang'] ?? 'fr') === 'en' ? $en : $fr;
?>

<style>
/* ── Timeline ─────────────────────────────────────────────── */
.timeline-wrapper {
    position: relative;
}
/* Vertical glow line */
.timeline-center-line {
    position: absolute;
    left: 50%;
    top: 30px;
    bottom: 30px;
    width: 2px;
    transform: translateX(-50%);
    background: linear-gradient(180deg,
        rgba(0,200,255,0.6) 0%,
        rgba(0,102,204,0.5) 40%,
        rgba(0,200,255,0.3) 70%,
        rgba(0,102,204,0.1) 100%);
    box-shadow: 0 0 12px rgba(0,200,255,0.3);
    border-radius: 2px;
}
@media (max-width: 767px) {
    .timeline-center-line { left: 28px; }
}

/* Each step row */
.tl-row {
    display: grid;
    grid-template-columns: 1fr 60px 1fr;
    align-items: start;
    gap: 0 20px;
    margin-bottom: 52px;
    position: relative;
}
@media (max-width: 767px) {
    .tl-row {
        grid-template-columns: 56px 1fr;
        gap: 0 16px;
        margin-bottom: 40px;
    }
    .tl-left-slot  { display: none; }
}

/* Icon circle */
.tl-icon {
    width: 60px; height: 60px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; position: relative; z-index: 2; flex-shrink: 0;
    border: 2px solid rgba(0,200,255,0.2);
}
.tl-icon::after {
    content: '';
    position: absolute; inset: -5px; border-radius: 50%;
    border: 1px solid rgba(0,200,255,0.12);
    animation: icon-pulse 3s ease-in-out infinite;
}
@keyframes icon-pulse {
    0%, 100% { transform: scale(1); opacity: 0.6; }
    50%       { transform: scale(1.12); opacity: 0.2; }
}

/* Content card */
.tl-card {
    background: rgba(10,24,58,0.7);
    border: 1px solid rgba(0,200,255,0.12);
    border-radius: 16px; padding: 28px;
    transition: all 0.3s;
    position: relative;
}
.tl-card:hover {
    border-color: rgba(0,200,255,0.35);
    transform: translateY(-3px);
    box-shadow: 0 20px 50px rgba(0,0,0,0.4), 0 0 20px rgba(0,200,255,0.08);
}
/* Triangle connector */
.tl-card.left-card::after {
    content: '';
    position: absolute;
    right: -9px; top: 24px;
    width: 0; height: 0;
    border-top: 9px solid transparent;
    border-bottom: 9px solid transparent;
    border-left: 9px solid rgba(0,200,255,0.18);
}
.tl-card.right-card::before {
    content: '';
    position: absolute;
    left: -9px; top: 24px;
    width: 0; height: 0;
    border-top: 9px solid transparent;
    border-bottom: 9px solid transparent;
    border-right: 9px solid rgba(0,200,255,0.18);
}
@media (max-width: 767px) {
    .tl-card.left-card::after  { display: none; }
    .tl-card.right-card::before { display: none; }
}

/* Tool badge */
.tool-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600;
    background: rgba(0,200,255,0.07);
    border: 1px solid rgba(0,200,255,0.15);
    color: #94a3b8;
}

/* Step number overlay */
.step-num {
    position: absolute;
    top: -10px; right: -10px;
    width: 26px; height: 26px; border-radius: 50%;
    background: linear-gradient(135deg,#00c8ff,#0066cc);
    color: #fff; font-size: 0.7rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 12px rgba(0,200,255,0.4);
    z-index: 3;
}

/* Why section cards */
.why-card {
    background: rgba(10,24,58,0.7);
    border: 1px solid rgba(0,200,255,0.12);
    border-radius: 16px; padding: 28px;
    transition: all 0.3s; text-align: center;
}
.why-card:hover {
    border-color: rgba(0,200,255,0.35);
    transform: translateY(-4px);
    box-shadow: 0 20px 50px rgba(0,0,0,0.4), 0 0 20px rgba(0,200,255,0.08);
}

/* Counter stat */
.stat-card {
    text-align: center; padding: 32px 20px;
    background: rgba(10,24,58,0.5);
    border: 1px solid rgba(0,200,255,0.12);
    border-radius: 16px;
    transition: all 0.3s;
}
.stat-card:hover {
    border-color: rgba(0,200,255,0.3);
    background: rgba(0,200,255,0.05);
}
</style>

<!-- ═══ HERO ═══ -->
<section class="relative pt-32 pb-16 overflow-hidden">
    <div class="blob bg-nc-cyan"  style="width:600px;height:600px;top:-200px;left:-250px;"></div>
    <div class="blob bg-nc-blue"  style="width:400px;height:400px;bottom:-100px;right:-150px;animation-delay:2s;"></div>
    <div class="blob bg-nc-violet" style="width:300px;height:300px;top:100px;right:10%;animation-delay:3.5s;opacity:0.08"></div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <div class="badge mb-6 mx-auto" data-aos="fade-down">
            <i class="fas fa-cogs"></i> <?= $_d('Méthode éprouvée','Proven Method') ?>
        </div>
        <h1 class="font-heading font-bold text-4xl md:text-6xl text-white mb-5 leading-tight" data-aos="fade-up">
            <?= $_d('Notre Processus de','Our Development') ?><br><span class="gradient-text"><?= $_d('Développement','Process') ?></span>
        </h1>
        <p class="text-gray-400 text-lg max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="100">
            <?= $_d('Transparence et rigueur à chaque étape — de la première réunion à la mise en ligne et au-delà.','Transparency and rigour at every stage — from the first meeting to go-live and beyond.') ?>
        </p>

        <!-- Quick stats -->
        <div class="flex flex-wrap justify-center gap-6 mt-10" data-aos="fade-up" data-aos-delay="200">
            <div class="flex items-center gap-2 glass px-5 py-2.5 rounded-full">
                <i class="fas fa-layer-group" style="color:#00c8ff"></i>
                <span class="text-white text-sm font-semibold"><?= $_d('6 étapes claires','6 clear stages') ?></span>
            </div>
            <div class="flex items-center gap-2 glass px-5 py-2.5 rounded-full">
                <i class="fas fa-comments" style="color:#00c8ff"></i>
                <span class="text-white text-sm font-semibold"><?= $_d('Suivi en temps réel','Real-time tracking') ?></span>
            </div>
            <div class="flex items-center gap-2 glass px-5 py-2.5 rounded-full">
                <i class="fas fa-shield-check" style="color:#00c8ff"></i>
                <span class="text-white text-sm font-semibold"><?= $_d('Qualité garantie','Quality guaranteed') ?></span>
            </div>
        </div>
    </div>
</section>

<!-- ═══ TIMELINE ═══ -->
<section class="py-10 pb-20 relative z-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-14" data-aos="fade-up">
            <div class="section-title-bar"></div>
            <h2 class="font-heading font-bold text-3xl md:text-4xl text-white"><?= $_d('Les 6 étapes de votre projet','The 6 stages of your project') ?></h2>
        </div>

        <div class="timeline-wrapper">
            <!-- Vertical glow line (hidden on mobile, adjusted to left) -->
            <div class="timeline-center-line hidden md:block"></div>

            <!-- ─── Step 1: Découverte & Analyse ─── -->
            <div class="tl-row" data-aos="fade-right" data-aos-delay="0">
                <!-- Left content -->
                <div class="tl-left-slot flex justify-end">
                    <div class="tl-card left-card max-w-sm w-full">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xs font-bold uppercase tracking-widest px-2 py-0.5 rounded" style="background:rgba(0,200,255,0.1);color:#00c8ff"><?= $_d('Jours 1–3','Days 1–3') ?></span>
                        </div>
                        <h3 class="font-heading font-bold text-white text-lg mb-2"><?= $_d('Découverte & Analyse','Discovery & Analysis') ?></h3>
                        <p class="text-gray-400 text-sm leading-relaxed mb-4">
                            <?= $_d('Compréhension de vos objectifs, analyse de la concurrence, définition des fonctionnalités requises et alignement sur votre vision.','Understanding your goals, competitive analysis, defining required features and aligning on your vision.') ?>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="tool-badge"><i class="fas fa-comments"></i><?= $_d('Entretiens','Interviews') ?></span>
                            <span class="tool-badge"><i class="fas fa-clipboard-list"></i><?= $_d('Questionnaires','Questionnaires') ?></span>
                            <span class="tool-badge"><i class="fas fa-chart-bar"></i><?= $_d('Analyse concurrentielle','Competitive analysis') ?></span>
                        </div>
                    </div>
                </div>
                <!-- Center icon -->
                <div class="flex justify-center">
                    <div class="tl-icon relative" style="background:rgba(0,200,255,0.1);border-color:rgba(0,200,255,0.3)">
                        <i class="fas fa-search" style="color:#00c8ff"></i>
                        <div class="step-num">1</div>
                    </div>
                </div>
                <!-- Right empty -->
                <div class="tl-right-slot hidden md:block"></div>
            </div>

            <!-- ─── Step 2: Conception & Maquettes ─── -->
            <div class="tl-row" data-aos="fade-left" data-aos-delay="80">
                <!-- Left empty -->
                <div class="tl-left-slot hidden md:block"></div>
                <!-- Center icon -->
                <div class="flex justify-center">
                    <div class="tl-icon relative" style="background:rgba(124,58,237,0.12);border-color:rgba(124,58,237,0.35)">
                        <i class="fas fa-pencil-ruler" style="color:#7c3aed"></i>
                        <div class="step-num" style="background:linear-gradient(135deg,#7c3aed,#0066cc)">2</div>
                    </div>
                </div>
                <!-- Right content -->
                <div class="tl-right-slot">
                    <div class="tl-card right-card">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xs font-bold uppercase tracking-widest px-2 py-0.5 rounded" style="background:rgba(124,58,237,0.12);color:#7c3aed"><?= $_d('Jours 4–8','Days 4–8') ?></span>
                        </div>
                        <h3 class="font-heading font-bold text-white text-lg mb-2"><?= $_d('Conception & Maquettes','Design & Mockups') ?></h3>
                        <p class="text-gray-400 text-sm leading-relaxed mb-4">
                            <?= $_d("Design des maquettes (wireframes puis mockups HD), choix de la palette, validation de l'UX et approbation client avant développement.",'Wireframes then HD mockups, palette selection, UX validation and client approval before development.') ?>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="tool-badge"><i class="fab fa-figma"></i>Figma</span>
                            <span class="tool-badge"><i class="fas fa-palette"></i>Adobe XD</span>
                            <span class="tool-badge"><i class="fas fa-wind"></i>Tailwind CSS</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── Step 3: Développement ─── -->
            <div class="tl-row" data-aos="fade-right" data-aos-delay="80">
                <!-- Left content -->
                <div class="tl-left-slot flex justify-end">
                    <div class="tl-card left-card max-w-sm w-full">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xs font-bold uppercase tracking-widest px-2 py-0.5 rounded" style="background:rgba(0,102,204,0.12);color:#4db8ff"><?= $_d('Semaines 2–5','Weeks 2–5') ?></span>
                        </div>
                        <h3 class="font-heading font-bold text-white text-lg mb-2"><?= $_d('Développement','Development') ?></h3>
                        <p class="text-gray-400 text-sm leading-relaxed mb-4">
                            <?= $_d('Intégration HTML/CSS/JS, développement back-end PHP/MySQL, mise en place des fonctionnalités et intégrations tierces.','HTML/CSS/JS integration, PHP/MySQL back-end development, feature implementation and third-party integrations.') ?>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="tool-badge"><i class="fab fa-php"></i>PHP</span>
                            <span class="tool-badge"><i class="fas fa-database"></i>MySQL</span>
                            <span class="tool-badge"><i class="fab fa-js"></i>JavaScript</span>
                            <span class="tool-badge"><i class="fab fa-git-alt"></i>Git</span>
                        </div>
                    </div>
                </div>
                <!-- Center icon -->
                <div class="flex justify-center">
                    <div class="tl-icon relative" style="background:rgba(0,102,204,0.12);border-color:rgba(0,102,204,0.35)">
                        <i class="fas fa-code" style="color:#0066cc"></i>
                        <div class="step-num" style="background:linear-gradient(135deg,#0066cc,#00c8ff)">3</div>
                    </div>
                </div>
                <div class="tl-right-slot hidden md:block"></div>
            </div>

            <!-- ─── Step 4: Tests & Qualité ─── -->
            <div class="tl-row" data-aos="fade-left" data-aos-delay="80">
                <div class="tl-left-slot hidden md:block"></div>
                <div class="flex justify-center">
                    <div class="tl-icon relative" style="background:rgba(16,185,129,0.12);border-color:rgba(16,185,129,0.35)">
                        <i class="fas fa-vial" style="color:#10b981"></i>
                        <div class="step-num" style="background:linear-gradient(135deg,#10b981,#0066cc)">4</div>
                    </div>
                </div>
                <div class="tl-right-slot">
                    <div class="tl-card right-card">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xs font-bold uppercase tracking-widest px-2 py-0.5 rounded" style="background:rgba(16,185,129,0.1);color:#10b981"><?= $_d('Semaine 6','Week 6') ?></span>
                        </div>
                        <h3 class="font-heading font-bold text-white text-lg mb-2"><?= $_d('Tests & Qualité','Testing & Quality') ?></h3>
                        <p class="text-gray-400 text-sm leading-relaxed mb-4">
                            <?= $_d('Tests cross-browser, audit de performance, tests de sécurité, vérification responsive sur tous les appareils.','Cross-browser testing, performance audit, security testing, responsive check across all devices.') ?>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="tool-badge"><i class="fab fa-chrome"></i>Chrome DevTools</span>
                            <span class="tool-badge"><i class="fas fa-tachometer-alt"></i>GTmetrix</span>
                            <span class="tool-badge"><i class="fas fa-shield-alt"></i>OWASP</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── Step 5: Déploiement ─── -->
            <div class="tl-row" data-aos="fade-right" data-aos-delay="80">
                <div class="tl-left-slot flex justify-end">
                    <div class="tl-card left-card max-w-sm w-full">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xs font-bold uppercase tracking-widest px-2 py-0.5 rounded" style="background:rgba(245,158,11,0.1);color:#f59e0b"><?= $_d('Semaine 7','Week 7') ?></span>
                        </div>
                        <h3 class="font-heading font-bold text-white text-lg mb-2"><?= $_d('Déploiement','Deployment') ?></h3>
                        <p class="text-gray-400 text-sm leading-relaxed mb-4">
                            <?= $_d("Mise en production, configuration SSL, DNS, optimisations finales et formation complète du client à l'administration.",'Go-live, SSL and DNS configuration, final optimisations and full client training on the admin panel.') ?>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="tool-badge"><i class="fas fa-server"></i>cPanel</span>
                            <span class="tool-badge"><i class="fas fa-exchange-alt"></i>FTP/SFTP</span>
                            <span class="tool-badge"><i class="fas fa-lock"></i>Let's Encrypt</span>
                            <span class="tool-badge"><i class="fas fa-globe"></i>DNS</span>
                        </div>
                    </div>
                </div>
                <div class="flex justify-center">
                    <div class="tl-icon relative" style="background:rgba(245,158,11,0.12);border-color:rgba(245,158,11,0.35)">
                        <i class="fas fa-rocket" style="color:#f59e0b"></i>
                        <div class="step-num" style="background:linear-gradient(135deg,#f59e0b,#ef4444)">5</div>
                    </div>
                </div>
                <div class="tl-right-slot hidden md:block"></div>
            </div>

            <!-- ─── Step 6: Support & Maintenance ─── -->
            <div class="tl-row" data-aos="fade-left" data-aos-delay="80" style="margin-bottom:0">
                <div class="tl-left-slot hidden md:block"></div>
                <div class="flex justify-center">
                    <div class="tl-icon relative" style="background:rgba(236,72,153,0.12);border-color:rgba(236,72,153,0.35)">
                        <i class="fas fa-headset" style="color:#ec4899"></i>
                        <div class="step-num" style="background:linear-gradient(135deg,#ec4899,#7c3aed)">6</div>
                    </div>
                </div>
                <div class="tl-right-slot">
                    <div class="tl-card right-card">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xs font-bold uppercase tracking-widest px-2 py-0.5 rounded" style="background:rgba(236,72,153,0.1);color:#ec4899"><?= $_d('Continu','Ongoing') ?></span>
                        </div>
                        <h3 class="font-heading font-bold text-white text-lg mb-2"><?= $_d('Support & Maintenance','Support & Maintenance') ?></h3>
                        <p class="text-gray-400 text-sm leading-relaxed mb-4">
                            <?= $_d('Suivi post-livraison, mises à jour régulières, corrections de bugs rapides et évolutions fonctionnelles selon vos besoins.','Post-delivery follow-up, regular updates, quick bug fixes and feature enhancements as needed.') ?>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="tool-badge"><i class="fas fa-chart-line"></i><?= $_d('Monitoring','Monitoring') ?></span>
                            <span class="tool-badge"><i class="fas fa-save"></i><?= $_d('Backups','Backups') ?></span>
                            <span class="tool-badge"><i class="fab fa-whatsapp"></i>WhatsApp Support</span>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- end .timeline-wrapper -->
    </div>
</section>

<div class="section-divider max-w-5xl mx-auto"></div>

<!-- ═══ COUNTERS ═══ -->
<section class="py-16 relative z-10" data-counters>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="stat-card" data-aos="fade-up" data-aos-delay="0">
                <div class="font-heading font-bold text-5xl gradient-text mb-2 stat-number">
                    <span data-count="150" data-suffix="+">0+</span>
                </div>
                <div class="text-white font-semibold text-sm"><?= $_d('Projets livrés','Projects delivered') ?></div>
                <div class="text-gray-500 text-xs mt-1"><?= $_d('Sites, apps & formations','Sites, apps & trainings') ?></div>
            </div>
            <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
                <div class="font-heading font-bold text-5xl gradient-text mb-2 stat-number">
                    <span data-count="98" data-suffix="%">0%</span>
                </div>
                <div class="text-white font-semibold text-sm"><?= $_d('Satisfaction client','Client satisfaction') ?></div>
                <div class="text-gray-500 text-xs mt-1"><?= $_d('Évaluations vérifiées','Verified reviews') ?></div>
            </div>
            <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
                <div class="font-heading font-bold text-5xl gradient-text mb-2 stat-number">
                    <span data-count="6" data-suffix=" ans">0 ans</span>
                </div>
                <div class="text-white font-semibold text-sm"><?= $_d("D'expérience",'Of experience') ?></div>
                <div class="text-gray-500 text-xs mt-1"><?= $_d('Au service de nos clients','At the service of our clients') ?></div>
            </div>
        </div>
    </div>
</section>

<div class="section-divider max-w-5xl mx-auto"></div>

<!-- ═══ WHY OUR PROCESS ═══ -->
<section class="py-20 relative z-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14" data-aos="fade-up">
            <div class="section-title-bar"></div>
            <h2 class="font-heading font-bold text-3xl md:text-4xl text-white mb-4">
                <?= $_d('Pourquoi notre <span class="gradient-text">processus</span> ?','Why our <span class="gradient-text">process</span>?') ?>
            </h2>
            <p class="text-gray-400 text-base max-w-xl mx-auto">
                <?= $_d('Notre méthodologie a été forgée sur des années de projets. Elle garantit clarté, respect des délais et excellence.','Our methodology has been forged through years of projects. It guarantees clarity, on-time delivery and excellence.') ?>
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <div class="why-card hover-glow" data-aos="fade-up" data-aos-delay="0">
                <div class="w-14 h-14 rounded-2xl mx-auto mb-5 flex items-center justify-center"
                     style="background:linear-gradient(135deg,rgba(0,200,255,0.15),rgba(0,102,204,0.15));border:1px solid rgba(0,200,255,0.25)">
                    <i class="fas fa-eye text-xl" style="color:#00c8ff"></i>
                </div>
                <h3 class="font-heading font-bold text-white text-lg mb-3"><?= $_d('Transparence totale','Full Transparency') ?></h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    <?= $_d("Vous êtes informé à chaque étape. Accès aux maquettes, rapports d'avancement hebdomadaires et canal de communication dédié.",'You are informed at every stage. Access to mockups, weekly progress reports and a dedicated communication channel.') ?>
                </p>
                <div class="mt-5 pt-5" style="border-top:1px solid rgba(0,200,255,0.08)">
                    <div class="flex flex-wrap gap-2 justify-center">
                        <span class="tool-badge"><?= $_d('Rapports hebdo','Weekly reports') ?></span>
                        <span class="tool-badge"><?= $_d('Accès démo','Demo access') ?></span>
                    </div>
                </div>
            </div>

            <div class="why-card hover-glow" data-aos="fade-up" data-aos-delay="120">
                <div class="w-14 h-14 rounded-2xl mx-auto mb-5 flex items-center justify-center"
                     style="background:linear-gradient(135deg,rgba(16,185,129,0.15),rgba(0,102,204,0.12));border:1px solid rgba(16,185,129,0.25)">
                    <i class="fas fa-calendar-check text-xl" style="color:#10b981"></i>
                </div>
                <h3 class="font-heading font-bold text-white text-lg mb-3"><?= $_d('Délais respectés','Deadlines Met') ?></h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    <?= $_d('Notre découpage en phases claires avec jalons définis nous permet de livrer dans les temps — sans sacrifier la qualité.','Our clear phase breakdown with defined milestones allows us to deliver on time — without sacrificing quality.') ?>
                </p>
                <div class="mt-5 pt-5" style="border-top:1px solid rgba(0,200,255,0.08)">
                    <div class="flex flex-wrap gap-2 justify-center">
                        <span class="tool-badge"><?= $_d('Jalons définis','Defined milestones') ?></span>
                        <span class="tool-badge"><?= $_d('Sprints agiles','Agile sprints') ?></span>
                    </div>
                </div>
            </div>

            <div class="why-card hover-glow" data-aos="fade-up" data-aos-delay="240">
                <div class="w-14 h-14 rounded-2xl mx-auto mb-5 flex items-center justify-center"
                     style="background:linear-gradient(135deg,rgba(245,158,11,0.12),rgba(239,68,68,0.1));border:1px solid rgba(245,158,11,0.2)">
                    <i class="fas fa-award text-xl" style="color:#f59e0b"></i>
                </div>
                <h3 class="font-heading font-bold text-white text-lg mb-3"><?= $_d('Qualité garantie','Quality Guaranteed') ?></h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    <?= $_d('Chaque livrable passe par une phase de tests rigoureux. Nous ne livrons que ce dont nous sommes fiers.','Every deliverable goes through rigorous testing. We only deliver what we are proud of.') ?>
                </p>
                <div class="mt-5 pt-5" style="border-top:1px solid rgba(0,200,255,0.08)">
                    <div class="flex flex-wrap gap-2 justify-center">
                        <span class="tool-badge"><?= $_d('Tests multi-devices','Multi-device tests') ?></span>
                        <span class="tool-badge"><?= $_d('Audit perf.','Perf. audit') ?></span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<div class="section-divider max-w-5xl mx-auto"></div>

<!-- ═══ TOOLS USED ═══ -->
<section class="py-16 relative z-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12" data-aos="fade-up">
            <div class="section-title-bar"></div>
            <h2 class="font-heading font-bold text-2xl md:text-3xl text-white mb-3">
                <?= $_d('Stack technique & outils','Tech Stack & Tools') ?>
            </h2>
            <p class="text-gray-400 text-sm max-w-lg mx-auto"><?= $_d('Technologies modernes et éprouvées pour des sites rapides, sécurisés et maintenables.','Modern and proven technologies for fast, secure and maintainable websites.') ?></p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4" data-aos="fade-up" data-aos-delay="100">
            <?php
            $tools = [
                ['fab fa-php',        '#7c3aed', 'PHP 8.x'],
                ['fab fa-js-square',  '#f59e0b', 'JavaScript ES6+'],
                ['fas fa-database',   '#00c8ff', 'MySQL / MariaDB'],
                ['fab fa-react',      '#4db8ff', 'React / Vue.js'],
                ['fab fa-figma',      '#ec4899', 'Figma / Mockup'],
                ['fab fa-git-alt',    '#ef4444', 'Git / GitHub'],
                ['fas fa-wind',       '#00c8ff', 'Tailwind CSS'],
                ['fas fa-shield-alt', '#10b981', 'SSL / HTTPS'],
            ];
            foreach ($tools as $t): ?>
            <div class="flex items-center gap-3 rounded-xl p-4 transition-all hover-glow"
                 style="background:rgba(10,24,58,0.6);border:1px solid rgba(0,200,255,0.1)">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background:<?= $t[1] ?>18;border:1px solid <?= $t[1] ?>28">
                    <i class="<?= $t[0] ?> text-sm" style="color:<?= $t[1] ?>"></i>
                </div>
                <span class="text-white text-xs font-semibold"><?= $t[2] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══ CTA ═══ -->
<section class="py-20 relative z-10 overflow-hidden">
    <div class="blob bg-nc-cyan" style="width:500px;height:500px;bottom:-150px;left:-200px;opacity:0.07"></div>
    <div class="blob bg-nc-blue" style="width:400px;height:400px;top:-100px;right:-150px;opacity:0.07;animation-delay:2s"></div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <div class="glass rounded-3xl p-10 sm:p-14 hover-glow"
             style="border-color:rgba(0,200,255,0.2);box-shadow:0 0 60px rgba(0,200,255,0.06)">
            <div class="w-16 h-16 rounded-2xl mx-auto mb-6 flex items-center justify-center"
                 style="background:linear-gradient(135deg,rgba(0,200,255,0.15),rgba(0,102,204,0.15));border:1px solid rgba(0,200,255,0.25)">
                <i class="fas fa-rocket text-2xl" style="color:#00c8ff"></i>
            </div>
            <h2 class="font-heading font-bold text-3xl md:text-4xl text-white mb-4" data-aos="fade-up">
                <?= $_d("Démarrez votre projet<br><span class=\"gradient-text\">aujourd'hui</span>",'Start your project<br><span class="gradient-text">today</span>') ?>
            </h2>
            <p class="text-gray-400 text-base mb-8 max-w-lg mx-auto" data-aos="fade-up" data-aos-delay="80">
                <?= $_d('Prêt à lancer votre projet ? Discutons de vos besoins ou configurez votre projet en quelques clics.','Ready to launch your project? Let\'s discuss your needs or configure your project in just a few clicks.') ?>
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center" data-aos="fade-up" data-aos-delay="160">
                <a href="<?= BASE ?>/configurateur.php" class="btn-primary">
                    <i class="fas fa-sliders-h"></i> <?= $_d('Configurer mon projet','Configure my project') ?>
                </a>
                <a href="<?= BASE ?>/devis.php" class="btn-outline">
                    <i class="fas fa-file-invoice"></i> <?= $_d('Demander un devis','Request a quote') ?>
                </a>
            </div>
            <div class="mt-6 flex flex-wrap justify-center gap-x-6 gap-y-2 text-gray-500 text-xs">
                <span><i class="fas fa-check text-nc-cyan mr-1"></i><?= $_d('Réponse sous 24h','Response within 24h') ?></span>
                <span><i class="fas fa-check text-nc-cyan mr-1"></i><?= $_d('Devis gratuit','Free quote') ?></span>
                <span><i class="fas fa-check text-nc-cyan mr-1"></i><?= $_d('Sans engagement','No commitment') ?></span>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
