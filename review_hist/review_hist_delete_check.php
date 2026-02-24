<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../app/security/session.php';

// セッションを安全に開始
startSecureSession();

// セッション固定化防止
session_regenerate_id(true);

require_once __DIR__ . '/../app/security/csrf.php';
require_once __DIR__ . '/../app/guards/auth_guard.php';
require_once __DIR__ . '/../app/guards/request_guard.php';
require_once __DIR__ . '/../app/guards/redirect_guard.php';
require_once __DIR__ . '/../app/validators/review_ids_validator.php';
require_once __DIR__ . '/../app/queries/reviews_query.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/exception_handler.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/utils.php';  //BASE_URLの読込み関数
require_once __DIR__ . '/../includes/header_nav.php';

// ==============================
// ガード処理
// ==============================

// ログインチェック
checkLogin();

// POSTリクエストかチェック（GET直叩き防止）
requirePost();

// CSRFトークンの検証
if (!validateCSRFToken()) {
    redirectWithError('不正な操作が検出されました。', '/review_hist/review_hist.php');
}

/* =========================================================
   メイン処理（確認画面）
   ========================================================= */
try {
  // review_ids を「安全な int 配列」に正規化（未選択・不正値は例外）
  $reviewIds = getNormalizedReviewIdsFromPost($_POST, 'review_ids');

  // DB接続
  $pdo = getPdo();

  // 所有者チェック込みでレビュー取得（表示用）
  $rows = fetchOwnedReviews($pdo, (int)$_SESSION['user_id'], $reviewIds);

  // 厳格チェック：送信されたID数と一致しない場合は不正扱い
  if (count($rows) !== count($reviewIds)) {
    redirectWithError('不正な値です。', '/review_hist/review_hist.php');
  }

} catch (InvalidArgumentException $e) {
  // 入力エラー（未選択・不正値）→ 一覧へ戻す
  redirectWithError($e->getMessage(), '/review_hist/review_hist.php');

} catch (Throwable $e) {
  // DB/システムエラー → 共通ハンドラ（ログ→ユーザー通知→戻す）
  handleDbError($e, '/review_hist/review_hist.php');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <!-- meta情報の読込み -->
	<?php require_once __DIR__ . '/../includes/meta.php'; ?>

  <title>仮想映画レビュー　レビュー履歴削除確認</title>
</head>
<body>
<header>
  <h1>仮想映画レビュー　レビュー履歴削除確認</h1>
  <nav><?php outputHeaderNav(); ?></nav>
</header>

<main>
  <p>以下のレビューを削除します。よろしいですか？</p>

  <div class="item-list history-list-wrap">
    <?php foreach ($rows as $val): ?>
      <div class="item-card history-card">
        <div class="item-row history-row">
          <div class="item-content history-content">
            <div class="history-card-body">
              <?= sanitize($val['comment']) ?>
            </div>
            <div class="history-card-meta">
              <?= sanitize($val['created_at']) ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- 削除実行へ -->
  <form method="post" action="<?= getBaseUrl() ?>/review_hist/review_hist_delete.php" class="button-group history-card-actions">
    <?php foreach ($rows as $val): ?>
      <input type="hidden" name="review_ids[]" value="<?= (int)$val['review_id'] ?>">
    <?php endforeach; ?>
    <?php embedCSRFToken(); ?>
    <input type="submit" value="削除">
  </form>

  <a href="<?= getBaseUrl() ?>/review_hist/review_hist.php">レビュー履歴に戻る</a>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
