<?php
declare(strict_types=1);

/**
 * review_ids（チェックボックス配列）の検証・正規化ユーティリティ
 *
 * 想定：
 * - フォームの name="review_ids[]" から送られる値を扱う
 * - ユーザー入力は改ざん可能なので、必ず正規化してから DB に渡す
 *
 * 方針：
 * - validator は「検証して、使える形に整えて返す」まで
 * - リダイレクトや exit は呼び出し元（コントローラ/ページ）の責務
 */

/**
 * POST値から review_ids を取り出し、正規化した int 配列を返す。
 *
 * ① 入力値の妥当性チェック
 * - review_ids が存在しない/空なら例外
 * - review_ids が配列でなければ例外（単一値や改ざんを想定）
 *
 * ② 正規化
 * - 各要素を int 化
 * - 0 以下を除外（不正値）
 * - 重複を除去
 * - 正規化後に空なら例外（全て不正値だった等）
 *
 * @param array $post $_POST 全体（または同等の配列）
 * @param string $key review_ids のキー名（デフォルト 'review_ids'）
 * @return int[] 正規化済み review_id 配列
 * @throws InvalidArgumentException 入力が不正な場合
 */
function getNormalizedReviewIdsFromPost(array $post, string $key = 'review_ids'): array
{
    // --- ① 入力値の妥当性チェック（存在・型・空） ---
    if (!isset($post[$key]) || empty($post[$key])) {
        throw new InvalidArgumentException('削除する履歴が選択されていません');
    }
    if (!is_array($post[$key])) {
        throw new InvalidArgumentException('不正な値です。');
    }

    // --- ② 正規化（int化・正の値のみ・重複除去） ---
    $ids = normalizePositiveIntIds($post[$key]);

    // 正規化後に空なら不正（全て 0/負/文字列など）
    if (empty($ids)) {
        throw new InvalidArgumentException('不正な値です。');
    }

    return $ids;
}

/**
 * 任意の配列（主に文字列配列）を「正のint配列」に正規化する。
 *
 * - int化
 * - 0以下除外
 * - 重複除外
 *
 * @param array $rawIds 入力のID配列（例：$_POST['review_ids']）
 * @return int[] 正規化済みID配列
 */
function normalizePositiveIntIds(array $rawIds): array
{
    $ids = [];

    foreach ($rawIds as $val) {
        $id = (int)$val;
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    // 重複を除去し、添字を 0.. に詰め直す
    return array_values(array_unique($ids));
}
