<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../app/security/session.php';

// セッションを安全に開始
startSecureSession();

require_once __DIR__ . '/../app/security/csrf.php';
require_once __DIR__ . '/../app/guards/request_guard.php';
require_once __DIR__ . '/../app/guards/redirect_guard.php';
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/exception_handler.php';

/* =========================================================
   ガード処理
   ========================================================= */

// POSTリクエストかチェック
requirePost();

// CSRFトークンの検証
if (!validateCSRFTokenOnce()) {
    redirectWithError('不正な操作が検出されました。');
}

/* =========================================================
   メイン処理
   ========================================================= */
try{
	// フォームからの入力を取得
	$email = $_POST['email'] ?? '';
	$pass = $_POST['pass'] ?? '';

	// DB接続
	$pdo = getPdo();

	// SQL文の実行
	$sql = 'SELECT user_id, name, pass, role  FROM users WHERE email=?';
	$stmt = $pdo -> prepare($sql);
	$data = [];
	$data[] = $email;
	$stmt -> execute($data);

	// DB切断
	$pdo = null;

	// レコードの取得
	$rec = $stmt -> fetch();

	if($rec === false || !password_verify($pass, $rec['pass'])) {
		// ログイン失敗
    redirectWithError('メールアドレスかパスワードが間違っています。');
	} else {
    
    // ログイン成功
    session_regenerate_id(true);
		$_SESSION['user_id'] = $rec['user_id'];
		$_SESSION['name'] = $rec['name'];
		$_SESSION['role'] = $rec['role'];

    redirectWithSuccess('ログインしました。', '/mypage/mypage.php');
	}

} catch(Exception $e) {
	handleDbError($e);
}
?>