<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$db = new SQLite3('messages.db');

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $message_id = (int)($_POST['message_id'] ?? 0);

    switch ($action) {
        case 'reply':
            $content = $_POST['content'] ?? '';
            if ($content) {
                $content = SQLite3::escapeString($content);
                $db->exec("INSERT INTO messages (content, parent_id, is_admin) 
                          VALUES ('$content', $message_id, 1)");
            }
            break;

        case 'hide':
            $db->exec("UPDATE messages SET is_hidden = 1 WHERE id = $message_id");
            break;

        case 'show':
            $db->exec("UPDATE messages SET is_hidden = 0 WHERE id = $message_id");
            break;

        case 'delete':
            $db->exec("DELETE FROM messages WHERE id = $message_id OR parent_id = $message_id");
            break;
    }
}

// 添加分页设置
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 获取总留言数和总页数
$total_messages = $db->querySingle("SELECT COUNT(*) FROM messages WHERE parent_id IS NULL");
$total_pages = ceil($total_messages / $per_page);

// 修改查询语句，添加分页
$messages = $db->query("
    SELECT m1.*, 
           GROUP_CONCAT(
               json_object(
                   'id', m2.id,
                   'content', m2.content,
                   'created_at', m2.created_at,
                   'is_admin', m2.is_admin,
                   'is_hidden', m2.is_hidden
               )
           ) as replies
    FROM messages m1
    LEFT JOIN messages m2 ON m1.id = m2.parent_id
    WHERE m1.parent_id IS NULL
    GROUP BY m1.id
    ORDER BY m1.created_at DESC
    LIMIT $per_page OFFSET $offset
");

// 检查查询是否成功
if ($messages === false) {
    die("查询错误：" . $db->lastErrorMsg());
}

if (isset($_GET['fetch']) && $_GET['fetch'] === 'latest') {
    header('Content-Type: application/json');
    
    // 获取客户端已有的最大消息ID
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    // 获取最新的消息
    $latest_messages = $db->query("
        SELECT m1.*, 
               GROUP_CONCAT(
                   json_object(
                       'id', m2.id,
                       'content', m2.content,
                       'created_at', m2.created_at,
                       'is_admin', m2.is_admin,
                       'is_hidden', m2.is_hidden
                   )
               ) as replies
        FROM messages m1
        LEFT JOIN messages m2 ON m1.id = m2.parent_id
        WHERE m1.parent_id IS NULL
        AND m1.id > $last_id
        GROUP BY m1.id
        ORDER BY m1.created_at DESC
    ");

    $new_messages = [];
    while ($row = $latest_messages->fetchArray(SQLITE3_ASSOC)) {
        $new_messages[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $new_messages
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>管理面板</title>
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
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --header-bg: white;
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
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --header-bg: #2d2d2d;
        }

        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: var(--header-bg);
            border-radius: 8px;
            box-shadow: 0 2px 4px var(--shadow-color);
        }

        .message-item {
            background: var(--card-bg);
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px var(--shadow-color);
            transition: all 0.3s ease;
        }

        .message-content {
            margin-bottom: 10px;
        }

        .message-meta {
            color: var(--text-color);
            opacity: 0.7;
            font-size: 0.9em;
        }

        textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--input-bg);
            color: var(--text-color);
            resize: vertical;
            transition: all 0.3s ease;
        }

        button {
            padding: 8px 15px;
            margin-right: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-reply { 
            background: var(--success-color); 
            color: white; 
        }

        .btn-hide { 
            background: var(--warning-color);
            color: #333;
        }

        .btn-delete { 
            background: var(--danger-color); 
            color: white; 
        }

        .hidden { 
            opacity: 0.5; 
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
            z-index: 1000;
        }

        .theme-toggle:hover {
            transform: scale(1.1);
        }

        .theme-toggle i {
            font-size: 20px;
            color: var(--text-color);
        }

        a {
            color: var(--button-bg);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        a:hover {
            color: var(--button-hover);
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

        .pagination {
            margin: 20px 0;
            text-align: center;
            padding: 10px;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 4px var(--shadow-color);
        }

        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 4px;
            border-radius: 4px;
            background: var(--bg-color);
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: var(--button-bg);
            color: white;
            transform: translateY(-2px);
        }

        .pagination a.active {
            background: var(--button-bg);
            color: white;
        }

        .pagination .page-info {
            display: inline-block;
            margin-left: 15px;
            color: var(--text-color);
            opacity: 0.7;
        }

        .first, .last {
            background: var(--button-bg) !important;
            color: white !important;
        }

        .prev, .next {
            background: var(--success-color) !important;
            color: white !important;
        }
        
    </style>
</head>
<body>
    <!-- 添加主题切换按钮 -->
    <div class="theme-toggle" onclick="toggleTheme()">
        <i class="material-icons">dark_mode</i>
    </div>

    <div class="header">
        <h1>留言管理</h1>
        <div>
            欢迎, <?php echo htmlspecialchars($_SESSION['admin_username']); ?> |
            <a href="logout.php">退出</a>
        </div>
    </div>

    <div class="message-list">
        <?php while ($message = $messages->fetchArray(SQLITE3_ASSOC)): ?>
            <div class="message-item <?php echo $message['is_hidden'] ? 'hidden' : ''; ?>">
                <!-- 主留言 -->
                <div class="message-content">
                    <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                </div>
                <div class="message-meta">
                    发布时间：<?php echo $message['created_at']; ?>
                    <?php if ($message['is_hidden']): ?>
                        <span>[已隐藏]</span>
                    <?php endif; ?>
                </div>

                <!-- 管理操作 -->
                <div class="actions">
                    <form method="POST" style="display: inline-block;">
                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                        
                        <?php if ($message['is_hidden']): ?>
                            <input type="hidden" name="action" value="show">
                            <button type="submit" class="btn-hide">显示</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="hide">
                            <button type="submit" class="btn-hide">隐藏</button>
                        <?php endif; ?>

                        <button type="submit" name="action" value="delete" 
                                class="btn-delete" 
                                onclick="return confirm('确定要删除这条留言吗？')">
                            删除
                        </button>
                    </form>
                </div>

                <!-- 回复列表 -->
                <?php 
                if ($message['replies']) {
                    $replies = json_decode('[' . $message['replies'] . ']', true);
                    if (is_array($replies)) {
                        foreach ($replies as $reply): 
                            if (isset($reply['content']) && isset($reply['created_at'])):
                ?>
                    <div class="reply-item <?php echo isset($reply['is_hidden']) && $reply['is_hidden'] ? 'hidden' : ''; ?>">
                        <div class="reply-content">
                            <span class="admin-badge">管理员回复</span>
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

                <!-- 回复表单 -->
                <div class="reply-form">
                    <form method="POST">
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                        <textarea name="content" placeholder="输入回复内容..." required></textarea>
                        <button type="submit" class="btn-reply">回复</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- 在 message-list div 的末尾添加分页导航 -->
    <div class="pagination">
        <?php if ($total_pages > 1): ?>
            <?php if ($page > 1): ?>
                <a href="?page=1" class="page-link first">首页</a>
                <a href="?page=<?php echo ($page - 1); ?>" class="page-link prev">上一页</a>
            <?php endif; ?>

            <?php
            // 显示页码
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            for ($i = $start; $i <= $end; $i++): 
            ?>
                <a href="?page=<?php echo $i; ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo ($page + 1); ?>" class="page-link next">下一页</a>
                <a href="?page=<?php echo $total_pages; ?>" class="page-link last">末页</a>
            <?php endif; ?>
            
            <span class="page-info">
                共 <?php echo $total_pages; ?> 页，当前第 <?php echo $page; ?> 页
            </span>
        <?php endif; ?>
    </div>

    <!-- 在 </body> 前添加 JavaScript 代码 -->
    <script>
        // 处理所有表单提交
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // 刷新页面，但不会出现确认弹窗
                    window.location.href = window.location.href;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('操作失败，请重试');
                });
            });
        });

        // 删除确认
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.onclick = function(e) {
                if (!confirm('确定要删除这条留言吗？')) {
                    e.preventDefault();
                    return false;
                }
            };
        });

        // 添加实时更新功能
        function fetchLatestMessages() {
            const currentPage = new URLSearchParams(window.location.search).get('page') || 1;
            fetch(`${window.location.href}&fetch=latest&page=${currentPage}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.messages && currentPage == 1) {
                    updateMessageList(data.messages);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // 更新消息列表
        function updateMessageList(messages) {
            const messageList = document.querySelector('.message-list');
            messages.forEach(message => {
                // 检查消息是否已存在
                if (!document.querySelector(`[data-message-id="${message.id}"]`)) {
                    const messageElement = createMessageElement(message);
                    messageList.insertBefore(messageElement, messageList.firstChild);
                    // 添加动画效果
                    setTimeout(() => messageElement.classList.add('show'), 10);
                }
            });
        }

        // 创建消息元素
        function createMessageElement(message) {
            const div = document.createElement('div');
            div.className = 'message-item new-message';
            div.setAttribute('data-message-id', message.id);
            div.style.opacity = '0';
            div.style.transform = 'translateY(-20px)';

            div.innerHTML = `
                <div class="message-content">
                    ${message.content}
                </div>
                <div class="message-meta">
                    发布时间：${message.created_at}
                </div>
                <div class="actions">
                    <form method="POST" style="display: inline-block;">
                        <input type="hidden" name="message_id" value="${message.id}">
                        <input type="hidden" name="action" value="hide">
                        <button type="submit" class="btn-hide">隐藏</button>
                        <button type="submit" name="action" value="delete" 
                                class="btn-delete" 
                                onclick="return confirm('确定要删除这条留言吗？')">
                            删除
                        </button>
                    </form>
                </div>
                <div class="reply-form">
                    <form method="POST">
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="message_id" value="${message.id}">
                        <textarea name="content" placeholder="输入回复内容..." required></textarea>
                        <button type="submit" class="btn-reply">回复</button>
                    </form>
                </div>
            `;

            // 为新消息添加事件监听器
            attachEventListeners(div);
            return div;
        }

        // 为新消息添加事件监听器
        function attachEventListeners(element) {
            element.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        window.location.href = window.location.href;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('操作失败，请重试');
                    });
                });
            });
        }

        // 添加样式
        const style = document.createElement('style');
        style.textContent = `
            .new-message {
                transition: opacity 0.3s ease, transform 0.3s ease;
            }
            .new-message.show {
                opacity: 1 !important;
                transform: translateY(0) !important;
            }
        `;
        document.head.appendChild(style);

        // 启动定时轮询
        setInterval(fetchLatestMessages, 5000); // 每5秒检查一次新消息

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