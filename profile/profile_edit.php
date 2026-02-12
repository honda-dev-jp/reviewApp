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
require_once __DIR__ . '/../lib/exception_handler.php';
require_once __DIR__ . '/../lib/utils.php';  //BASE_URLの読込み関数
require_once __DIR__ . '/../includes/header_nav.php';

// ==============================
// ガード処理（GET表示ページ）
// ==============================

// ログインチェック
checkLogin();

// CSRFトークンをセッションに保存（存在しなければ生成）
generateCSRFToken();

// バリデーションエラー時の入力値の保持
$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

$user_id = (int)($_SESSION['user_id'] ?? 0);

/* =========================================================
   DB処理（現ユーザー情報取得）
   ========================================================= */
try {
  $pdo = getPdo();

  $sql = 'SELECT * FROM users WHERE user_id = ?';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$user_id]);

  $rec = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

  $pdo = null;

} catch (Exception $e) {
  handleDbError($e, '/profile/profile_edit.php');
}

// サニタイズ（old 優先）
$nameValue  = sanitize($old['name']  ?? $rec['name']  ?? '');
$emailValue = sanitize($old['email'] ?? $rec['email'] ?? '');
$profValue  = sanitize($old['prof']  ?? $rec['prof']  ?? '');

// 画像名（表示に使うなら basename 保険）
$imageName = $rec['image'] ?? '';
$imageNameSafe = basename((string)$imageName);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <!-- meta情報の読込み -->
	<?php require_once __DIR__ . '/../includes/meta.php'; ?>

  <title>仮想映画レビュー　プロフィール編集</title>
</head>
<body>
<header>
  <h1>仮想映画レビュー　プロフィール編集</h1>
  <nav>
    <?php outputHeaderNav(); ?>
  </nav>
</header>

<main>
  <?php outputFlash(); ?>

  <form method="post" action="<?php echo getBaseUrl(); ?>/profile/profile_edit_check.php" enctype="multipart/form-data">
    ユーザーネーム：
    <input type="text" name="name" value="<?= $nameValue ?>" required><br><br>

    メールアドレス：
    <input type="email" name="email" value="<?= $emailValue ?>" required><br><br>

    <p style="color: red; font-weight: bold;">＊パスワードは変更する時のみ入力してください。</p>
    パスワード　　：
    <input type="password" name="pass" value=""><br><br>

    確　認　用　　：
    <input type="password" name="pass2" value=""><br><br>

    プロフィール：<br>
    <textarea name="prof" cols="45" rows="15"><?= $profValue ?></textarea><br><br>

    アイコン画像　：
    <input type="file" name="image"><br><br>

    <input type="hidden" name="mode" value="edit">
    <?php embedCSRFToken(); ?>
    <br>
    <button type="submit">変更する</button>
  </form>

  <br>
  <a href="../mypage/mypage.php">マイページに戻る</a>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
