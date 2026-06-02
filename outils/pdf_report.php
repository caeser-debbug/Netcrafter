<?php
// outils/pdf_report.php — PDF Report Generator (Audit & Pentest)

$type = trim($_GET['type'] ?? '');
if (!in_array($type, ['audit', 'pentest'], true) || empty($_POST['data'])) {
    http_response_code(400); echo 'Paramètres invalides'; exit;
}

$data = json_decode($_POST['data'] ?? '', true);
if (!is_array($data)) { http_response_code(400); echo 'Données JSON invalides'; exit; }

$tcpdf_path = __DIR__ . '/../certificates/vendor/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdf_path)) { http_response_code(500); echo 'Bibliothèque PDF introuvable'; exit; }

require_once $tcpdf_path;

$date = date('d/m/Y à H:i');

// ── Helpers ───────────────────────────────────────────────────────────────────
function sevColor(string $s): string {
    return match($s) { 'red' => '#dc2626', 'orange' => '#ea580c', 'yellow' => '#ca8a04', default => '#2563eb' };
}
function sevBg(string $s): string {
    return match($s) { 'red' => '#fee2e2', 'orange' => '#fed7aa', 'yellow' => '#fef9c3', default => '#dbeafe' };
}
function sevLabel(string $s): string {
    return match($s) { 'red' => 'Critique', 'orange' => 'Avertissement', 'yellow' => 'Conseil', default => 'Info' };
}
function scoreColor(int $s): string {
    return $s >= 80 ? '#16a34a' : ($s >= 60 ? '#d97706' : ($s >= 40 ? '#ea580c' : '#dc2626'));
}
function scoreLabel(int $s): string {
    return $s >= 90 ? 'Excellent' : ($s >= 75 ? 'Bon' : ($s >= 55 ? 'Moyen' : ($s >= 35 ? 'Faible' : 'Critique')));
}
function catScore(array $cat_issues, string $cat): int {
    $ded = 0;
    foreach ($cat_issues[$cat] ?? [] as $i)
        $ded += ($i[1] === 'red' ? 10 : ($i[1] === 'orange' ? 5 : 2));
    return max(0, min(100, 100 - $ded));
}

function buildPdf(string $title, string $filename, string $htmlBody): void {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Netcrafter');
    $pdf->SetAuthor('Netcrafter — netcrafterniger.com');
    $pdf->SetTitle($title);
    $pdf->SetSubject($title);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->AddPage();

    $css = '
<style>
body  { font-family: helvetica; font-size: 10pt; color: #1e293b; }
h1    { font-size: 16pt; font-weight: bold; color: #1e3a8a; margin: 0 0 4px; }
h2    { font-size: 11pt; font-weight: bold; color: #1e3a8a; margin: 12px 0 5px; }
h3    { font-size: 10pt; font-weight: bold; color: #374151; margin: 8px 0 3px; }
p     { font-size: 9.5pt; color: #374151; margin: 2px 0; }
small { font-size: 8pt; color: #64748b; }
table { width: 100%; border-collapse: collapse; font-size: 9pt; margin: 4px 0 8px; }
th    { background-color: #f1f5f9; padding: 5px 8px; text-align: left; font-weight: bold; color: #374151; border-bottom: 1px solid #cbd5e1; }
td    { padding: 4px 8px; color: #374151; border-bottom: 1px solid #f1f5f9; }
hr    { border: none; border-top: 1px solid #e2e8f0; margin: 8px 0; }
</style>';

    $pdf->writeHTML($css . $htmlBody, true, false, true, false, '');
    $pdf->Output($filename, 'D');
}

// ── AUDIT REPORT ──────────────────────────────────────────────────────────────
if ($type === 'audit') {
    $d     = $data;
    $score = (int)($d['score'] ?? 0);
    $url   = htmlspecialchars($d['raw_url'] ?? '—');
    $ptitle = htmlspecialchars($d['title']   ?? '(Sans titre)');
    $sc    = scoreColor($score);
    $sl    = scoreLabel($score);
    $cat_issues = $d['cat_issues'] ?? [];
    $issues     = $d['issues']     ?? [];

    // Sort issues by severity
    $order = ['red' => 0, 'orange' => 1, 'yellow' => 2];
    usort($issues, fn($a, $b) => ($order[$a[1]] ?? 9) - ($order[$b[1]] ?? 9));

    $counts = ['red' => 0, 'orange' => 0, 'yellow' => 0];
    foreach ($issues as $i) if (isset($counts[$i[1]])) $counts[$i[1]]++;

    $html  = '';

    // ── Header banner ──
    $html .= '<table style="margin-bottom:8px"><tr>';
    $html .= '<td style="background-color:#1e3a8a;padding:10px 14px">';
    $html .= '<span style="font-size:15pt;font-weight:bold;color:#ffffff">NETCRAFTER</span>';
    $html .= '<span style="font-size:8pt;color:#93c5fd"> &nbsp;·&nbsp; Rapport d\'Audit SEO &amp; Performance</span>';
    $html .= '</td></tr></table>';

    // ── Meta info ──
    $html .= '<p><b>URL :</b> <span style="color:#2563eb">' . $url . '</span></p>';
    $html .= '<p><b>Titre :</b> ' . $ptitle . '</p>';
    $html .= '<p><small>Rapport généré le ' . $date . ' par Netcrafter — netcrafterniger.com</small></p>';
    $html .= '<hr>';

    // ── Score global ──
    $html .= '<h2>Score Global</h2>';
    $html .= '<p><span style="font-size:26pt;font-weight:bold;color:' . $sc . '">' . $score . '</span>';
    $html .= '<span style="font-size:12pt;color:#64748b"> /100 — ' . $sl . '</span></p>';

    // ── Category scores ──
    $html .= '<table style="margin-top:8px"><tr>';
    foreach ([
        ['SEO',           catScore($cat_issues, 'seo')],
        ['Technique',     catScore($cat_issues, 'tech')],
        ['Social',        catScore($cat_issues, 'social')],
        ['Performance',   catScore($cat_issues, 'perf')],
        ['Accessibilité', catScore($cat_issues, 'access')],
    ] as [$lbl, $cs]) {
        $col = scoreColor($cs);
        $html .= '<td style="text-align:center;padding:7px 4px;background-color:#f8fafc;border:1px solid #e2e8f0">';
        $html .= '<span style="font-size:14pt;font-weight:bold;color:' . $col . '">' . $cs . '</span><br>';
        $html .= '<span style="font-size:7.5pt;color:#64748b">' . $lbl . '</span>';
        $html .= '</td>';
    }
    $html .= '</tr></table><hr>';

    // ── Métriques clés ──
    $html .= '<h2>Métriques Clés</h2>';
    $html .= '<table><tr><th>Indicateur</th><th>Valeur</th><th>Statut</th></tr>';
    $metrics = [
        ['Temps de chargement', ($d['load_time_ms'] ?? 0) . ' ms',  ($d['load_time_ms'] ?? 0) < 2000  ? ['✓ Bon',          '#16a34a'] : ['✗ Lent',       '#dc2626']],
        ['Taille de la page',   ($d['page_size_kb'] ?? 0) . ' KB',  ($d['page_size_kb'] ?? 0) < 1000  ? ['✓ OK',           '#16a34a'] : ['⚠ Lourd',      '#ea580c']],
        ['Nombre de mots',      ($d['word_count']   ?? 0),           ($d['word_count']   ?? 0) >= 300  ? ['✓ OK',           '#16a34a'] : ['⚠ Insuffisant','#ea580c']],
        ['HTTPS',               ($d['is_https']     ?? false) ? 'Oui':'Non', ($d['is_https'] ?? false) ? ['✓ Sécurisé',    '#16a34a'] : ['✗ Non sécurisé','#dc2626']],
        ['Statut HTTP',         $d['http_status']   ?? 0,           ($d['http_status']   ?? 0) < 300  ? ['✓ OK',           '#16a34a'] : ['✗ Erreur',     '#dc2626']],
        ['Meta description',    ($d['meta_desc_len']?? 0) . ' car.',($d['meta_desc_len'] ?? 0) >= 100 ? ['✓ Correcte',     '#16a34a'] : ['⚠ Problème',   '#ea580c']],
        ['Balise H1',           ($d['h1_count']     ?? 0) . ' balise(s)', ($d['h1_count'] ?? 0) === 1 ? ['✓ Unique',       '#16a34a'] : ['✗ Problème',   '#dc2626']],
        ['Images sans alt',     ($d['imgs_no_alt']  ?? 0) . '/' . ($d['total_imgs'] ?? 0), ($d['imgs_no_alt'] ?? 0) === 0 ? ['✓ OK', '#16a34a'] : ['✗ Manquants', '#dc2626']],
        ['robots.txt',          ($d['robots_ok']    ?? false) ? '✓ Trouvé':'✗ Absent', ($d['robots_ok'] ?? false) ? ['✓', '#16a34a'] : ['✗', '#dc2626']],
        ['Sitemap XML',         ($d['sitemap_ok']   ?? false) ? '✓ Trouvé':'✗ Absent', ($d['sitemap_ok'] ?? false) ? ['✓', '#16a34a'] : ['✗', '#dc2626']],
    ];
    foreach ($metrics as [$lbl, $val, $stat]) {
        $html .= '<tr><td>' . $lbl . '</td>';
        $html .= '<td><b>' . $val . '</b></td>';
        $html .= '<td style="color:' . $stat[1] . ';font-weight:bold">' . $stat[0] . '</td></tr>';
    }
    $html .= '</table><hr>';

    // ── Résumé sévérités ──
    $html .= '<h2>Résumé des Problèmes</h2>';
    $html .= '<table><tr>';
    $html .= '<td style="text-align:center;background-color:#fee2e2;padding:8px;border:1px solid #fca5a5"><span style="font-size:16pt;font-weight:bold;color:#dc2626">' . $counts['red'] . '</span><br><span style="font-size:8pt;color:#991b1b">Critiques</span></td>';
    $html .= '<td style="text-align:center;background-color:#fed7aa;padding:8px;border:1px solid #fdba74"><span style="font-size:16pt;font-weight:bold;color:#ea580c">' . $counts['orange'] . '</span><br><span style="font-size:8pt;color:#9a3412">Avertissements</span></td>';
    $html .= '<td style="text-align:center;background-color:#fef9c3;padding:8px;border:1px solid #fde047"><span style="font-size:16pt;font-weight:bold;color:#ca8a04">' . ($counts['yellow'] ?? 0) . '</span><br><span style="font-size:8pt;color:#854d0e">Conseils</span></td>';
    $total_issues = array_sum($counts);
    $html .= '<td style="text-align:center;background-color:#f1f5f9;padding:8px;border:1px solid #cbd5e1"><span style="font-size:16pt;font-weight:bold;color:#334155">' . $total_issues . '</span><br><span style="font-size:8pt;color:#64748b">Total</span></td>';
    $html .= '</tr></table><hr>';

    // ── Problèmes détaillés ──
    if (!empty($issues)) {
        $html .= '<h2>Problèmes Détaillés (' . count($issues) . ')</h2>';
        $html .= '<table><tr><th>Catégorie</th><th>Sévérité</th><th>Description</th></tr>';
        foreach ($issues as $iss) {
            $sev = $iss[1] ?? 'yellow';
            $cat = strtoupper($iss[3] ?? '');
            $desc= htmlspecialchars($iss[0] ?? '');
            $html .= '<tr>';
            $html .= '<td style="font-size:8pt;font-weight:bold;color:#475569;white-space:nowrap">' . $cat . '</td>';
            $html .= '<td style="white-space:nowrap"><span style="background-color:' . sevBg($sev) . ';color:' . sevColor($sev) . ';padding:1px 5px;font-size:7.5pt;font-weight:bold">' . sevLabel($sev) . '</span></td>';
            $html .= '<td>' . $desc . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
    } else {
        $html .= '<h2>Problèmes Détaillés</h2>';
        $html .= '<p style="color:#16a34a;font-weight:bold">✓ Aucun problème détecté — excellent résultat !</p>';
    }

    // ── Footer ──
    $html .= '<hr>';
    $html .= '<p><small>Ce rapport est fourni à titre indicatif. Les résultats peuvent varier selon la disponibilité et la configuration du serveur cible.</small></p>';
    $html .= '<p><small><b>Netcrafter</b> — netcrafterniger.com &nbsp;·&nbsp; +227 88 67 21 15 &nbsp;·&nbsp; Niamey, Niger</small></p>';

    buildPdf('Rapport Audit SEO — ' . ($d['raw_url'] ?? ''), 'netcrafter-audit-' . date('Ymd-His') . '.pdf', $html);
    exit;
}

// ── PENTEST REPORT ────────────────────────────────────────────────────────────
if ($type === 'pentest') {
    $d       = $data;
    $score   = (int)($d['score']  ?? 0);
    $host    = htmlspecialchars($d['host']   ?? '—');
    $server  = htmlspecialchars($d['server'] ?? '—');
    $powered = htmlspecialchars($d['powered']?? '—');
    $code    = (int)($d['code']   ?? 0);
    $is_https= (bool)($d['https'] ?? false);
    $issues  = $d['issues'] ?? [];
    $sc      = scoreColor($score);
    $sl      = scoreLabel($score);

    $counts = ['red' => 0, 'orange' => 0, 'yellow' => 0, 'blue' => 0];
    foreach ($issues as $i) if (isset($counts[$i[1]])) $counts[$i[1]]++;

    $groups = [
        'tls'     => 'Transport TLS / HTTPS',
        'headers' => 'En-têtes de sécurité',
        'info'    => "Divulgation d'informations",
        'files'   => 'Fichiers exposés',
        'methods' => 'Méthodes HTTP dangereuses',
        'cookies' => 'Sécurité des cookies',
        'config'  => 'Configuration serveur',
    ];

    $grouped = [];
    foreach ($issues as $iss) {
        $g = $iss[3] ?? 'config';
        $grouped[$g][] = $iss;
    }

    $html = '';

    // ── Header banner ──
    $html .= '<table style="margin-bottom:8px"><tr>';
    $html .= '<td style="background-color:#1e3a8a;padding:10px 14px">';
    $html .= '<span style="font-size:15pt;font-weight:bold;color:#ffffff">NETCRAFTER</span>';
    $html .= '<span style="font-size:8pt;color:#93c5fd"> &nbsp;·&nbsp; Rapport d\'Audit Sécurité</span>';
    $html .= '</td></tr></table>';

    $html .= '<p><b>Hôte analysé :</b> <span style="color:#2563eb">' . $host . '</span></p>';
    $html .= '<p><small>Rapport généré le ' . $date . ' par Netcrafter — netcrafterniger.com</small></p>';
    $html .= '<hr>';

    // ── Score ──
    $html .= '<h2>Score de Sécurité</h2>';
    $html .= '<p><span style="font-size:26pt;font-weight:bold;color:' . $sc . '">' . $score . '</span>';
    $html .= '<span style="font-size:12pt;color:#64748b"> /100 — ' . $sl . '</span></p>';

    // ── Infos serveur ──
    $html .= '<h2>Informations Serveur</h2>';
    $html .= '<table><tr><th>Paramètre</th><th>Valeur</th></tr>';
    $html .= '<tr><td>HTTPS</td><td style="color:' . ($is_https ? '#16a34a' : '#dc2626') . ';font-weight:bold">' . ($is_https ? '✓ Oui' : '✗ Non') . '</td></tr>';
    $html .= '<tr><td>Serveur</td><td><b>' . $server . '</b></td></tr>';
    $html .= '<tr><td>Technologie</td><td><b>' . $powered . '</b></td></tr>';
    $html .= '<tr><td>Code HTTP</td><td><b>' . $code . '</b></td></tr>';
    $html .= '<tr><td>Problèmes détectés</td><td><b>' . count($issues) . '</b></td></tr>';
    $html .= '</table><hr>';

    // ── Résumé ──
    $html .= '<h2>Résumé</h2>';
    $html .= '<table><tr>';
    $html .= '<td style="text-align:center;background-color:#fee2e2;padding:8px;border:1px solid #fca5a5"><span style="font-size:16pt;font-weight:bold;color:#dc2626">' . $counts['red'] . '</span><br><span style="font-size:8pt;color:#991b1b">Critiques</span></td>';
    $html .= '<td style="text-align:center;background-color:#fed7aa;padding:8px;border:1px solid #fdba74"><span style="font-size:16pt;font-weight:bold;color:#ea580c">' . $counts['orange'] . '</span><br><span style="font-size:8pt;color:#9a3412">Avertissements</span></td>';
    $html .= '<td style="text-align:center;background-color:#fef9c3;padding:8px;border:1px solid #fde047"><span style="font-size:16pt;font-weight:bold;color:#ca8a04">' . $counts['yellow'] . '</span><br><span style="font-size:8pt;color:#854d0e">Conseils</span></td>';
    $html .= '<td style="text-align:center;background-color:#dbeafe;padding:8px;border:1px solid #93c5fd"><span style="font-size:16pt;font-weight:bold;color:#2563eb">' . $counts['blue'] . '</span><br><span style="font-size:8pt;color:#1e40af">Informatifs</span></td>';
    $html .= '</tr></table><hr>';

    // ── Résultats détaillés ──
    if (!empty($issues)) {
        $html .= '<h2>Résultats Détaillés (' . count($issues) . ' problèmes)</h2>';
        foreach ($groups as $gid => $glabel) {
            if (empty($grouped[$gid])) continue;
            $html .= '<h3>' . htmlspecialchars($glabel) . ' (' . count($grouped[$gid]) . ')</h3>';
            $html .= '<table><tr><th style="width:25%">Sévérité</th><th>Description</th></tr>';
            foreach ($grouped[$gid] as $iss) {
                $sev  = $iss[1] ?? 'yellow';
                $desc = htmlspecialchars($iss[0] ?? '');
                $html .= '<tr>';
                $html .= '<td style="white-space:nowrap"><span style="background-color:' . sevBg($sev) . ';color:' . sevColor($sev) . ';padding:1px 5px;font-size:7.5pt;font-weight:bold">' . sevLabel($sev) . '</span></td>';
                $html .= '<td>' . $desc . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }
    } else {
        $html .= '<h2>Résultats</h2>';
        $html .= '<p style="color:#16a34a;font-weight:bold">✓ Aucun problème de sécurité détecté !</p>';
    }

    // ── Avertissement légal ──
    $html .= '<hr>';
    $html .= '<h2>Avertissement Légal</h2>';
    $html .= '<p><small>Cet audit a été réalisé avec l\'outil d\'analyse passive de Netcrafter. Les vérifications effectuées sont non-destructives et se limitent à l\'observation des en-têtes HTTP et de l\'accessibilité de certains chemins. Ce rapport est fourni à titre informatif uniquement. Netcrafter ne peut être tenu responsable de l\'utilisation de ces informations. N\'utilisez cet outil que sur des sites dont vous êtes propriétaire ou pour lesquels vous disposez d\'une autorisation écrite.</small></p>';
    $html .= '<p><small><b>Netcrafter</b> — netcrafterniger.com &nbsp;·&nbsp; +227 88 67 21 15 &nbsp;·&nbsp; Niamey, Niger</small></p>';

    buildPdf('Rapport Sécurité — ' . $host, 'netcrafter-pentest-' . date('Ymd-His') . '.pdf', $html);
    exit;
}
