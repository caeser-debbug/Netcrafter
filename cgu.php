<?php
$page_title = 'Netcrafter - Conditions Générales d\'Utilisation';
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
             style="background:radial-gradient(ellipse,#0066cc 0%,transparent 70%);filter:blur(60px)"></div>
    </div>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 relative">
        <span class="legal-badge"><i class="fas fa-file-contract mr-1"></i><?= $_d('Conditions Générales d\'Utilisation','Terms of Service') ?></span>
        <h1 class="text-4xl font-heading font-bold text-white mb-4">
            <?= $_d('CGU — Règles d\'utilisation de nos services','ToS — Rules for using our services') ?>
        </h1>
        <p class="text-gray-400">
            <?= $_d('Dernière mise à jour : mai 2026 · Veuillez lire attentivement ces conditions avant d\'utiliser nos services.','Last updated: May 2026 · Please read these terms carefully before using our services.') ?>
        </p>
    </div>
</section>

<!-- Content -->
<section class="pb-24">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">

        <div class="legal-section">
            <h2>1. <?= $_d('Présentation','Presentation') ?></h2>
            <p><?= $_d(
                'Le site <strong class="text-white">netcrafterniger.com</strong> est édité par <strong class="text-white">Netcrafter</strong>, agence digitale basée à Niamey, Niger, spécialisée dans le développement web, le marketing digital, la sécurité informatique et les formations professionnelles.',
                'The website <strong class="text-white">netcrafterniger.com</strong> is published by <strong class="text-white">Netcrafter</strong>, a digital agency based in Niamey, Niger, specializing in web development, digital marketing, cybersecurity, and professional training.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>2. <?= $_d('Acceptation des conditions','Acceptance of Terms') ?></h2>
            <p><?= $_d(
                'L\'utilisation de ce site implique l\'acceptation pleine et entière des présentes conditions générales d\'utilisation. Si vous n\'acceptez pas ces conditions, veuillez ne pas utiliser ce site.',
                'Use of this website implies full acceptance of these terms of service. If you do not accept these terms, please do not use this site.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>3. <?= $_d('Services proposés','Services Offered') ?></h2>
            <p><?= $_d('Netcrafter propose les services suivants via ce site :','Netcrafter offers the following services through this site:') ?></p>
            <ul>
                <li><?= $_d('Développement de sites web, applications et logiciels sur mesure','Custom website, application and software development') ?></li>
                <li><?= $_d('Boutique en ligne de produits informatiques et électroniques','Online store for IT and electronic products') ?></li>
                <li><?= $_d('Formations professionnelles en ligne (développement, sécurité, marketing)','Online professional training (development, security, marketing)') ?></li>
                <li><?= $_d('Outils en ligne gratuits (configurateur, audit, palette, prévisualisation SEO)','Free online tools (configurator, audit, palette, SEO preview)') ?></li>
                <li><?= $_d('Demande de devis pour des projets sur mesure','Quote requests for custom projects') ?></li>
            </ul>
        </div>

        <div class="legal-section">
            <h2>4. <?= $_d('Commandes et paiements','Orders and Payments') ?></h2>
            <p><?= $_d(
                'Toute commande passée sur la boutique implique l\'acceptation du prix et de la description du produit. Le paiement est dû à la commande. Nous nous réservons le droit de refuser ou d\'annuler toute commande en cas de problème de disponibilité ou de suspicion de fraude.',
                'Any order placed in the store implies acceptance of the price and product description. Payment is due at the time of order. We reserve the right to refuse or cancel any order in case of availability issues or suspected fraud.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>5. <?= $_d('Formations en ligne','Online Training') ?></h2>
            <p><?= $_d(
                'L\'accès aux formations est personnel et non transférable. Toute tentative de partage d\'accès entraîne la résiliation immédiate de l\'abonnement sans remboursement. Les certifications délivrées sont propres à Netcrafter et n\'engagent pas une institution officielle.',
                'Access to training is personal and non-transferable. Any attempt to share access will result in immediate account termination without refund. Certificates issued are specific to Netcrafter and do not imply accreditation by any official institution.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>6. <?= $_d('Propriété intellectuelle','Intellectual Property') ?></h2>
            <p><?= $_d(
                'L\'ensemble du contenu de ce site (textes, images, vidéos, logos, code source) est la propriété exclusive de Netcrafter ou de ses partenaires et est protégé par le droit d\'auteur. Toute reproduction, même partielle, est interdite sans autorisation écrite préalable.',
                'All content on this site (texts, images, videos, logos, source code) is the exclusive property of Netcrafter or its partners and is protected by copyright. Any reproduction, even partial, is prohibited without prior written authorization.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>7. <?= $_d('Responsabilité','Liability') ?></h2>
            <p><?= $_d(
                'Netcrafter s\'efforce d\'assurer l\'exactitude des informations publiées mais ne saurait être tenu responsable des erreurs, omissions ou résultats obtenus par un mauvais usage des informations. Les outils en ligne (audit, configurateur) sont fournis à titre indicatif.',
                'Netcrafter strives to ensure the accuracy of published information but cannot be held responsible for errors, omissions or results obtained from misuse of information. Online tools (audit, configurator) are provided for informational purposes only.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>8. <?= $_d('Liens hypertextes','Hyperlinks') ?></h2>
            <p><?= $_d(
                'Ce site peut contenir des liens vers des sites tiers. Netcrafter n\'exerce aucun contrôle sur ces sites et décline toute responsabilité quant à leur contenu ou leur politique de confidentialité.',
                'This site may contain links to third-party sites. Netcrafter has no control over these sites and accepts no responsibility for their content or privacy policies.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>9. <?= $_d('Droit applicable','Applicable Law') ?></h2>
            <p><?= $_d(
                'Les présentes CGU sont soumises au droit nigérien. Tout litige relatif à leur interprétation ou exécution relève de la compétence des tribunaux de Niamey, Niger.',
                'These Terms of Service are governed by Nigerian law. Any dispute relating to their interpretation or execution shall fall under the jurisdiction of the courts of Niamey, Niger.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2>10. <?= $_d('Modifications','Modifications') ?></h2>
            <p><?= $_d(
                'Netcrafter se réserve le droit de modifier ces CGU à tout moment. Les utilisateurs seront informés des changements via une notification sur le site. La poursuite de l\'utilisation du site après modification vaut acceptation des nouvelles conditions.',
                'Netcrafter reserves the right to modify these Terms at any time. Users will be notified of changes via a notification on the site. Continued use of the site after modification constitutes acceptance of the new terms.'
            ) ?></p>
        </div>

        <!-- CTA -->
        <div class="mt-12 p-6 rounded-2xl text-center"
             style="background:rgba(0,102,204,0.08);border:1px solid rgba(0,102,204,0.2)">
            <p class="text-gray-400 mb-4"><?= $_d('Une question sur nos conditions ?','Questions about our terms?') ?></p>
            <a href="<?= BASE ?>/devis.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-sm text-white transition-all hover:scale-105"
               style="background:linear-gradient(135deg,#00c8ff,#0066cc)">
                <i class="fas fa-envelope"></i> <?= $_d('Nous contacter','Contact us') ?>
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
