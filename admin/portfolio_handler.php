<?php
mysqli_report(MYSQLI_REPORT_OFF);
require_once 'includes/auth.php';
header('Content-Type: application/json');

// Connexion base vitrine
$_vh = $_SERVER['HTTP_HOST'] ?? '';
$_vl = PHP_OS_FAMILY === 'Windows'
    || strpos($_vh,'localhost') !== false
    || strpos($_vh,'127.0.0.1') !== false
    || strpos($_vh,'::1')       !== false;
$vconn = new mysqli('localhost',
    $_vl ? 'root'                      : 'u264396140_netcrefternige',
    $_vl ? ''                          : 'Hondaand@1',
    $_vl ? 'netcrafter'               : 'u264396140_netcrafternige'
);
if ($vconn->connect_error) { echo json_encode(['ok'=>false,'msg'=>'Erreur connexion vitrine']); exit; }
$vconn->set_charset('utf8mb4');

function slugify($str) {
    $str = mb_strtolower(trim($str));
    $str = preg_replace('/[àáâãäå]/u', 'a', $str);
    $str = preg_replace('/[èéêë]/u',   'e', $str);
    $str = preg_replace('/[ìíîï]/u',   'i', $str);
    $str = preg_replace('/[òóôõö]/u',  'o', $str);
    $str = preg_replace('/[ùúûü]/u',   'u', $str);
    $str = preg_replace('/[ç]/u',      'c', $str);
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    return trim($str, '-');
}

function uploadPortfolioImage($file) {
    $dir = '../uploads/portfolio/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed))         return ['ok'=>false,'msg'=>'Extension non autorisée'];
    if ($file['size'] > 3 * 1024 * 1024)  return ['ok'=>false,'msg'=>'Image trop lourde (max 3MB)'];
    $name = uniqid('pf_') . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) return ['ok'=>false,'msg'=>'Erreur upload'];
    return ['ok'=>true,'path'=>'uploads/portfolio/' . $name];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ─── LISTE ───────────────────────────────────────────
    case 'list':
        $rows = $vconn->query("SELECT * FROM portfolio_projects ORDER BY order_num ASC, created_at DESC");
        echo json_encode(['ok'=>true,'data'=>$rows->fetch_all(MYSQLI_ASSOC)]);
        break;

    // ─── CRÉER ───────────────────────────────────────────
    case 'create':
        $title     = trim($_POST['title']     ?? '');
        $category  = trim($_POST['category']  ?? 'dev-web');
        $short_desc= trim($_POST['short_desc']?? '');
        $full_desc = trim($_POST['full_desc'] ?? '');
        $tags      = trim($_POST['tags']      ?? '');
        $client    = trim($_POST['client']    ?? '');
        $year      = intval($_POST['year']    ?? 0) ?: null;
        $live_url  = trim($_POST['live_url']  ?? '');
        $featured  = isset($_POST['featured']) ? 1 : 0;
        $status    = in_array($_POST['status']??'', ['published','draft']) ? $_POST['status'] : 'published';
        $order_num = intval($_POST['order_num'] ?? 0);

        if (!$title || !$short_desc) { echo json_encode(['ok'=>false,'msg'=>'Titre et description courte requis']); break; }

        // slug unique
        $base = slugify($title);
        $slug = $base;
        $i = 1;
        while ($vconn->query("SELECT id FROM portfolio_projects WHERE slug='$slug'")->num_rows > 0)
            $slug = $base . '-' . $i++;

        $image = null;
        if (!empty($_FILES['image']['name'])) {
            $up = uploadPortfolioImage($_FILES['image']);
            if (!$up['ok']) { echo json_encode(['ok'=>false,'msg'=>$up['msg']]); break; }
            $image = $up['path'];
        }

        $stmt = $vconn->prepare("INSERT INTO portfolio_projects (title,slug,category,short_desc,full_desc,image,tags,client,year,live_url,featured,status,order_num)
                                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssssssisssi', $title,$slug,$category,$short_desc,$full_desc,$image,$tags,$client,$year,$live_url,$featured,$status,$order_num);
        if ($stmt->execute()) echo json_encode(['ok'=>true,'msg'=>'Projet créé','id'=>$vconn->insert_id]);
        else                  echo json_encode(['ok'=>false,'msg'=>$vconn->error]);
        break;

    // ─── DÉTAIL (edit form) ───────────────────────────────
    case 'get':
        $id = intval($_GET['id'] ?? 0);
        $r  = $vconn->query("SELECT * FROM portfolio_projects WHERE id=$id");
        if ($r->num_rows === 0) { echo json_encode(['ok'=>false,'msg'=>'Introuvable']); break; }
        echo json_encode(['ok'=>true,'data'=>$r->fetch_assoc()]);
        break;

    // ─── METTRE À JOUR ───────────────────────────────────
    case 'update':
        $id        = intval($_POST['id'] ?? 0);
        $title     = trim($_POST['title']     ?? '');
        $category  = trim($_POST['category']  ?? 'dev-web');
        $short_desc= trim($_POST['short_desc']?? '');
        $full_desc = trim($_POST['full_desc'] ?? '');
        $tags      = trim($_POST['tags']      ?? '');
        $client    = trim($_POST['client']    ?? '');
        $year      = intval($_POST['year']    ?? 0) ?: null;
        $live_url  = trim($_POST['live_url']  ?? '');
        $featured  = isset($_POST['featured']) ? 1 : 0;
        $status    = in_array($_POST['status']??'', ['published','draft']) ? $_POST['status'] : 'published';
        $order_num = intval($_POST['order_num'] ?? 0);

        if (!$id || !$title || !$short_desc) { echo json_encode(['ok'=>false,'msg'=>'Données manquantes']); break; }

        $image_sql = '';
        if (!empty($_FILES['image']['name'])) {
            $up = uploadPortfolioImage($_FILES['image']);
            if (!$up['ok']) { echo json_encode(['ok'=>false,'msg'=>$up['msg']]); break; }
            $img = $vconn->real_escape_string($up['path']);
            $image_sql = ", image='$img'";
        }

        $t  = $vconn->real_escape_string($title);
        $c  = $vconn->real_escape_string($category);
        $sd = $vconn->real_escape_string($short_desc);
        $fd = $vconn->real_escape_string($full_desc);
        $tg = $vconn->real_escape_string($tags);
        $cl = $vconn->real_escape_string($client);
        $yr = $year ? intval($year) : 'NULL';
        $lu = $vconn->real_escape_string($live_url);

        $sql = "UPDATE portfolio_projects SET
                title='$t', category='$c', short_desc='$sd', full_desc='$fd',
                tags='$tg', client='$cl', year=$yr, live_url='$lu',
                featured=$featured, status='$status', order_num=$order_num $image_sql
                WHERE id=$id";
        if ($vconn->query($sql)) echo json_encode(['ok'=>true,'msg'=>'Projet mis à jour']);
        else                     echo json_encode(['ok'=>false,'msg'=>$vconn->error]);
        break;

    // ─── SUPPRIMER ───────────────────────────────────────
    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID manquant']); break; }
        // delete image file
        $r = $vconn->query("SELECT image FROM portfolio_projects WHERE id=$id");
        if ($r->num_rows > 0) {
            $row = $r->fetch_assoc();
            if ($row['image'] && file_exists('../' . $row['image'])) @unlink('../' . $row['image']);
        }
        if ($vconn->query("DELETE FROM portfolio_projects WHERE id=$id"))
            echo json_encode(['ok'=>true,'msg'=>'Projet supprimé']);
        else
            echo json_encode(['ok'=>false,'msg'=>$vconn->error]);
        break;

    // ─── TOGGLE FEATURED ─────────────────────────────────
    case 'toggle_featured':
        $id  = intval($_POST['id'] ?? 0);
        $val = intval($_POST['val'] ?? 0);
        $vconn->query("UPDATE portfolio_projects SET featured=$val WHERE id=$id");
        echo json_encode(['ok'=>true]);
        break;

    // ─── TOGGLE STATUS ───────────────────────────────────
    case 'toggle_status':
        $id  = intval($_POST['id'] ?? 0);
        $val = ($_POST['val'] ?? '') === 'published' ? 'published' : 'draft';
        $vconn->query("UPDATE portfolio_projects SET status='$val' WHERE id=$id");
        echo json_encode(['ok'=>true]);
        break;

    default:
        echo json_encode(['ok'=>false,'msg'=>'Action inconnue']);
}
