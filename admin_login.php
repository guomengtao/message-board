<?php
session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: admin_panel.php');
    exit;
}

$db = new SQLite3('messages.db');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare('SELECT * FROM admins WHERE username = :username');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $admin = $result->fetchArray(SQLITE3_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        header('Location: admin_panel.php');
        exit;
    } else {
        $error = '用户名或密码错误';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>管理员登录</title>
    <meta charset="utf-8">
    <style>
        :root {
            /* 亮色主题变量 */
            --bg-color: #f5f5f5;
            --card-bg: white;
            --text-color: #333;
            --border-color: #ddd;
            --input-bg: white;
            --input-border: #ddd;
            --button-bg: #007bff;
            --button-hover: #0056b3;
            --shadow-color: rgba(0,0,0,0.1);
            --error-color: #dc3545;
        }

        /* 暗色主题变量 */
        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --card-bg: #2d2d2d;
            --text-color: #e0e0e0;
            --border-color: #404040;
            --input-bg: #333;
            --input-border: #404040;
            --button-bg: #0d6efd;
            --button-hover: #0b5ed7;
            --shadow-color: rgba(0,0,0,0.3);
            --error-color: #ff4444;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            transition: all 0.3s ease;
        }

        .login-form {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px var(--shadow-color);
            width: 300px;
            transition: all 0.3s ease;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--input-border);
            border-radius: 4px;
            box-sizing: border-box;
            background: var(--input-bg);
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--button-bg);
            box-shadow: 0 0 5px rgba(13, 110, 253, 0.25);
        }

        button {
            width: 100%;
            padding: 10px;
            background: var(--button-bg);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        button:hover {
            background: var(--button-hover);
        }

        .error {
            color: var(--error-color);
            margin-bottom: 15px;
        }

        /* 主题切换按钮 */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px var(--shadow-color);
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            transform: scale(1.1);
        }

        .theme-toggle i {
            font-size: 20px;
            color: var(--text-color);
        }

        /* 添加图标字体 */
        @font-face {
            font-family: 'Material Icons';
            font-style: normal;
            font-weight: 400;
            src: url(https://fonts.gstatic.com/s/materialicons/v140/flUhRq6tzZclQEJ-Vdg-IuiaDsNc.woff2) format('woff2');
        }

        .material-icons {
            font-family: 'Material Icons';
            font-weight: normal;
            font-style: normal;
            font-size: 24px;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            -webkit-font-smoothing: antialiased;
        }
    </style>
</head>
<body>
    <div class="theme-toggle" onclick="toggleTheme()">
        <i class="material-icons">dark_mode</i>
    </div>

    <div class="login-form">
        <h2>管理员登录</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">登录</button>
        </form>
    </div>

    <script>
        // 检查并应用保存的主题
        const savedTheme = localStorage.getItem('admin-theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('admin-theme', newTheme);
            
            updateThemeIcon(newTheme);
        }

        function updateThemeIcon(theme) {
            const icon = document.querySelector('.theme-toggle i');
            icon.textContent = theme === 'dark' ? 'light_mode' : 'dark_mode';
        }
    </script>
</body>
</html> 