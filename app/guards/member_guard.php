<?php
declare(strict_types=1);

/**
 * 会員権限を確認する認可ガード関数。
 *
 * セッションが未開始の場合は session_start() を実行し、セッションに設定されたユーザー権限（role）が 'member' であるかを確認する。
 * 会員以外のユーザーがアクセスした場合は、エラーメッセージをセッションに格納した上でトップページへリダイレクトし、処理を中断する。
 *
 * 【仕様】
 * - session_status() によりセッション開始状態を判定
 * - $_SESSION['role'] が 'member' の場合のみ通過
 * - 会員以外の場合はリダイレクトして exit
 *
 * 【前提】
 * - ログイン処理により $_SESSION['role'] が設定されていること
 * - role の値は 'admin' または 'member' を想定
 *
 * 【注意】
 * - header() を使用するため、事前に出力が行われていないこと
 * - checkLogin() 実行後に呼び出すことを推奨
 *
 * @return void
 */

require_once __DIR__ . '/../../lib/utils.php'; // 共通化したutils.phpをインクルード

function checkMember(): void
{
  // セッション未開始なら開始（事故防止）
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }

  // 会員以外はアクセス不可
  if ($_SESSION['role'] !== 'member') {
    $_SESSION['error'][] = "会員専用ページにアクセスできません。";

    // ベースURLを取得して、リダイレクト先のURLを組み立て
    $baseUrl = getBaseUrl();
    $location = $baseUrl . '/index.php'; // ベースURLに `/index.php` を追加

    header('Location: ' . $location);
    exit;
  }
}
