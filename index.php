<?php
declare(strict_types=1);

require_once __DIR__ . '/config/env.php'; // 環境設定の読み込み
require_once __DIR__ . '/app/security/session.php'; // セッションの管理

// セッションを安全に開始
startSecureSession();

// キャッシュ無効化（戻る対策）
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/app/security/csrf.php'; // CSRF対策
require_once __DIR__ . '/lib/sanitize.php'; // サニタイズ関数
require_once __DIR__ . '/lib/flash.php'; // フラッシュメッセージ
require_once __DIR__ . '/lib/utils.php';  //BASE_URLの読込み関数

// CSRFトークンの生成
generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <!-- meta情報の読込み -->
  <?php require_once __DIR__ . '/includes/meta.php'; ?>
  <title>仮想映画レビュー　　トップページ</title>
</head>
<body>
  <header>
    <h1>仮想映画レビュー　　トップページ</h1>
  </header>
  <main>
    <!-- エラーメッセージ表示 -->
    <?php outputFlash(); ?>

    <!-- ログインフォーム -->
    <form method="post" action="./login/login_check.php">
      <span class="form">メールアドレス</span>
      <input type="email" name="email" required><br/><br>

      <span class="form">パスワード</span>
      <input type="password" name="pass" required><br/><br>

      <?php embedCSRFToken(); ?> <!-- CSRFトークン埋め込み -->

      <input type="submit" value="ログイン"><br/>
    </form>

    <br>

    <!-- 新規会員登録リンク -->
    <a href="profile/profile_add.php">新規会員登録</a><br/><br>

    <!-- ゲストとして閲覧 -->
    <form action="items/item_list.php">
      <input type="submit" value="ゲストとして見る">
    </form>
  </main>

  <?php
  // footerの読み込み
  require_once __DIR__.'/includes/footer.php';
  ?>

</body>
</html>
