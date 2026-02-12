<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/utils.php';

/**
 * リダイレクト系ガード（Web層）
 *
 * 目的：
 * - セッションにエラーメッセージ等を積み、指定先へリダイレクトする処理を共通化する
 *
 * 方針：
 * - HTTP（header）とセッションに依存するため lib ではなく app/guards に置く
 * - この関数を通すことで「必ず exit する」ことを保証し、後続処理の誤実行を防ぐ
 */

/**
 * エラーメッセージをセッションへ追加し、指定先へリダイレクトする
 * ※ 必ず exit するため、以降の処理は実行されない
 *
 * @param string $message
 * @param string $location
 * @return never
 */
function redirectWithError(string $message, string $location = ''): never
{
    $_SESSION['error'][] = $message;

    // ベースURLを取得して、リダイレクト先のURLを組み立て
    $baseUrl = getBaseUrl();
    $location = $location ? $baseUrl . $location : $baseUrl . '/index.php'; // デフォルト値を追加

    header('Location: ' . $location);
    exit;
}

/**
 * 成功メッセージをセッションへ追加し、指定先へリダイレクトする
 * ※ 必ず exit するため、以降の処理は実行されない
 *
 * @param string $message
 * @param string $location
 * @return never
 */
function redirectWithSuccess(string $message, string $location = ''): never
{
    $_SESSION['success'][] = $message;

    // ベースURLを取得して、リダイレクト先のURLを組み立て
    $baseUrl = getBaseUrl();
    $location = $location ? $baseUrl . $location : $baseUrl . '/index.php'; // デフォルト値を追加

    header('Location: ' . $location);
    exit;
}

/**
 * メッセージを追加せずに指定先へリダイレクトする（必ず exit）
 *
 * @param string $location
 * @return never
 */
function redirectTo(string $location = ''): never
{
    // ベースURLを取得して、リダイレクト先のURLを組み立て
    $baseUrl = getBaseUrl();
    $location = $location ? $baseUrl . $location : $baseUrl . '/index.php'; // デフォルト値を追加

    header('Location: ' . $location);
    exit;
}