<?php
// 本ファイルは公開用サンプルです。
// 実際の接続情報は各環境に合わせて設定してください。

return [
    // ローカル環境
    'local' => [
        // 例：mysql:dbname=sample_db;host=localhost;charset=utf8mb4
        'dsn' => 'DB種類:dbname=DB名;host=ホスト名;charset=文字コード',
        'user' => 'DBユーザ名',
        'password' => 'パスワード'
    ],
    // 本番環境
    'dev' => [
        'dsn' => 'DB種類:dbname=DB名;host=ホスト名;charset=文字コード',
        'user' => 'DBユーザ名',
        'password' => 'パスワード'
    ]
];
