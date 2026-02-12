<?php
declare(strict_types=1);

/**
 * レビュー・返信投稿に関する入力バリデーション関数群
 *
 * このファイルでは以下を責務とする：
 * - ユーザー入力値（$_POST）の妥当性チェックのみを行う
 * - 空チェック、数値範囲チェック、最低限の型確認を担当
 * - DBアクセスやセッション判定は行わない
 *
 * DBとの整合性チェック（item_id / review_id の存在確認など）は
 * 呼び出し元（item_detail.php）で行う想定。
 */

/**
 * レビュー投稿の入力バリデーション
 *
 * - レビュー本文が空でないこと
 * - 評価（rating）が 1〜5 の範囲であること
 *
 * @param array $post 送信されたフォーム値（例：$_POST）
 *                    - comment : string（必須）
 *                    - rating  : int|string（必須。1〜5 の数値）
 *
 * @return string[] エラーメッセージ配列
 *                  バリデーションエラーがなければ空配列を返す
 */
function validateAddReview(array $post): array
{
    $errors = [];

    $comment = trim((string)($post['comment'] ?? ''));
    $rating  = (int)($post['rating'] ?? 0);

    if ($comment === '') {
        $errors[] = 'レビュー本文が空です。';
    }

    if ($rating < 1 || $rating > 5) {
        $errors[] = '評価は1〜5で選んでください。';
    }

    return $errors;
}

/**
 * 返信投稿の入力バリデーション
 *
 * - 返信本文が空でないこと
 * - review_id が正の整数であること
 *
 * @param array $post 送信されたフォーム値（例：$_POST）
 *                    - reply_text : string（必須）
 *                    - review_id  : int|string（必須。正の整数）
 *
 * @return string[] エラーメッセージ配列
 *                  バリデーションエラーがなければ空配列を返す
 */
function validateAddReply(array $post): array
{
    $errors = [];

    $reply_text = trim((string)($post['reply_text'] ?? ''));
    $review_id  = (int)($post['review_id'] ?? 0);

    if ($review_id <= 0) {
        $errors[] = '返信先レビューが不正です。';
    }

    if ($reply_text === '') {
        $errors[] = '返信本文が空です。';
    }

    return $errors;
}
