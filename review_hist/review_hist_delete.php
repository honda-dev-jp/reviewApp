<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../app/security/session.php';

// セッションを安全に開始
startSecureSession();

require_once __DIR__ . '/../app/security/csrf.php';
require_once __DIR__ . '/../app/guards/auth_guard.php';
require_once __DIR__ . '/../app/guards/request_guard.php';
require_once __DIR__ . '/../app/guards/redirect_guard.php';
require_once __DIR__ . '/../app/validators/review_ids_validator.php';
require_once __DIR__ . '/../app/queries/reviews_query.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/sql_helper.php';
require_once __DIR__ . '/../lib/exception_handler.php';

// ==============================
// ガード処理
// ==============================

// ログインチェック
checkLogin();

// レビュー履歴削除処理の為セッションID再生成
session_regenerate_id(true);

// POSTリクエストかチェック（GET直叩き防止）
requirePost();

// CSRFトークンの検証（成功時にワンタイム消費）
if (!validateCSRFTokenOnce()) {
    redirectWithError('不正な操作が検出されました。');
}

/* =========================================================
   メイン処理（削除実行）
   ========================================================= */
try {
    // review_ids を安全な int 配列に正規化
    $reviewIds = getNormalizedReviewIdsFromPost($_POST, 'review_ids');
    $userId = (int)$_SESSION['user_id'];

    $pdo = getPdo();
    $pdo->beginTransaction();

    // 所有する review_id のみを確定（削除可能ID）
    $ownedReviewIds = fetchOwnedReviewIds($pdo, $userId, $reviewIds);

    // 追加防御：所有できたIDが0件なら不正（または状態不整合）として扱う
    // ※ validator が弾く前提でも、IN() 事故防止のため明示しておく
    if (count($ownedReviewIds) === 0) {
        $pdo->rollBack();
        redirectWithError('不正な値です。', '/review_hist/review_hist.php');
    }

    // 厳格チェック：送信ID数と一致しない場合は不正扱い
    if (count($ownedReviewIds) !== count($reviewIds)) {
        $pdo->rollBack();
        redirectWithError('不正な値です。', '/review_hist/review_hist.php');
    }

    // 予期しない環境差異（CASCADE未設定など）への防御として返信を先に削除
    $placeholders = buildInPlaceholders(count($ownedReviewIds));

    $sqlDeleteReplies = "DELETE FROM review_replies WHERE review_id IN ($placeholders)";
    $stmtDeleteReplies = $pdo->prepare($sqlDeleteReplies);
    $stmtDeleteReplies->execute($ownedReviewIds);

    // レビュー削除（所有者限定）
    $sqlDeleteReviews = "
        DELETE FROM reviews
        WHERE user_id = ?
          AND review_id IN ($placeholders)
    ";
    $stmtDeleteReviews = $pdo->prepare($sqlDeleteReviews);
    $stmtDeleteReviews->execute(array_merge([$userId], $ownedReviewIds));

    $deletedCount = $stmtDeleteReviews->rowCount();

    // 整合性チェック：削除件数が一致しない場合は失敗扱い
    if ($deletedCount !== count($ownedReviewIds)) {
        $pdo->rollBack();
        redirectWithError('削除に失敗しました。', '/review_hist/review_hist.php');
    }

    $pdo->commit();

    redirectWithSuccess("レビューを削除しました。（{$deletedCount}件）", '/review_hist/review_hist.php');

} catch (InvalidArgumentException $e) {
    redirectWithError($e->getMessage(), '/review_hist/review_hist.php');

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    handleDbError($e, '/review_hist/review_hist.php');
}
