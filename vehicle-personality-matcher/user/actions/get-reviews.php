<?php
require_once __DIR__ . "/../../includes/db.php";

date_default_timezone_set('Asia/Kolkata');

/* ================= INPUT ================= */
$vehicle_id = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

if ($vehicle_id <= 0 || empty($type)) {
    exit("Invalid request");
}

/* ================= TIME FORMAT ================= */
function timeAgo($datetime) {

    if (!$datetime) return "just now";

    $time = strtotime($datetime);
    if (!$time) return "just now";

    $diff = time() - $time;

    if ($diff < 60) return "just now";
    if ($diff < 3600) return floor($diff/60) . " min ago";
    if ($diff < 86400) return floor($diff/3600) . " hr ago";
    if ($diff < 604800) return floor($diff/86400) . " days ago";

    return date("M d, Y", $time);
}

/* ================= PARENT COMMENTS ================= */
$stmt = $conn->prepare("
    SELECT r.*, u.username, u.profile_pic 
    FROM reviews r
    JOIN users u ON r.user_id = u.user_id
    WHERE r.vehicle_id = ? AND r.type = ? AND r.parent_id IS NULL
    ORDER BY r.created_at DESC
");

$stmt->bind_param("is", $vehicle_id, $type);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<p style='color:#9ca3af;'>No reviews yet</p>";
}

/* ================= LOOP PARENTS ================= */
while ($row = $res->fetch_assoc()):
?>

<!-- ===== MAIN COMMENT ===== -->
<div class="review">

    <img src="/vehicle-personality-matcher/<?= htmlspecialchars($row['profile_pic'] ?: 'assets/images/default.jpg') ?>" class="review-pic">

    <div class="review-body">

        <!-- USER + TIME -->
        <div class="review-header">
            <span class="review-username">
                <?= htmlspecialchars($row['username']) ?>
            </span>
            <span class="review-time">
                • <?= timeAgo($row['created_at']) ?>
            </span>
        </div>

        <!-- COMMENT -->
        <p><?= nl2br(htmlspecialchars($row['content'])) ?></p>

        <!-- REPLY BUTTON -->
        <button class="reply-btn"
            onclick="reply(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>')">
            Reply
        </button>

        <div id="replyBox<?= $row['id'] ?>"></div>

        <!-- ================= REPLIES ================= -->
        <?php
        $replyStmt = $conn->prepare("
            SELECT r.*, u.username, u.profile_pic 
            FROM reviews r
            JOIN users u ON r.user_id = u.user_id
            WHERE r.parent_id = ?
            ORDER BY r.created_at ASC
        ");

        $replyStmt->bind_param("i", $row['id']);
        $replyStmt->execute();
        $replies = $replyStmt->get_result();

        $replyCount = $replies->num_rows;
        ?>

        <?php if ($replyCount > 0): ?>

            <!-- TOGGLE BUTTON -->
            <button class="view-replies-btn" onclick="toggleReplies(<?= $row['id'] ?>)">
                View replies (<?= $replyCount ?>)
            </button>

            <!-- HIDDEN REPLIES -->
            <div id="replies<?= $row['id'] ?>" class="replies" style="display:none;">

                <?php while ($reply = $replies->fetch_assoc()): ?>

                    <div class="review reply">

                        <img src="/vehicle-personality-matcher/<?= htmlspecialchars($reply['profile_pic'] ?: 'assets/images/default.jpg') ?>" class="review-pic">

                        <div class="review-body">

                            <!-- USER + TIME -->
                            <div class="review-header">
                                <span class="review-username">
                                    <?= htmlspecialchars($reply['username']) ?>
                                </span>
                                <span class="review-time">
                                    • <?= timeAgo($reply['created_at']) ?>
                                </span>
                            </div>

                            <!-- COMMENT -->
                            <p><?= nl2br(htmlspecialchars($reply['content'])) ?></p>

                            <!-- REPLY BUTTON -->
                            <button class="reply-btn"
                                onclick="reply(<?= $row['id'] ?>, '<?= htmlspecialchars($reply['username'], ENT_QUOTES) ?>')">
                                Reply
                            </button>

                        </div>

                    </div>

                <?php endwhile; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php endwhile; ?>