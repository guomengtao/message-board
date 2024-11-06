<?php
// 连接到 SQLite 数据库
$db = new SQLite3('messages.db');

// 创建管理员表
$db->exec("CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// 创建留言表
$db->exec("CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    content TEXT NOT NULL,
    is_hidden INTEGER DEFAULT 0,
    parent_id INTEGER DEFAULT NULL,
    is_admin INTEGER DEFAULT 0,
    reply_level INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES messages(id)
)");

// 创建默认管理员账号
$default_username = 'admin';
$default_password = password_hash('admin123', PASSWORD_DEFAULT);

try {
    $db->exec("INSERT INTO admins (username, password) VALUES ('$default_username', '$default_password')");
    echo "默认管理员账号创建成功！\n";
} catch (Exception $e) {
    echo "默认管理员账号已存在。\n";
}

echo "数据库和表已成功创建！";
?> 