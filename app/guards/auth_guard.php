<?php
declare(strict_types=1);

/**
 * ログイン状態を確認する認証ガード関数。
 *
 * セッションが未開始の場合は session_start() を実行し、
 * セッションに user_id が存在しない場合は未ログインと判断する。
 * 未ログイン時はエラーメッセージをセッションに格納し、
 * ログインページへリダイレクトして処理を中断する。
 *
 * 【仕様】
 * - session_status() を用いてセッション開始状態を判定
 * - ログイン済みの場合は何もせず処理を継続
 * - 未ログインの場合はログインページへリダイレクトして exit
 *
 * 【注意】
 * - header() を使用するため、事前に出力が行われていないこと
 *
 * @return void
 */

require_once __DIR__ . '/../../lib/utils.php'; 

function checkLogin(): void
{
  // セッション未開始なら開始（事故防止）
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }

  // ログインしていない場合は、エラーメッセージを格納してログインページにリダイレクト
  if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $_SESSION['error'][] = 'ログインが必要です。';

    // ベースURLを取得して、リダイレクト先のURLを組み立て
    $baseUrl = getBaseUrl();
    $location = $baseUrl . '/index.php'; // ベースURLに `/index.php` を追加

    header('Location: ' . $location);
    exit;
  }
}
