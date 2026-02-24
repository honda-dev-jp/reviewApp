<?php
  declare(strict_types=1);

  require_once __DIR__ . '/../config/env.php';
  require_once __DIR__ . '/../app/security/session.php';

  // セッションを安全に開始
  startSecureSession();

  require_once __DIR__ . '/../app/security/csrf.php';
  require_once __DIR__ .'/../app/guards/auth_guard.php';
  require_once __DIR__ .'/../lib/db.php';
  require_once __DIR__ .'/../lib/sanitize.php';
  require_once __DIR__ .'/../lib/flash.php';
	require_once __DIR__ .'/../lib/exception_handler.php';
  require_once __DIR__ . '/../lib/utils.php';  //BASE_URLの読込み関数
	require_once __DIR__ .'/../includes/header_nav.php';
  require_once __DIR__ . '/../includes/pagination.php'; // pagination.php をインクルード

  // ログインチェック
	checkLogin();

  // CSRFトークンの生成
  generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
	<!-- meta情報の読込み -->
	<?php require_once __DIR__ . '/../includes/meta.php'; ?>

  <link rel="stylesheet" href="<?= getBaseUrl() ?>/css/pagination.css">
  <link rel="stylesheet" href="<?= getBaseUrl() ?>/css/scroll_arrow.css">
	<title>仮想映画レビュー　レビュー履歴</title>
</head>
<body>
<header>
	<h1>仮想映画レビュー　レビュー履歴</h1>
	<nav>
	<!-- ヘッダー：ユーザー名とログイン／ログアウト表示 -->
	<?php outputHeaderNav(); ?>
	</nav>
</header>
<div class="page-scroll">
<main>
<?php
// 削除成功、エラー表示
outputFlash();

// ==============================
// ガード処理(ページネーション)
// ==============================
// 修正：以前は $page をガード内で一度定義した後、再度上書きしていた（二重定義）
// → まず $page を確定させてからガードを行う方式に統一し、冗長な再代入を除去
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) {
  redirectWithError('不正なアクセスです。正しいページからアクセスしてください。');
}

// ページネーション設定
$perPage = 10;

try{
  // DB接続
  $pdo = getPdo();

  // ==============================
  // レビュー履歴一覧を取得
  // ==============================
  $totalSql = 'SELECT COUNT(*) FROM reviews WHERE user_id = :user_id';
  $querySql = 'SELECT review_id, comment, created_at
      FROM reviews
      WHERE user_id = :user_id
      ORDER BY created_at DESC';

// バインドパラメーター
  $bindParams = [
      ':user_id' => (int)$_SESSION['user_id']
  ];

  // ==============================
  // ページネーション関数の呼び出し
  // ==============================
  $paginationData = getPagination($pdo, $perPage, $page, $totalSql, $querySql, $bindParams);

  // 取得したデータ
  $rec = $paginationData['stmt'] -> fetchAll();

  // DB切断
  $pdo = null;

} catch (Exception $e) {
  handleDbError($e, './review_hist.php');
}

?>

<!-- レビュー書込み履歴 -->
<form method="post" action="<?= getBaseUrl() ?>/review_hist/review_hist_delete_check.php">

  <div class="item-list history-list-wrap">

  <?php foreach ($rec as $val): ?>
    <div class="item-card history-card">
      <div class="item-row history-row">

        <!-- チェックボックス列（サムネの代替） -->
        <div class="history-card-check">
          <input
            type="checkbox"
            name="review_ids[]"
            value="<?= (int)$val['review_id'] ?>"
          >
        </div>

        <!-- 本文側 -->
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

  <div class="button-group history-card-actions">
    <input type="submit" value="削除">
  </div>

  <?php embedCSRFToken(); ?>
  </form>

	<a href="<?= getBaseUrl() ?>/mypage/mypage.php">マイページに戻る</a>
  
  <?php
  // ページネーションリンクの生成
  echo getPaginationLinks($page, $paginationData['totalPages']);
  ?>
</main>
<!-- フッター -->
<?php require_once __DIR__.'/../includes/footer.php'; ?>

<!-- ページ最下部へ -->
<div id="scroll-bottom" class="scroll-arrow scroll-arrow--bottom" title="ページ最下部へ">↓</div>

<!-- ページトップへ -->
<div id="scroll-top" class="scroll-arrow scroll-arrow--top" title="ページトップへ">↑</div>

<!-- スクロール矢印（ページ上下移動） -->
<script src="<?= getBaseUrl() ?>/js/scroll_arrow.js?v=1"></script>
</div>
</body>
</html>