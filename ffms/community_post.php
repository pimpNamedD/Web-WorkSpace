<?php
/**
 * FFMS (Field Ledger) - View Community Discussion & Replies
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

$post_id = (int)($_GET['id'] ?? 0);

// Fetch post with author info
$stmt_p = $pdo->prepare('
    SELECT p.*, u.full_name, u.location_district 
    FROM community_posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
');
$stmt_p->execute([$post_id]);
$post = $stmt_p->fetch();

if (!$post) {
    set_flash('amber', 'Discussion topic not found.');
    header('Location: community.php');
    exit;
}

$error = '';

// Handle posting a reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $reply_text = trim($_POST['reply_text'] ?? '');
    if (empty($reply_text) || strlen($reply_text) < 3) {
        $error = 'Reply must not be empty.';
    } else {
        $stmt_ins = $pdo->prepare('
            INSERT INTO community_replies (post_id, user_id, reply_text)
            VALUES (?, ?, ?)
        ');
        $stmt_ins->execute([$post_id, $user_id, $reply_text]);

        set_flash('green', 'Your reply has been added to the discussion!');
        header('Location: community_post.php?id=' . $post_id);
        exit;
    }
}

// Fetch all replies
$stmt_rep = $pdo->prepare('
    SELECT r.*, u.full_name, u.location_district 
    FROM community_replies r
    JOIN users u ON r.user_id = u.id
    WHERE r.post_id = ?
    ORDER BY r.created_at ASC
');
$stmt_rep->execute([$post_id]);
$replies = $stmt_rep->fetchAll();

$page_title = sanitize($post['title']) . ' — Community';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Back Navigation -->
    <div style="margin-bottom: 12px;">
        <a href="community.php" class="ledger-btn ledger-btn-sm">&larr; Back to All Discussions</a>
    </div>

    <!-- Main Post Card -->
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag"><?php echo strtoupper(sanitize($post['category'])); ?> DISPATCH &bull; № FL-COMM-<?php echo str_pad((string)$post['id'], 3, '0', STR_PAD_LEFT); ?></span>
                <h1 style="margin-top: 6px; font-size: 1.8rem;"><?php echo sanitize($post['title']); ?></h1>
                <div style="font-size: 13px; color: var(--ink-faint);">
                    Initiated by <strong><?php echo sanitize($post['full_name']); ?></strong> (<?php echo sanitize($post['location_district']); ?> District) &bull; <span class="mono"><?php echo format_date_mono($post['created_at']); ?></span>
                </div>
            </div>
            <div style="text-align: right;">
                <span class="stamp-badge stamp-green">[ PEER TOPIC ]</span>
            </div>
        </div>

        <div style="font-size: 15px; line-height: 1.7; margin: 18px 0; color: var(--ink-primary); white-space: pre-wrap;">
            <?php echo sanitize($post['body']); ?>
        </div>
    </div>

    <!-- Thread Replies Section -->
    <div class="ledger-card" style="margin-top: 20px;">
        <h3 style="font-size: 1.25rem; margin-bottom: 16px;">
            Discussion Responses (<?php echo count($replies); ?>)
        </h3>

        <?php if (empty($replies)): ?>
        <p style="color: var(--ink-muted); font-size: 14px;">No replies yet. Be the first fellow farmer to share your experience!</p>
        <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px;">
            <?php foreach ($replies as $r): ?>
            <div style="background: var(--paper-subtle); padding: 14px 18px; border-left: 3px solid var(--stamp-navy); border-radius: 2px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <div style="font-size: 13px;">
                        <strong><?php echo sanitize($r['full_name']); ?></strong> 
                        <span class="stamp-badge stamp-neutral" style="transform:none; font-size: 9.5px; padding: 1px 5px;"><?php echo sanitize($r['location_district']); ?></span>
                    </div>
                    <span class="mono" style="font-size: 11.5px; color: var(--ink-faint);">
                        <?php echo time_ago($r['created_at']); ?>
                    </span>
                </div>
                <div style="font-size: 14px; color: var(--ink-primary); line-height: 1.6;">
                    <?php echo nl2br(sanitize($r['reply_text'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Post a Reply Form -->
        <div style="border-top: 1px dashed var(--border-rule); padding-top: 18px;">
            <h4 style="font-size: 1.05rem; margin-bottom: 8px;">Post Your Perspective</h4>

            <?php if (!empty($error)): ?>
            <div class="flash-message flash-red" style="margin-bottom: 12px;">
                <?php echo sanitize($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="community_post.php?id=<?php echo $post_id; ?>">
                <?php echo csrf_field(); ?>
                <div style="margin-bottom: 12px;">
                    <textarea name="reply_text" class="form-control" rows="3" required placeholder="Share your agronomic advice, local pricing info, or reply to this query..."></textarea>
                </div>
                <button type="submit" class="ledger-btn ledger-btn-primary ledger-btn-sm">
                    Submit Response
                </button>
            </form>
        </div>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
