<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../app/security/session.php';

// セッションを安全に開始
startSecureSession();

// 編集処理に入る為セッションID再生成
session_regenerate_id(true);

require_once __DIR__ . '/../app/guards/request_guard.php';
require_once __DIR__ . '/../app/guards/auth_guard.php';
require_once __DIR__ . '/../app/guards/redirect_guard.php';
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

// ログインチェック
checkLogin();

// CSRFトークンの検証（成功時にワンタイム消費）
if (!validateCSRFTokenOnce()) {
    redirectWithError('不正な操作が検出されました。');
}

/* =========================================================
   バリエーション処理
   ========================================================= */
$user_id = (int)$_SESSION['user_id'];

$name  = $_POST['name']  ?? '';
$email = $_POST['email'] ?? '';
$pass  = $_POST['pass']  ?? '';
$pass2 = $_POST['pass2'] ?? '';
$prof  = $_POST['prof']  ?? '';

// mode('edit) をホワイトリスト化（想定外は不正として弾く）
$mode = $_POST['mode'] ?? '';
$allowedModes = ['edit'];

if (!in_array($mode, $allowedModes, true)) {
    redirectWithError('不正な操作が検出されました。');
}

$errors = validateUser($_POST, $_FILES, $mode);

if (!empty($errors)) {
	$_SESSION['error'] = $errors;
	$_SESSION['old']   = $_POST;
	unset($_SESSION['old']['pass'], $_SESSION['old']['pass2']);
  redirectTo('/profile/profile_edit.php');
}

/* =========================================================
   メイン処理
   ========================================================= */
try {
  // DB接続
  $pdo = getPdo();

  // トランザクション開始
  $pdo->beginTransaction();

  // ===== 追加：旧画像名を取得（新画像に差し替える時の削除用） =====
  $oldImageName = null;
  $stmt = $pdo->prepare('SELECT image FROM users WHERE user_id = ?');
  $stmt->execute([$user_id]);
  $oldImageName = $stmt->fetchColumn() ?: null;

  // 新画像アップロード時に、削除対象（旧画像）として控える（commit後に消す）
  $imageToDelete = null;
  // 予約名を使っている場合は除外（必要に応じて追加/変更）
  $protectedImages = ['no_image.png', 'default.png'];

  // 画像アップロード処理（ここだけ単純化：$img を作らず $_FILES['image'] を直接見る）
  $savedImageName = null;
  if (!empty($_FILES['image']['name'])) {
    $savedImageName = uploadImage($_FILES['image'], __DIR__ . '/../images/icon');

    // ===== 追加：旧画像があり、保護対象でなく、かつ今回の新画像と異なるなら削除候補 =====
    if ($oldImageName !== null
        && $oldImageName !== ''
        && !in_array($oldImageName, $protectedImages, true)) {
      $imageToDelete = $oldImageName;
    }
  }

	// UPDATE文を組み立て（画像未選択なら image は更新しない）
	$params = [];
	if ($pass !== '') {
		if ($savedImageName !== null) {
			$sql = 'UPDATE users SET email=?, pass=?, name=?, prof=?, image=? WHERE user_id=?';
			$params = [
				$email,
				password_hash($pass, PASSWORD_DEFAULT),
				$name,
				$prof,
				$savedImageName,
				$user_id
			];
		} else {
			$sql = 'UPDATE users SET email=?, pass=?, name=?, prof=? WHERE user_id=?';
			$params = [
				$email,
				password_hash($pass, PASSWORD_DEFAULT),
				$name,
				$prof,
				$user_id
			];
		}
	} else {
		if ($savedImageName !== null) {
			$sql = 'UPDATE users SET email=?, name=?, prof=?, image=? WHERE user_id=?';
			$params = [
				$email,
				$name,
				$prof,
				$savedImageName,
				$user_id
			];
		} else {
			$sql = 'UPDATE users SET email=?, name=?, prof=? WHERE user_id=?';
			$params = [
				$email,
				$name,
				$prof,
				$user_id
			];
		}
	}

	$stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  $pdo->commit();

  // ===== 追加：commit 後に旧画像ファイルを削除 =====
  if ($imageToDelete !== null && $imageToDelete !== $savedImageName) {
    $safeOld = basename($imageToDelete); // パストラバーサル保険
    $oldPath = __DIR__ . '/../images/icon/' . $safeOld;
    if (is_file($oldPath)) {
      @unlink($oldPath);
    }
  }

  // 表示名が変わった場合に反映（必要なら）
  $_SESSION['name'] = $name;

  // フラッシュメッセージをセットし、マイページへリダイレクト
  redirectWithSuccess('プロフィール編集が完了しました。', '/mypage/mypage.php');

} catch (Exception $e) {
	if (isset($pdo) && $pdo->inTransaction()) {
		$pdo->rollBack();
	}

	// DB更新で落ちた場合、先に保存してしまった画像があれば削除（最小のクリーンアップ）
	if ($savedImageName !== null) {
		$path = __DIR__ . '/../images/icon/' . $savedImageName;
		if (is_file($path)) {
			@unlink($path);
		}
	}

	handleDbError($e, '/profile/profile_edit_check.php');
}
