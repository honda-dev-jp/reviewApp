<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/sanitize.php';

/**
 * CSRF用：セッション開始を保証（事故防止）
 * ※ startSecureSession() を使う設計でも、呼び忘れ対策として入れておく
 */
function ensureSessionStarted(): void
{
  if (session_status() === PHP_SESSION_ACTIVE) {
    return;
  }

  // 可能なら安全なセッション開始を優先（session.php 側の設計を尊重）
  if (function_exists('startSecureSession')) {
    startSecureSession();
    return;
  }

  // 最後の保険（事故防止）
  session_start();
}

/**
 * CSRFトークンをセッションに保存（存在しなければ生成）
 */
function generateCSRFToken(): string
{
  ensureSessionStarted();

  if (empty($_SESSION['csrf_token'])) {
    // 32バイトのランダムトークン（16進で64文字）
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }

  return (string)$_SESSION['csrf_token'];
}

/**
 * フォームにCSRFトークンを埋め込む
 */
function embedCSRFToken(): void
{
  $token = generateCSRFToken();
  echo '<input type="hidden" name="csrf_token" value="' . sanitize($token) . '">';
}

/**
 * CSRFトークンの検証（消費しない）
 * - 一致なら true / 不一致なら false
 */
function validateCSRFToken(): bool
{
  ensureSessionStarted();

  $posted = $_POST['csrf_token'] ?? '';
  $saved  = $_SESSION['csrf_token'] ?? '';

  if ($posted === '' || $saved === '') {
    return false;
  }

  // CSRFトークンの検証
  // 単純な === 比較ではなく hash_equals() を使用することで、
  // 比較途中で処理が終了することによる時間差を防ぎ、
  // トークン推測（タイミング攻撃）を防止する
  return hash_equals($saved, (string)$posted);
}

/**
 * CSRFトークンの検証（成功時にワンタイム消費）
 * - 退会/削除など破壊操作で推奨
 */
function validateCSRFTokenOnce(): bool
{
  if (!validateCSRFToken()) {
    return false;
  }

  unset($_SESSION['csrf_token']);
  return true;
}
