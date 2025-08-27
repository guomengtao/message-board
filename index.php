<?php
$db = new SQLite3('messages.db');

// 处理留言提交和回复
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'reply') {
        // 处理回复
        $content = $_POST['content'] ?? '';
        $parent_id = (int)($_POST['parent_id'] ?? 0);
        
        if ($content && $parent_id) {
            $content = SQLite3::escapeString($content);
            $query = "INSERT INTO messages (content, parent_id) VALUES ('$content', $parent_id)";
            $success = $db->exec($query);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => $success]);
            exit;
        }
    } elseif (!empty($_POST['message'])) {
        // 处理主留言
        $message = substr($_POST['message'], 0, 1000);
        $message = SQLite3::escapeString($message);
        
        // 检查是否为匿名留言
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        
        $query = "INSERT INTO messages (content, is_anonymous) VALUES ('$message', $is_anonymous)";
        $success = $db->exec($query);
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => $success]);
            exit;
        }
    }
}

// 分页设置
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 获取总留言数和总页数（只计算未隐藏且非匿名的留言）
$total_messages = $db->querySingle("SELECT COUNT(*) FROM messages WHERE is_hidden = 0 AND parent_id IS NULL AND is_anonymous = 0");
$total_pages = ceil($total_messages / $per_page);

// 获取当前页的留言列表（只显示未隐藏且非匿名的留言）
$messages = $db->query("
    SELECT m1.*, 
           GROUP_CONCAT(
               json_object(
                   'content', m2.content,
                   'created_at', m2.created_at,
                   'is_admin', m2.is_admin
               )
           ) as replies
    FROM messages m1
    LEFT JOIN messages m2 ON m1.id = m2.parent_id
    WHERE m1.parent_id IS NULL 
    AND m1.is_hidden = 0
    AND m1.is_anonymous = 0
    GROUP BY m1.id
    ORDER BY m1.created_at DESC 
    LIMIT $per_page OFFSET $offset
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>留言板</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #e74c3c;
            --bg-color: #ecf0f1;
            --text-color: #2c3e50;
        }

        body {
            width: 100%;
            margin: 0;
            padding: 20px;
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            box-sizing: border-box;
        }

        h1 {
            text-align: center;
            color: var(--primary-color);
            font-size: 2.5em;
            margin-bottom: 30px;
            font-weight: 600;
        }

        .message-form {
            width: 100%;
            max-width: 100%;
            margin: 0 auto 40px auto;
            padding: 30px;
            background: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            border-radius: 10px;
            box-sizing: border-box;
        }

        textarea {
            width: 100%;
            height: 150px;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            resize: vertical;
            transition: all 0.3s ease;
            box-sizing: border-box;
            font-family: inherit;
        }

        textarea:focus {
            border-color: var(--accent-color);
            outline: none;
            box-shadow: 0 0 10px rgba(231, 76, 60, 0.1);
        }

        .char-counter {
            margin: 10px 0;
            color: var(--secondary-color);
            font-size: 0.9em;
        }

        button {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        button:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .message-list {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
        }

        .message-item {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            opacity: 1;
            border-left: 4px solid var(--accent-color);
        }

        .message-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .message-item p {
            margin: 0 0 10px 0;
            line-height: 1.6;
            color: var(--text-color);
        }

        .message-item small {
            color: #7f8c8d;
            font-size: 0.85em;
        }

        .pagination {
            margin: 30px 0;
            text-align: center;
        }

        .pagination a {
            display: inline-block;
            padding: 8px 15px;
            margin: 0 3px;
            border-radius: 20px;
            background: white;
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: var(--accent-color);
            color: white;
            transform: translateY(-2px);
        }

        .pagination a.active {
            background: var(--accent-color);
            color: white;
        }

        @keyframes slideIn {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .new-message {
            animation: slideIn 0.5s ease-out;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .message-form {
                padding: 20px;
            }

            h1 {
                font-size: 2em;
            }

            .form-controls {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }

        /* 当前的默认风格，命名为 theme-modern */
        .theme-modern {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #e74c3c;
            --bg-color: #ecf0f1;
            --text-color: #2c3e50;
        }

        /* 暗黑风格 */
        .theme-dark {
            --primary-color: #bb86fc;
            --secondary-color: #03dac6;
            --accent-color: #cf6679;
            --bg-color: #121212;
            --text-color: #ffffff;
        }

        /* 森林风格 */
        .theme-forest {
            --primary-color: #2d5a27;
            --secondary-color: #4a8b38;
            --accent-color: #8bc34a;
            --bg-color: #f1f8e9;
            --text-color: #1b5e20;
        }

        /* 海洋风格 */
        .theme-ocean {
            --primary-color: #006064;
            --secondary-color: #0097a7;
            --accent-color: #00bcd4;
            --bg-color: #e0f7fa;
            --text-color: #006064;
        }

        /* 主题切换按钮样式 */
        .theme-switcher {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px;
            border-radius: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .theme-switcher {
            width: 60px;
            overflow: hidden;
            background: transparent;
            border: none;
            transition: width 0.3s ease;
            backdrop-filter: none;
        }

        .theme-switcher.expanded {
            width: 400px;
        }

        .theme-switcher .theme-btn:not(.toggle-btn) {
            opacity: 0;
            transform: translateX(20px);
            pointer-events: none;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .theme-switcher.expanded .theme-btn:not(.toggle-btn) {
            opacity: 1;
            transform: translateX(0);
            pointer-events: auto;
        }

        .toggle-btn {
            background: transparent !important;
            color: var(--accent-color) !important;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            position: relative;
            z-index: 1001;
            text-align: center;
            line-height: 1;
            white-space: nowrap;
        }



        .toggle-btn:hover {
            color: var(--secondary-color) !important;
            transform: scale(1.05);
        }



        /* 添加提示动画 */


        .theme-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid white;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .theme-btn:hover {
            transform: scale(1.1);
        }

        .theme-btn.active {
            transform: scale(1.2);
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }

        #btn-modern {
            background: #e74c3c;
        }

        #btn-dark {
            background: #bb86fc;
        }

        #btn-forest {
            background: #8bc34a;
        }

        #btn-ocean {
            background: #00bcd4;
        }

        /* 主题切换过渡动画 */
        body {
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .message-form, .message-item, .pagination a {
            transition: background-color 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
        }

        /* 暗色主题特殊样式 */
        .theme-dark .message-form,
        .theme-dark .message-item,
        .theme-dark .pagination a {
            background: #1e1e1e;
            color: #ffffff;
        }

        .theme-dark textarea {
            background: #2d2d2d;
            color: #ffffff;
            border-color: #404040;
        }

        .theme-dark .char-counter {
            color: #bbbbbb;
        }

        /* macOS 风格 */
        .theme-macos {
            --primary-color: #007AFF;
            --secondary-color: #5856D6;
            --accent-color: #007AFF;
            --bg-color: #f5f5f7;
            --text-color: #1d1d1f;
        }

        .theme-macos .message-form {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .theme-macos .message-item {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 10px;
        }

        .theme-macos button {
            background: var(--accent-color);
            font-weight: 500;
        }

        /* 代码风格 */
        .theme-code {
            --primary-color: #00ff00;
            --secondary-color: #00cc00;
            --accent-color: #00ff00;
            --bg-color: #1a1a1a;
            --text-color: #00ff00;
        }

        .theme-code .message-form,
        .theme-code .message-item {
            background: #2a2a2a;
            border: 1px solid #00ff00;
            box-shadow: 0 0 10px rgba(0, 255, 0, 0.2);
        }

        .theme-code textarea {
            background: #1a1a1a;
            color: #00ff00;
            border-color: #00ff00;
            font-family: 'Courier New', monospace;
        }

        .theme-code button {
            background: transparent;
            border: 1px solid #00ff00;
            color: #00ff00;
            font-family: 'Courier New', monospace;
        }

        .theme-code button:hover {
            background: #00ff00;
            color: #1a1a1a;
        }

        /* 机械风格 */
        .theme-mechanical {
            --primary-color: #ff9800;
            --secondary-color: #f57c00;
            --accent-color: #ff9800;
            --bg-color: #263238;
            --text-color: #eceff1;
        }

        .theme-mechanical .message-form,
        .theme-mechanical .message-item {
            background: #37474f;
            border: 2px solid #ff9800;
            box-shadow: inset 0 0 10px rgba(255, 152, 0, 0.3);
            clip-path: polygon(0 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%);
        }

        .theme-mechanical button {
            background: #ff9800;
            clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
        }

        .theme-mechanical textarea {
            background: #263238;
            color: #eceff1;
            border-color: #ff9800;
        }

        /* Bootstrap 风格 */
        .theme-bootstrap {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --accent-color: #0d6efd;
            --bg-color: #f8f9fa;
            --text-color: #212529;
        }

        .theme-bootstrap .message-form {
            background: white;
            border: 1px solid rgba(0,0,0,.125);
        }

        .theme-bootstrap .message-item {
            background: white;
            border: 1px solid rgba(0,0,0,.125);
            border-left: 5px solid #0d6efd;
        }

        .theme-bootstrap button {
            background: #0d6efd;
            font-weight: 400;
            border-radius: 4px;
        }

        .theme-bootstrap button:hover {
            background: #0b5ed7;
        }

        /* 为新主题添加切换按钮样式 */
        #btn-macos {
            background: linear-gradient(45deg, #007AFF, #5856D6);
        }

        #btn-code {
            background: #00ff00;
        }

        #btn-mechanical {
            background: linear-gradient(45deg, #ff9800, #f57c00);
        }

        #btn-bootstrap {
            background: #0d6efd;
        }

        .message-item {
            transition: margin-left 0.3s ease;
        }
        
        .reply-form {
            margin-top: 10px;
            padding: 10px;
            background: rgba(0,0,0,0.05);
            border-radius: 5px;
        }
        
        .reply-actions {
            margin-top: 10px;
        }
        
        .btn-reply {
            background: var(--accent-color);
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .admin-badge {
            background: var(--accent-color);
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8em;
            margin-right: 5px;
        }
        
        .form-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .reply-item {
            margin: 10px 0;
            padding: 10px;
            background: rgba(var(--accent-color-rgb), 0.1);
            border-left: 3px solid var(--accent-color);
            border-radius: 4px;
        }
        
        .reply-meta {
            font-size: 0.8em;
            color: var(--secondary-color);
            margin-top: 5px;
        }

        .form-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
            flex-wrap: wrap;
            gap: 10px;
        }

        .char-counter {
            color: var(--secondary-color);
            font-size: 0.9em;
            white-space: nowrap;
        }

        .form-options {
            display: flex;
            align-items: center;
        }

        .anonymous-checkbox {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            color: var(--secondary-color);
            user-select: none;
            white-space: nowrap;
        }

        .anonymous-checkbox input[type="checkbox"] {
            margin-right: 8px;
            width: 16px;
            height: 16px;
            accent-color: var(--accent-color);
        }

        .anonymous-checkbox:hover {
            color: var(--accent-color);
        }

        .anonymous-checkbox input[type="checkbox"]:checked + .checkmark {
            color: var(--accent-color);
            font-weight: 500;
        }
    </style>
</head>
<body class="theme-modern">
    <div class="theme-switcher">
        <div class="toggle-btn" title="展开主题选择">主题</div>
        <div id="btn-modern" class="theme-btn active" title="现代风格"></div>
        <div id="btn-dark" class="theme-btn" title="暗黑风格"></div>
        <div id="btn-forest" class="theme-btn" title="森林风格"></div>
        <div id="btn-ocean" class="theme-btn" title="海洋风格"></div>
        <div id="btn-macos" class="theme-btn" title="macOS风格"></div>
        <div id="btn-code" class="theme-btn" title="代码风格"></div>
        <div id="btn-mechanical" class="theme-btn" title="机械风格"></div>
        <div id="btn-bootstrap" class="theme-btn" title="Bootstrap风格"></div>
    </div>

    <h1>留言板</h1>
    
    <div class="message-form">
        <form method="POST" id="message-form">
            <textarea name="message" id="message" placeholder="请输入留言内容..." 
                      maxlength="1000" required></textarea>
            <div class="form-controls">
                <div class="char-counter">
                    已输入: <span id="char-count">0</span> 字
                    还可输入: <span id="char-remaining">1000</span> 字
                </div>
                <div class="form-options">
                    <label class="anonymous-checkbox">
                        <input type="checkbox" name="is_anonymous" id="is_anonymous">
                        <span class="checkmark"></span>
                        匿名留言（仅管理员可见）
                    </label>
                </div>
            </div>
            <button type="submit">提交留言</button>
        </form>
    </div>

    <div class="message-list">
        <h2>所有留言</h2>
        <?php while ($row = $messages->fetchArray(SQLITE3_ASSOC)): ?>
            <div class="message-item">
                <!-- 主留言 -->
                <div class="message-content">
                    <?php echo nl2br(htmlspecialchars($row['content'])); ?>
                </div>
                <div class="message-meta">
                    发布时间：<?php echo $row['created_at']; ?>
                </div>

                <!-- 回复列表 -->
                <?php 
                if ($row['replies']) {
                    $replies = json_decode('[' . $row['replies'] . ']', true);
                    if (is_array($replies)) {
                        foreach ($replies as $reply): 
                            if (isset($reply['content']) && isset($reply['created_at'])):
                ?>
                    <div class="reply-item">
                        <div class="reply-content">
                            <?php if (isset($reply['is_admin']) && $reply['is_admin']): ?>
                                <span class="admin-badge">管理员回复</span>
                            <?php endif; ?>
                            <?php echo nl2br(htmlspecialchars($reply['content'] ?? '')); ?>
                        </div>
                        <div class="reply-meta">
                            回复时间：<?php echo htmlspecialchars($reply['created_at'] ?? '未知时间'); ?>
                        </div>
                    </div>
                <?php 
                            endif;
                        endforeach;
                    }
                }
                ?>

                <!-- 回复按钮和表单 -->
                <div class="reply-actions">
                    <button onclick="showReplyForm(<?php echo $row['id']; ?>)" class="btn-reply">
                        回复
                    </button>
                    <div id="reply-form-<?php echo $row['id']; ?>" class="reply-form" style="display: none;">
                        <form method="POST" class="reply-message-form" onsubmit="return submitReply(event, <?php echo $row['id']; ?>)">
                            <textarea name="content" placeholder="请输入回复内容..." required maxlength="1000"></textarea>
                            <div class="form-actions">
                                <button type="submit">提交回复</button>
                                <button type="button" onclick="hideReplyForm(<?php echo $row['id']; ?>)">取消</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>" 
               <?php echo $i == $page ? 'class="active"' : ''; ?>>
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>

    <script>
        const textarea = document.getElementById('message');
        const charCount = document.getElementById('char-count');
        const charRemaining = document.getElementById('char-remaining');

        textarea.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length;
            charRemaining.textContent = 1000 - length;
            
            // 根据剩余字数改变颜色
            if (1000 - length < 100) {
                charRemaining.style.color = '#ff4444';
            } else {
                charRemaining.style.color = '#666';
            }
        });

        // 添加留言项的渐入动画
        document.querySelectorAll('.message-item').forEach((item, index) => {
            setTimeout(() => {
                item.style.opacity = '1';
            }, index * 100);
        });

        // 添加提交按钮的涟漪效果
        document.querySelector('button').addEventListener('click', function(e) {
            if (!textarea.value.trim()) {
                e.preventDefault();
                textarea.classList.add('shake');
                setTimeout(() => textarea.classList.remove('shake'), 500);
            }
        });

        // 添加表单提交处理
        document.getElementById('message-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const textarea = document.getElementById('message');
            const isAnonymous = document.getElementById('is_anonymous').checked;
            
            if (!textarea.value.trim()) {
                return;
            }

            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 如果是匿名留言，不显示在前台
                    if (!isAnonymous) {
                        // 创建新留言元素
                        const messageList = document.querySelector('.message-list');
                        const newMessage = document.createElement('div');
                        newMessage.className = 'message-item new-message';
                        newMessage.style.opacity = '1';
                        
                        const now = new Date().toLocaleString();
                        newMessage.innerHTML = `
                            <p>${textarea.value.replace(/\n/g, '<br>')}</p>
                            <small>发布时间：${now}</small>
                        `;

                        // 插入到留言列表开头
                        const firstMessage = messageList.querySelector('.message-item');
                        if (firstMessage) {
                            messageList.insertBefore(newMessage, firstMessage);
                        } else {
                            messageList.appendChild(newMessage);
                        }
                    }

                    // 清空输入框和复选框
                    textarea.value = '';
                    document.getElementById('is_anonymous').checked = false;
                    charCount.textContent = '0';
                    charRemaining.textContent = '1000';
                    
                    // 显示提交成功提示
                    if (isAnonymous) {
                        alert('匿名留言提交成功！该留言仅管理员可见。');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('提交失败，请重试');
            });
        });

        // 添加主题切换功能
        const themes = {
            'modern': 'theme-modern',
            'dark': 'theme-dark',
            'forest': 'theme-forest',
            'ocean': 'theme-ocean',
            'macos': 'theme-macos',
            'code': 'theme-code',
            'mechanical': 'theme-mechanical',
            'bootstrap': 'theme-bootstrap'
        };

        function switchTheme(themeName) {
            // 移除所有主题类
            document.body.classList.remove(...Object.values(themes));
            // 添加新主题类
            document.body.classList.add(themes[themeName]);
            // 更新按钮状态
            document.querySelectorAll('.theme-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById(`btn-${themeName}`).classList.add('active');
            
            // 更新齿轮按钮颜色为当前主题的强调色
            const toggleBtn = document.querySelector('.toggle-btn');
            if (toggleBtn) {
                toggleBtn.style.color = getComputedStyle(document.documentElement).getPropertyValue('--accent-color');
            }
            
            // 保存主题选择到 localStorage
            localStorage.setItem('preferred-theme', themeName);
        }

        // 初始化主题切换按钮和切换功能
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化主题切换按钮
            document.querySelectorAll('.theme-btn').forEach(btn => {
                if (btn.classList.contains('toggle-btn')) return; // 跳过切换按钮
                
                btn.addEventListener('click', function() {
                    const themeName = this.id.replace('btn-', '');
                    switchTheme(themeName);
                });
            });

            // 添加切换按钮功能
            const toggleBtn = document.querySelector('.toggle-btn');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const themeSwitcher = document.querySelector('.theme-switcher');
                    
                    // 简单切换展开/收起状态
                    themeSwitcher.classList.toggle('expanded');
                });
            }

            // 加载保存的主题
            const savedTheme = localStorage.getItem('preferred-theme');
            if (savedTheme) {
                switchTheme(savedTheme);
            } else {
                // 如果没有保存的主题，设置齿轮按钮为默认主题颜色
                if (toggleBtn) {
                    toggleBtn.style.color = getComputedStyle(document.documentElement).getPropertyValue('--accent-color');
                }
            }
        });

        function showReplyForm(id) {
            document.querySelectorAll('.reply-form').forEach(form => form.style.display = 'none');
            document.getElementById(`reply-form-${id}`).style.display = 'block';
        }

        function hideReplyForm(id) {
            document.getElementById(`reply-form-${id}`).style.display = 'none';
        }

        function submitReply(event, parentId) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'reply');
            formData.append('parent_id', parentId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // 刷新页面显示新回复
                } else {
                    alert('回复失败，请重试');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('提交失败，请重试');
            });
            
            return false;
        }
    </script>
</body>
</html> 