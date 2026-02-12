<?php
// flash.php 内で sanitize を読み込み、呼び出し元の読み込み忘れを防ぐ
require_once __DIR__.'/sanitize.php';

/**
 * $_SESSION['success']、の
 * フラッシュメッセージを表示する関数
 * 注意：session_start()後に実行
 */
function outputFlash(): void {
    // 登録・編集・削除の完了表示
  if (!empty($_SESSION['success'])) {
    $successes = is_array($_SESSION['success']) ? $_SESSION['success'] : [$_SESSION['success']];
    echo '<ul class="success-message">';
    foreach ($successes as $message) {
        echo '<li>' . sanitize($message) . '</li>';
    }
    echo '</ul>';

    // セッションの成功情報消去
    unset($_SESSION['success']);
  }

	// エラーメッセージ表示
	if(!empty($_SESSION['error'])){
		$errors = is_array($_SESSION['error']) ? $_SESSION['error'] : [$_SESSION['error']];
		echo '<ul class="error-message">';
		foreach($errors as $message) {
			echo '<li>'.sanitize($message).'</li>';
		}
		echo '</ul>';

		// セッションのエラー情報消去
		unset($_SESSION['error']);
	}
}