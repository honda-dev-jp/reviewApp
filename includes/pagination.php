<?php
/**
 * ページネーションを処理する関数群
 *
 * このファイルには、ページネーションの設定、データの取得、ページリンクの生成を行う関数が含まれています。
 * 主に、ページ数の計算、データの取得、およびページリンクの生成を行い、ページネーション機能を実現します。
 */

/**
 * ページネーションを計算してデータを取得する関数
 *
 * この関数は、指定されたページ番号に基づいてデータを取得し、ページネーションの設定を計算して返します。
 *
 * @param PDO $pdo PDOオブジェクト。データベースへの接続を管理します。
 * @param int $perPage 1ページに表示するデータの数（デフォルトは10件）。
 * @param int $page 現在のページ番号（デフォルトは1）。
 * @param string $totalSql 総件数を取得するためのSQLクエリ。
 * @param string $querySql データを取得するためのSQLクエリ（`LIMIT` と `OFFSET` はこの関数内で追加されます）。
 * @param array $bindParams （オプション）SQLクエリにバインドする追加のパラメータ。
 * 
 * @return array ページネーションに関するデータを含む連想配列
 *   - 'stmt' => PDOStatement ページのデータを取得した結果のステートメントオブジェクト
 *   - 'totalPages' => int 総ページ数
 *   - 'page' => int 現在のページ番号
 *   - 'midStart' => int 表示するページ範囲の開始ページ
 *   - 'midEnd' => int 表示するページ範囲の終了ページ
 * 
 * @throws PDOException データベースエラーが発生した場合
 */
function getPagination($pdo, $perPage = 10, $page = 1, $totalSql, $querySql, $bindParams = []) {
    // 総件数を取得
    // $total = (int)$pdo->query($totalSql)->fetchColumn();
    if (!empty($bindParams)) {
        $totalStmt = $pdo->prepare($totalSql);
        foreach ($bindParams as $key => $value) {
            $totalStmt->bindValue($key, $value);
        }
        $totalStmt->execute();
        $total = (int)$totalStmt->fetchColumn();
    } else {
        $total = (int)$pdo->query($totalSql)->fetchColumn();
    }

    $totalPages = max(1, (int)ceil($total / $perPage));

    // 存在しないページ番号は末尾へ丸める（任意だが事故防止）
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    // データ取得（LIMIT / OFFSET）
    $sql = $querySql . ' LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    // 追加のバインドパラメータを適用
    foreach ($bindParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    // ページネーションリンクの表示範囲を計算
    $range = 2; // 現在ページの前後に表示する数
    $start = max(1, $page - $range);
    $end = min($totalPages, $page + $range);

    // 省略表示範囲の設定
    $midStart = max(2, $start);
    $midEnd = min($totalPages - 1, $end);

    return [
        'stmt' => $stmt,
        'totalPages' => $totalPages,
        'page' => $page,
        'midStart' => $midStart,
        'midEnd' => $midEnd
    ];
}

/**
 * ページネーションのリンクを生成する関数
 *
 * @param int $page 現在のページ番号
 * @param int $totalPages 総ページ数
 * @param int $range 現在ページの前後に表示するページ数
 *
 * @return string ページネーションリンクのHTML
 */
function getPaginationLinks($page, $totalPages, $range = 2) {
    // 表示レンジ設定
    $start = max(1, $page - $range);
    $end   = min($totalPages, $page + $range);

    $paginationLinks = '<nav class="pagination" aria-label="pagination">';

    // 前へ（1ページ目では出さない）
    if ($page > 1) {
        $paginationLinks .= '<a href="?page=' . ($page - 1) . '">前へ</a> ';
    }

    // 先頭ページ（1）
    if ($page === 1) {
        $paginationLinks .= '<strong>1</strong> ';
    } else {
        $paginationLinks .= '<a href="?page=1">1</a> ';
    }

    // 先頭側の省略（2ページ目より先がレンジ外なら …）
    if ($start > 2) {
        $paginationLinks .= '<span class="ellipsis">…</span> ';
    }

    // 中央レンジ（2～totalPages-1の範囲だけ）
    $midStart = max(2, $start);
    $midEnd   = min($totalPages - 1, $end);

    for ($p = $midStart; $p <= $midEnd; $p++) {
        if ($p === $page) {
            $paginationLinks .= '<strong>' . $p . '</strong> ';
        } else {
            $paginationLinks .= '<a href="?page=' . $p . '">' . $p . '</a> ';
        }
    }

    // 末尾側の省略（totalPages-1ページより手前がレンジ外なら …）
    if ($end < $totalPages - 1) {
        $paginationLinks .= '<span class="ellipsis">…</span> ';
    }

    // 末尾ページ（総ページが1の場合は既に出ているので除外）
    if ($totalPages > 1) {
        if ($page === $totalPages) {
            $paginationLinks .= '<strong>' . $totalPages . '</strong> ';
        } else {
            $paginationLinks .= '<a href="?page=' . $totalPages . '">' . $totalPages . '</a> ';
        }
    }

    // 次へ（最終ページでは出さない）
    if ($page < $totalPages) {
        $paginationLinks .= '<a href="?page=' . ($page + 1) . '">次へ</a>';
    }

    $paginationLinks .= '</nav>';

    return $paginationLinks;
}
