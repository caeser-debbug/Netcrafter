<?php
require_once __DIR__ . '/db.php';

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset('utf8');

// Auto-create tables
$conn->query("CREATE TABLE IF NOT EXISTS blog_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(110) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn->query("CREATE TABLE IF NOT EXISTS blog_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(270) NOT NULL UNIQUE,
  excerpt TEXT,
  content LONGTEXT,
  image VARCHAR(500) DEFAULT NULL,
  author VARCHAR(100) DEFAULT 'Netcrafter',
  status ENUM('draft','published') DEFAULT 'published',
  views INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_cat (category_id),
  INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

// Filters
$cat_slug    = trim($_GET['category'] ?? '');
$search      = trim($_GET['q'] ?? '');
$page        = max(1, intval($_GET['page'] ?? 1));
$per_page    = 9;

// Fetch categories
$categories = $conn->query("SELECT * FROM blog_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Build query
$conditions = ["p.status='published'"];
$params     = [];
$types      = '';

if ($cat_slug) {
    $conditions[] = "c.slug=?";
    $params[] = $cat_slug;
    $types   .= 's';
}
if ($search) {
    $conditions[] = "(p.title LIKE ? OR p.excerpt LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types   .= 'ss';
}

$where = 'WHERE ' . implode(' AND ', $conditions);

// Count
$count_sql = "SELECT COUNT(*) as total FROM blog_posts p LEFT JOIN blog_categories c ON p.category_id=c.id $where";
if ($params) {
    $cs = $conn->prepare($count_sql);
    $cs->bind_param($types, ...$params);
    $cs->execute();
    $total = $cs->get_result()->fetch_assoc()['total'];
} else {
    $total = $conn->query($count_sql)->fetch_assoc()['total'] ?? 0;
}
$total_pages = max(1, ceil($total / $per_page));
$offset = ($page - 1) * $per_page;

// Fetch posts
$sql = "SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id=c.id
        $where
        ORDER BY p.created_at DESC
        LIMIT ?, ?";
$all_params = array_merge($params, [$offset, $per_page]);
$all_types  = $types . 'ii';
$ps = $conn->prepare($sql);
$ps->bind_param($all_types, ...$all_params);
$ps->execute();
$posts = $ps->get_result()->fetch_all(MYSQLI_ASSOC);

// Featured (latest 1)
$featured = !empty($posts) ? $posts[0] : null;
$rest     = array_slice($posts, 1);

// Recent + Popular for sidebar
$recent  = $conn->query("SELECT id,title,slug,image,created_at FROM blog_posts WHERE status='published' ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$popular = $conn->query("SELECT id,title,slug,image,views FROM blog_posts WHERE status='published' ORDER BY views DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$conn->close();

$page_title    = 'Blog — Netcrafter';
$page_keywords = 'blog numérique Niger, actualités web Niamey, conseils informatique Niger, articles SEO Niger, blog développement web Netcrafter';
include '../includes/header.php';
?>
<style>
.blog-card {
    background: rgba(10,24,58,0.7);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(0,200,255,0.12);
    border-radius: 16px;
    transition: all 0.3s;
    overflow: hidden;
}
.blog-card:hover {
    border-color: rgba(0,200,255,0.35);
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.4), 0 0 20px rgba(0,200,255,0.08);
}
.blog-cat-badge {
    background: rgba(0,200,255,0.15);
    color: #00c8ff;
    border: 1px solid rgba(0,200,255,0.3);
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 50px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.sidebar-card {
    background: rgba(10,24,58,0.7);
    border: 1px solid rgba(0,200,255,0.1);
    border-radius: 14px;
    padding: 20px;
}
.blog-input {
    background: rgba(10,24,58,0.8);
    border: 1px solid rgba(0,200,255,0.2);
    border-radius: 10px;
    color: #fff;
    padding: 10px 14px;
    width: 100%;
    outline: none;
    transition: border-color 0.2s;
}
.blog-input:focus { border-color: rgba(0,200,255,0.5); }
.blog-input::placeholder { color: #64748b; }
</style>

<!-- Hero -->
<section class="relative pt-32 pb-14 overflow-hidden">
    <div class="blob bg-nc-cyan" style="width:350px;height:350px;top:-120px;left:-100px;opacity:0.4"></div>
    <div class="blob bg-nc-blue" style="width:300px;height:300px;bottom:-80px;right:-60px;animation-delay:2s;opacity:0.3"></div>
    <div class="max-w-7xl mx-auto px-4 relative z-10 text-center" data-aos="fade-up">
        <div class="badge mb-4 mx-auto inline-flex"><i class="fas fa-rss mr-2"></i>Blog</div>
        <h1 class="font-heading font-bold text-4xl md:text-5xl text-white mb-4">
            Actualités &amp; <span class="gradient-text">Conseils Tech</span>
        </h1>
        <p class="text-gray-400 text-lg max-w-2xl mx-auto">
            Restez informé des dernières tendances en technologie, cybersécurité et développement web.
        </p>
    </div>
</section>

<section class="pb-16 max-w-7xl mx-auto px-4">

    <!-- Search + filters bar -->
    <div class="flex flex-col md:flex-row gap-4 mb-10" data-aos="fade-up">
        <form action="index.php" method="GET" class="flex-1 flex gap-3">
            <?php if ($cat_slug): ?><input type="hidden" name="category" value="<?= htmlspecialchars($cat_slug) ?>"><?php endif; ?>
            <div class="relative flex-1">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Rechercher un article…"
                       class="blog-input pl-10">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm"></i>
            </div>
            <button type="submit" class="btn-primary py-2.5 px-5 text-sm">
                <i class="fas fa-search"></i>
            </button>
        </form>
        <div class="flex flex-wrap gap-2">
            <a href="index.php<?= $search ? '?q='.urlencode($search) : '' ?>"
               class="text-sm px-3 py-2 rounded-xl border transition-colors <?= !$cat_slug ? 'border-nc-cyan text-nc-cyan' : 'border-white/10 text-slate-400 hover:text-nc-cyan hover:border-nc-cyan' ?>">
                Tous
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="index.php?category=<?= urlencode($cat['slug']) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
               class="text-sm px-3 py-2 rounded-xl border transition-colors <?= $cat_slug===$cat['slug'] ? 'border-nc-cyan text-nc-cyan' : 'border-white/10 text-slate-400 hover:text-nc-cyan hover:border-nc-cyan' ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-8">

        <!-- Main content -->
        <div class="lg:w-2/3">

            <?php if (empty($posts)): ?>
            <div class="blog-card p-12 text-center" data-aos="fade-up">
                <i class="fas fa-file-alt text-6xl mb-4" style="color:rgba(0,200,255,0.2)"></i>
                <h3 class="text-xl font-bold text-white mb-2">Aucun article trouvé</h3>
                <p class="text-slate-400 mb-4">
                    <?= $search ? 'Aucun résultat pour "'.htmlspecialchars($search).'".' : 'Aucun article publié pour le moment.' ?>
                </p>
                <a href="index.php" class="btn-primary text-sm py-2.5">
                    <i class="fas fa-undo mr-1"></i>Voir tous les articles
                </a>
            </div>
            <?php else: ?>

            <!-- Featured post -->
            <?php if ($featured && $page === 1 && !$search && !$cat_slug): ?>
            <a href="post.php?slug=<?= urlencode($featured['slug']) ?>"
               class="blog-card flex flex-col md:flex-row overflow-hidden mb-8 group" data-aos="fade-up">
                <div class="md:w-2/5 h-52 md:h-auto overflow-hidden flex-shrink-0">
                    <?php if ($featured['image']): ?>
                    <img src="../<?= htmlspecialchars($featured['image']) ?>"
                         alt="<?= htmlspecialchars($featured['title']) ?>"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center" style="background:linear-gradient(135deg,rgba(0,102,204,0.3),rgba(0,200,255,0.1))">
                        <i class="fas fa-newspaper text-4xl text-nc-cyan/40"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3 mb-3">
                            <?php if ($featured['category_name']): ?>
                            <span class="blog-cat-badge"><?= htmlspecialchars($featured['category_name']) ?></span>
                            <?php endif; ?>
                            <span class="text-slate-500 text-xs">
                                <i class="fas fa-star text-amber-400 mr-1"></i>À la une
                            </span>
                        </div>
                        <h2 class="text-xl font-bold text-white mb-3 group-hover:text-nc-cyan transition-colors leading-snug">
                            <?= htmlspecialchars($featured['title']) ?>
                        </h2>
                        <?php if ($featured['excerpt']): ?>
                        <p class="text-slate-400 text-sm leading-relaxed line-clamp-3">
                            <?= htmlspecialchars(mb_substr($featured['excerpt'], 0, 200)) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-4 mt-4 text-xs text-slate-500">
                        <span><i class="fas fa-user mr-1"></i><?= htmlspecialchars($featured['author']) ?></span>
                        <span><i class="fas fa-calendar mr-1"></i><?= date('d/m/Y', strtotime($featured['created_at'])) ?></span>
                        <span><i class="fas fa-eye mr-1"></i><?= number_format($featured['views']) ?> vues</span>
                    </div>
                </div>
            </a>
            <?php endif; ?>

            <!-- Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <?php foreach (($page === 1 && !$search && !$cat_slug ? $rest : $posts) as $i => $post): ?>
                <a href="post.php?slug=<?= urlencode($post['slug']) ?>"
                   class="blog-card group" data-aos="fade-up" data-aos-delay="<?= ($i % 2) * 100 ?>">
                    <div class="h-44 overflow-hidden">
                        <?php if ($post['image']): ?>
                        <img src="../<?= htmlspecialchars($post['image']) ?>"
                             alt="<?= htmlspecialchars($post['title']) ?>"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center" style="background:linear-gradient(135deg,rgba(0,102,204,0.2),rgba(0,200,255,0.07))">
                            <i class="fas fa-newspaper text-3xl text-nc-cyan/30"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-5">
                        <?php if ($post['category_name']): ?>
                        <span class="blog-cat-badge inline-block mb-3"><?= htmlspecialchars($post['category_name']) ?></span>
                        <?php endif; ?>
                        <h3 class="font-bold text-white text-base leading-snug mb-2 group-hover:text-nc-cyan transition-colors line-clamp-2">
                            <?= htmlspecialchars($post['title']) ?>
                        </h3>
                        <?php if ($post['excerpt']): ?>
                        <p class="text-slate-400 text-sm line-clamp-2 mb-3"><?= htmlspecialchars(mb_substr($post['excerpt'], 0, 120)) ?></p>
                        <?php endif; ?>
                        <div class="flex items-center gap-3 text-xs text-slate-500">
                            <span><i class="fas fa-calendar mr-1"></i><?= date('d/m/Y', strtotime($post['created_at'])) ?></span>
                            <span><i class="fas fa-eye mr-1"></i><?= number_format($post['views']) ?></span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center gap-1 mt-10 flex-wrap" data-aos="fade-up">
                <?php
                $qp = http_build_query(array_filter(['q'=>$search,'category'=>$cat_slug]));
                $sp = max(1, $page-2); $ep = min($total_pages, $page+2);
                if ($page > 1) echo '<a href="?page='.($page-1).($qp?"&$qp":'').'" class="pagination-btn"><i class="fas fa-chevron-left"></i></a>';
                if ($sp > 1)  { echo '<a href="?page=1'.($qp?"&$qp":'').'" class="pagination-btn">1</a>'; if ($sp>2) echo '<span class="pagination-btn cursor-default">…</span>'; }
                for ($i=$sp;$i<=$ep;$i++) echo $i==$page ? '<span class="pagination-btn active">'.$i.'</span>' : '<a href="?page='.$i.($qp?"&$qp":'').'" class="pagination-btn">'.$i.'</a>';
                if ($ep<$total_pages) { if ($ep<$total_pages-1) echo '<span class="pagination-btn cursor-default">…</span>'; echo '<a href="?page='.$total_pages.($qp?"&$qp":'').'" class="pagination-btn">'.$total_pages.'</a>'; }
                if ($page < $total_pages) echo '<a href="?page='.($page+1).($qp?"&$qp":'').'" class="pagination-btn"><i class="fas fa-chevron-right"></i></a>';
                ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <aside class="lg:w-1/3 space-y-6">

            <!-- Search -->
            <div class="sidebar-card" data-aos="fade-left">
                <h3 class="font-heading font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-search text-nc-cyan text-sm"></i> Recherche
                </h3>
                <form action="index.php" method="GET" class="flex gap-2">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Mot-clé…" class="blog-input text-sm">
                    <button type="submit" class="btn-primary py-2 px-4 text-sm flex-shrink-0">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>

            <!-- Categories -->
            <?php if (!empty($categories)): ?>
            <div class="sidebar-card" data-aos="fade-left" data-aos-delay="50">
                <h3 class="font-heading font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-folder text-nc-cyan text-sm"></i> Catégories
                </h3>
                <ul class="space-y-2">
                    <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="index.php?category=<?= urlencode($cat['slug']) ?>"
                           class="flex items-center justify-between text-sm transition-colors hover:text-nc-cyan <?= $cat_slug===$cat['slug'] ? 'text-nc-cyan' : 'text-slate-300' ?>">
                            <span><i class="fas fa-chevron-right text-xs mr-2 text-nc-cyan/50"></i><?= htmlspecialchars($cat['name']) ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Recent -->
            <?php if (!empty($recent)): ?>
            <div class="sidebar-card" data-aos="fade-left" data-aos-delay="100">
                <h3 class="font-heading font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-clock text-nc-cyan text-sm"></i> Récents
                </h3>
                <ul class="space-y-4">
                    <?php foreach ($recent as $r): ?>
                    <li>
                        <a href="post.php?slug=<?= urlencode($r['slug']) ?>"
                           class="flex gap-3 items-start group">
                            <div class="w-14 h-14 flex-shrink-0 rounded-xl overflow-hidden" style="background:rgba(0,200,255,0.05)">
                                <?php if ($r['image']): ?>
                                <img src="../<?= htmlspecialchars($r['image']) ?>" alt="" class="w-full h-full object-cover">
                                <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="fas fa-newspaper text-nc-cyan/30"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-300 group-hover:text-nc-cyan transition-colors line-clamp-2 leading-snug"><?= htmlspecialchars($r['title']) ?></p>
                                <p class="text-xs text-slate-500 mt-1"><?= date('d/m/Y', strtotime($r['created_at'])) ?></p>
                            </div>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Popular -->
            <?php if (!empty($popular)): ?>
            <div class="sidebar-card" data-aos="fade-left" data-aos-delay="150">
                <h3 class="font-heading font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-fire text-amber-400 text-sm"></i> Populaires
                </h3>
                <ul class="space-y-3">
                    <?php foreach ($popular as $i => $p): ?>
                    <li>
                        <a href="post.php?slug=<?= urlencode($p['slug']) ?>"
                           class="flex items-center gap-3 group">
                            <span class="font-heading font-bold text-xl w-6 text-center flex-shrink-0"
                                  style="color:rgba(0,200,255,<?= 1 - $i * 0.15 ?>)"><?= $i + 1 ?></span>
                            <p class="text-sm text-slate-300 group-hover:text-nc-cyan transition-colors line-clamp-2"><?= htmlspecialchars($p['title']) ?></p>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- CTA -->
            <div class="rounded-2xl p-6 text-center" data-aos="fade-left" data-aos-delay="200"
                 style="background:linear-gradient(135deg,rgba(0,102,204,0.3),rgba(0,200,255,0.1));border:1px solid rgba(0,200,255,0.2)">
                <i class="fas fa-comments text-nc-cyan text-3xl mb-3"></i>
                <h3 class="font-heading font-bold text-white mb-2">Un projet ?</h3>
                <p class="text-slate-400 text-sm mb-4">Discutons de votre projet digital.</p>
                <a href="../devis.php" class="btn-primary text-sm py-2.5 w-full justify-center">
                    <i class="fas fa-paper-plane mr-2"></i>Devis gratuit
                </a>
            </div>

        </aside>
    </div>
</section>

<style>
.pagination-btn {
    background: rgba(10,24,58,0.7);
    border: 1px solid rgba(0,200,255,0.2);
    color: #fff;
    padding: 8px 14px;
    border-radius: 8px;
    transition: all 0.2s;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
}
.pagination-btn:hover, .pagination-btn.active {
    background: rgba(0,200,255,0.15);
    border-color: rgba(0,200,255,0.5);
    color: #00c8ff;
}
.line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.line-clamp-3 { display:-webkit-box; -webkit-line-clamp:3; line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
</style>

<?php include '../includes/footer.php'; ?>
