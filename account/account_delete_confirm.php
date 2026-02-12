<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../app/security/session.php';

// セッションを安全に開始
startSecureSession();

require_once __DIR__ . '/../app/security/csrf.php';
require_once __DIR__ . '/../app/guards/auth_guard.php';
require_once __DIR__ . '/../app/guards/request_guard.php';
require_once __DIR__ . '/../app/guards/member_guard.php';
require_once __DIR__ . '/../app/guards/redirect_guard.php';
require_once __DIR__ . '/../includes/header_nav.php';
require_once __DIR__ . '/../lib/flash.php';
require_once __DIR__ . '/../lib/utils.php';  //BASE_URLの読込み関数

// ログインチェック
checkLogin();

// POSTリクエストかチェック
requirePost();

// roleが会員かチェック
checkMember();

// CSRFトークンの検証
if (!validateCSRFToken()) {
    redirectWithError('不正な操作が検出されました。');
}

// 退会確認を通過したフラグを立てる
$_SESSION['account_delete_confirmed'] = true;

?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<!-- meta情報の読込み -->
	<?php require_once __DIR__ . '/../includes/meta.php'; ?>

	<title>仮想映画レビュー　退会手続き</title>
</head>
<body>
<header>
	<h1>退会手続きの確認</h1>
	<nav>
		<!-- ヘッダー：ユーザー名とログイン／ログアウト表示 -->
		<?php outputHeaderNav(); ?>
	</nav>
</header>

<main>
	<!-- 退会手続きのエラー表示 -->
	<?php outputFlash(); ?>

	<div>
    <p>以下の内容をご確認ください。</p>
    <p>
    この操作を実行すると、アカウントは削除され、
    これまでに投稿したレビューや関連する情報はすべて利用できなくなります。
    </p>
    <p>退会後は、同じアカウントを元に戻すことはできません。</p>
  </div>
  <?php

  ?>
  <div class="delete-confirm-actions">
    <form method="post" action="./account_delete_execute.php">
      <?php embedCSRFToken(); ?>
      <div class="button-group">
        <a href="../mypage/mypage.php" class="btn-cancel">キャンセル</a>
        <input type="submit" class="btn-danger" value="退会する">
      </div>
    </form>
  </div>
</main>

<!-- フッターの表示 -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>