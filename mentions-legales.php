<?php
$page_title = 'Netcrafter - Mentions Légales';
require_once __DIR__ . '/includes/header.php';
$_d = fn($fr, $en) => ($GLOBALS['nc_lang'] ?? 'fr') === 'en' ? $en : $fr;
?>

<style>
.legal-section { border-left: 2px solid rgba(0,200,255,0.3); padding-left: 1.5rem; margin-bottom: 2.5rem; }
.legal-section h2 { color: #00c8ff; font-size: 1.1rem; font-weight: 700; margin-bottom: .75rem; }
.legal-section p, .legal-section li { color: #9ca3af; line-height: 1.8; font-size: .95rem; }
.legal-section ul { list-style: none; padding: 0; margin-top: .5rem; }
.legal-section ul li { display: flex; gap: .5rem; margin-bottom: .5rem; }
.legal-section ul li .lbl { color: #6b7280; min-width: 130px; flex-shrink: 0; }
.legal-section ul li .val { color: #d1d5db; }
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
        <span class="legal-badge"><i class="fas fa-balance-scale mr-1"></i><?= $_d('Mentions légales','Legal Notice') ?></span>
        <h1 class="text-4xl font-heading font-bold text-white mb-4">
            <?= $_d('Informations légales','Legal Information') ?>
        </h1>
        <p class="text-gray-400">
            <?= $_d('Conformément aux dispositions légales en vigueur.','In accordance with applicable legal provisions.') ?>
        </p>
    </div>
</section>

<!-- Content -->
<section class="pb-24">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">

        <div class="legal-section">
            <h2><?= $_d('Éditeur du site','Site Publisher') ?></h2>
            <ul>
                <li><span class="lbl"><?= $_d('Nom','Name') ?></span><span class="val">Netcrafter</span></li>
                <li><span class="lbl"><?= $_d('Forme','Type') ?></span><span class="val"><?= $_d('Agence digitale','Digital Agency') ?></span></li>
                <li><span class="lbl"><?= $_d('Siège social','Registered office') ?></span><span class="val">Niamey, Niger</span></li>
                <li><span class="lbl"><?= $_d('Téléphone','Phone') ?></span><span class="val"><a href="tel:+22788672115" class="hover:text-nc-cyan transition-colors">+227 88 67 21 15</a></span></li>
                <li><span class="lbl">Email</span><span class="val"><a href="mailto:contact@netcrafterniger.com" class="hover:text-nc-cyan transition-colors">contact@netcrafterniger.com</a></span></li>
                <li><span class="lbl">WhatsApp</span><span class="val"><a href="https://wa.me/22788672115" target="_blank" class="hover:text-nc-cyan transition-colors">+227 88 67 21 15</a></span></li>
            </ul>
        </div>

        <div class="legal-section">
            <h2><?= $_d('Directeur de la publication','Publication Director') ?></h2>
            <p><?= $_d(
                'Le directeur de la publication est le responsable légal de l\'agence Netcrafter.',
                'The publication director is the legal representative of the Netcrafter agency.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2><?= $_d('Hébergement','Hosting') ?></h2>
            <ul>
                <li><span class="lbl"><?= $_d('Hébergeur','Host') ?></span><span class="val">Hostinger International Ltd</span></li>
                <li><span class="lbl"><?= $_d('Adresse','Address') ?></span><span class="val">61 Lordou Vironos Street, 6023 Larnaca, Cyprus</span></li>
                <li><span class="lbl">Site web</span><span class="val"><a href="https://www.hostinger.com" target="_blank" rel="noopener" class="hover:text-nc-cyan transition-colors">www.hostinger.com</a></span></li>
            </ul>
        </div>

        <div class="legal-section">
            <h2><?= $_d('Propriété intellectuelle','Intellectual Property') ?></h2>
            <p><?= $_d(
                'L\'ensemble des contenus présents sur le site netcrafterniger.com (textes, images, graphismes, logo, icônes, sons, logiciels) est la propriété exclusive de Netcrafter, à l\'exception des marques, logos ou contenus appartenant à d\'autres sociétés partenaires ou auteurs.',
                'All content on the netcrafterniger.com website (texts, images, graphics, logo, icons, sounds, software) is the exclusive property of Netcrafter, except for trademarks, logos or content belonging to other partner companies or authors.'
            ) ?></p>
            <p class="mt-3"><?= $_d(
                'Toute reproduction, représentation, modification, publication, adaptation de tout ou partie des éléments du site, quel que soit le moyen ou le procédé utilisé, est interdite, sauf autorisation écrite préalable de Netcrafter.',
                'Any reproduction, representation, modification, publication, adaptation of all or part of the site elements, regardless of the means or process used, is prohibited without prior written authorization from Netcrafter.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2><?= $_d('Limitation de responsabilité','Limitation of Liability') ?></h2>
            <p><?= $_d(
                'Netcrafter ne pourra être tenu responsable des dommages directs et indirects causés au matériel de l\'utilisateur lors de l\'accès au site, résultant soit de l\'utilisation d\'un matériel ne répondant pas aux spécifications, soit de l\'apparition d\'un bug ou d\'une incompatibilité.',
                'Netcrafter cannot be held responsible for direct or indirect damage caused to the user\'s equipment when accessing the site, resulting from the use of equipment that does not meet specifications, or the appearance of a bug or incompatibility.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2><?= $_d('Liens hypertextes et cookies','Hyperlinks and Cookies') ?></h2>
            <p><?= $_d(
                'Le site netcrafterniger.com contient des liens hypertextes vers d\'autres sites. Netcrafter n\'a pas la possibilité de vérifier le contenu de ces sites, et n\'assumera en conséquence aucune responsabilité de ce fait. La navigation sur le site est susceptible de provoquer l\'installation de cookie(s) sur l\'ordinateur de l\'utilisateur. Plus d\'informations dans notre <a href="' . BASE . '/confidentialite.php" class="text-nc-cyan hover:underline">Politique de confidentialité</a>.',
                'The netcrafterniger.com website contains hyperlinks to other sites. Netcrafter has no ability to verify the content of these sites and will assume no responsibility. Navigation on the site may result in the installation of cookie(s) on the user\'s computer. More information in our <a href="' . BASE . '/confidentialite.php" class="text-nc-cyan hover:underline">Privacy Policy</a>.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2><?= $_d('Droit applicable et attribution de juridiction','Applicable Law and Jurisdiction') ?></h2>
            <p><?= $_d(
                'Tout litige en relation avec l\'utilisation du site netcrafterniger.com est soumis au droit nigérien. En dehors des cas où la loi ne le permet pas, il est fait attribution exclusive de juridiction aux tribunaux compétents de Niamey.',
                'Any dispute relating to the use of the netcrafterniger.com website is subject to Nigerian law. Except where the law does not permit, exclusive jurisdiction is attributed to the competent courts of Niamey.'
            ) ?></p>
        </div>

        <div class="legal-section">
            <h2><?= $_d('Contact','Contact') ?></h2>
            <p><?= $_d(
                'Pour toute question relative aux présentes mentions légales, vous pouvez nous contacter à l\'adresse : <a href="mailto:contact@netcrafterniger.com" class="text-nc-cyan hover:underline">contact@netcrafterniger.com</a>',
                'For any questions regarding these legal notices, you can contact us at: <a href="mailto:contact@netcrafterniger.com" class="text-nc-cyan hover:underline">contact@netcrafterniger.com</a>'
            ) ?></p>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
