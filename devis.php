<?php
$page_title    = 'Netcrafter - Demande de Devis Gratuit';
$page_keywords = 'devis site web Niger, devis développement web Niamey, tarif agence web Niger, prix site internet Niger, devis gratuit Netcrafter, contact agence digitale Niger';
include 'includes/header.php';
$_d = fn($fr, $en) => ($GLOBALS['nc_lang'] ?? 'fr') === 'en' ? $en : $fr;
?>

<!-- Hero -->
<section class="relative pt-32 pb-20 overflow-hidden">
    <div class="blob bg-nc-cyan" style="width:500px;height:500px;top:-150px;left:-200px;"></div>
    <div class="blob bg-nc-blue" style="width:400px;height:400px;bottom:-100px;right:-150px;animation-delay:2s;"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center" data-aos="fade-up">
        <div class="badge mb-6 mx-auto"><i class="fas fa-lock-open"></i> <?= t('devis.form_badge') ?></div>
        <h1 class="font-heading font-bold text-5xl md:text-7xl text-white mb-6">
            <span class="gradient-text"><?= t('devis.badge') ?></span>
        </h1>
        <p class="text-gray-400 text-xl max-w-3xl mx-auto">
            <?= t('devis.sub') ?>
        </p>
    </div>
</section>

<!-- Main -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

        <!-- ══ FORM ══ -->
        <div class="lg:col-span-2" data-aos="fade-right">
            <div class="glass rounded-3xl overflow-hidden" style="border-color:rgba(0,200,255,0.15)">

                <!-- Form header -->
                <div class="px-8 py-6" style="background:linear-gradient(135deg,rgba(0,200,255,0.1),rgba(0,102,204,0.1));border-bottom:1px solid rgba(0,200,255,0.1)">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center"
                             style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.25)">
                            <i class="fas fa-file-invoice text-xl" style="color:#00c8ff"></i>
                        </div>
                        <div>
                            <h2 class="font-heading font-bold text-white text-xl"><?= t('devis.form_title') ?></h2>
                            <p class="text-gray-400 text-sm"><?= t('devis.fill_below') ?></p>
                        </div>
                    </div>
                    <!-- Steps -->
                    <div class="flex items-center gap-2">
                        <?php foreach ([['1', t('devis.step_info')],['2', t('devis.step_svcs')],['3', t('devis.step_project')]] as $si => $step): ?>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full text-sm font-bold flex items-center justify-center"
                                 style="<?= $si===0 ? 'background:#00c8ff;color:#060d1e' : 'background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.2);color:#6b7280' ?>">
                                <?= $step[0] ?>
                            </div>
                            <span class="text-sm hidden sm:block <?= $si===0 ? 'text-white font-medium' : 'text-gray-500' ?>"><?= $step[1] ?></span>
                        </div>
                        <?php if ($si < 2): ?><div class="flex-1 h-px" style="background:rgba(0,200,255,0.1)"></div><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Form body -->
                <form id="devisForm" method="POST" action="submit-devis.php" class="p-8 space-y-10">

                    <!-- Step 1 -->
                    <div>
                        <h3 class="font-heading font-semibold text-white text-lg flex items-center gap-3 mb-6">
                            <span class="w-8 h-8 rounded-full flex items-center justify-center"
                                  style="background:rgba(0,200,255,0.1);border:1px solid rgba(0,200,255,0.25)">
                                <i class="fas fa-user text-xs" style="color:#00c8ff"></i>
                            </span>
                            <?= t('devis.info_step') ?>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <?php foreach ([
                                ['nom',       $_d('Nom','Last name'),     $_d('Votre nom','Your last name')],
                                ['prenom',    $_d('Prénom','First name'),  $_d('Votre prénom','Your first name')],
                                ['email',     'Email',                     'vous@exemple.com'],
                                ['telephone', $_d('Téléphone','Phone'),    '+227 XX XX XX XX'],
                            ] as $f): ?>
                            <div>
                                <label class="block text-gray-300 text-sm font-medium mb-2"><?= $f[1] ?> <span style="color:#00c8ff">*</span></label>
                                <input type="<?= $f[0]==='email' ? 'email' : ($f[0]==='telephone' ? 'tel' : 'text') ?>"
                                       name="<?= $f[0] ?>" required placeholder="<?= $f[2] ?>"
                                       class="w-full px-4 py-3 glass rounded-xl text-white placeholder-gray-600 outline-none border border-transparent transition-all"
                                       style="background:rgba(10,24,58,0.5)"
                                       onfocus="this.style.borderColor='rgba(0,200,255,0.4)'"
                                       onblur="this.style.borderColor='transparent'">
                            </div>
                            <?php endforeach; ?>
                            <div class="md:col-span-2">
                                <label class="block text-gray-300 text-sm font-medium mb-2"><?= t('devis.company') ?></label>
                                <input type="text" name="entreprise" placeholder="<?= t('devis.company_opt') ?>"
                                       class="w-full px-4 py-3 glass rounded-xl text-white placeholder-gray-600 outline-none border border-transparent transition-all"
                                       style="background:rgba(10,24,58,0.5)"
                                       onfocus="this.style.borderColor='rgba(0,200,255,0.4)'"
                                       onblur="this.style.borderColor='transparent'">
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div>
                        <h3 class="font-heading font-semibold text-white text-lg flex items-center gap-3 mb-6">
                            <span class="w-8 h-8 rounded-full flex items-center justify-center"
                                  style="background:rgba(0,102,204,0.1);border:1px solid rgba(0,102,204,0.25)">
                                <i class="fas fa-briefcase text-xs" style="color:#0066cc"></i>
                            </span>
                            <?= t('devis.services_step') ?>
                        </h3>
                        <p class="text-gray-500 text-sm mb-5"><?= t('devis.select_svcs') ?></p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                            <?php
                            $devisServices = [
                                [t('devis.svc_web'),    'fa-code',          '#00c8ff', t('devis.svc_web_sub')],
                                [t('devis.svc_mkt'),    'fa-bullhorn',      '#4db8ff', t('devis.svc_mkt_sub')],
                                [t('devis.svc_design'), 'fa-palette',       '#0099dd', t('devis.svc_design_sub')],
                                [t('devis.svc_secu'),   'fa-shield-alt',    '#0066cc', t('devis.svc_secu_sub')],
                                [t('devis.svc_it'),     'fa-server',        '#00c8ff', t('devis.svc_it_sub')],
                                [t('devis.svc_form'),   'fa-graduation-cap','#4db8ff', t('devis.svc_form_sub')],
                            ];
                            foreach ($devisServices as $s): ?>
                            <label class="service-card p-4 rounded-xl cursor-pointer" id="svc-<?= md5($s[0]) ?>">
                                <input type="checkbox" name="services[]" value="<?= htmlspecialchars($s[0]) ?>" class="sr-only"
                                       onchange="toggleSvcCard('<?= md5($s[0]) ?>',this.checked,'<?= $s[2] ?>')">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                         style="background:<?= $s[2] ?>15;border:1px solid <?= $s[2] ?>35">
                                        <i class="fas <?= $s[1] ?> text-sm" style="color:<?= $s[2] ?>"></i>
                                    </div>
                                    <div>
                                        <p class="text-white text-sm font-medium"><?= $s[0] ?></p>
                                        <p class="text-gray-500 text-xs"><?= $s[3] ?></p>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div>
                        <h3 class="font-heading font-semibold text-white text-lg flex items-center gap-3 mb-6">
                            <span class="w-8 h-8 rounded-full flex items-center justify-center"
                                  style="background:rgba(77,184,255,0.1);border:1px solid rgba(77,184,255,0.25)">
                                <i class="fas fa-project-diagram text-xs" style="color:#4db8ff"></i>
                            </span>
                            <?= t('devis.project_step') ?>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                            <div>
                                <label class="block text-gray-300 text-sm font-medium mb-2"><?= t('devis.budget_label') ?></label>
                                <select name="budget" class="w-full px-4 py-3 glass rounded-xl text-white outline-none border border-transparent transition-all bg-transparent"
                                        style="background:rgba(10,24,58,0.5)"
                                        onfocus="this.style.borderColor='rgba(0,200,255,0.4)'"
                                        onblur="this.style.borderColor='transparent'">
                                    <option value="" class="bg-gray-900"><?= t('devis.budget_ph') ?></option>
                                    <option value="moins-50000 FCFA" class="bg-gray-900"><?= t('devis.b_under50') ?></option>
                                    <option value="50000-100000 FCFA" class="bg-gray-900"><?= t('devis.b_50_100') ?></option>
                                    <option value="100000-300000 FCFA" class="bg-gray-900"><?= t('devis.b_100_300') ?></option>
                                    <option value="300000-500000 FCFA" class="bg-gray-900"><?= t('devis.b_300_500') ?></option>
                                    <option value="plus-500000 FCFA" class="bg-gray-900"><?= t('devis.b_over500') ?></option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-300 text-sm font-medium mb-2"><?= t('devis.delay_label') ?></label>
                                <select name="delai" class="w-full px-4 py-3 glass rounded-xl text-white outline-none border border-transparent transition-all bg-transparent"
                                        style="background:rgba(10,24,58,0.5)"
                                        onfocus="this.style.borderColor='rgba(0,200,255,0.4)'"
                                        onblur="this.style.borderColor='transparent'">
                                    <option value="" class="bg-gray-900"><?= t('devis.delay_ph') ?></option>
                                    <option value="urgent" class="bg-gray-900"><?= t('devis.d_urgent') ?></option>
                                    <option value="1-mois" class="bg-gray-900"><?= t('devis.d_1m') ?></option>
                                    <option value="2-3-mois" class="bg-gray-900"><?= t('devis.d_2_3m') ?></option>
                                    <option value="flexible" class="bg-gray-900"><?= t('devis.d_flex') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-5">
                            <label class="block text-gray-300 text-sm font-medium mb-2"><?= t('devis.desc_label') ?> <span style="color:#00c8ff">*</span></label>
                            <textarea name="description" rows="6" required
                                      placeholder="<?= t('devis.desc_placeholder') ?>"
                                      class="w-full px-4 py-3 glass rounded-xl text-white placeholder-gray-600 outline-none border border-transparent transition-all resize-none"
                                      style="background:rgba(10,24,58,0.5)"
                                      onfocus="this.style.borderColor='rgba(0,200,255,0.4)'"
                                      onblur="this.style.borderColor='transparent'"></textarea>
                        </div>
                        <div>
                            <label class="block text-gray-300 text-sm font-medium mb-2"><?= t('devis.how_found') ?></label>
                            <select name="source" class="w-full px-4 py-3 glass rounded-xl text-white outline-none border border-transparent transition-all bg-transparent"
                                    style="background:rgba(10,24,58,0.5)"
                                    onfocus="this.style.borderColor='rgba(0,200,255,0.4)'"
                                    onblur="this.style.borderColor='transparent'">
                                <option value="" class="bg-gray-900"><?= $_d('Sélectionnez une option','Select an option') ?></option>
                                <option value="recherche-google" class="bg-gray-900"><?= $_d('Recherche Google','Google Search') ?></option>
                                <option value="reseaux-sociaux" class="bg-gray-900"><?= $_d('Réseaux sociaux','Social media') ?></option>
                                <option value="recommandation" class="bg-gray-900"><?= $_d('Recommandation','Recommendation') ?></option>
                                <option value="publicite" class="bg-gray-900"><?= $_d('Publicité','Advertisement') ?></option>
                                <option value="autre" class="bg-gray-900"><?= $_d('Autre','Other') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row gap-4 items-center justify-end pt-4" style="border-top:1px solid rgba(0,200,255,0.1)">
                        <button type="reset" class="btn-outline text-sm py-3 px-6">
                            <i class="fas fa-undo"></i> <?= t('devis.reset') ?>
                        </button>
                        <button type="submit" id="submitBtn" class="btn-primary text-sm py-3 px-8">
                            <i class="fas fa-paper-plane"></i> <?= t('devis.send_request') ?>
                        </button>
                    </div>

                    <div id="formMessage" class="hidden"></div>
                </form>
            </div>
        </div>

        <!-- ══ SIDEBAR ══ -->
        <div class="space-y-6" data-aos="fade-left">

            <!-- Avantages -->
            <div class="glass rounded-2xl p-6" style="border-color:rgba(0,200,255,0.12)">
                <h3 class="font-heading font-semibold text-white mb-5"><?= t('devis.why_us') ?></h3>
                <div class="space-y-4">
                    <?php foreach ([
                        ['fa-bolt',        '#00c8ff', $_d('Réponse Rapide','Quick Response'),      $_d('Devis reçu sous 24 à 48 h','Quote received within 24–48 hours')],
                        ['fa-check-circle','#4db8ff', $_d('Sur Mesure','Tailored'),                $_d('Propositions adaptées à vos besoins','Proposals adapted to your needs')],
                        ['fa-shield-alt',  '#0066cc', $_d('Qualité Garantie','Quality Guaranteed'),$_d('Suivi sur toutes nos prestations','Support on all our services')],
                        ['fa-headset',     '#0099dd', $_d('Support Local','Local Support'),        $_d('Équipe disponible à Niamey, Niger','Team available in Niamey, Niger')],
                    ] as $b): ?>
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                             style="background:<?= $b[1] ?>15">
                            <i class="fas <?= $b[0] ?> text-sm" style="color:<?= $b[1] ?>"></i>
                        </div>
                        <div>
                            <p class="text-white text-sm font-medium"><?= $b[2] ?></p>
                            <p class="text-gray-500 text-xs mt-0.5"><?= $b[3] ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Contact direct -->
            <div class="glass rounded-2xl p-6" style="border-color:rgba(0,200,255,0.12)">
                <h3 class="font-heading font-semibold text-white mb-5"><?= t('devis.contact_dir') ?></h3>
                <div class="space-y-3">
                    <a href="https://wa.me/22788672115" target="_blank"
                       class="flex items-center gap-3 p-3 rounded-xl transition-all"
                       style="background:rgba(37,211,102,0.07);border:1px solid rgba(37,211,102,0.2)"
                       onmouseover="this.style.background='rgba(37,211,102,0.15)'"
                       onmouseout="this.style.background='rgba(37,211,102,0.07)'">
                        <i class="fab fa-whatsapp text-xl" style="color:#25d366"></i>
                        <div><p class="text-white text-sm font-medium">WhatsApp</p><p class="text-gray-500 text-xs">+227 88 67 21 15</p></div>
                    </a>
                    <a href="tel:+22788672115"
                       class="flex items-center gap-3 p-3 rounded-xl transition-all"
                       style="background:rgba(0,200,255,0.07);border:1px solid rgba(0,200,255,0.2)"
                       onmouseover="this.style.background='rgba(0,200,255,0.15)'"
                       onmouseout="this.style.background='rgba(0,200,255,0.07)'">
                        <i class="fas fa-phone text-xl" style="color:#00c8ff"></i>
                        <div><p class="text-white text-sm font-medium"><?= $_d('Téléphone','Phone') ?></p><p class="text-gray-500 text-xs">+227 88 67 21 15</p></div>
                    </a>
                    <a href="mailto:contact@netcrafterniger.com"
                       class="flex items-center gap-3 p-3 rounded-xl transition-all"
                       style="background:rgba(0,102,204,0.07);border:1px solid rgba(0,102,204,0.2)"
                       onmouseover="this.style.background='rgba(0,102,204,0.15)'"
                       onmouseout="this.style.background='rgba(0,102,204,0.07)'">
                        <i class="fas fa-envelope text-xl" style="color:#0066cc"></i>
                        <div><p class="text-white text-sm font-medium">Email</p><p class="text-gray-500 text-xs">contact@netcrafterniger.com</p></div>
                    </a>
                </div>
            </div>

            <!-- Témoignages -->
            <div class="glass rounded-2xl p-6" style="border-color:rgba(0,200,255,0.12)">
                <h3 class="font-heading font-semibold text-white mb-5"><?= t('devis.testimonials') ?></h3>
                <?php foreach ([
                    ['ML','Maria L.',
                     $_d('Directrice, Fashion Store','Director, Fashion Store'),
                     '#00c8ff',
                     $_d('Réactivité et professionnalisme. Notre site e-commerce livré dans les délais, exactement comme attendu.',
                         'Responsiveness and professionalism. Our e-commerce site delivered on time, exactly as expected.')],
                    ['KN','Kader N.',
                     $_d('Gérant, Restaurant Le Délice','Manager, Restaurant Le Délice'),
                     '#0066cc',
                     $_d("L'équipe a su comprendre mes besoins et y répondre parfaitement. Suivi exemplaire.",
                         'The team understood my needs and responded perfectly. Exemplary follow-up.')],
                ] as $tm): ?>
                <div class="p-4 rounded-xl mb-3" style="background:rgba(0,200,255,0.04);border:1px solid rgba(0,200,255,0.08)">
                    <p class="text-gray-400 text-sm italic mb-3">"<?= $tm[4] ?>"</p>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                             style="background:<?= $tm[3] ?>20;color:<?= $tm[3] ?>">
                            <?= $tm[0] ?>
                        </div>
                        <div>
                            <p class="text-white text-xs font-medium"><?= $tm[1] ?></p>
                            <p class="text-gray-600 text-xs"><?= $tm[2] ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-20" data-aos="fade-up">
    <h2 class="font-heading font-bold text-3xl text-center text-white mb-10"><?= t('devis.faq') ?></h2>
    <div class="space-y-3">
        <?php
        $faqs = [
            [
                $_d('Combien de temps faut-il pour recevoir un devis ?','How long does it take to receive a quote?'),
                $_d("Nous nous engageons à vous fournir un devis détaillé dans un délai de 24 à 48 heures ouvrables. Pour des projets complexes, ce délai peut s'étendre à 72 heures.",
                    'We commit to providing you with a detailed quote within 24 to 48 business hours. For complex projects, this may extend to 72 hours.'),
            ],
            [
                $_d('Comment se déroule le processus après validation du devis ?','What happens after the quote is approved?'),
                $_d('Après validation, nous organisons une réunion de cadrage pour définir précisément les attentes. Un planning et un chef de projet vous sont assignés.',
                    'After approval, we organise a scoping meeting to precisely define expectations. A project plan and dedicated manager are assigned to you.'),
            ],
            [
                $_d('Proposez-vous des facilités de paiement ?','Do you offer payment facilities?'),
                $_d("Oui : 30% d'acompte à la signature, paiements intermédiaires aux étapes clés, et solde de 20% à la livraison finale. Modalités adaptables selon les projets.",
                    'Yes: 30% deposit on signing, milestone payments at key stages, and 20% balance on final delivery. Terms adaptable per project.'),
            ],
            [
                $_d('Quelles garanties offrez-vous sur vos prestations ?','What guarantees do you offer on your services?'),
                $_d('3 mois de garantie de bon fonctionnement pour les développements web après mise en production. Garanties constructeur pour le matériel.',
                    '3-month warranty on web developments after go-live. Manufacturer warranties on hardware.'),
            ],
        ];
        foreach ($faqs as $idx => $faq):
        ?>
        <div class="glass rounded-xl overflow-hidden" style="border-color:rgba(0,200,255,0.1)">
            <button onclick="toggleFaq(<?= $idx ?>)"
                    class="w-full px-6 py-4 text-left flex items-center justify-between gap-4 hover:bg-white/5 transition-all">
                <span class="font-medium text-white"><?= $faq[0] ?></span>
                <i id="faq-icon-<?= $idx ?>" class="fas fa-chevron-down transition-transform flex-shrink-0" style="color:#00c8ff"></i>
            </button>
            <div id="faq-content-<?= $idx ?>" class="hidden px-6 pb-5">
                <p class="text-gray-400 text-sm leading-relaxed"><?= $faq[1] ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Floating WhatsApp -->
<a href="https://wa.me/22788672115" target="_blank"
   class="fixed bottom-6 right-6 z-50 w-14 h-14 rounded-full flex items-center justify-center hover:scale-110 transition-all"
   style="background:#25d366;box-shadow:0 0 25px rgba(37,211,102,0.4)">
    <i class="fab fa-whatsapp text-white text-2xl"></i>
</a>

<script>
function toggleFaq(idx) {
    const c = document.getElementById('faq-content-' + idx);
    const i = document.getElementById('faq-icon-' + idx);
    c.classList.toggle('hidden');
    i.style.transform = c.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
}

function toggleSvcCard(id, checked, color) {
    const card = document.getElementById('svc-' + id);
    card.style.borderColor = checked ? color + '66' : '';
    card.style.background  = checked ? color + '0a' : '';
}

document.getElementById('devisForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const msg = document.getElementById('formMessage');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= $_d('Envoi en cours...','Sending...') ?>';
    msg.className = 'glass rounded-xl p-4 text-sm mt-4';
    msg.style.color = '#00c8ff';
    msg.textContent = '<?= $_d('Envoi de votre demande...','Sending your request...') ?>';
    msg.classList.remove('hidden');

    fetch('submit-devis.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                msg.style.background = 'rgba(37,211,102,0.07)';
                msg.style.border     = '1px solid rgba(37,211,102,0.3)';
                msg.style.color      = '#25d366';
                msg.innerHTML = '<i class="fas fa-check-circle mr-2"></i><strong><?= $_d('Succès !','Success!') ?></strong> <?= $_d('Demande #','Request #') ?>' + data.devis_id + '<?= $_d(' envoyée. Nous vous contacterons très prochainement.',' sent. We will contact you shortly.') ?>'
                    + (data.whatsapp_url ? ' <a href="' + data.whatsapp_url + '" target="_blank" style="text-decoration:underline;margin-left:8px"><?= $_d('Continuer sur WhatsApp','Continue on WhatsApp') ?></a>' : '');
                this.reset();
            } else {
                msg.style.background = 'rgba(239,68,68,0.07)';
                msg.style.border     = '1px solid rgba(239,68,68,0.3)';
                msg.style.color      = '#f87171';
                msg.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + (data.message || '<?= $_d('Une erreur est survenue.','An error occurred.') ?>');
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> <?= t('devis.send_request') ?>';
            msg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        })
        .catch(err => {
            msg.style.background = 'rgba(239,68,68,0.07)';
            msg.style.border     = '1px solid rgba(239,68,68,0.3)';
            msg.style.color      = '#f87171';
            msg.textContent = '<?= $_d('Erreur réseau : ','Network error: ') ?>' + err.message;
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> <?= t('devis.send_request') ?>';
        });
});
</script>

<?php include 'includes/footer.php'; ?>
