# 多主题留言板 v2.1.0

一个基于 PHP 和 SQLite 的现代化留言板系统，支持多种主题风格切换和管理功能。

## 最新功能 (v2.1.0)

### 前台新功能
- 支持暗黑模式切换
- 支持多种主题风格
- 支持回复功能
- 分页显示（每页10条）
- 异步提交留言
- 实时字数统计

### 后台新功能
- 管理员登录系统
- 暗黑模式支持
- 留言管理功能
- 实时消息提醒
- 分页管理界面

## 主题系统

### 基础主题
- 现代风格（默认）
- 暗黑风格 - 护眼夜间模式
- 森林风格 - 自然清新
- 海洋风格 - 蓝色清爽

### 特色主题
- macOS 风格 - 苹果设计风格
- 代码风格 - 程序员专属
- 机械风格 - 工业化设计
- Bootstrap 风格 - 经典 Web
- 生态环保风格 - 低能耗设计

## 功能特点

### 前台功能
- 无需登录即可留言
- 支持 1000 字留言
- 支持回复功能
- 实时字数统计
- 异步提交体验
- 多主题切换
- 响应式设计

### 后台功能
- 管理员登录系统
- 留言管理（隐藏/显示/删除）
- 回复留言功能
- 实时消息提醒
- 分页管理
- 暗黑模式支持

## 安装说明

1. 环境要求：
   - PHP 7.0+
   - SQLite3 扩展
   - 现代浏览器（支持 ES6）

2. 安装步骤：
```bash
# 克隆仓库
git clone https://github.com/guomengtao/message-board.git

# 进入项目目录
cd message-board

# 初始化数据库
php init_db.php

# 启动开发服务器
php -S localhost:8000
```

## 使用指南

### 前台使用
1. 访问首页发布留言
2. 支持回复其他留言
3. 切换不同主题风格
4. 使用暗黑模式

### 后台使用
1. 访问 admin_login.php 登录
   - 默认用户名：admin
   - 默认密码：admin123
2. 管理留言
   - 回复留言
   - 隐藏/显示留言
   - 删除留言
3. 切换暗黑模式

## 技术特点
- 原生 JavaScript 实现
- CSS 变量实现主题系统
- SQLite 数据库存储
- 异步通信
- 响应式设计
- 性能优化

## 安全特性
- SQL 注入防护
- XSS 攻击防护
- 密码加密存储
- 会话管理
- 输入验证

## 后续计划
- [ ] 添加更多主题风格
- [ ] 支持 Emoji 表情
- [ ] 添加留言点赞功能
- [ ] 优化移动端体验
- [ ] 添加管理统计功能

## 贡献指南
欢迎提交 Issue 和 Pull Request 来帮助改进项目。

## 许可证
MIT License - 详见 [LICENSE](LICENSE) 文件