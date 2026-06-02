<?php
ob_start();
$page_title = "Modifier une formation";
require_once 'includes/header.php';

$f_cfg = ['localhost', 'root', '', 'netcrafter_formation'];
$_eh = $_SERVER['HTTP_HOST'] ?? '';
if (PHP_OS_FAMILY !== 'Windows' && strpos($_eh,'localhost')===false && strpos($_eh,'127.0.0.1')===false && strpos($_eh,'::1')===false) {
    $f_cfg = ['localhost', 'u264396140_formation', 'Hondaand@1', 'u264396140_formation'];
}
$fconn = new mysqli($f_cfg[0], $f_cfg[1], $f_cfg[2], $f_cfg[3]);
if ($fconn->connect_error) { die("Erreur DB formation: " . $fconn->connect_error); }
$fconn->set_charset("utf8");

$fid = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($fid === 0) { header("Location: formations.php"); exit; }

// Load formation
$f = $fconn->query("SELECT * FROM formations WHERE id=$fid")->fetch_assoc();
if (!$f) { header("Location: formations.php?error=not_found"); exit; }

// Load categories
$cats = [];
$r = $fconn->query("SELECT id, name FROM formation_categories WHERE status='active' ORDER BY name");
while ($row = $r->fetch_assoc()) $cats[] = $row;

// Load modules + videos
$modules = [];
$mr = $fconn->query("SELECT * FROM formation_modules WHERE formation_id=$fid ORDER BY order_number");
while ($m = $mr->fetch_assoc()) {
    $vr = $fconn->query("SELECT * FROM formation_videos WHERE module_id={$m['id']} ORDER BY order_number");
    $m['videos'] = [];
    while ($v = $vr->fetch_assoc()) $m['videos'][] = $v;
    $modules[] = $m;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $cat_id      = intval($_POST['category_id'] ?? 0);
    $level       = $_POST['level'] ?? 'débutant';
    $duration    = trim($_POST['duration'] ?? '');
    $price       = str_replace(',', '.', $_POST['price_per_month'] ?? '0');
    $description = trim($_POST['description'] ?? '');
    $short_desc  = trim($_POST['short_description'] ?? '');
    $objectives  = trim($_POST['objectives'] ?? '');
    $requirements= trim($_POST['requirements'] ?? '');
    $status      = $_POST['status'] ?? 'active';
    $is_free     = isset($_POST['is_free']) ? 1 : 0;
    $language    = trim($_POST['language'] ?? 'Français');

    if (empty($title)) $errors[] = "Le titre est requis.";
    if ($cat_id === 0) $errors[] = "Sélectionnez une catégorie.";

    $cover_image = $f['cover_image'];
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['size'] > 0) {
        $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $errors[] = "Format image invalide.";
        } elseif ($_FILES['cover_image']['size'] > 3000000) {
            $errors[] = "Image trop lourde (max 3MB).";
        } else {
            $dir = '../formation/uploads/covers/';
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $fn = $dir . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $fn)) {
                $cover_image = 'uploads/covers/' . basename($fn);
            } else {
                $errors[] = "Erreur upload image.";
            }
        }
    }

    if (empty($errors)) {
        $st = $fconn->prepare("UPDATE formations SET title=?, category_id=?, level=?, duration=?, price_per_month=?, description=?, short_description=?, objectives=?, requirements=?, cover_image=?, status=? WHERE id=?");
        $st->bind_param("sissdssssssi", $title, $cat_id, $level, $duration, $price, $description, $short_desc, $objectives, $requirements, $cover_image, $status, $fid);

        if ($st->execute()) {
            // Rebuild modules: delete existing, then re-insert
            $old_mids = [];
            $omr = $fconn->query("SELECT id FROM formation_modules WHERE formation_id=$fid");
            while ($om = $omr->fetch_assoc()) $old_mids[] = $om['id'];
            foreach ($old_mids as $omid) {
                $fconn->query("DELETE FROM formation_videos WHERE module_id=$omid");
            }
            $fconn->query("DELETE FROM formation_modules WHERE formation_id=$fid");

            $mod_titles  = $_POST['mod_title'] ?? [];
            $mod_descs   = $_POST['mod_desc'] ?? [];
            $vid_mod_idx = $_POST['vid_mod_idx'] ?? [];
            $vid_titles  = $_POST['vid_title'] ?? [];
            $vid_urls    = $_POST['vid_url'] ?? [];
            $vid_durs    = $_POST['vid_duration'] ?? [];
            $vid_free    = $_POST['vid_free'] ?? [];

            $videos_by_mod = [];
            foreach ($vid_mod_idx as $vi => $mi) {
                $videos_by_mod[$mi][] = $vi;
            }

            foreach ($mod_titles as $mi => $mod_title) {
                if (empty(trim($mod_title))) continue;
                $mod_desc = $mod_descs[$mi] ?? '';
                $order = $mi + 1;
                $ms = $fconn->prepare("INSERT INTO formation_modules (formation_id, title, description, order_number) VALUES (?, ?, ?, ?)");
                $ms->bind_param("issi", $fid, $mod_title, $mod_desc, $order);
                $ms->execute();
                $mod_id = $fconn->insert_id;

                if (isset($videos_by_mod[$mi])) {
                    $vo = 0;
                    foreach ($videos_by_mod[$mi] as $vi) {
                        $vt = trim($vid_titles[$vi] ?? '');
                        $vu = trim($vid_urls[$vi] ?? '');
                        if (empty($vt) || empty($vu)) continue;
                        $vd = trim($vid_durs[$vi] ?? '');
                        $vf = in_array($vi, $vid_free) ? 1 : 0;
                        $vo++;
                        $vs = $fconn->prepare("INSERT INTO formation_videos (module_id, title, video_url, duration, is_free, order_number) VALUES (?, ?, ?, ?, ?, ?)");
                        $vs->bind_param("isssii", $mod_id, $vt, $vu, $vd, $vf, $vo);
                        $vs->execute();
                    }
                }
            }

            // Reload formation data
            $f = $fconn->query("SELECT * FROM formations WHERE id=$fid")->fetch_assoc();
            $modules = [];
            $mr2 = $fconn->query("SELECT * FROM formation_modules WHERE formation_id=$fid ORDER BY order_number");
            while ($m2 = $mr2->fetch_assoc()) {
                $vr2 = $fconn->query("SELECT * FROM formation_videos WHERE module_id={$m2['id']} ORDER BY order_number");
                $m2['videos'] = [];
                while ($v2 = $vr2->fetch_assoc()) $m2['videos'][] = $v2;
                $modules[] = $m2;
            }
            $success = true;
        } else {
            $errors[] = "Erreur: " . $fconn->error;
        }
    }
}
?>

<div class="p-6">
    <div class="flex items-center gap-3 mb-6">
        <a href="formations.php" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Modifier la formation</h1>
            <p class="text-sm text-gray-500"><?= htmlspecialchars($f['title']) ?></p>
        </div>
    </div>

    <?php if ($success): ?><div class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg text-sm">Formation mise à jour avec succès.</div><?php endif; ?>
    <?php if (!empty($errors)): ?>
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
        <ul class="text-red-600 text-sm list-disc list-inside">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="form-formation">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Informations générales</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Titre <span class="text-red-500">*</span></label>
                            <input type="text" name="title" required value="<?= htmlspecialchars($f['title']) ?>"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description courte</label>
                            <input type="text" name="short_description" value="<?= htmlspecialchars($f['short_description'] ?? '') ?>"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description complète</label>
                            <textarea name="description" rows="4"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($f['description'] ?? '') ?></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Objectifs</label>
                                <textarea name="objectives" rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($f['objectives'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prérequis</label>
                                <textarea name="requirements" rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($f['requirements'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modules & Videos -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Modules et vidéos</h2>
                        <button type="button" id="btn-add-module"
                            class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus text-xs"></i> Ajouter un module
                        </button>
                    </div>
                    <div id="modules-container" class="space-y-4">
                        <?php foreach ($modules as $mi => $module): ?>
                        <div class="module-block border border-gray-200 dark:border-gray-600 rounded-xl p-4" data-mi="<?= $mi ?>">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 bg-blue-600 text-white text-xs rounded-full flex items-center justify-center font-bold module-num"><?= $mi+1 ?></span>
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Module</span>
                                </div>
                                <button type="button" class="btn-del-module text-red-400 hover:text-red-600 text-sm"><i class="fas fa-times"></i></button>
                            </div>
                            <input type="text" name="mod_title[]" value="<?= htmlspecialchars($module['title']) ?>" placeholder="Titre du module" required
                                class="w-full px-3 py-2 mb-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <input type="text" name="mod_desc[]" value="<?= htmlspecialchars($module['description'] ?? '') ?>" placeholder="Description (optionnel)"
                                class="w-full px-3 py-2 mb-3 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <div class="videos-container space-y-2 mb-3">
                                <?php foreach ($module['videos'] as $vi => $video): ?>
                                <?php $gvi = $mi * 100 + $vi; ?>
                                <div class="video-row flex items-start gap-2 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
                                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <input type="hidden" name="vid_mod_idx[]" value="<?= $mi ?>">
                                        <input type="text" name="vid_title[]" value="<?= htmlspecialchars($video['title']) ?>" placeholder="Titre de la vidéo" required
                                            class="sm:col-span-2 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <input type="text" name="vid_duration[]" value="<?= htmlspecialchars($video['duration'] ?? '') ?>" placeholder="Durée"
                                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <input type="url" name="vid_url[]" value="<?= htmlspecialchars($video['video_url']) ?>" placeholder="URL de la vidéo" required
                                            class="sm:col-span-3 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div class="flex flex-col items-center gap-2 pt-1">
                                        <label class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer whitespace-nowrap">
                                            <input type="checkbox" name="vid_free[]" value="<?= $gvi ?>" <?= $video['is_free'] ? 'checked' : '' ?> class="rounded"> Gratuit
                                        </label>
                                        <button type="button" class="btn-del-video text-red-400 hover:text-red-600"><i class="fas fa-times text-sm"></i></button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn-add-video text-sm text-blue-600 hover:underline flex items-center gap-1">
                                <i class="fas fa-plus text-xs"></i> Ajouter une vidéo
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($modules)): ?>
                    <p id="no-module-msg" class="text-center text-gray-400 text-sm py-6">Cliquez sur "Ajouter un module" pour structurer votre formation.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Image de couverture</h2>
                    <?php if (!empty($f['cover_image'])): ?>
                    <div id="cover-preview" class="mb-3">
                        <img id="cover-img" src="../formation/<?= htmlspecialchars($f['cover_image']) ?>" alt="Couverture" class="w-full h-40 object-cover rounded-lg">
                    </div>
                    <?php else: ?>
                    <div id="cover-preview" class="mb-3 hidden">
                        <img id="cover-img" src="" alt="Aperçu" class="w-full h-40 object-cover rounded-lg">
                    </div>
                    <?php endif; ?>
                    <label class="cursor-pointer block">
                        <input type="file" name="cover_image" accept="image/*" class="hidden" id="cover-input" onchange="previewCover(this)">
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center hover:border-blue-400 transition-colors">
                            <p class="text-sm text-gray-500">Changer l'image</p>
                        </div>
                    </label>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Paramètres</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Catégorie <span class="text-red-500">*</span></label>
                            <select name="category_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Choisir...</option>
                                <?php foreach ($cats as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $f['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Niveau</label>
                            <select name="level" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?php foreach (['débutant','intermédiaire','avancé'] as $lv): ?>
                                <option value="<?= $lv ?>" <?= $f['level'] === $lv ? 'selected' : '' ?>><?= ucfirst($lv) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Durée totale</label>
                            <input type="text" name="duration" value="<?= htmlspecialchars($f['duration'] ?? '') ?>"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prix/mois (FCFA)</label>
                            <input type="number" name="price_per_month" min="0" value="<?= htmlspecialchars($f['price_per_month'] ?? '0') ?>"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Langue</label>
                            <input type="text" name="language" value="<?= htmlspecialchars($f['language'] ?? 'Français') ?>"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Statut</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="active" <?= $f['status']==='active' ? 'selected' : '' ?>>Actif</option>
                                <option value="inactive" <?= $f['status']==='inactive' ? 'selected' : '' ?>>Inactif</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_free" value="1" <?= $f['is_free'] ? 'checked' : '' ?> class="rounded">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Formation gratuite</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition-colors">
                    <i class="fas fa-save mr-2"></i> Enregistrer les modifications
                </button>
                <a href="formations.php" class="block text-center text-sm text-gray-500 hover:text-gray-700 mt-2">Annuler</a>
            </div>
        </div>
    </form>
</div>

<template id="tpl-module">
    <div class="module-block border border-gray-200 dark:border-gray-600 rounded-xl p-4" data-mi="__MI__">
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 bg-blue-600 text-white text-xs rounded-full flex items-center justify-center font-bold module-num">?</span>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Module</span>
            </div>
            <button type="button" class="btn-del-module text-red-400 hover:text-red-600 text-sm"><i class="fas fa-times"></i></button>
        </div>
        <input type="text" name="mod_title[]" placeholder="Titre du module" required
            class="w-full px-3 py-2 mb-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
        <input type="text" name="mod_desc[]" placeholder="Description (optionnel)"
            class="w-full px-3 py-2 mb-3 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
        <div class="videos-container space-y-2 mb-3"></div>
        <button type="button" class="btn-add-video text-sm text-blue-600 hover:underline flex items-center gap-1">
            <i class="fas fa-plus text-xs"></i> Ajouter une vidéo
        </button>
    </div>
</template>

<template id="tpl-video">
    <div class="video-row flex items-start gap-2 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
        <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-2">
            <input type="hidden" name="vid_mod_idx[]" value="__MI__">
            <input type="text" name="vid_title[]" placeholder="Titre de la vidéo" required
                class="sm:col-span-2 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            <input type="text" name="vid_duration[]" placeholder="Durée (ex: 12:30)"
                class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            <input type="url" name="vid_url[]" placeholder="URL de la vidéo" required
                class="sm:col-span-3 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex flex-col items-center gap-2 pt-1">
            <label class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer whitespace-nowrap">
                <input type="checkbox" name="vid_free[]" value="__VI__" class="rounded"> Gratuit
            </label>
            <button type="button" class="btn-del-video text-red-400 hover:text-red-600"><i class="fas fa-times text-sm"></i></button>
        </div>
    </div>
</template>

<script>
let moduleCount = <?= count($modules) ?>;
let videoCount  = <?= array_sum(array_map(fn($m) => count($m['videos']), $modules)) ?> + 10000;

function renumberModules() {
    document.querySelectorAll('.module-num').forEach((el, i) => el.textContent = i + 1);
    const noMsg = document.getElementById('no-module-msg');
    if (noMsg) noMsg.style.display = document.querySelectorAll('.module-block').length ? 'none' : 'block';
}

document.querySelectorAll('.btn-del-module').forEach(btn => {
    btn.addEventListener('click', () => { btn.closest('.module-block').remove(); renumberModules(); });
});
document.querySelectorAll('.btn-add-video').forEach(btn => {
    btn.addEventListener('click', () => {
        const block = btn.closest('.module-block');
        const mi = parseInt(block.dataset.mi);
        addVideo(block, mi);
    });
});
document.querySelectorAll('.btn-del-video').forEach(btn => {
    btn.addEventListener('click', () => btn.closest('.video-row').remove());
});

document.getElementById('btn-add-module').addEventListener('click', () => {
    const mi = moduleCount++;
    const tpl = document.getElementById('tpl-module').innerHTML.replace(/__MI__/g, mi);
    const wrap = document.createElement('div');
    wrap.innerHTML = tpl;
    const block = wrap.firstElementChild;
    block.querySelector('.btn-del-module').addEventListener('click', () => { block.remove(); renumberModules(); });
    block.querySelector('.btn-add-video').addEventListener('click', () => addVideo(block, mi));
    document.getElementById('modules-container').appendChild(block);
    renumberModules();
});

function addVideo(moduleBlock, mi) {
    const vi = videoCount++;
    const tpl = document.getElementById('tpl-video').innerHTML.replace(/__MI__/g, mi).replace(/__VI__/g, vi);
    const wrap = document.createElement('div');
    wrap.innerHTML = tpl;
    const row = wrap.firstElementChild;
    row.querySelector('.btn-del-video').addEventListener('click', () => row.remove());
    moduleBlock.querySelector('.videos-container').appendChild(row);
}

function previewCover(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('cover-img').src = e.target.result;
            document.getElementById('cover-preview').classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

renumberModules();
</script>

<?php $fconn->close(); require_once 'includes/footer.php'; ?>
