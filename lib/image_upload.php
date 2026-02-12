<?php

/**
 * 画像を安全にアップロードする
 *
 * @param array  $file $_FILES['image']
 * @param string $dir  保存先ディレクトリ（末尾スラッシュ不要）
 * @return string|null 保存したファイル名（未選択時は null）
 * @throws RuntimeException
 */
function uploadImage(array $file, string $dir): ?string
{
    // 未選択
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    // アップロードエラー
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('画像のアップロードに失敗しました。');
    }

    // tmp_name の存在・正当性
    if (empty($file['tmp_name'])) {
        throw new RuntimeException('一時ファイルが見つかりません。');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('不正なファイルです。');
    }

    // 保存先チェック（必須修正）
    if (!is_dir($dir)) {
        throw new RuntimeException('保存先ディレクトリが存在しません。');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('保存先ディレクトリに書き込めません。');
    }

    // サイズ制限（1MB）
    $maxSize = 1000000;
    if (($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('ファイルサイズが大きすぎます。');
    }

    // MIMEタイプチェック
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('対応していない画像形式です。');
    }

    // 画像として読み取れるか（推奨）
    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('画像ファイルとして読み取れません。');
    }

    // ファイル名生成
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        throw new RuntimeException('ファイル保存に失敗しました。');
    }

    return $filename;
}
