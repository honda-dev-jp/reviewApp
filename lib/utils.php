<?php
// 環境設定を読み込む
$envConfig = require __DIR__ . '/../config/env.php';

/**
 * ベースURLを取得する関数
 *
 * @return string ベースURL
 */
function getBaseUrl(): string
{
    global $envConfig;
    $appEnv = $envConfig['APP_ENV'] ?? 'local'; // デフォルトは 'local'
    return $envConfig['BASE_URL'][$appEnv] ?? $envConfig['BASE_URL']['local']; // 環境に応じたURLを取得
}
