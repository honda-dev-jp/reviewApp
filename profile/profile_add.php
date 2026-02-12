<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../app/security/session.php';

// セッションを安全に開始
startSecureSession();

require_once __DIR__ . '/../app/security/csrf.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/flash.php';
require_once __DIR__ . '/../lib/utils.php';  //BASE_URLの読込み関数

// CSRFトークンの生成
generateCSRFToken();

// バリデーションエラー時の入力値の保持
$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<!-- meta情報の読込み -->
	<?php require_once __DIR__ . '/../includes/meta.php'; ?>

	<title>仮想映画レビュー　新規会員登録</title>
</head>
<body>
<header>
	<h1>仮想映画レビュー　新規会員登録</h1>
</header>
<main>
	<!-- エラーメッセージ表示 -->
	<?php outputFlash(); ?>
  
	<form method="post" action="profile_add_check.php" enctype="multipart/form-data">
		ユーザーネーム：
		<input type="text" name="name" value="<?= sanitize($old['name'] ?? '') ?>" required><br />
		<br>
		メールアドレス：
		<input type="email" name="email" value="<?= sanitize($old['email'] ?? '') ?>" required><br />
		<!--requiredでnameに応じたエラーメッセージ-->
		<br>
		パスワード　　：
		<input type="password" name="pass" required><br />
		<br>
		確　認　用　　：
		<input type="password" name="pass2" required><br />
		<br>
		プロフィール　：<br>
		<textarea name="prof" cols="45" rows="15"><?= sanitize($old['prof'] ?? '') ?></textarea><br />
		<br>
		アイコン画像　：
		<input type="file" name="image" accept="image/*"><br/>
		<br>
		<input type="hidden" name="mode" value="register">
    <?php embedCSRFToken(); ?>
		<br>
		<input type="button" onclick="history.back()" value="戻る">
		<input type="submit" value="登録">
	</form>
</main>
<?php
// footerの読み込み
require_once __DIR__.'/../includes/footer.php';
?>
</body>
</html>