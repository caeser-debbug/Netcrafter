<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_d = fn($fr, $en) => (($_SESSION['nc_lang'] ?? 'fr') === 'en') ? $en : $fr;
require_once __DIR__ . '/db.php';

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset('utf8');

// Auto-create comments table
$conn->query("CREATE TABLE IF NOT EXISTS blog_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  comment TEXT NOT NULL,
  status ENUM('pending','approved') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post (post_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

// Resolve post by slug or id
$slug = trim($_GET['slug'] ?? '');
$pid  = intval($_GET['id'] ?? 0);

if ($slug) {
    $st = $conn->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug FROM blog_posts p LEFT JOIN blog_categories c ON p.category_id=c.id WHERE p.slug=? AND p.status='published'");
    $st->bind_param('s', $slug);
} elseif ($pid) {
    $st = $conn->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug FROM blog_posts p LEFT JOIN blog_categories c ON p.category_id=c.id WHERE p.id=? AND p.status='published'");
    $st->bind_param('i', $pid);
} else {
    header('Location: index.php');
    exit;
}
$st->execute();
$post = $st->get_result()->fetch_assoc();
if (!$post) { header('Location: index.php'); exit; }

// Increment views
$conn->query("UPDATE blog_posts SET views=views+1 WHERE id={$post['id']}");

// Handle comment submission
$comment_msg     = '';
$comment_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['comment'])) {
    $cname    = trim(htmlspecialchars($_POST['name']    ?? '', ENT_QUOTES));
    $cemail   = trim($_POST['email'] ?? '');
    $ccomment = trim(htmlspecialchars($_POST['comment'] ?? '', ENT_QUOTES));
    $cemail_valid = filter_var($cemail, FILTER_VALIDATE_EMAIL);

    if (!$cname || !$cemail_valid || !$ccomment) {
        $comment_msg = $_d('Veuillez remplir tous les champs correctement.','Please fill in all fields correctly.');
    } else {
        $cemail = htmlspecialchars($cemail, ENT_QUOTES);
        $ins = $conn->prepare("INSERT INTO blog_comments (post_id, name, email, comment) VALUES (?,?,?,?)");
        $ins->bind_param('isss', $post['id'], $cname, $cemail, $ccomment);
        if ($ins->execute()) {
            $comment_success = true;
            $comment_msg = $_d('Votre commentaire a été soumis et sera publié après modération.','Your comment has been submitted and will be published after moderation.');
        } else {
            $comment_msg = $_d('Erreur lors de l\'enregistrement.','An error occurred while saving.');
        }
    }
}

// Fetch approved comments
$comments_stmt = $conn->prepare("SELECT name, comment, created_at FROM blog_comments WHERE post_id=? AND status='approved' ORDER BY created_at ASC");
$comments_stmt->bind_param('i', $post['id']);
$comments_stmt->execute();
$comments = $comments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Related posts
$related = [];
if ($post['category_id']) {
    $rs = $conn->prepare("SELECT id,title,slug,image,created_at FROM blog_posts WHERE category_id=? AND id!=? AND status='published' ORDER BY created_at DESC LIMIT 3");
    $rs->bind_param('ii', $post['category_id'], $post['id']);
    $rs->execute();
    $related = $rs->get_result()->fetch_all(MYSQLI_ASSOC);
}

$conn->close();

$page_title = htmlspecialchars($post['title']) . ' — Netcrafter Blog';
include '../includes/header.php';
?>
<style>
.blog-prose {
    color: #cbd5e1;
    line-height: 1.8;
    font-size: 1.05rem;
}
.blog-prose h2, .blog-prose h3, .blog-prose h4 { color: #fff; font-weight: 700; margin: 1.5em 0 0.6em; }
.blog-prose h2 { font-size: 1.5rem; }
.blog-prose h3 { font-size: 1.25rem; }
.blog-prose p  { margin: 0 0 1.2em; }
.blog-prose a  { color: #00c8ff; text-decoration: underline; }
.blog-prose ul, .blog-prose ol { padding-left: 1.5em; margin: 0 0 1.2em; }
.blog-prose li { margin-bottom: 0.4em; }
.blog-prose blockquote {
    border-left: 3px solid #00c8ff;
    background: rgba(0,200,255,0.06);
    padding: 1em 1.2em;
    margin: 1.2em 0;
    border-radius: 0 10px 10px 0;
    color: #94a3b8;
    font-style: italic;
}
.blog-prose pre {
    background: rgba(6,13,30,0.9);
    border: 1px solid rgba(0,200,255,0.15);
    border-radius: 10px;
    padding: 1em 1.2em;
    overflow-x: auto;
    font-size: 0.9rem;
    margin: 1.2em 0;
}
.blog-prose code { background: rgba(0,200,255,0.1); padding: 0.1em 0.4em; border-radius: 4px; font-size: 0.88em; }
.blog-prose pre code { background: none; padding: 0; }
.blog-prose img { border-radius: 12px; max-width: 100%; margin: 1em 0; }
.blog-card {
    background: rgba(10,24,58,0.7);
    border: 1px solid rgba(0,200,255,0.12);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s;
}
.blog-card:hover { border-color: rgba(0,200,255,0.35); transform: translateY(-3px); }
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

<!-- Breadcrumb -->
<div class="pt-24 pb-4 border-b border-white/5">
    <div class="max-w-7xl mx-auto px-4">
        <nav class="flex text-sm text-slate-400 gap-2 items-center flex-wrap">
            <a href="index.php" class="hover:text-nc-cyan transition-colors">Blog</a>
            <?php if ($post['category_name']): ?>
            <i class="fas fa-chevron-right text-xs text-white/20"></i>
            <a href="index.php?category=<?= urlencode($post['category_slug']) ?>"
               class="hover:text-nc-cyan transition-colors"><?= htmlspecialchars($post['category_name']) ?></a>
            <?php endif; ?>
            <i class="fas fa-chevron-right text-xs text-white/20"></i>
            <span class="text-white line-clamp-1"><?= htmlspecialchars(mb_substr($post['title'],0,50)) ?></span>
        </nav>
    </div>
</div>

<section class="py-10">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex flex-col lg:flex-row gap-10">

            <!-- Article -->
            <article class="lg:w-2/3">

                <!-- Cover image -->
                <?php if ($post['image']): ?>
                <div class="rounded-2xl overflow-hidden h-64 md:h-96 mb-8" data-aos="fade-up">
                    <img src="../<?= htmlspecialchars($post['image']) ?>"
                         alt="<?= htmlspecialchars($post['title']) ?>"
                         class="w-full h-full object-cover">
                </div>
                <?php endif; ?>

                <!-- Meta -->
                <div class="flex flex-wrap items-center gap-4 mb-4 text-sm text-slate-500" data-aos="fade-up">
                    <?php if ($post['category_name']): ?>
                    <a href="index.php?category=<?= urlencode($post['category_slug']) ?>"
                       class="inline-block"
                       style="background:rgba(0,200,255,0.15);color:#00c8ff;border:1px solid rgba(0,200,255,0.3);font-size:0.68rem;font-weight:700;padding:2px 10px;border-radius:50px;text-transform:uppercase;letter-spacing:0.06em">
                        <?= htmlspecialchars($post['category_name']) ?>
                    </a>
                    <?php endif; ?>
                    <span><i class="fas fa-user mr-1"></i><?= htmlspecialchars($post['author']) ?></span>
                    <span><i class="fas fa-calendar mr-1"></i><?= date('d/m/Y', strtotime($post['created_at'])) ?></span>
                    <span><i class="fas fa-eye mr-1"></i><?= number_format($post['views']) ?> <?= $_d('vues','views') ?></span>
                    <span><i class="fas fa-comments mr-1"></i><?= count($comments) ?> <?= $_d('commentaire','comment') ?><?= count($comments)>1?'s':'' ?></span>
                </div>

                <!-- Title -->
                <h1 class="font-heading font-bold text-3xl md:text-4xl text-white mb-6 leading-tight" data-aos="fade-up">
                    <?= htmlspecialchars($post['title']) ?>
                </h1>

                <!-- Content -->
                <div class="blog-prose" data-aos="fade-up">
                    <?= $post['content'] ?>
                </div>

                <!-- Share -->
                <div class="flex items-center gap-4 mt-8 pt-6 border-t border-white/10" data-aos="fade-up">
                    <span class="text-sm text-slate-400"><?= $_d('Partager :','Share:') ?></span>
                    <?php
                    $post_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    $enc_url  = urlencode($post_url);
                    $enc_title = urlencode($post['title']);
                    ?>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $enc_url ?>" target="_blank"
                       class="w-9 h-9 rounded-xl flex items-center justify-center text-gray-400 hover:text-white hover:bg-blue-600 transition-all"
                       style="background:rgba(0,200,255,0.07);border:1px solid rgba(0,200,255,0.15)">
                        <i class="fab fa-facebook-f text-sm"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?text=<?= $enc_title ?>&url=<?= $enc_url ?>" target="_blank"
                       class="w-9 h-9 rounded-xl flex items-center justify-center text-gray-400 hover:text-white hover:bg-sky-500 transition-all"
                       style="background:rgba(0,200,255,0.07);border:1px solid rgba(0,200,255,0.15)">
                        <i class="fab fa-twitter text-sm"></i>
                    </a>
                    <a href="https://wa.me/?text=<?= $enc_title ?>+<?= $enc_url ?>" target="_blank"
                       class="w-9 h-9 rounded-xl flex items-center justify-center text-gray-400 hover:text-white hover:bg-green-600 transition-all"
                       style="background:rgba(0,200,255,0.07);border:1px solid rgba(0,200,255,0.15)">
                        <i class="fab fa-whatsapp text-sm"></i>
                    </a>
                </div>

                <!-- Comments -->
                <div class="mt-10" data-aos="fade-up">
                    <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                        <i class="fas fa-comments text-nc-cyan"></i>
                        <?= $_d('Commentaires','Comments') ?> (<?= count($comments) ?>)
                    </h2>

                    <?php if (!empty($comments)): ?>
                    <div class="space-y-4 mb-8">
                        <?php foreach ($comments as $c): ?>
                        <div class="rounded-xl p-4" style="background:rgba(10,24,58,0.6);border:1px solid rgba(0,200,255,0.1)">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                                     style="background:linear-gradient(135deg,#00c8ff,#0066cc);color:#fff">
                                    <?= strtoupper(mb_substr($c['name'],0,1)) ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-white text-sm"><?= htmlspecialchars($c['name']) ?></p>
                                    <p class="text-xs text-slate-500"><?= date('d/m/Y', strtotime($c['created_at'])) ?></p>
                                </div>
                            </div>
                            <p class="text-slate-300 text-sm leading-relaxed pl-12"><?= nl2br(htmlspecialchars($c['comment'])) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Comment form -->
                    <h3 class="text-lg font-semibold text-white mb-4"><?= $_d('Laisser un commentaire','Leave a comment') ?></h3>
                    <?php if ($comment_msg): ?>
                    <div class="rounded-xl px-4 py-3 mb-4 text-sm <?= $comment_success ? 'text-nc-green' : 'text-red-400' ?>"
                         style="background:<?= $comment_success ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)' ?>;border:1px solid <?= $comment_success ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)' ?>">
                        <i class="fas fa-<?= $comment_success ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i><?= htmlspecialchars($comment_msg) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!$comment_success): ?>
                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-slate-300 mb-1"><?= $_d('Nom','Name') ?> *</label>
                                <input type="text" name="name" class="blog-input" required>
                            </div>
                            <div>
                                <label class="block text-sm text-slate-300 mb-1">Email *</label>
                                <input type="email" name="email" class="blog-input" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-300 mb-1"><?= $_d('Commentaire','Comment') ?> *</label>
                            <textarea name="comment" rows="4" class="blog-input resize-none" required></textarea>
                        </div>
                        <button type="submit" class="btn-primary text-sm py-2.5 px-6">
                            <i class="fas fa-paper-plane mr-2"></i><?= $_d('Publier le commentaire','Post comment') ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </article>

            <!-- Sidebar -->
            <aside class="lg:w-1/3 space-y-6">

                <!-- Author card -->
                <div class="rounded-2xl p-6 text-center" data-aos="fade-left"
                     style="background:rgba(10,24,58,0.7);border:1px solid rgba(0,200,255,0.12)">
                    <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center font-bold text-xl"
                         style="background:linear-gradient(135deg,#00c8ff,#0066cc);color:#fff">
                        <?= strtoupper(mb_substr($post['author'],0,1)) ?>
                    </div>
                    <h3 class="font-semibold text-white mb-1"><?= htmlspecialchars($post['author']) ?></h3>
                    <p class="text-slate-400 text-sm"><?= $_d('Équipe Netcrafter','Netcrafter Team') ?></p>
                </div>

                <!-- Related -->
                <?php if (!empty($related)): ?>
                <div style="background:rgba(10,24,58,0.7);border:1px solid rgba(0,200,255,0.1);border-radius:14px;padding:20px" data-aos="fade-left" data-aos-delay="50">
                    <h3 class="font-heading font-semibold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-link text-nc-cyan text-sm"></i> <?= $_d('Articles liés','Related articles') ?>
                    </h3>
                    <ul class="space-y-4">
                        <?php foreach ($related as $r): ?>
                        <li>
                            <a href="post.php?slug=<?= urlencode($r['slug']) ?>"
                               class="flex gap-3 items-start group">
                                <div class="w-14 h-14 flex-shrink-0 rounded-xl overflow-hidden" style="background:rgba(0,200,255,0.05)">
                                    <?php if ($r['image']): ?>
                                    <img src="../<?= htmlspecialchars($r['image']) ?>" alt="" class="w-full h-full object-cover">
                                    <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center"><i class="fas fa-newspaper text-nc-cyan/30"></i></div>
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

                <!-- Back to blog -->
                <a href="index.php" class="btn-secondary w-full justify-center py-3" data-aos="fade-left" data-aos-delay="100">
                    <i class="fas fa-arrow-left mr-2"></i><?= $_d('Retour au blog','Back to blog') ?>
                </a>

                <!-- CTA -->
                <div class="rounded-2xl p-6 text-center" data-aos="fade-left" data-aos-delay="150"
                     style="background:linear-gradient(135deg,rgba(0,102,204,0.3),rgba(0,200,255,0.1));border:1px solid rgba(0,200,255,0.2)">
                    <i class="fas fa-rocket text-nc-cyan text-2xl mb-3"></i>
                    <h3 class="font-heading font-bold text-white mb-2 text-sm"><?= $_d('Un projet digital ?','Got a digital project?') ?></h3>
                    <a href="../devis.php" class="btn-primary text-sm py-2 w-full justify-center mt-2">
                        <?= $_d('Devis gratuit','Free quote') ?>
                    </a>
                </div>
            </aside>

        </div>
    </div>
</section>

<!-- Related posts (mobile/full width) -->
<?php if (!empty($related)): ?>
<section class="pb-14 max-w-7xl mx-auto px-4" data-aos="fade-up">
    <h2 class="font-heading font-bold text-2xl text-white mb-6">
        <?= $_d('Plus dans','More in') ?> <span class="gradient-text"><?= htmlspecialchars($post['category_name']) ?></span>
    </h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <?php foreach ($related as $r): ?>
        <a href="post.php?slug=<?= urlencode($r['slug']) ?>" class="blog-card group">
            <div class="h-36 overflow-hidden">
                <?php if ($r['image']): ?>
                <img src="../<?= htmlspecialchars($r['image']) ?>" alt="<?= htmlspecialchars($r['title']) ?>"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <?php else: ?>
                <div class="w-full h-full flex items-center justify-center" style="background:rgba(0,200,255,0.06)">
                    <i class="fas fa-newspaper text-3xl text-nc-cyan/20"></i>
                </div>
                <?php endif; ?>
            </div>
            <div class="p-4">
                <h3 class="text-sm font-semibold text-white group-hover:text-nc-cyan transition-colors line-clamp-2">
                    <?= htmlspecialchars($r['title']) ?>
                </h3>
                <p class="text-xs text-slate-500 mt-1"><?= date('d/m/Y', strtotime($r['created_at'])) ?></p>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<style>
.line-clamp-1 { display:-webkit-box;-webkit-line-clamp:1;line-clamp:1;-webkit-box-orient:vertical;overflow:hidden; }
.line-clamp-2 { display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
<style>
/* Override Prism theme to match dark navy design */
pre[class*="language-"] {
    background: rgba(6,13,30,0.92) !important;
    border: 1px solid rgba(0,200,255,0.15) !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 24px rgba(0,0,0,0.4) !important;
}
.token.keyword { color: #00c8ff !important; }
.token.string  { color: #10b981 !important; }
.token.function{ color: #7c3aed !important; }
.token.comment { color: #475569 !important; }
.token.number  { color: #f59e0b !important; }
.token.operator{ color: #94a3b8 !important; }
/* Language label */
pre[class*="language-"]::before {
    content: attr(data-lang);
    display: block;
    font-size: 0.7rem;
    color: #00c8ff;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    margin-bottom: 8px;
    opacity: 0.7;
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
<script>
// Add data-lang attr to pre blocks for the CSS label
document.querySelectorAll('pre[class*="language-"]').forEach(pre => {
    const cls = Array.from(pre.classList).find(c => c.startsWith('language-'));
    if (cls) pre.setAttribute('data-lang', cls.replace('language-',''));
});
</script>
<?php include '../includes/footer.php'; ?>
