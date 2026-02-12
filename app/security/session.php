<?php
declare(strict_types=1);

/**
 * セッションを安全に開始する（PHP 7.2 / 7.4 / 8.4 対応）
 *
 * 方針：
 * - PHP 7.2 : session_start() を確実に動かす（SameSiteは付けない）
 * - PHP 7.4+: Cookie params を配列指定し SameSite=Lax を付与
 * - Secure は「HTTPS判定」を基本としつつ、既存の DB_ENV=dev も尊重（後方互換）
 */
function startSecureSession(): void
{
  if (session_status() === PHP_SESSION_ACTIVE) {
    return;
  }

  // HTTPS 判定（基本）
  $isHttps =
    (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

  // DB_ENV が prod / aws のときのみ HTTPS扱い（Secure=true）
  $isHttpsEnv = defined('APP_ENV') && in_array(APP_ENV, ['prod', 'aws'], true);

  // Secure は「HTTPS判定 or prod/aws運用」のどちらかで true
  $secure = $isHttps || $isHttpsEnv;

  // PHP 7.4+：SameSite を付けられる
  if (PHP_VERSION_ID >= 70400) {
    session_set_cookie_params([
      'lifetime' => 0,
      'path'     => '/',
      'secure'   => $secure,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    session_start();
    return;
  }

  // PHP 7.2 / 7.3：配列指定が使えない → 確実に開始（SameSiteは諦める）
  $path = '/';
  if ($secure) {
    $path .= '; secure';
  }
  $path .= '; httponly';

  session_set_cookie_params(0, $path, '', $secure, true);
  session_start();
}
