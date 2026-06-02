<?php
$page_title = 'Netcrafter - Politique de confidentialité';
require_once __DIR__ . '/includes/header.php';
$_d = fn($fr, $en) => ($GLOBALS['nc_lang'] ?? 'fr') === 'en' ? $en : $fr;
?>

<style>
.legal-section { border-left: 2px solid rgba(0,200,255,0.3); padding-left: 1.5rem; margin-bottom: 2.5rem; }
.legal-section h2 { color: #00c8ff; font-size: 1.1rem; font-weight: 700; margin-bottom: .75rem; }
.legal-section p, .legal-section li { color: #9ca3af; line-height: 1.8; font-size: .95rem; }
.legal-section ul { list-style: disc; padding-left: 1.25rem; margin-top: .5rem; }
.legal-section li { margin-bottom: .4rem; }
.legal-badge { display: inline-block; padding: .2rem .75rem; border-radius: 999px; font-size: .75rem;
               background: rgba(0,200,255,.1); border: 1px solid rgba(0,200,255,.25); color: #00c8ff; margin-bottom: 1.5rem; }
</style>

<!-- Hero -->
<section class="relative pt-32 pb-16 overflow-hidden">
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-20 left-1/2 -translate-x-1/2 w-[600px] h-[300px] rounded-full opacity-10"
             style="background:radial-gradient(ellipse,#00c8ff 0%,transparent 70%);filter:blur(60px)"></div>
    </div>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 relative">
        <span class="legal-badge"><i class="fas fa-shield-alt mr-1"></i><?= $_d('Politique de confidentialité','Privacy Policy') ?></span>
        <h1 class="text-4xl font-heading font-bold text-white mb-4">
            <?= $_d('Vos données, notre responsabilité','Your data, our responsibility') ?>
        </h1>
        <p class="text-gray-400">
            <?= $_d('Dernière mise à jour : mai 2026','Last updated: May 2026') ?>
        </p>
    </div>
</section>

<!-- Content -->
<section class="pb-24">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">

        <div class="legal-section">
            <h2>1. <?= $_d('Responsable du traitement','Data Controller') ?></h2>
            <p><?= $_d(
                'Le responsable du traitement des données personnelles collectées sur ce site est <strong class="text-white">Netcrafter</strong>, agence digitale sise à Niamey, Niger. Contact : <a href="mailto:contact@netcrafterniger.com" class="text-nc-cyan hover:underline">contact@netcrafterniger.com</a>.',
                'The data controller for personal data collected on this site is <strong class="text-white">Netcrafter</strong>, a digital agency located in Niamey, Niger. Contact: <a href="mailto:contact@netcrafterniger.com" class="text-nc-cyan hover:underline">contact@netcrafterniger.com</a>.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>2. <?= $_d('Données collectées','Data Collected') ?></h2>
            <p><?= $_d('Nous collectons les informations suivantes :','We collect the following information:') ?></p>
            <ul>
                <li><?= $_d('Données d\'identification (nom, prénom, e-mail) lors d\'un contact ou d\'une commande','Identification data (name, email) when contacting us or placing an order') ?></li>
                <li><?= $_d('Données de navigation (adresse IP, pages visitées, durée de visite) via des cookies analytiques','Browsing data (IP address, pages visited, visit duration) via analytics cookies') ?></li>
                <li><?= $_d('Informations de paiement traitées exclusivement par notre prestataire sécurisé — nous ne stockons aucune donnée bancaire','Payment information processed exclusively by our secure payment provider — we store no banking data') ?></li>
                <li><?= $_d('Données de formations : nom, e-mail, progression, certifications obtenues','Training data: name, email, progress, certificates obtained') ?></li>
            </ul>
        </div>

        <div class="legal-section">
            <h2>3. <?= $_d('Finalités du traitement','Purposes of Processing') ?></h2>
            <ul>
                <li><?= $_d('Traitement et suivi des commandes','Order processing and tracking') ?></li>
                <li><?= $_d('Réponse aux demandes de devis et de contact','Responding to quote requests and inquiries') ?></li>
                <li><?= $_d('Gestion des formations et délivrance de certificats','Training management and certificate issuance') ?></li>
                <li><?= $_d('Amélioration de nos services et de l\'expérience utilisateur','Improving our services and user experience') ?></li>
                <li><?= $_d('Envoi de newsletters (uniquement avec votre consentement)','Sending newsletters (only with your consent)') ?></li>
            </ul>
        </div>

        <div class="legal-section">
            <h2>4. <?= $_d('Cookies','Cookies') ?></h2>
            <p><?= $_d(
                'Ce site utilise des cookies fonctionnels (nécessaires au fonctionnement) et des cookies analytiques (pour mesurer l\'audience). Vous pouvez accepter ou refuser les cookies non essentiels via la bannière de consentement affichée lors de votre première visite.',
                'This site uses functional cookies (necessary for operation) and analytics cookies (to measure audience). You can accept or refuse non-essential cookies via the consent banner displayed on your first visit.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>5. <?= $_d('Conservation des données','Data Retention') ?></h2>
            <p><?= $_d(
                'Les données clients sont conservées pendant 3 ans à compter du dernier achat. Les données de prospects (devis, contact) sont conservées 1 an. Les logs de navigation sont anonymisés après 13 mois.',
                'Customer data is retained for 3 years from the last purchase. Prospect data (quotes, contact) is retained for 1 year. Browsing logs are anonymized after 13 months.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>6. <?= $_d('Partage des données','Data Sharing') ?></h2>
            <p><?= $_d(
                'Nous ne vendons ni ne louons vos données à des tiers. Nous pouvons partager vos données avec des sous-traitants (hébergeur, prestataire de paiement) dans le strict cadre de l\'exécution de nos services et sous contrat de confidentialité.',
                'We do not sell or rent your data to third parties. We may share your data with subcontractors (hosting provider, payment provider) strictly within the scope of service delivery and under confidentiality agreements.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>7. <?= $_d('Vos droits','Your Rights') ?></h2>
            <p><?= $_d('Conformément à la réglementation applicable, vous disposez des droits suivants :','Under applicable regulations, you have the following rights:') ?></p>
            <ul>
                <li><?= $_d('Droit d\'accès à vos données personnelles','Right of access to your personal data') ?></li>
                <li><?= $_d('Droit de rectification des données inexactes','Right to rectification of inaccurate data') ?></li>
                <li><?= $_d('Droit à l\'effacement (droit à l\'oubli)','Right to erasure (right to be forgotten)') ?></li>
                <li><?= $_d('Droit à la portabilité de vos données','Right to data portability') ?></li>
                <li><?= $_d('Droit d\'opposition au traitement','Right to object to processing') ?></li>
            </ul>
            <p class="mt-3"><?= $_d(
                'Pour exercer ces droits, contactez-nous à : <a href="mailto:contact@netcrafterniger.com" class="text-nc-cyan hover:underline">contact@netcrafterniger.com</a>',
                'To exercise these rights, contact us at: <a href="mailto:contact@netcrafterniger.com" class="text-nc-cyan hover:underline">contact@netcrafterniger.com</a>'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>8. <?= $_d('Sécurité','Security') ?></h2>
            <p><?= $_d(
                'Nous mettons en œuvre des mesures techniques et organisationnelles appropriées pour protéger vos données contre tout accès non autorisé, perte ou destruction : connexions HTTPS, chiffrement des mots de passe, accès restreint aux données.',
                'We implement appropriate technical and organizational measures to protect your data against unauthorized access, loss or destruction: HTTPS connections, password encryption, restricted data access.'
            ) ?></p>
        </div>

        <!-- CTA -->
        <div class="mt-12 p-6 rounded-2xl text-center"
             style="background:rgba(0,200,255,0.05);border:1px solid rgba(0,200,255,0.15)">
            <p class="text-gray-400 mb-4"><?= $_d('Des questions sur vos données ?','Questions about your data?') ?></p>
            <a href="<?= BASE ?>/devis.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-sm text-white transition-all hover:scale-105"
               style="background:linear-gradient(135deg,#00c8ff,#0066cc)">
                <i class="fas fa-envelope"></i> <?= $_d('Nous contacter','Contact us') ?>
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
