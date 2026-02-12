<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../app/security/session.php';

// セッションを安全に開始
startSecureSession();

// 退会処理の為セッションID再生成
session_regenerate_id(true);

require_once __DIR__ . '/../app/security/csrf.php';
require_once __DIR__ . '/../app/guards/auth_guard.php';
require_once __DIR__ . '/../app/guards/request_guard.php';
require_once __DIR__ . '/../app/guards/member_guard.php';
require_once __DIR__ . '/../app/guards/redirect_guard.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/exception_handler.php';

// ==============================
// ガード処理
// ==============================

// ログインチェック
checkLogin();

// POSTリクエストかチェック（GET直叩き防止）
requirePost();

// role が会員かチェック（管理者は不可）
checkMember();

// confirm 画面を経由しているかチェック（ワンタイム）
if (empty($_SESSION['account_delete_confirmed'])) {
    redirectWithError('不正なアクセスです');
}
unset($_SESSION['account_delete_confirmed']);

// CSRFトークンの検証（成功時にワンタイム消費）
if (!validateCSRFTokenOnce()) {
    redirectWithError('不正な操作が検出されました。');
}

try {
  // ==============================
  // DB処理
  // ==============================

  // DB接続
  $pdo = getPdo();

  // ログイン中ユーザーIDを取得
  $userId = (int)$_SESSION['user_id'];

  // ユーザー削除（退会処理）
  $sql = 'DELETE FROM users WHERE user_id = ?';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$userId]);

  // DELETE が正しく 1件だけ実行されたか確認
  // ・0件の場合：既に削除されている / user_id 不整合 / 不正遷移の可能性
  // ・2件以上の場合：設計・DB状態の異常（通常は起こらない）
  // 想定外の件数は安全側に倒し、失敗扱いとする
  if ($stmt->rowCount() !== 1) {
    redirectWithError('退会処理に失敗しました。');
  }

  // ==============================
  // セッション処理
  // ==============================

  // ログイン情報を破棄（フラッシュを残すため session_destroy はしない）
  unset(
      $_SESSION['user_id'],
      $_SESSION['name'],
      $_SESSION['role']
  );

  // セッションIDを再生成（固定化対策：退会後は別セッションへ）
  session_regenerate_id(true);

  // ==============================
  // 完了処理
  // ==============================

  // フラッシュメッセージをセットし、トップページへリダイレクト
  redirectWithSuccess('退会されました。');

} catch (Throwable $e) {
    // DB例外発生時は共通ハンドラで処理
    handleDbError($e);
}
