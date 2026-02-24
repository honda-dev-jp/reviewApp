<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../app/security/session.php';

// セッションを安全に開始
startSecureSession();

// 会員登録処理に入る為セッションID再生成
session_regenerate_id(true);

require_once __DIR__ . '/../app/guards/request_guard.php';
require_once __DIR__ . '/../app/security/csrf.php';
require_once __DIR__ . '/../app/guards/redirect_guard.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/validation/user.php';
require_once __DIR__ . '/../lib/image_upload.php';
require_once __DIR__.'/../lib/exception_handler.php';

// ==============================
// ガード処理
// ==============================

// POSTリクエストかチェック（GET直叩き防止）
requirePost();

// CSRFトークンの検証（成功時にワンタイム消費）
if (!validateCSRFTokenOnce()) {
    redirectWithError('不正な操作が検出されました。');
}

/* =========================================================
   バリエーション処理
   ========================================================= */
$name  = $_POST['name']  ?? '';
$email = $_POST['email'] ?? '';
$pass  = $_POST['pass']  ?? '';
$pass2 = $_POST['pass2'] ?? '';
$prof  = $_POST['prof']  ?? '';

// mode('register') をホワイトリスト化（想定外は不正として弾く）
$mode = $_POST['mode'] ?? '';
$allowedModes = ['register'];

if (!in_array($mode, $allowedModes, true)) {
    redirectWithError('不正な操作が検出されました。');
}

$errors = validateUser($_POST, $_FILES, $mode);

if (!empty($errors)) {
  $_SESSION['error'] = $errors;
  $_SESSION['old'] = $_POST;
  unset($_SESSION['old']['pass'], $_SESSION['old']['pass2']);
  redirectTo('/profile/profile_add.php');
}

/* =========================================================
   メイン処理
   ========================================================= */
try {
  // DB接続
  $pdo = getPdo();

  // トランザクション開始
  $pdo->beginTransaction();

  // メールアドレスの重複チェック
  $sql  = 'SELECT user_id FROM users WHERE email=?';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    throw new RuntimeException('このメールアドレスは既に使用されています。');
  }

  // 画像アップロード処理（ここだけ単純化：$img を作らず $_FILES['image'] を直接見る）
  $savedImageName = null;
  if (!empty($_FILES['image']['name'])) {
    $savedImageName = uploadImage($_FILES['image'], __DIR__ . '/../images/icon');
  }

  // パスワードのハッシュ化
  $hash = password_hash($pass, PASSWORD_DEFAULT);

  // ユーザー情報の挿入
  $sql  = 'INSERT INTO users (name, email, pass, prof, image, role) VALUES (?,?,?,?,?,?)';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
      $name,
      $email,
      $hash,
      $prof,
      $savedImageName,  // 画像は任意
      'member'
  ]);

  // user_idをコミット前に確保
  $newUserId = $pdo->lastInsertId();

  // コミット
  $pdo->commit();

  // ==============================
  // 完了処理
  // ==============================

  // セッション作成
  $_SESSION['user_id'] = $newUserId;
  $_SESSION['name']    = $name;
  $_SESSION['role']    = 'member';

  // フラッシュメッセージをセットし、マイページへリダイレクト
  redirectWithSuccess('ユーザー登録が完了しました。', '/mypage/mypage.php');

} catch (RuntimeException $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  // 画面に出したい業務エラーメッセージをそのまま積む
  $_SESSION['error'] = $_SESSION['error'] ?? [];
  $_SESSION['error'][] = $e->getMessage();
  $_SESSION['old'] = $_POST;
  unset($_SESSION['old']['pass'], $_SESSION['old']['pass2']);

  redirectTo('/profile/profile_add.php');
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  handleDbError($e, '/profile/profile_add.php');
}
