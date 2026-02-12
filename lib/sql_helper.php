<?php
declare(strict_types=1);

/**
 * SQL 構築用の補助関数群
 *
 * このファイルには、
 * - SQL 文の安全な組み立てを補助する関数
 * - 特定のテーブルや業務ロジックに依存しない関数
 * を集約する。
 *
 * 【設計方針】
 * - DB 接続処理（PDO生成）は含めない
 * - セッション・HTTP・画面遷移には依存しない
 * - pure function（副作用なし）を基本とする
 *
 * 主な利用箇所：
 * - IN 句を使った SELECT / DELETE / UPDATE
 * - 一括処理（削除・取得など）
 */

/**
 * SQL の IN 句用プレースホルダ文字列を生成する
 *
 * 配列の件数に応じて「?, ?, ?」形式の文字列を返す。
 * PDO のプリペアドステートメントと組み合わせて使用する。
 *
 * 例：
 *   $ids = [10, 20, 30];
 *   $placeholders = buildInPlaceholders(count($ids));
 *
 *   // 結果：
 *   // "?, ?, ?"
 *
 *   $sql = "SELECT * FROM reviews WHERE review_id IN ($placeholders)";
 *   $stmt = $pdo->prepare($sql);
 *   $stmt->execute($ids);
 *
 * 【注意】
 * - $count は 1 以上であることを前提とする
 * - 0 件の場合は呼び出し側でバリデーションを行うこと
 *   （IN () という不正SQLを防ぐため）
 *
 * @param int $count
 *   IN 句に含める要素数（通常は配列の件数）
 *
 * @return string
 *   プレースホルダ文字列（例: "?, ?, ?"）
 */
function buildInPlaceholders(int $count): string
{
    return implode(',', array_fill(0, $count, '?'));
}
