<?php
/**
 * FFMS (Field Ledger) - Peer Farmer Community Forum
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

$categories = [
    'general'       => 'General Discussion',
    'crops'         => 'Crops & Agronomy',
    'livestock'     => 'Livestock & Animal Health',
    'market_prices' => 'Commodity & Farm-Gate Prices',
    'pests_disease' => 'Pest & Disease Advisory',
    'equipment'     => 'Equipment, Tractors & Irrigation'
];

$filter_cat = isset($_GET['category']) && array_key_exists($_GET['category'], $categories) ? $_GET['category'] : '';
$error = '';

// Handle creating a new discussion topic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $title    = trim($_POST['title'] ?? '');
    $cat      = trim($_POST['category'] ?? 'general');
    $body     = trim($_POST['body'] ?? '');

    if (!array_key_exists($cat, $categories)) {
        $cat = 'general';
    }

    if (empty($title) || strlen($title) < 5) {
        $error = 'Topic title must be at least 5 characters long.';
    } elseif (empty($body) || strlen($body) < 10) {
        $error = 'Topic body must be at least 10 characters long.';
    } else {
        $stmt_ins = $pdo->prepare('
            INSERT INTO community_posts (user_id, category, title, body)
            VALUES (?, ?, ?, ?)
        ');
        $stmt_ins->execute([$user_id, $cat, $title, $body]);
        $post_id = (int)$pdo->lastInsertId();

        set_flash('green', 'Discussion topic published to the Farmer Peer Forum!');
        header('Location: community_post.php?id=' . $post_id);
        exit;
    }
}

// Fetch discussion topics
$where = [];
$params = [];

if (!empty($filter_cat)) {
    $where[] = 'p.category = ?';
    $params[] = $filter_cat;
}

$sql = '
    SELECT p.*, u.full_name, u.location_district,
           (SELECT COUNT(*) FROM community_replies r WHERE r.post_id = p.id) as reply_count
    FROM community_posts p
    JOIN users u ON p.user_id = u.id
    ' . (!empty($where) ? 'WHERE ' . implode(' AND ', $where) : '') . '
    ORDER BY p.created_at DESC
';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$page_title = 'Farmer Community & Peer Exchange';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Header Card -->
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">COMMUNITY FOLIO &bull; PEER FARMER NETWORK</span>
                <h1 style="margin-top: 6px;">Farmer Community &amp; Knowledge Exchange</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Peer-to-peer agronomic discussions, market price discovery across Zambian districts, and pest alerts.
                </p>
            </div>
            <div style="text-align: right;">
                <span class="stamp-badge stamp-green">[ OPEN FORUM ]</span>
            </div>
        </div>

        <!-- Filter Category Tabs -->
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; padding-top: 10px; border-top: 1px dashed var(--border-rule);">
            <a href="community.php" class="ledger-btn ledger-btn-sm <?php echo empty($filter_cat) ? 'ledger-btn-primary' : ''; ?>">
                All Topics
            </a>
            <?php foreach ($categories as $ckey => $clabel): ?>
            <a href="community.php?category=<?php echo $ckey; ?>" class="ledger-btn ledger-btn-sm <?php echo ($filter_cat === $ckey) ? 'ledger-btn-primary' : ''; ?>">
                <?php echo sanitize($clabel); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Forum Layout: Feed & Start Discussion Form -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 16px;">
        
        <!-- Left: Topics List -->
        <div>
            <?php if (empty($posts)): ?>
            <div class="ledger-card" style="text-align: center; padding: 40px;">
                <p style="color: var(--ink-muted);">No topics posted in this category yet. Be the first to start the conversation!</p>
            </div>
            <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($posts as $p): ?>
                <div class="ledger-card" style="padding: 16px 20px; transition: transform 0.15s ease;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                        <div>
                            <span class="folio-tag" style="font-size: 10.5px;">
                                <?php echo strtoupper($categories[$p['category']] ?? $p['category']); ?>
                            </span>
                            <h3 style="margin-top: 4px; font-size: 1.15rem;">
                                <a href="community_post.php?id=<?php echo (int)$p['id']; ?>" style="color: var(--ink-primary); text-decoration: none;">
                                    <?php echo sanitize($p['title']); ?>
                                </a>
                            </h3>
                            <p style="color: var(--ink-muted); font-size: 13.5px; margin-bottom: 8px;">
                                <?php echo sanitize(substr($p['body'], 0, 140)) . (strlen($p['body']) > 140 ? '...' : ''); ?>
                            </p>
                            <div style="font-size: 12px; color: var(--ink-faint); display: flex; gap: 12px; align-items: center;">
                                <span>By <strong><?php echo sanitize($p['full_name']); ?></strong> (<?php echo sanitize($p['location_district']); ?>)</span>
                                <span>&bull;</span>
                                <span class="mono"><?php echo time_ago($p['created_at']); ?></span>
                            </div>
                        </div>
                        <div style="text-align: center; min-width: 65px; background: var(--paper-subtle); padding: 8px 12px; border: 1px solid var(--border-rule); border-radius: 2px;">
                            <div class="mono" style="font-size: 1.25rem; font-weight: 700; color: var(--stamp-navy);">
                                <?php echo (int)$p['reply_count']; ?>
                            </div>
                            <div style="font-size: 10px; text-transform: uppercase; color: var(--ink-muted);">Replies</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Start a Discussion Form -->
        <div>
            <div class="ledger-card border-navy" style="position: sticky; top: 20px;">
                <h3 style="font-size: 1.15rem; margin-bottom: 8px;">Start a New Discussion</h3>
                <p style="font-size: 12.5px; color: var(--ink-muted); margin-bottom: 14px;">
                    Ask advice on crop varieties, weather anomalies, pest control, or negotiate farm-gate commodity contracts.
                </p>

                <?php if (!empty($error)): ?>
                <div class="flash-message flash-red" style="margin-bottom: 12px; font-size: 12px;">
                    <?php echo sanitize($error); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="community.php">
                    <?php echo csrf_field(); ?>

                    <div style="margin-bottom: 10px;">
                        <label style="font-size: 11px; font-weight: 600;">Category:</label>
                        <select name="category" class="form-control-sm" style="width: 100%;" required>
                            <?php foreach ($categories as $ckey => $clabel): ?>
                            <option value="<?php echo $ckey; ?>"><?php echo sanitize($clabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom: 10px;">
                        <label style="font-size: 11px; font-weight: 600;">Topic Title:</label>
                        <input type="text" name="title" class="form-control-sm" style="width: 100%;" required placeholder="e.g. Soya Bean farm-gate price trends in Mkushi">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 11px; font-weight: 600;">Discussion Details / Observations:</label>
                        <textarea name="body" class="form-control-sm" rows="5" style="width: 100%;" required placeholder="Describe your question or observation in detail..."></textarea>
                    </div>

                    <button type="submit" class="ledger-btn ledger-btn-primary ledger-btn-sm" style="width: 100%;">
                        Publish to Forum
                    </button>
                </form>
            </div>
        </div>

    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
