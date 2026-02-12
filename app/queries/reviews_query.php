<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/sql_helper.php';

/**
 * reviews テーブル向け Query（ドメイン依存のSQL）
 *
 * 目的：
 * - reviews に関するSQLを画面（controller/view）から分離し、責務を明確化する
 * - 所有者チェック等、セキュリティ上重要な条件を関数に閉じ込める
 *
 * 方針：
 * - テーブルに依存するため lib ではなく app/queries に置く
 * - PDO を引数に取り、副作用（リダイレクト等）は持たせない
 */

/**
 * 指定された review_id のうち「ログインユーザーが所有するレビュー」だけを取得する
 *
 * 用途：
 * - 削除確認画面（review_hist_delete_check.php）で、表示対象を安全に確定する
 *
 * ポイント：
 * - user_id 条件を必ず付ける（他人のレビュー混入防止）
 * - review_id は IN 句でまとめて指定
 *
 * @param PDO $pdo
 * @param int $userId
 * @param int[] $reviewIds
 * @return array<int, array{review_id:int, comment:string, created_at:string}>
 */
function fetchOwnedReviews(PDO $pdo, int $userId, array $reviewIds): array
{
  $placeholders = buildInPlaceholders(count($reviewIds));

  $sql = "
      SELECT review_id, comment, created_at
      FROM reviews
      WHERE user_id = ?
        AND review_id IN ($placeholders)
      ORDER BY created_at DESC
  ";

  $stmt = $pdo->prepare($sql);

  // プレースホルダ順：user_id → review_ids...
  $params = array_merge([$userId], $reviewIds);
  $stmt->execute($params);

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // 取得結果の型を明示的に整える（review_id を int に正規化）
  $out = [];
  foreach ($rows as $row) {
    $out[] = [
      'review_id'   => (int)$row['review_id'],
      'comment'     => (string)$row['comment'],
      'created_at'  => (string)$row['created_at'],
    ];
  }

  return $out;
}

/**
 * 指定された review_id のうち「ログインユーザーが所有する review_id」だけを取得する
 *
 * 用途：
 * - 削除実行（review_hist_delete.php）で、削除対象IDを安全に確定する
 *
 * @param PDO $pdo
 * @param int $userId
 * @param int[] $reviewIds
 * @return int[]
 */
function fetchOwnedReviewIds(PDO $pdo, int $userId, array $reviewIds): array
{
  $placeholders = buildInPlaceholders(count($reviewIds));

  $sql = "
      SELECT review_id
      FROM reviews
      WHERE user_id = ?
        AND review_id IN ($placeholders)
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute(array_merge([$userId], $reviewIds));

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $ownedReviewIds = [];
  foreach ($rows as $row) {
    $ownedReviewIds[] = (int)$row['review_id'];
  }

  return $ownedReviewIds;
}
