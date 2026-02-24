<?php
declare(strict_types=1);

/**
 * 作品一覧ページ
 *
 * 修正履歴：
 * - [SEC] サムネイル画像パスに basename() を適用
 *   DBから取得した image 値をそのままパスに連結していたため
 *   パストラバーサルの可能性があった → basename() + sanitize() の二重防御に統一
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../app/security/session.php';

// セッションを安全に開始
startSecureSession();

require_once __DIR__ . '/../app/guards/redirect_guard.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/rating.php';
require_once __DIR__ . '/../lib/exception_handler.php';
require_once __DIR__ . '/../lib/utils.php';  //BASE_URLの読込み関数
require_once __DIR__ . '/../includes/header_nav.php';
require_once __DIR__ . '/../includes/pagination.php'; // pagination.php をインクルード

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

?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <!-- meta情報の読込み -->
	<?php require_once __DIR__ . '/../includes/meta.php'; ?>

  <link rel="stylesheet" href="../css/pagination.css">
  <link rel="stylesheet" href="../css/scroll_arrow.css">
  <title>仮想映画レビュー　作品一覧</title>
</head>
<body>
<header>
  <h1>仮想映画レビュー　作品一覧</h1>
  <nav>
    <!-- ヘッダー：ユーザー名とログイン／ログアウト表示 -->
    <?php outputHeaderNav() ?>
  </nav>
</header>

<div class="page-scroll">
<main>

<?php
try {
  // DB
  $pdo = getPdo(); 

  // ページネーション関数の呼び出し
  $totalSql = 'SELECT COUNT(*) FROM items';
  $querySql = 'SELECT
                  i.item_id,
                  i.title,
                  i.image,
                  AVG(r.rating) AS avg_rating,
                  COUNT(r.rating) AS rating_count
              FROM items i
              LEFT JOIN reviews r ON i.item_id = r.item_id
              GROUP BY i.item_id
              ORDER BY avg_rating DESC, rating_count DESC';

  $paginationData = getPagination($pdo, $perPage, $page, $totalSql, $querySql);

  // 取得したデータ
  $rec = $paginationData['stmt'] -> fetchAll();

} catch (Throwable $e) {

  // DBエラー発生時、index.phpにリダイレクト
  handleDbError($e);
}

// 作品一覧表示
if (empty($rec)) {
  echo '検索結果がありません';
} else {
  echo '<div class="item-list">';
  foreach ($rec as $val) {
    // 画像の有無で処理を分岐
    // 修正：DBの値に basename() を適用してパストラバーサルを防ぐ
    // （mypage.php / profile_edit.php と同様の防御に統一）
    $imageFile = !empty($val['image'])
        ? '../images/thumbnail/' . sanitize(basename($val['image']))
        : '../images/no_image/no_image.png';

    echo '<div class="item-card">';
    echo '<div class="item-row">';

    // サムネイル
    echo '<a href="item_detail.php?item_id=' . (int)$val['item_id'] . '">';
    echo '<img src="' . $imageFile . '" alt="' . sanitize($val['title']) . '" class="item-thumb">';
    echo '</a>';
    echo '<div class="item-content">';
    echo '<a href="item_detail.php?item_id=' . (int)$val['item_id'] . '" class="item-title">';

    // タイトル
    echo sanitize($val['title']);
    echo '</a>';

    // 平均評価の表示
    echo '<div class="item-rating">';
    echo '<span class="rating-label">平均評価点：</span>';
    echo renderStarRating((float)$val['avg_rating']);
    echo '</div>';  // .item-rating

    echo '</div>';  // .item-content
    echo '</div>'; // .item-row
    echo '</div>';  // .item-card
  }
  echo '</div>'; // .item-list
}

// ページネーションリンクの生成
echo getPaginationLinks($page, $paginationData['totalPages']);

?>
<?php
	if(isset($_SESSION['user_id'])) {
		echo '<a href="../mypage/mypage.php">マイページに戻る</a>';
	} else {
		echo '<a href="../index.php">トップページに戻る</a>';
	}
?>
</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

<div id="scroll-bottom" class="scroll-arrow scroll-arrow--bottom" title="ページ最下部へ">↓</div>
<div id="scroll-top" class="scroll-arrow scroll-arrow--top" title="ページトップへ">↑</div>

<script src="../js/scroll_arrow.js?v=1"></script>
</div>
</body>
</html>
