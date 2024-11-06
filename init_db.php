<?php
// 连接到 SQLite 数据库
$db = new SQLite3('messages.db');

// 创建留言表
$db->exec("CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

echo "数据库和表已成功创建！";
?> 