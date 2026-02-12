<?php
declare(strict_types=1);

require_once __DIR__ . '/redirect_guard.php';

/**
 * POST リクエストであることを強制する。
 * GET直叩きや不正遷移を防ぐためのガード。
 *
 * @param string $redirectTo リダイレクト先（アプリルート基準のパス。例: '/index.php'）
 * @param string $message    エラーメッセージ
 * @return void
 */
function requirePost(string $redirectTo = '/index.php', string $message = '不正なアクセスです'): void
{
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    // redirect_guard 側で exit するため、ここ以降は実行されない
    redirectWithError($message, $redirectTo);
  }
}
