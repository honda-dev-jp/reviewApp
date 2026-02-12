<?php
require_once __DIR__ . '/../config/env.php'; // 環境設定ファイル
require_once __DIR__ . '/../config/database.php'; // データベース設定ファイル

/**
 * 汎用DB接続関数
 * @return PDO PDOオブジェクト
 * @throws RuntimeException
 */
function getPdo(): PDO
{
  // 環境設定を読み込む
  $envConfig = require __DIR__ . '/../config/env.php';
  $dbConfig = require __DIR__ . '/../config/database.php';

  // APP_ENV を取得
  $env = $envConfig['APP_ENV']; // 'local', 'home', 'prod', 'aws'など

  // 環境に基づいた設定を取得
  if (!isset($dbConfig[$env])) {
    throw new RuntimeException('DB設定が存在しません（APP_ENV=' . $env . '）');
  }

  $config = $dbConfig[$env];

  // PDO接続を作成
  return new PDO(
    $config['dsn'],
    $config['user'],
    $config['password'],
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );
}