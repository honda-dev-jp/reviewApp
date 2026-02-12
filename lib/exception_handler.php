<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/guards/redirect_guard.php';

/**
 * DB処理中に発生した例外を共通的に処理するハンドラ。
 *
 * - 例外内容を PHP の error_log に記録する（開発者向け）
 * - ユーザー向けの統一エラーメッセージを $_SESSION['error'] に追加する
 * - 指定URLへリダイレクトし、処理を終了する
 *
 * 注意：
 * - トランザクションの rollBack() は本関数では行わない。
 *   必要な場合は呼び出し側の catch 内で実施すること。
 * - 本関数はリダイレクトして exit するため、呼び出し元に制御は戻らない。
 *
 * @param Throwable $e 発生した例外（PDOException / Error 等を含む）
 * @param string $redirectTo リダイレクト先（アプリルート基準のパス推奨）
 * @param string|null $userMessage ユーザー表示用メッセージ（nullなら既定文言）
 * @return never
 */
function handleDbError(Throwable $e, string $redirectTo = '/index.php', ?string $userMessage = null): never
{
  error_log(sprintf(
    '[%s] %s in %s:%d',
    get_class($e),
    $e->getMessage(),
    $e->getFile(),
    $e->getLine()
  ));

  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }

  $message = $userMessage ?? 'システムエラーが発生しました。しばらくしてから再度お試しください。';

  // redirect_guard 側で $_SESSION['error'][] に積んで exit まで完結させる
  redirectWithError($message, $redirectTo);
}
