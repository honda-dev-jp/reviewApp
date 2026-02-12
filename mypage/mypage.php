<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../app/security/session.php';

// セッションを安全に開始
startSecureSession();

require_once __DIR__ . '/../app/guards/auth_guard.php';
require_once __DIR__ . '/../app/security/csrf.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/flash.php';
require_once __DIR__ . '/../lib/utils.php';
require_once __DIR__ . '/../lib/utils.php';  //BASE_URLの読込み関数
require_once __DIR__ . '/../includes/header_nav.php';

// ログインチェック
checkLogin();

// CSRFトークンをセッションに保存（存在しなければ生成）
generateCSRFToken();

// アイコン画像名をDBから取得（失敗してもマイページは表示する）
$icon = null;

try {
	$pdo = getPdo();
	$stmt = $pdo->prepare('SELECT image FROM users WHERE user_id = ?');
	$stmt->execute([(int)$_SESSION['user_id']]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$icon = $row['image'] ?? null;

	// 明示的に切断したい場合は残す（なくてもOK）
	$pdo = null;

} catch (Throwable $e) {
	// ここは「補助情報取得」なので、リダイレクトせずログだけ出して継続する
	error_log(sprintf(
			'[%s] %s in %s:%d',
			get_class($e),
			$e->getMessage(),
			$e->getFile(),
			$e->getLine()
	));
	$icon = null;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<!-- meta情報の読込み -->
	<?php require_once __DIR__ . '/../includes/meta.php'; ?>
	<title>仮想映画レビュー　マイページ</title>
</head>
<body>
<header>
	<h1>仮想映画レビュー　マイページ</h1>
	<nav>
		<!-- ヘッダー：ユーザー名とログイン／ログアウト表示 -->
		<?php outputHeaderNav(); ?>
	</nav>
</header>

<main>
	<!-- 登録・編集の完了表示 -->
	<?php outputFlash(); ?>

	<!-- プロフィールアイコン・ユーザー名表示 -->
	<div class="mypage-user">
		<?php if (!empty($icon)): ?>
      <?php $iconSafe = basename((string)$icon); ?>
			<img src="<?php echo getBaseUrl(); ?>/images/icon/<?= sanitize($iconSafe) ?>" alt="プロフィールアイコン">
			<span class="mypage-username"><?= sanitize($_SESSION['name']) ?></span>
		<?php else: ?>
			<img src="<?php echo getBaseUrl(); ?>/images/no_image/no_image.png" alt="プロフィールアイコン">
			<span class="mypage-username"><?= sanitize($_SESSION['name']) ?></span>
		<?php endif; ?>
	</div>

	<ul class="menu-buttons">
		<li><a href="<?= getBaseUrl() ?>/profile/profile_edit.php" class="menu-btn">プロフィール編集</a></li>
		<li><a href="<?= getBaseUrl() ?>/review_hist/review_hist.php" class="menu-btn">レビュー履歴</a></li>
		<li><a href="<?= getBaseUrl() ?>/items/item_list.php" class="menu-btn">作品一覧</a></li>

		<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
		<li><a href="<?= getBaseUrl() ?>/admin/item_add.php" class="menu-btn">作品紹介ページ追加入力</a></li>
		<?php endif; ?>
	</ul>

  <!-- 会員のみ退会手続きボタン表示 -->
  <?php if (($_SESSION['role'] ?? '') === 'member'): ?>
    <div class="form-wrap">
      <form action="../account/account_delete_confirm.php" method="post" class="form-right">
        <?php embedCSRFToken(); ?>
        <input type="submit" value="退会手続き">
      </form>
    </div>
  <?php endif; ?>

</main>

<!-- フッターの表示 -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
