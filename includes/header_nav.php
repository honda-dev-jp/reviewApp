<?php
require_once __DIR__.'/../lib/sanitize.php';
require_once __DIR__.'/../lib/utils.php';

/**
 * $_SESSION['name']を<header><nav>要素に表示する関数
 * 注意：session_start()後に実行
 */
function outputHeaderNav() {
  // ログイン時
  if(!empty($_SESSION['user_id'])) {
    echo '<span>ようこそ';
    echo sanitize($_SESSION['name']);

    // 管理者用
    if($_SESSION['role'] === 'admin'){
      echo 'さん';
    // 会員用
    } else if($_SESSION['role'] === 'member') {
      echo '様';
    }
    echo '</span>';
    echo '<a href="' . getBaseUrl() . '/login/logout.php">ログアウト</a>';
  // ゲスト用
  } else {
    echo '<span>ようこそゲスト様</span>';
    echo '<a href="' . getBaseUrl() . '/index.php">ログイン・会員登録</a>';
  }
}
