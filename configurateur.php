<?php
$page_title = 'Netcrafter - Configurateur de Projet';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.type-card, .feature-card {
    cursor: pointer;
    border: 2px solid rgba(0,200,255,0.15);
    border-radius: 16px;
    padding: 20px;
    transition: all 0.25s;
    background: rgba(10,24,58,0.6);
}
.type-card.selected, .feature-card.selected {
    border-color: #00c8ff;
    background: rgba(0,200,255,0.1);
    box-shadow: 0 0 20px rgba(0,200,255,0.2);
}
.type-card:hover, .feature-card:hover {
    border-color: rgba(0,200,255,0.4);
    background: rgba(0,200,255,0.06);
}
.design-card {
    cursor: pointer;
    border: 2px solid rgba(0,200,255,0.15);
    border-radius: 16px;
    padding: 20px;
    transition: all 0.25s;
    background: rgba(10,24,58,0.6);
}
.design-card.selected {
    border-color: #00c8ff;
    background: rgba(0,200,255,0.1);
    box-shadow: 0 0 20px rgba(0,200,255,0.2);
}
.design-card:hover {
    border-color: rgba(0,200,255,0.4);
    background: rgba(0,200,255,0.06);
}
.timeline-card {
    cursor: pointer;
    border: 2px solid rgba(0,200,255,0.15);
    border-radius: 16px;
    padding: 18px;
    transition: all 0.25s;
    background: rgba(10,24,58,0.6);
}
.timeline-card.selected {
    border-color: #00c8ff;
    background: rgba(0,200,255,0.1);
    box-shadow: 0 0 20px rgba(0,200,255,0.2);
}
.timeline-card:hover {
    border-color: rgba(0,200,255,0.4);
    background: rgba(0,200,255,0.06);
}
.step-panel {
    display: none;
    animation: fadeInStep 0.4s ease;
}
.step-panel.active {
    display: block;
}
@keyframes fadeInStep {
    from { opacity: 0; transform: translateY(15px); }
    to   { opacity: 1; transform: translateY(0); }
}
.step-dot {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 14px; transition: all 0.3s;
    flex-shrink: 0;
}
.step-dot.done   { background: #00c8ff; color: #000; }
.step-dot.active { background: linear-gradient(135deg,#00c8ff,#0066cc); color: #fff; box-shadow: 0 0 20px rgba(0,200,255,0.4); }
.step-dot.todo   { background: rgba(10,24,58,0.8); color: #475569; border: 1px solid rgba(0,200,255,0.2); }
.step-line { flex: 1; height: 2px; background: rgba(0,200,255,0.15); transition: background 0.4s; }
.step-line.done  { background: linear-gradient(90deg,#00c8ff,#0066cc); }
.price-panel {
    position: sticky; top: 80px;
    background: rgba(10,24,58,0.9);
    border: 1px solid rgba(0,200,255,0.2);
    border-radius: 20px; padding: 24px;
}
.price-total-box {
    background: linear-gradient(135deg,rgba(0,200,255,0.08),rgba(0,102,204,0.12));
    border: 1px solid rgba(0,200,255,0.25);
    border-radius: 14px; padding: 18px;
}
.form-input {
    width: 100%;
    background: rgba(10,24,58,0.6);
    border: 1px solid rgba(0,200,255,0.2);
    border-radius: 12px;
    color: #fff;
    padding: 12px 16px;
    transition: border-color 0.25s;
    font-family: 'Inter', sans-serif;
    font-size: 0.9rem;
    outline: none;
}
.form-input:focus { border-color: rgba(0,200,255,0.55); box-shadow: 0 0 0 3px rgba(0,200,255,0.08); }
.form-input::placeholder { color: #475569; }
.nav-btn {
    border-radius: 50px; padding: 11px 24px; font-weight: 700;
    cursor: pointer; transition: all 0.25s; display: inline-flex; align-items: center; gap: 8px;
    font-family: 'Inter', sans-serif; font-size: 0.9rem;
}
.nav-btn-prev {
    background: rgba(10,24,58,0.7);
    border: 1px solid rgba(0,200,255,0.25);
    color: #94a3b8;
}
.nav-btn-prev:hover { border-color: rgba(0,200,255,0.5); color: #fff; }
.nav-btn-next {
    background: linear-gradient(135deg,#00c8ff,#0066cc);
    color: #fff; border: none;
    box-shadow: 0 0 20px rgba(0,200,255,0.25);
}
.nav-btn-next:hover { box-shadow: 0 0 35px rgba(0,200,255,0.45); transform: translateY(-1px); }
.price-line { display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; padding: 5px 0; }
.summary-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid rgba(0,200,255,0.08); }
</style>

<!-- ═══ HERO ═══ -->
<section class="relative pt-32 pb-12 overflow-hidden">
    <div class="blob bg-nc-cyan"  style="width:500px;height:500px;top:-150px;left:-200px;"></div>
    <div class="blob bg-nc-blue"  style="width:400px;height:400px;bottom:-100px;right:-150px;animation-delay:2s;"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <div class="badge mb-6 mx-auto" data-aos="fade-down">
            <i class="fas fa-calculator"></i> Estimateur de prix gratuit
        </div>
        <h1 class="font-heading font-bold text-4xl md:text-6xl text-white mb-5" data-aos="fade-up">
            Configurez votre <span class="gradient-text">projet</span>
        </h1>
        <p class="text-gray-400 text-lg max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="100">
            Définissez vos besoins étape par étape et obtenez une estimation de prix instantanée. Sans engagement.
        </p>
    </div>
</section>

<!-- ═══ WIZARD ═══ -->
<section class="pb-24 relative z-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row gap-8 items-start">

            <!-- LEFT: Wizard -->
            <div class="flex-1 min-w-0">

                <!-- Step Indicator -->
                <div class="glass rounded-2xl p-5 mb-6" data-aos="fade-up">
                    <div class="flex items-center">
                        <!-- Step 1 -->
                        <div class="flex flex-col items-center gap-1">
                            <div id="dot-1" class="step-dot active">1</div>
                            <span id="lbl-1" class="text-xs font-medium text-nc-cyan hidden sm:block">Type</span>
                        </div>
                        <div id="line-1" class="step-line mx-2"></div>
                        <!-- Step 2 -->
                        <div class="flex flex-col items-center gap-1">
                            <div id="dot-2" class="step-dot todo">2</div>
                            <span id="lbl-2" class="text-xs font-medium text-gray-500 hidden sm:block">Fonctionnalités</span>
                        </div>
                        <div id="line-2" class="step-line mx-2"></div>
                        <!-- Step 3 -->
                        <div class="flex flex-col items-center gap-1">
                            <div id="dot-3" class="step-dot todo">3</div>
                            <span id="lbl-3" class="text-xs font-medium text-gray-500 hidden sm:block">Design & Délai</span>
                        </div>
                        <div id="line-3" class="step-line mx-2"></div>
                        <!-- Step 4 -->
                        <div class="flex flex-col items-center gap-1">
                            <div id="dot-4" class="step-dot todo">4</div>
                            <span id="lbl-4" class="text-xs font-medium text-gray-500 hidden sm:block">Récapitulatif</span>
                        </div>
                    </div>
                </div>

                <!-- ── STEP 1: Type de projet ── -->
                <div id="step-1" class="step-panel active glass rounded-2xl p-6 sm:p-8" data-aos="fade-up" data-aos-delay="100">
                    <h2 class="font-heading font-bold text-white text-2xl mb-2">Type de projet</h2>
                    <p class="text-gray-400 text-sm mb-6">Quel type de site ou d'application souhaitez-vous créer ?</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" id="type-grid">
                        <div class="type-card" data-type="Site Vitrine" data-price="80000" onclick="selectType(this)">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.2)">
                                    <i class="fas fa-globe text-xl" style="color:#00c8ff"></i>
                                </div>
                                <div>
                                    <div class="font-heading font-bold text-white text-base">Site Vitrine</div>
                                    <div class="text-gray-400 text-xs mt-1">Présence en ligne professionnelle</div>
                                    <div class="mt-3 font-bold text-sm" style="color:#00c8ff">80 000 FCFA</div>
                                </div>
                            </div>
                        </div>
                        <div class="type-card" data-type="E-Commerce" data-price="200000" onclick="selectType(this)">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(0,102,204,0.1);border:1px solid rgba(0,102,204,0.25)">
                                    <i class="fas fa-shopping-cart text-xl" style="color:#4db8ff"></i>
                                </div>
                                <div>
                                    <div class="font-heading font-bold text-white text-base">E-Commerce</div>
                                    <div class="text-gray-400 text-xs mt-1">Boutique en ligne complète</div>
                                    <div class="mt-3 font-bold text-sm" style="color:#00c8ff">200 000 FCFA</div>
                                </div>
                            </div>
                        </div>
                        <div class="type-card" data-type="Application Web" data-price="350000" onclick="selectType(this)">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(124,58,237,0.1);border:1px solid rgba(124,58,237,0.25)">
                                    <i class="fas fa-laptop-code text-xl" style="color:#7c3aed"></i>
                                </div>
                                <div>
                                    <div class="font-heading font-bold text-white text-base">Application Web</div>
                                    <div class="text-gray-400 text-xs mt-1">App web sur mesure</div>
                                    <div class="mt-3 font-bold text-sm" style="color:#00c8ff">350 000 FCFA</div>
                                </div>
                            </div>
                        </div>
                        <div class="type-card" data-type="Refonte de site" data-price="120000" onclick="selectType(this)">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2)">
                                    <i class="fas fa-sync-alt text-xl" style="color:#10b981"></i>
                                </div>
                                <div>
                                    <div class="font-heading font-bold text-white text-base">Refonte de site</div>
                                    <div class="text-gray-400 text-xs mt-1">Modernisation de l'existant</div>
                                    <div class="mt-3 font-bold text-sm" style="color:#00c8ff">120 000 FCFA</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="step1-error" class="hidden mt-4 text-sm font-medium flex items-center gap-2" style="color:#f87171">
                        <i class="fas fa-exclamation-circle"></i> Veuillez sélectionner un type de projet.
                    </div>

                    <div class="flex justify-end mt-8">
                        <button class="nav-btn nav-btn-next" onclick="nextStep()">
                            Suivant <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ── STEP 2: Fonctionnalités ── -->
                <div id="step-2" class="step-panel glass rounded-2xl p-6 sm:p-8">
                    <h2 class="font-heading font-bold text-white text-2xl mb-2">Fonctionnalités</h2>
                    <p class="text-gray-400 text-sm mb-6">Sélectionnez les fonctionnalités dont vous avez besoin. Vous pouvez en choisir plusieurs.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="feature-grid">
                        <div class="feature-card" data-feat="Blog intégré" data-price="20000" onclick="toggleFeature(this)">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.18)">
                                    <i class="fas fa-newspaper text-sm" style="color:#00c8ff"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-white text-sm">Blog intégré</div>
                                    <div class="text-xs" style="color:#00c8ff">+20 000 FCFA</div>
                                </div>
                                <div class="check-icon w-5 h-5 rounded-full border-2 border-gray-600 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-xs opacity-0"></i>
                                </div>
                            </div>
                        </div>
                        <div class="feature-card" data-feat="Espace membres" data-price="50000" onclick="toggleFeature(this)">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.18)">
                                    <i class="fas fa-users text-sm" style="color:#4db8ff"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-white text-sm">Espace membres</div>
                                    <div class="text-xs" style="color:#00c8ff">+50 000 FCFA</div>
                                </div>
                                <div class="check-icon w-5 h-5 rounded-full border-2 border-gray-600 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-xs opacity-0"></i>
                                </div>
                            </div>
                        </div>
                        <div class="feature-card" data-feat="Paiement en ligne" data-price="70000" onclick="toggleFeature(this)">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.18)">
                                    <i class="fas fa-credit-card text-sm" style="color:#0066cc"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-white text-sm">Paiement en ligne</div>
                                    <div class="text-xs" style="color:#00c8ff">+70 000 FCFA</div>
                                </div>
                                <div class="check-icon w-5 h-5 rounded-full border-2 border-gray-600 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-xs opacity-0"></i>
                                </div>
                            </div>
                        </div>
                        <div class="feature-card" data-feat="Chat en direct" data-price="30000" onclick="toggleFeature(this)">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.18)">
                                    <i class="fas fa-comments text-sm" style="color:#10b981"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-white text-sm">Chat en direct</div>
                                    <div class="text-xs" style="color:#00c8ff">+30 000 FCFA</div>
                                </div>
                                <div class="check-icon w-5 h-5 rounded-full border-2 border-gray-600 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-xs opacity-0"></i>
                                </div>
                            </div>
                        </div>
                        <div class="feature-card" data-feat="Newsletter" data-price="15000" onclick="toggleFeature(this)">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.18)">
                                    <i class="fas fa-envelope text-sm" style="color:#f59e0b"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-white text-sm">Newsletter</div>
                                    <div class="text-xs" style="color:#00c8ff">+15 000 FCFA</div>
                                </div>
                                <div class="check-icon w-5 h-5 rounded-full border-2 border-gray-600 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-xs opacity-0"></i>
                                </div>
                            </div>
                        </div>
                        <div class="feature-card" data-feat="Multi-langue" data-price="25000" onclick="toggleFeature(this)">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.18)">
                                    <i class="fas fa-language text-sm" style="color:#7c3aed"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-white text-sm">Multi-langue</div>
                                    <div class="text-xs" style="color:#00c8ff">+25 000 FCFA</div>
                                </div>
                                <div class="check-icon w-5 h-5 rounded-full border-2 border-gray-600 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-xs opacity-0"></i>
                                </div>
                            </div>
                        </div>
                        <div class="feature-card" data-feat="SEO avancé" data-price="30000" onclick="toggleFeature(this)">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.18)">
                                    <i class="fas fa-search text-sm" style="color:#ec4899"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-white text-sm">SEO avancé</div>
                                    <div class="text-xs" style="color:#00c8ff">+30 000 FCFA</div>
                                </div>
                                <div class="check-icon w-5 h-5 rounded-full border-2 border-gray-600 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-xs opacity-0"></i>
                                </div>
                            </div>
                        </div>
                        <div class="feature-card" data-feat="Maintenance 1 an" data-price="40000" onclick="toggleFeature(this)">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.18)">
                                    <i class="fas fa-wrench text-sm" style="color:#00c8ff"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-white text-sm">Maintenance 1 an</div>
                                    <div class="text-xs" style="color:#00c8ff">+40 000 FCFA</div>
                                </div>
                                <div class="check-icon w-5 h-5 rounded-full border-2 border-gray-600 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-xs opacity-0"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 text-xs text-gray-500 flex items-center gap-2">
                        <i class="fas fa-info-circle" style="color:#00c8ff"></i>
                        Aucune sélection requise — vous pouvez passer cette étape.
                    </div>

                    <div class="flex justify-between mt-8">
                        <button class="nav-btn nav-btn-prev" onclick="prevStep()">
                            <i class="fas fa-arrow-left"></i> Précédent
                        </button>
                        <button class="nav-btn nav-btn-next" onclick="nextStep()">
                            Suivant <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ── STEP 3: Design + Délai ── -->
                <div id="step-3" class="step-panel glass rounded-2xl p-6 sm:p-8">
                    <h2 class="font-heading font-bold text-white text-2xl mb-2">Design & Délai</h2>
                    <p class="text-gray-400 text-sm mb-6">Choisissez votre niveau de design et votre délai de livraison.</p>

                    <!-- Design level -->
                    <div class="mb-7">
                        <h3 class="font-heading font-semibold text-white text-base mb-4 flex items-center gap-2">
                            <i class="fas fa-palette text-nc-cyan"></i> Niveau de design
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" id="design-grid">
                            <div class="design-card" data-design="Standard" data-price="0" onclick="selectDesign(this)">
                                <div class="text-center">
                                    <div class="w-10 h-10 rounded-xl mx-auto mb-3 flex items-center justify-center"
                                         style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.2)">
                                        <i class="fas fa-paint-brush" style="color:#00c8ff"></i>
                                    </div>
                                    <div class="font-heading font-bold text-white text-sm">Standard</div>
                                    <div class="text-gray-400 text-xs mt-1">Template personnalisé</div>
                                    <div class="mt-2 font-bold text-sm" style="color:#10b981">Inclus</div>
                                </div>
                            </div>
                            <div class="design-card" data-design="Sur mesure" data-price="30000" onclick="selectDesign(this)">
                                <div class="text-center">
                                    <div class="w-10 h-10 rounded-xl mx-auto mb-3 flex items-center justify-center"
                                         style="background:rgba(0,102,204,0.15);border:1px solid rgba(0,102,204,0.3)">
                                        <i class="fas fa-magic" style="color:#4db8ff"></i>
                                    </div>
                                    <div class="font-heading font-bold text-white text-sm">Sur mesure</div>
                                    <div class="text-gray-400 text-xs mt-1">Design unique créé pour vous</div>
                                    <div class="mt-2 font-bold text-sm" style="color:#00c8ff">+30 000 FCFA</div>
                                </div>
                            </div>
                            <div class="design-card" data-design="Premium" data-price="75000" onclick="selectDesign(this)">
                                <div class="text-center">
                                    <div class="w-10 h-10 rounded-xl mx-auto mb-3 flex items-center justify-center"
                                         style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.25)">
                                        <i class="fas fa-crown" style="color:#f59e0b"></i>
                                    </div>
                                    <div class="font-heading font-bold text-white text-sm">Premium</div>
                                    <div class="text-gray-400 text-xs mt-1">Design exclusif + animations</div>
                                    <div class="mt-2 font-bold text-sm" style="color:#00c8ff">+75 000 FCFA</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div>
                        <h3 class="font-heading font-semibold text-white text-base mb-4 flex items-center gap-2">
                            <i class="fas fa-clock text-nc-cyan"></i> Délai de livraison
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" id="timeline-grid">
                            <div class="timeline-card" data-tl="Urgent < 2 semaines" data-price="30000" onclick="selectTimeline(this)">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                         style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2)">
                                        <i class="fas fa-bolt" style="color:#ef4444"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-white text-sm">Urgent</div>
                                        <div class="text-gray-400 text-xs">Moins de 2 semaines</div>
                                        <div class="text-xs font-bold mt-1" style="color:#ef4444">+30 000 FCFA</div>
                                    </div>
                                </div>
                            </div>
                            <div class="timeline-card" data-tl="Normal 1-2 mois" data-price="0" onclick="selectTimeline(this)">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                         style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.18)">
                                        <i class="fas fa-calendar" style="color:#00c8ff"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-white text-sm">Normal</div>
                                        <div class="text-gray-400 text-xs">1 à 2 mois</div>
                                        <div class="text-xs font-bold mt-1" style="color:#10b981">Inclus</div>
                                    </div>
                                </div>
                            </div>
                            <div class="timeline-card" data-tl="Flexible (remise)" data-price="-20000" onclick="selectTimeline(this)">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                         style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.18)">
                                        <i class="fas fa-leaf" style="color:#10b981"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-white text-sm">Flexible</div>
                                        <div class="text-gray-400 text-xs">Pas de contrainte</div>
                                        <div class="text-xs font-bold mt-1" style="color:#10b981">-20 000 FCFA</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="step3-error" class="hidden mt-4 text-sm font-medium flex items-center gap-2" style="color:#f87171">
                        <i class="fas fa-exclamation-circle"></i> Veuillez sélectionner un niveau de design.
                    </div>

                    <div class="flex justify-between mt-8">
                        <button class="nav-btn nav-btn-prev" onclick="prevStep()">
                            <i class="fas fa-arrow-left"></i> Précédent
                        </button>
                        <button class="nav-btn nav-btn-next" onclick="nextStep()">
                            Voir le récapitulatif <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- ── STEP 4: Récapitulatif ── -->
                <div id="step-4" class="step-panel glass rounded-2xl p-6 sm:p-8">
                    <h2 class="font-heading font-bold text-white text-2xl mb-2">Récapitulatif de votre projet</h2>
                    <p class="text-gray-400 text-sm mb-6">Vérifiez votre configuration avant d'envoyer votre demande.</p>

                    <div id="summary-content"><!-- filled by JS --></div>

                    <!-- Contact form -->
                    <div class="mt-8 pt-8" style="border-top:1px solid rgba(0,200,255,0.1)">
                        <h3 class="font-heading font-bold text-white text-lg mb-5 flex items-center gap-2">
                            <i class="fas fa-paper-plane" style="color:#00c8ff"></i> Envoyez votre demande
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-gray-400 text-xs font-semibold mb-2 uppercase tracking-wide">Nom complet *</label>
                                <input id="f-name" type="text" class="form-input" placeholder="Votre nom">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-xs font-semibold mb-2 uppercase tracking-wide">Email *</label>
                                <input id="f-email" type="email" class="form-input" placeholder="votre@email.com">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-xs font-semibold mb-2 uppercase tracking-wide">Téléphone</label>
                                <input id="f-phone" type="tel" class="form-input" placeholder="+227 XX XX XX XX">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-xs font-semibold mb-2 uppercase tracking-wide">Budget estimé</label>
                                <input id="f-budget" type="text" class="form-input" readonly style="color:#00c8ff;cursor:default">
                            </div>
                        </div>
                        <div class="mb-6">
                            <label class="block text-gray-400 text-xs font-semibold mb-2 uppercase tracking-wide">Message / précisions</label>
                            <textarea id="f-message" rows="4" class="form-input" placeholder="Décrivez votre projet en quelques mots..."></textarea>
                        </div>
                        <div id="form-error" class="hidden mb-4 text-sm font-medium flex items-center gap-2" style="color:#f87171">
                            <i class="fas fa-exclamation-circle"></i> Veuillez remplir votre nom et votre email.
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <button class="nav-btn nav-btn-prev" onclick="prevStep()">
                                <i class="fas fa-arrow-left"></i> Modifier
                            </button>
                            <button class="nav-btn nav-btn-next flex-1 justify-center" onclick="sendWhatsApp()" style="background:linear-gradient(135deg,#25d366,#128c7e)">
                                <i class="fab fa-whatsapp text-base"></i> Envoyer via WhatsApp
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 p-4 rounded-xl text-xs text-gray-500 flex items-start gap-3"
                         style="background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.15)">
                        <i class="fas fa-info-circle mt-0.5 flex-shrink-0" style="color:#f59e0b"></i>
                        <span><strong style="color:#f59e0b">Note :</strong> Prix estimatifs basés sur vos sélections. Le devis final sera établi après analyse approfondie de votre projet.</span>
                    </div>
                </div>

            </div><!-- end .flex-1 -->

            <!-- RIGHT: Price display -->
            <div class="w-full lg:w-72 xl:w-80" data-aos="fade-left" data-aos-delay="150">
                <div class="price-panel">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                             style="background:rgba(0,200,255,0.12);border:1px solid rgba(0,200,255,0.2)">
                            <i class="fas fa-calculator" style="color:#00c8ff"></i>
                        </div>
                        <div>
                            <div class="font-heading font-bold text-white text-sm">Estimation en temps réel</div>
                            <div class="text-gray-500 text-xs">Mis à jour automatiquement</div>
                        </div>
                    </div>

                    <!-- Breakdown -->
                    <div id="price-breakdown" class="space-y-1 mb-5 min-h-[80px]">
                        <div class="text-gray-500 text-xs italic text-center py-4">Sélectionnez un type de projet...</div>
                    </div>

                    <div class="section-divider mb-4"></div>

                    <!-- Total -->
                    <div class="price-total-box">
                        <div class="text-gray-400 text-xs font-semibold uppercase tracking-wider mb-2">Estimation totale</div>
                        <div id="price-total" class="font-heading font-bold text-3xl gradient-text stat-number">0 FCFA</div>
                        <div class="text-gray-500 text-xs mt-2">TTC · Hors hébergement</div>
                    </div>

                    <div class="mt-4 text-xs text-gray-600 leading-relaxed">
                        <i class="fas fa-shield-alt mr-1" style="color:#10b981"></i>
                        Devis gratuit et sans engagement.
                    </div>

                    <!-- Step progress labels -->
                    <div class="mt-5 space-y-2">
                        <div id="prog-type" class="flex items-center gap-2 text-xs text-gray-600">
                            <i class="fas fa-circle text-[6px]" style="color:#475569"></i> Type de projet
                        </div>
                        <div id="prog-feat" class="flex items-center gap-2 text-xs text-gray-600">
                            <i class="fas fa-circle text-[6px]" style="color:#475569"></i> Fonctionnalités
                        </div>
                        <div id="prog-design" class="flex items-center gap-2 text-xs text-gray-600">
                            <i class="fas fa-circle text-[6px]" style="color:#475569"></i> Design
                        </div>
                        <div id="prog-tl" class="flex items-center gap-2 text-xs text-gray-600">
                            <i class="fas fa-circle text-[6px]" style="color:#475569"></i> Délai
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- end flex row -->
    </div>
</section>

<script>
// ─── State ───────────────────────────────────────────────────────────────────
let currentStep = 1;
const config = {
    type:        '',
    typePrice:   0,
    features:    [],
    featPrices:  {},
    design:      '',
    designPrice: 0,
    timeline:    '',
    tlPrice:     0,
};

// ─── Formatters ──────────────────────────────────────────────────────────────
function fmt(n) {
    const abs = Math.abs(n);
    const s = abs.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return (n < 0 ? '- ' : '') + s + ' FCFA';
}
function fmtPlain(n) {
    return Math.abs(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
}

// ─── Calculations ────────────────────────────────────────────────────────────
function calcTotal() {
    let total = config.typePrice + config.designPrice + config.tlPrice;
    config.features.forEach(f => total += config.featPrices[f] || 0);
    return Math.max(0, total);
}

// ─── Update price panel ──────────────────────────────────────────────────────
function updatePriceDisplay() {
    const bd = document.getElementById('price-breakdown');
    const rows = [];

    if (config.type) {
        rows.push(`<div class="price-line"><span class="text-gray-300">${config.type}</span><span style="color:#00c8ff;font-weight:600">${fmtPlain(config.typePrice)}</span></div>`);
        document.getElementById('prog-type').innerHTML = `<i class="fas fa-check-circle text-[10px]" style="color:#00c8ff"></i> <span style="color:#00c8ff">${config.type}</span>`;
    } else {
        document.getElementById('prog-type').innerHTML = `<i class="fas fa-circle text-[6px]" style="color:#475569"></i> <span>Type de projet</span>`;
    }

    config.features.forEach(f => {
        const p = config.featPrices[f] || 0;
        rows.push(`<div class="price-line"><span class="text-gray-400 text-xs">+ ${f}</span><span class="text-xs" style="color:#4db8ff">+${fmtPlain(p)}</span></div>`);
    });
    if (config.features.length > 0) {
        document.getElementById('prog-feat').innerHTML = `<i class="fas fa-check-circle text-[10px]" style="color:#00c8ff"></i> <span style="color:#00c8ff">${config.features.length} fonctionnalité(s)</span>`;
    } else {
        document.getElementById('prog-feat').innerHTML = `<i class="fas fa-circle text-[6px]" style="color:#475569"></i> <span>Fonctionnalités</span>`;
    }

    if (config.design) {
        if (config.designPrice > 0) {
            rows.push(`<div class="price-line"><span class="text-gray-400 text-xs">Design ${config.design}</span><span class="text-xs" style="color:#4db8ff">+${fmtPlain(config.designPrice)}</span></div>`);
        }
        document.getElementById('prog-design').innerHTML = `<i class="fas fa-check-circle text-[10px]" style="color:#00c8ff"></i> <span style="color:#00c8ff">Design ${config.design}</span>`;
    } else {
        document.getElementById('prog-design').innerHTML = `<i class="fas fa-circle text-[6px]" style="color:#475569"></i> <span>Design</span>`;
    }

    if (config.timeline) {
        if (config.tlPrice !== 0) {
            const cl = config.tlPrice < 0 ? '#10b981' : '#ef4444';
            const sign = config.tlPrice < 0 ? '' : '+';
            rows.push(`<div class="price-line"><span class="text-gray-400 text-xs">${config.timeline}</span><span class="text-xs font-semibold" style="color:${cl}">${sign}${fmt(config.tlPrice)}</span></div>`);
        }
        document.getElementById('prog-tl').innerHTML = `<i class="fas fa-check-circle text-[10px]" style="color:#00c8ff"></i> <span style="color:#00c8ff">${config.timeline}</span>`;
    } else {
        document.getElementById('prog-tl').innerHTML = `<i class="fas fa-circle text-[6px]" style="color:#475569"></i> <span>Délai</span>`;
    }

    bd.innerHTML = rows.length ? rows.join('') : '<div class="text-gray-500 text-xs italic text-center py-4">Sélectionnez un type de projet...</div>';
    document.getElementById('price-total').textContent = fmt(calcTotal()).replace('- ', '-');

    // Update budget field if present
    const bf = document.getElementById('f-budget');
    if (bf) bf.value = fmt(calcTotal()).replace('- ', '');
}

// ─── Step 1: type selection ───────────────────────────────────────────────────
function selectType(el) {
    document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    config.type      = el.dataset.type;
    config.typePrice = parseInt(el.dataset.price, 10);
    document.getElementById('step1-error').classList.add('hidden');
    updatePriceDisplay();
}

// ─── Step 2: feature toggle ───────────────────────────────────────────────────
function toggleFeature(el) {
    const feat  = el.dataset.feat;
    const price = parseInt(el.dataset.price, 10);
    const chk   = el.querySelector('.check-icon');
    const ico   = chk.querySelector('i');

    if (el.classList.contains('selected')) {
        el.classList.remove('selected');
        chk.style.borderColor = '#4b5563';
        ico.style.opacity = '0';
        config.features = config.features.filter(f => f !== feat);
        delete config.featPrices[feat];
    } else {
        el.classList.add('selected');
        chk.style.borderColor = '#00c8ff';
        chk.style.background  = '#00c8ff';
        ico.style.opacity = '1';
        ico.style.color   = '#000';
        config.features.push(feat);
        config.featPrices[feat] = price;
    }
    updatePriceDisplay();
}

// ─── Step 3: design selection ─────────────────────────────────────────────────
function selectDesign(el) {
    document.querySelectorAll('.design-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    config.design      = el.dataset.design;
    config.designPrice = parseInt(el.dataset.price, 10);
    document.getElementById('step3-error').classList.add('hidden');
    updatePriceDisplay();
}

// ─── Step 3: timeline selection ───────────────────────────────────────────────
function selectTimeline(el) {
    document.querySelectorAll('.timeline-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    config.timeline = el.dataset.tl;
    config.tlPrice  = parseInt(el.dataset.price, 10);
    updatePriceDisplay();
}

// ─── Step indicator update ────────────────────────────────────────────────────
function updateStepUI(step) {
    const labels = ['', 'Type', 'Fonctionnalités', 'Design & Délai', 'Récapitulatif'];
    for (let i = 1; i <= 4; i++) {
        const dot = document.getElementById('dot-' + i);
        const lbl = document.getElementById('lbl-' + i);
        dot.className = 'step-dot';
        if (i < step)       { dot.classList.add('done');   lbl.style.color = '#00c8ff'; }
        else if (i === step){ dot.classList.add('active');  lbl.style.color = '#00c8ff'; }
        else                { dot.classList.add('todo');    lbl.style.color = '#475569'; }
    }
    for (let i = 1; i <= 3; i++) {
        const line = document.getElementById('line-' + i);
        if (i < step) line.classList.add('done');
        else          line.classList.remove('done');
    }
}

// ─── Navigation ──────────────────────────────────────────────────────────────
function nextStep() {
    // Validation
    if (currentStep === 1) {
        if (!config.type) {
            document.getElementById('step1-error').classList.remove('hidden');
            return;
        }
    }
    if (currentStep === 3) {
        if (!config.design) {
            document.getElementById('step3-error').classList.remove('hidden');
            return;
        }
    }

    const cur = document.getElementById('step-' + currentStep);
    cur.classList.remove('active');

    currentStep++;
    updateStepUI(currentStep);

    const next = document.getElementById('step-' + currentStep);
    next.classList.add('active');

    if (currentStep === 4) renderSummary();

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function prevStep() {
    if (currentStep <= 1) return;
    const cur = document.getElementById('step-' + currentStep);
    cur.classList.remove('active');
    currentStep--;
    updateStepUI(currentStep);
    document.getElementById('step-' + currentStep).classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ─── Summary render ───────────────────────────────────────────────────────────
function renderSummary() {
    const total = calcTotal();
    const featHtml = config.features.length
        ? config.features.map(f => `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
              style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.2);color:#00c8ff">
              <i class="fas fa-check text-[9px]"></i>${f}</span>`).join('')
        : '<span class="text-gray-500 text-xs">Aucune fonctionnalité additionnelle</span>';

    const tlColor = config.tlPrice < 0 ? '#10b981' : (config.tlPrice > 0 ? '#ef4444' : '#10b981');
    const tlSign  = config.tlPrice < 0 ? '- ' : (config.tlPrice > 0 ? '+ ' : '');
    const tlFmt   = config.tlPrice !== 0 ? `(${tlSign}${fmtPlain(Math.abs(config.tlPrice))})` : '(Inclus)';

    document.getElementById('summary-content').innerHTML = `
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="rounded-xl p-4" style="background:rgba(0,200,255,0.05);border:1px solid rgba(0,200,255,0.12)">
                <div class="text-gray-500 text-xs uppercase tracking-wider mb-2">Type de projet</div>
                <div class="font-heading font-bold text-white">${config.type}</div>
                <div class="text-xs mt-1" style="color:#00c8ff">${fmtPlain(config.typePrice)}</div>
            </div>
            <div class="rounded-xl p-4" style="background:rgba(0,200,255,0.05);border:1px solid rgba(0,200,255,0.12)">
                <div class="text-gray-500 text-xs uppercase tracking-wider mb-2">Design</div>
                <div class="font-heading font-bold text-white">${config.design}</div>
                <div class="text-xs mt-1" style="color:#00c8ff">${config.designPrice > 0 ? '+' + fmtPlain(config.designPrice) : 'Inclus'}</div>
            </div>
            <div class="rounded-xl p-4 sm:col-span-2" style="background:rgba(0,200,255,0.05);border:1px solid rgba(0,200,255,0.12)">
                <div class="text-gray-500 text-xs uppercase tracking-wider mb-2">Délai</div>
                <div class="font-heading font-bold text-white">${config.timeline || 'Non précisé'}</div>
                <div class="text-xs mt-1" style="color:${tlColor}">${tlFmt}</div>
            </div>
        </div>

        <div class="rounded-xl p-4 mb-6" style="background:rgba(0,200,255,0.05);border:1px solid rgba(0,200,255,0.12)">
            <div class="text-gray-500 text-xs uppercase tracking-wider mb-3">Fonctionnalités sélectionnées</div>
            <div class="flex flex-wrap gap-2">${featHtml}</div>
        </div>

        <div class="rounded-2xl p-5 mb-2" style="background:linear-gradient(135deg,rgba(0,200,255,0.1),rgba(0,102,204,0.14));border:1px solid rgba(0,200,255,0.3)">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <div class="text-gray-400 text-sm font-semibold">Estimation totale du projet</div>
                    <div class="text-gray-500 text-xs mt-0.5">TTC · Hors hébergement annuel</div>
                </div>
                <div class="font-heading font-bold text-4xl gradient-text stat-number">${fmt(total)}</div>
            </div>
        </div>
    `;

    document.getElementById('f-budget').value = fmt(total);
    prefillMessage();
}

function prefillMessage() {
    const lines = [
        `Bonjour, je souhaite configurer un projet web :`,
        `• Type : ${config.type}`,
        `• Design : ${config.design}`,
        `• Délai : ${config.timeline || 'Non précisé'}`,
        config.features.length ? `• Fonctionnalités : ${config.features.join(', ')}` : '',
        `• Budget estimé : ${fmt(calcTotal())}`,
        `Pouvez-vous me contacter pour affiner le devis ?`
    ].filter(Boolean).join('\n');
    document.getElementById('f-message').value = lines;
}

// ─── WhatsApp send ────────────────────────────────────────────────────────────
function sendWhatsApp() {
    const name  = document.getElementById('f-name').value.trim();
    const email = document.getElementById('f-email').value.trim();

    if (!name || !email) {
        document.getElementById('form-error').classList.remove('hidden');
        return;
    }
    document.getElementById('form-error').classList.add('hidden');

    const msg = document.getElementById('f-message').value.trim();
    const phone = document.getElementById('f-phone').value.trim();

    const text = [
        `*Configurateur Netcrafter*`,
        ``,
        `*Nom :* ${name}`,
        `*Email :* ${email}`,
        phone ? `*Tél :* ${phone}` : '',
        ``,
        msg
    ].filter(l => l !== null && l !== undefined && !(l === '' && !msg)).join('\n');

    const url = 'https://wa.me/22788672115?text=' + encodeURIComponent(text);
    window.open(url, '_blank');
}

// ─── Init ─────────────────────────────────────────────────────────────────────
updateStepUI(1);
updatePriceDisplay();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
