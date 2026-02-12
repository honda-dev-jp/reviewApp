<?php
declare(strict_types=1);

/**
 * ユーザー登録・プロフィール編集フォームの入力値を検証する。
 *
 * - $mode が 'register' の場合：パスワード入力は必須
 * - $mode が 'edit' の場合：パスワードは任意（入力がある場合のみ検証）
 * - 画像はアップロードエラー（サイズ超過）のみ検証（ファイル種別や拡張子等は uploadImage 側で扱う想定）
 *
 * @param array $post  送信されたフォーム値（例：$_POST）
 *                    - name  : string（必須、20文字以内）
 *                    - email : string（必須、簡易メール形式チェック）
 *                    - pass  : string（register時は必須、8文字以上の半角英数字、64文字以内）
 *                    - pass2 : string（passと一致すること）
 * @param array $files 送信されたファイル情報（例：$_FILES）
 *                    - image : array（任意。UPLOAD_ERR_* を参照）
 * @param string $mode 'register' または 'edit' を想定。想定外は 'register' として扱う。
 *
 * @return string[] エラーメッセージ配列（エラーがなければ空配列）
 */
function validateUser(array $post, array $files, string $mode): array
{
  $errors = [];

  // mode を正規化（想定外は register 扱い）
  $mode = ($mode === 'edit') ? 'edit' : 'register';

  // 名前(共通)
  if (trim($post['name'] ?? '') === '') {
    $errors[] = 'お名前を入力してください。';
  } elseif (mb_strlen($post['name'] ?? '') > 20) {
    $errors[] = 'お名前を20文字以内で入力してください。';
  }

  // メールアドレス(共通)
  if (trim($post['email'] ?? '') === '') {
    $errors[] = 'メールアドレスが入力されていません。';
  } elseif (!preg_match('/^[\w\-.]+@[\w\-.]+\.[a-zA-Z]+$/', (string)($post['email'] ?? ''))) {
    $errors[] = 'メールアドレスを正確に入力してください。';
  }

  // パスワード
  $pass  = $post['pass']  ?? '';
  $pass2 = $post['pass2'] ?? '';

  // register のみ、パスワード未入力をエラー
  if ($mode === 'register' && $pass === '') {
    $errors[] = 'パスワードを入力してください。';
  }

  // パスワードが入力されている場合のみ詳細検証（edit でも pass を変更したい場合に対応）
  if ($pass !== '') {
    if (strlen($pass) > 64) {
      $errors[] = 'パスワードは64文字以内で入力してください。';
    }
    if (!preg_match('/^[a-zA-Z0-9]{8,}$/', $pass)) {
      $errors[] = 'パスワードは8文字以上の半角英数字で入力してください。';
    }
    if ($pass !== $pass2) {
      $errors[] = 'パスワードと確認用パスワードが一致しません。';
    }
  }

  // edit の場合、パスワードと確認用が空ならパスワード更新しない
  if ($mode === 'edit' && $pass === '' && $pass2 === '') {
      // パスワードが空の場合は更新しない
      // ここでは何もしない（バリデーションのみ）
  } elseif ($mode === 'edit' && $pass === '' && $pass2 !== '') {
      $errors[] = 'パスワードが未入力ですが、確認用パスワードが入力されています。';
  }

  // 画像ファイル：ここでは「アップロードエラーが出ていないか」だけを見る（任意）
  if (isset($files['image'])) {
    $err = $files['image']['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
      $errors[] = '画像のサイズが大きすぎます。';
    }
  }

  return $errors;
}
