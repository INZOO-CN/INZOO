<?php
session_start();
require_once 'config.php';

// 记录管理员操作日志的函数
function logAdminOperation($pdo, $adminId, $operation, $type) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO admin_operation_logs (admin_id, operation, type, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$adminId, $operation, $type, $ip, $userAgent]);
    } catch (Exception $e) {
        error_log('Failed to log admin operation: ' . $e->getMessage());
    }
}

// 记录用户操作日志的函数
function logUserOperation($pdo, $userId, $operation, $type) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO operation_logs (user_id, operation, type, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $operation, $type]);
    } catch (Exception $e) {
        error_log('Failed to log user operation: ' . $e->getMessage());
    }
}

// 检查用户是否已登录且为管理员
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] !== 1) {
    header('Location: login.php');
    exit;
}

// 获取当前页面
$currentPage = $_GET['page'] ?? 'dashboard';

// 处理表单提交
$action = $_POST['action'] ?? '';
$message = '';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // 处理表单提交
    switch ($action) {
        case 'add_user':
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (empty($username) || empty($password)) {
                throw new Exception('用户名和密码不能为空');
            }

            // 检查用户名是否已存在
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception('用户名已存在');
            }

            // 添加用户
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, is_active, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$username, $passwordHash, $isActive]);
            
            // 记录管理员操作日志
            logAdminOperation($pdo, $_SESSION['user_id'], "添加用户: $username", 'add_user');
            $message = '用户添加成功';
            break;

        case 'delete_user':
            $userId = intval($_POST['user_id'] ?? 0);

            if ($userId <= 0) {
                throw new Exception('无效的用户ID');
            }

            // 不能删除自己
            if ($userId === $_SESSION['user_id']) {
                throw new Exception('不能删除当前登录用户');
            }

            // 获取要删除的用户名
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('用户不存在');
            }
            
            // 删除用户
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            // 记录管理员操作日志
            logAdminOperation($pdo, $_SESSION['user_id'], "删除用户: {$user['username']} (ID: $userId)", 'delete_user');
            $message = '用户删除成功';
            break;

        case 'reset_password':
            $userId = intval($_POST['user_id'] ?? 0);
            $newPassword = trim($_POST['new_password'] ?? '');

            if ($userId <= 0 || empty($newPassword)) {
                throw new Exception('无效的用户ID或新密码');
            }

            // 获取要重置密码的用户名
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('用户不存在');
            }
            
            // 重置密码
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$passwordHash, $userId]);
            
            // 记录管理员操作日志
            logAdminOperation($pdo, $_SESSION['user_id'], "重置用户密码: {$user['username']} (ID: $userId)", 'reset_password');
            $message = '密码重置成功';
            break;

        case 'toggle_user_status':
            $userId = intval($_POST['user_id'] ?? 0);
            $newStatus = intval($_POST['new_status'] ?? 0);

            if ($userId <= 0) {
                throw new Exception('无效的用户ID');
            }

            // 不能封禁管理员账号(UID=1)
            if ($userId === 1) {
                throw new Exception('管理员账号不允许被封禁');
            }

            // 不能封禁自己
            if ($userId === $_SESSION['user_id']) {
                throw new Exception('不能封禁当前登录用户');
            }

            // 获取要修改状态的用户名
            $stmt = $pdo->prepare("SELECT username, is_active FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('用户不存在');
            }

            // 如果状态没有变化
            if ($user['is_active'] == $newStatus) {
                throw new Exception('用户状态已经是' . ($newStatus ? '启用' : '禁用') . '状态');
            }
            
            // 修改用户状态
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->execute([$newStatus, $userId]);
            
            // 记录管理员操作日志
            $actionType = $newStatus ? 'activate_user' : 'deactivate_user';
            $actionDesc = $newStatus ? '启用用户' : '封禁用户';
            logAdminOperation($pdo, $_SESSION['user_id'], "$actionDesc: {$user['username']} (ID: $userId)", $actionType);
            $message = $actionDesc . '成功';
            break;
    }

    // 获取用户列表
    $stmt = $pdo->query("SELECT id, username, is_active FROM users");
    $users = $stmt->fetchAll();

    // 获取日志数据函数
    function getLogData($pdo, $table, $join = '') {
        if ($table === 'operation_logs') {
            $columns = "u.username, ol.operation, ol.type, DATE_FORMAT(ol.created_at, '%Y-%m-%d %H:%i:%s') as time";
        } else {
            $columns = "u.username, ol.operation, ol.type, ol.ip_address, DATE_FORMAT(ol.created_at, '%Y-%m-%d %H:%i:%s') as time";
        }
        
        $stmt = $pdo->prepare("
            SELECT $columns 
            FROM $table ol
            $join
            ORDER BY ol.created_at DESC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    // 根据当前页面获取对应的数据
    if ($currentPage === 'user_logs') {
        $logData = getLogData(
            $pdo, 
            'operation_logs', 
            'JOIN users u ON ol.user_id = u.id'
        );
    } elseif ($currentPage === 'admin_logs') {
        $logData = getLogData(
            $pdo, 
            'admin_operation_logs', 
            'JOIN users u ON ol.admin_id = u.id'
        );
    } else {
        $logData = [];
    }

} catch (PDOException $e) {
    $message = '数据库操作错误，请稍后再试';
    error_log('PDOException: ' . $e->getMessage());
    $logData = [];
} catch (Exception $e) {
    $message = $e->getMessage();
    $logData = [];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN后台管理控制台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#165DFF',
                        secondary: '#00B42A',
                        danger: '#F53F3F',
                        warning: '#FF7D00',
                        info: '#86909C',
                        light: '#F2F3F5',
                        dark: '#1D2129',
                    },
                    fontFamily: {
                        inter: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
            .card-shadow {
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            }
            .btn-hover {
                @apply transition-all duration-300 transform hover:scale-105 hover:shadow-lg;
            }
            .modal-backdrop {
                backdrop-filter: blur(4px);
            }
            .scrollbar-thin {
                scrollbar-width: thin;
            }
            .scrollbar-thin::-webkit-scrollbar {
                width: 6px;
            }
            .scrollbar-thin::-webkit-scrollbar-thumb {
                background-color: rgba(156, 163, 175, 0.5);
                border-radius: 3px;
            }
            .sidebar-active {
                @apply bg-primary/10 text-primary border-r-4 border-primary;
            }
            .status-badge {
                @apply inline-block px-2 py-0.5 rounded-full text-xs font-medium;
            }
            .status-active {
                @apply bg-green-100 text-green-800;
            }
            .status-inactive {
                @apply bg-red-100 text-red-800;
            }
            .admin-badge {
                @apply inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 ml-2;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-inter min-h-screen flex flex-col">
    <div class="flex h-screen overflow-hidden">
        <!-- 侧边栏 -->
        <aside id="sidebar" class="w-64 bg-white shadow-lg z-20 transition-all duration-300 transform lg:translate-x-0" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
            <div class="flex items-center justify-between h-16 px-6 border-b">
                <div class="flex items-center space-x-2">
                    <i class="fa fa-cloud text-primary text-2xl"></i>
                    <h1 class="text-xl font-bold text-primary">VPN管理</h1>
                </div>
                <button id="closeSidebar" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            
            <nav class="px-4 py-6">
                <ul class="space-y-1">
                    <li>
                        <a href="?page=dashboard" class="flex items-center px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors duration-200 <?php echo $currentPage === 'dashboard' ? 'sidebar-active' : ''; ?>">
                            <i class="fa fa-tachometer-alt w-5 h-5 mr-3"></i>
                            <span>首页</span>
                        </a>
                    </li>
                    <li>
                        <a href="?page=user_management" class="flex items-center px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors duration-200 <?php echo $currentPage === 'user_management' ? 'sidebar-active' : ''; ?>">
                            <i class="fa fa-users w-5 h-5 mr-3"></i>
                            <span>用户管理</span>
                        </a>
                    </li>
                    <li>
                        <a href="?page=user_logs" class="flex items-center px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors duration-200 <?php echo $currentPage === 'user_logs' ? 'sidebar-active' : ''; ?>">
                            <i class="fa fa-history w-5 h-5 mr-3"></i>
                            <span>用户操作日志</span>
                        </a>
                    </li>
                    <li>
                        <a href="?page=admin_logs" class="flex items-center px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors duration-200 <?php echo $currentPage === 'admin_logs' ? 'sidebar-active' : ''; ?>">
                            <i class="fa fa-shield-alt w-5 h-5 mr-3"></i>
                            <span>管理员操作日志</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- 主内容区 -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- 顶部导航 -->
            <header class="bg-white shadow-md h-16 z-10 flex items-center justify-between px-6">
                <div class="flex items-center">
                    <button id="openSidebar" class="lg:hidden text-gray-500 hover:text-gray-700 mr-4">
                        <i class="fa fa-bars"></i>
                    </button>
                    <h2 class="text-xl font-bold text-gray-800">
                        <?php echo [
                            'dashboard' => '首页概览',
                            'user_management' => '用户管理',
                            'user_logs' => '用户操作日志',
                            'admin_logs' => '管理员操作日志'
                        ][$currentPage] ?? '未知页面'; ?>
                    </h2>
                    
                </div>
                <div class="flex items-center space-x-6">
                    <a href="logout.php" class="text-gray-700 hover:text-primary transition-colors duration-200 font-medium">退出登录</a>
                </div>
            </header>

            <!-- 消息提示 -->
            <?php if (!empty($message)): ?>
            <div id="message" class="fixed top-20 right-4 p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-0 opacity-100 z-50 bg-green-50 border border-green-200 text-green-700">
                <div class="flex items-center">
                    <i class="fa fa-check-circle mr-2 text-lg"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            </div>
            <script>
                setTimeout(() => {
                    document.getElementById('message').style.opacity = '0';
                    document.getElementById('message').style.transform = 'translateX(100%)';
                }, 3000);
            </script>
            <?php endif; ?>

            <!-- 内容区域 -->
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
                <?php if ($currentPage === 'dashboard'): ?>
                    <!-- 首页内容 -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- 统计卡片 -->
                        <div class="bg-white rounded-xl p-6 card-shadow">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">系统统计</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <p class="text-sm text-gray-500">用户总数</p>
                                    <p class="text-2xl font-bold text-blue-600"><?php echo count($users); ?></p>
                                </div>
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <p class="text-sm text-gray-500">活跃用户</p>
                                    <p class="text-2xl font-bold text-green-600">正在开发</p>
                                </div>
                                <div class="bg-yellow-50 p-4 rounded-lg">
                                    <p class="text-sm text-gray-500">今日连接</p>
                                    <p class="text-2xl font-bold text-yellow-600">正在开发</p>
                                </div>
                                <div class="bg-red-50 p-4 rounded-lg">
                                    <p class="text-sm text-gray-500">封禁用户</p>
                                    <p class="text-2xl font-bold text-red-600">正在开发</p>
                                </div>
                            </div>
                        </div>

                        <!-- 最近活动 -->
                        <div class="bg-white rounded-xl p-6 card-shadow">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">最近活动（正在开发）</h3>
                            <div class="space-y-4">
                                <div class="flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                    <div class="flex-shrink-0 bg-blue-100 p-2 rounded-full">
                                        <i class="fa fa-user-plus text-blue-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-800">管理员添加了新用户 testuser</p>
                                        <p class="text-xs text-gray-500">2分钟前</p>
                                    </div>
                                </div>
                                <div class="flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                    <div class="flex-shrink-0 bg-green-100 p-2 rounded-full">
                                        <i class="fa fa-sign-in-alt text-green-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-800">用户 admin 登录系统</p>
                                        <p class="text-xs text-gray-500">15分钟前</p>
                                    </div>
                                </div>
                                <div class="flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                    <div class="flex-shrink-0 bg-red-100 p-2 rounded-full">
                                        <i class="fa fa-ban text-red-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-800">用户 guest 的账号被封禁</p>
                                        <p class="text-xs text-gray-500">1小时前</p>
                                    </div>
                                </div>
                                <div class="flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                    <div class="flex-shrink-0 bg-purple-100 p-2 rounded-full">
                                        <i class="fa fa-server text-purple-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-800">服务器负载恢复正常</p>
                                        <p class="text-xs text-gray-500">3小时前</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 操作日志预览 -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- 管理员操作日志预览 -->
                        <div class="bg-white rounded-xl p-6 card-shadow h-full flex flex-col">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-gray-800">管理员操作日志</h3>
                                <a href="?page=admin_logs" class="text-primary hover:text-primary/80 text-sm font-medium">查看全部</a>
                            </div>
                            <div class="overflow-y-auto max-h-[300px] scrollbar-thin">
                                <table class="w-full table-auto">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-2">管理员</th>
                                            <th class="px-4 py-2">操作内容</th>
                                            <th class="px-4 py-2">时间</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // 查询管理员日志（dashboard页面专用）
                                        $adminLogs = [];
                                        if ($pdo) {
                                            $stmt = $pdo->prepare("
                                                SELECT u.username, ol.operation, ol.type, 
                                                DATE_FORMAT(ol.created_at, '%Y-%m-%d %H:%i:%s') as time
                                                FROM admin_operation_logs ol
                                                JOIN users u ON ol.admin_id = u.id
                                                ORDER BY ol.created_at DESC
                                                LIMIT 5
                                            ");
                                            $stmt->execute();
                                            $adminLogs = $stmt->fetchAll();
                                        }
                                        ?>
                                        <?php if (!empty($adminLogs)): ?>
                                            <?php foreach ($adminLogs as $log): ?>
                                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                                    <td class="border px-4 py-2 font-medium"><?php echo htmlspecialchars($log['username']); ?></td>
                                                    <td class="border px-4 py-2">
                                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-medium <?php echo $log['type'] === 'login' ? 'bg-blue-100 text-blue-800' : ($log['type'] === 'add_user' ? 'bg-green-100 text-green-800' : ($log['type'] === 'delete_user' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); ?>">
                                                            <?php echo htmlspecialchars($log['type']); ?>
                                                        </span>
                                                        <span class="ml-2"><?php echo htmlspecialchars($log['operation']); ?></span>
                                                    </td>
                                                    <td class="border px-4 py-2 text-sm text-gray-600"><?php echo htmlspecialchars($log['time']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="border px-4 py-2 text-center text-gray-500">暂无管理员操作日志</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 用户操作日志预览 -->
                        <div class="bg-white rounded-xl p-6 card-shadow h-full flex flex-col">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-gray-800">用户操作日志</h3>
                                <a href="?page=user_logs" class="text-primary hover:text-primary/80 text-sm font-medium">查看全部</a>
                            </div>
                            <div class="overflow-y-auto max-h-[300px] scrollbar-thin">
                                <table class="w-full table-auto">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-2">用户</th>
                                            <th class="px-4 py-2">操作内容</th>
                                            <th class="px-4 py-2">时间</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // 查询用户日志（dashboard页面专用）
                                        $userLogs = [];
                                        if ($pdo) {
                                            $stmt = $pdo->prepare("
                                                SELECT u.username, ol.operation, ol.type, 
                                                DATE_FORMAT(ol.created_at, '%Y-%m-%d %H:%i:%s') as time
                                                FROM operation_logs ol
                                                JOIN users u ON ol.user_id = u.id
                                                ORDER BY ol.created_at DESC
                                                LIMIT 5
                                            ");
                                            $stmt->execute();
                                            $userLogs = $stmt->fetchAll();
                                        }
                                        ?>
                                        <?php if (!empty($userLogs)): ?>
                                            <?php foreach ($userLogs as $log): ?>
                                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                                    <td class="border px-4 py-2 font-medium"><?php echo htmlspecialchars($log['username']); ?></td>
                                                    <td class="border px-4 py-2">
                                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-medium <?php echo $log['type'] === 'login' ? 'bg-blue-100 text-blue-800' : ($log['type'] === 'connect' ? 'bg-green-100 text-green-800' : ($log['type'] === 'disconnect' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')); ?>">
                                                            <?php echo htmlspecialchars($log['type']); ?>
                                                        </span>
                                                        <span class="ml-2"><?php echo htmlspecialchars($log['operation']); ?></span>
                                                    </td>
                                                    <td class="border px-4 py-2 text-sm text-gray-600"><?php echo htmlspecialchars($log['time']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="border px-4 py-2 text-center text-gray-500">暂无用户操作日志</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php elseif ($currentPage === 'user_management'): ?>
                    <!-- 用户管理页面 -->
                    <div class="bg-white rounded-xl p-6 card-shadow mb-8">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800">用户管理</h2>
                            <button id="addUserBtn" class="px-4 py-2 bg-primary text-white rounded-lg font-medium btn-hover">
                                <i class="fa fa-plus mr-2"></i> 添加用户
                            </button>
                        </div>
                        
                        <table class="w-full table-auto">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2">用户 ID</th>
                                    <th class="px-4 py-2">用户名</th>
                                    <th class="px-4 py-2">状态</th>
                                    <th class="px-4 py-2">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td class="border px-4 py-2"><?php echo $user['id']; ?></td>
                                        <td class="border px-4 py-2">
                                            <?php echo $user['username']; ?>
                                            <?php if ($user['id'] === 1): ?>
                                                <span class="admin-badge">管理员</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="border px-4 py-2">
                                            <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $user['is_active'] ? '已激活' : '已封禁'; ?>
                                            </span>
                                        </td>
                                        <td class="border px-4 py-2">
                                            <?php if ($user['id'] !== 1): ?>
                                            <button class="toggle-status-btn px-3 py-1 <?php echo $user['is_active'] ? 'bg-danger' : 'bg-secondary'; ?> text-white rounded mr-2 btn-hover" data-user-id="<?php echo $user['id']; ?>" data-current-status="<?php echo $user['is_active']; ?>">
                                                <i class="fa fa-user-lock mr-1"></i> <?php echo $user['is_active'] ? '封禁' : '解封'; ?>
                                            </button>
                                            <?php else: ?>
                                            <button class="px-3 py-1 bg-gray-300 text-gray-500 rounded mr-2 cursor-not-allowed" title="管理员账号不可封禁">
                                                <i class="fa fa-user-lock mr-1"></i> 封禁
                                            </button>
                                            <?php endif; ?>
                                            <button class="reset-password-btn px-3 py-1 bg-info text-white rounded mr-2 btn-hover" data-user-id="<?php echo $user['id']; ?>">
                                                <i class="fa fa-key mr-1"></i> 重置密码
                                            </button>
                                            <?php if ($user['id'] !== $_SESSION['user_id'] && $user['id'] !== 1): ?>
                                            <button class="delete-user-btn px-3 py-1 bg-danger text-white rounded btn-hover" data-user-id="<?php echo $user['id']; ?>">
                                                <i class="fa fa-trash mr-1"></i> 删除
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif (in_array($currentPage, ['user_logs', 'admin_logs'])): ?>
                    <!-- 日志列表页面 -->
                    <div class="bg-white rounded-xl p-6 card-shadow mb-8">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-800">
                                <?php echo $currentPage === 'user_logs' ? '用户操作日志' : '管理员操作日志'; ?>
                            </h2>
                            <div class="flex items-center space-x-4">
                                <div class="relative">
                                    <input type="text" placeholder="搜索..." class="border border-gray-300 rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                                    <i class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                </div>
                                
                            </div>
                        </div>
                        
                        <table class="w-full table-auto">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2">
                                        <?php echo $currentPage === 'user_logs' ? '用户' : '管理员'; ?>
                                    </th>
                                    <th class="px-4 py-2">操作内容</th>
                                    <?php if ($currentPage === 'admin_logs'): ?>
                                    <th class="px-4 py-2">IP地址</th>
                                    <?php endif; ?>
                                    <th class="px-4 py-2">操作时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($logData)): ?>
                                    <?php foreach ($logData as $log): ?>
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="border px-4 py-2 font-medium"><?php echo htmlspecialchars($log['username']); ?></td>
                                            <td class="border px-4 py-2">
                                                <span class="inline-block px-2 py-1 rounded-full text-xs font-medium <?php echo $log['type'] === 'login' ? 'bg-blue-100 text-blue-800' : ($log['type'] === 'connect' ? 'bg-green-100 text-green-800' : ($log['type'] === 'disconnect' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')); ?>">
                                                    <?php echo htmlspecialchars($log['type']); ?>
                                                </span>
                                                <span class="ml-2"><?php echo htmlspecialchars($log['operation']); ?></span>
                                            </td>
                                            <?php if ($currentPage === 'admin_logs'): ?>
                                            <td class="border px-4 py-2 text-sm"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                            <?php endif; ?>
                                            <td class="border px-4 py-2 text-sm text-gray-600"><?php echo htmlspecialchars($log['time']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo $currentPage === 'admin_logs' ? '4' : '3'; ?>" class="border px-4 py-2 text-center text-gray-500">暂无操作日志</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </main>
            
            <!-- 页脚 -->
            <footer class="bg-white border-t py-4 px-6 text-sm text-gray-600">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <a href="https://offs.inzoo.art/MIT.html"class="text-gray-500 text-sm">遵循MIT License 开发者：映筑视觉</a>
                    <div class="mt-2 md:mt-0">
                       <div class="flex items-center inline-block ml-4">
                            <a
                                id="cy-effective-orcid-url"
                                class="underline text-gray-400 hover:text-primary transition-colors duration-200 flex items-center"
                                href="https://orcid.org/0009-0005-8345-6998"
                                target="orcid.widget"
                                rel="me noopener noreferrer"
                                style="vertical-align: top">
                                <img
                                    src="https://orcid.org/sites/default/files/images/orcid_16x16.png"
                                    style="width: 1em; margin-inline-end: 0.5em"alt="ORCID iD icon" />
                                ORCID: 0009-0005-8345-6998  <!-- 优化显示文本 -->
                        </a>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- 添加用户模态框 -->
    <div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center modal-backdrop">
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">添加用户</h3>
                <button id="closeAddUserModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <form id="addUserForm" method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">用户名</label>
                    <input type="text" id="username" name="username" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                    <input type="password" id="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary" required>
                </div>
                <div class="mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" checked class="w-4 h-4 text-primary focus:ring-primary border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-700">启用此用户</span>
                    </label>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelAddUser" class="px-4 py-2 border border-gray-300 rounded-lg font-medium hover:bg-gray-50 transition-colors duration-200">取消</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg font-medium btn-hover">添加</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 重置密码模态框 -->
    <div id="resetPasswordModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center modal-backdrop">
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 transform transition-all duration-300 scale-95 opacity-0" id="resetPasswordModalContent">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">重置密码</h3>
                <button id="closeResetPasswordModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <form id="resetPasswordForm" method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" id="resetUserId" name="user_id">
                <div class="mb-4">
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">新密码</label>
                    <input type="password" id="new_password" name="new_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelResetPassword" class="px-4 py-2 border border-gray-300 rounded-lg font-medium hover:bg-gray-50 transition-colors duration-200">取消</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg font-medium btn-hover">重置</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 删除用户确认模态框 -->
    <div id="deleteUserModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center modal-backdrop">
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 transform transition-all duration-300 scale-95 opacity-0" id="deleteModalContent">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">确认删除</h3>
                <button id="closeDeleteModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <p class="text-gray-700 mb-6">您确定要删除此用户吗？此操作不可撤销。</p>
            <form id="deleteUserForm" method="POST">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" id="deleteUserId" name="user_id">
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelDelete" class="px-4 py-2 border border-gray-300 rounded-lg font-medium hover:bg-gray-50 transition-colors duration-200">取消</button>
                    <button type="submit" class="px-4 py-2 bg-danger text-white rounded-lg font-medium btn-hover">删除</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 封禁/解封用户确认模态框 -->
    <div id="toggleStatusModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center modal-backdrop">
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 transform transition-all duration-300 scale-95 opacity-0" id="toggleStatusModalContent">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800" id="toggleStatusModalTitle">确认操作</h3>
                <button id="closeToggleStatusModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <p class="text-gray-700 mb-6" id="toggleStatusModalMessage">您确定要执行此操作吗？</p>
            <form id="toggleStatusForm" method="POST">
                <input type="hidden" name="action" value="toggle_user_status">
                <input type="hidden" id="toggleStatusUserId" name="user_id">
                <input type="hidden" id="toggleStatusNewStatus" name="new_status">
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelToggleStatus" class="px-4 py-2 border border-gray-300 rounded-lg font-medium hover:bg-gray-50 transition-colors duration-200">取消</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg font-medium btn-hover" id="confirmToggleStatus">确认</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 侧边栏控制
        document.getElementById('openSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('-translate-x-full');
        });
        
        document.getElementById('closeSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.add('-translate-x-full');
        });

        // 添加用户模态框
        document.getElementById('addUserBtn').addEventListener('click', function() {
            document.getElementById('addUserModal').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('modalContent').classList.remove('scale-95', 'opacity-0');
                document.getElementById('modalContent').classList.add('scale-100', 'opacity-100');
            }, 10);
        });

        function closeAddUserModal() {
            document.getElementById('modalContent').classList.remove('scale-100', 'opacity-100');
            document.getElementById('modalContent').classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                document.getElementById('addUserModal').classList.add('hidden');
                document.getElementById('addUserForm').reset();
            }, 300);
        }

        document.getElementById('closeAddUserModal').addEventListener('click', closeAddUserModal);
        document.getElementById('cancelAddUser').addEventListener('click', closeAddUserModal);

        // 重置密码模态框
        document.querySelectorAll('.reset-password-btn').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                document.getElementById('resetUserId').value = userId;
                document.getElementById('resetPasswordModal').classList.remove('hidden');
                setTimeout(() => {
                    document.getElementById('resetPasswordModalContent').classList.remove('scale-95', 'opacity-0');
                    document.getElementById('resetPasswordModalContent').classList.add('scale-100', 'opacity-100');
                }, 10);
            });
        });

        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModalContent').classList.remove('scale-100', 'opacity-100');
            document.getElementById('resetPasswordModalContent').classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                document.getElementById('resetPasswordModal').classList.add('hidden');
                document.getElementById('resetPasswordForm').reset();
            }, 300);
        }

        document.getElementById('closeResetPasswordModal').addEventListener('click', closeResetPasswordModal);
        document.getElementById('cancelResetPassword').addEventListener('click', closeResetPasswordModal);

        // 删除用户模态框
        document.querySelectorAll('.delete-user-btn').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                document.getElementById('deleteUserId').value = userId;
                document.getElementById('deleteUserModal').classList.remove('hidden');
                setTimeout(() => {
                    document.getElementById('deleteModalContent').classList.remove('scale-95', 'opacity-0');
                    document.getElementById('deleteModalContent').classList.add('scale-100', 'opacity-100');
                }, 10);
            });
        });

        function closeDeleteModal() {
            document.getElementById('deleteModalContent').classList.remove('scale-100', 'opacity-100');
            document.getElementById('deleteModalContent').classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                document.getElementById('deleteUserModal').classList.add('hidden');
            }, 300);
        }

        document.getElementById('closeDeleteModal').addEventListener('click', closeDeleteModal);
        document.getElementById('cancelDelete').addEventListener('click', closeDeleteModal);

        // 封禁/解封用户模态框
        document.querySelectorAll('.toggle-status-btn').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const currentStatus = this.getAttribute('data-current-status') === '1';
                
                document.getElementById('toggleStatusUserId').value = userId;
                document.getElementById('toggleStatusNewStatus').value = currentStatus ? '0' : '1';
                
                const modalTitle = document.getElementById('toggleStatusModalTitle');
                const modalMessage = document.getElementById('toggleStatusModalMessage');
                const confirmButton = document.getElementById('confirmToggleStatus');
                
                if (currentStatus) {
                    modalTitle.textContent = '确认封禁';
                    modalMessage.textContent = '您确定要封禁此用户吗？封禁后用户将无法登录系统。';
                    confirmButton.textContent = '封禁';
                    confirmButton.className = 'px-4 py-2 bg-danger text-white rounded-lg font-medium btn-hover';
                } else {
                    modalTitle.textContent = '确认解封';
                    modalMessage.textContent = '您确定要解封此用户吗？解封后用户将可以正常登录系统。';
                    confirmButton.textContent = '解封';
                    confirmButton.className = 'px-4 py-2 bg-secondary text-white rounded-lg font-medium btn-hover';
                }
                
                document.getElementById('toggleStatusModal').classList.remove('hidden');
                setTimeout(() => {
                    document.getElementById('toggleStatusModalContent').classList.remove('scale-95', 'opacity-0');
                    document.getElementById('toggleStatusModalContent').classList.add('scale-100', 'opacity-100');
                }, 10);
            });
        });

        function closeToggleStatusModal() {
            document.getElementById('toggleStatusModalContent').classList.remove('scale-100', 'opacity-100');
            document.getElementById('toggleStatusModalContent').classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                document.getElementById('toggleStatusModal').classList.add('hidden');
            }, 300);
        }

        document.getElementById('closeToggleStatusModal').addEventListener('click', closeToggleStatusModal);
        document.getElementById('cancelToggleStatus').addEventListener('click', closeToggleStatusModal);

        // 平滑滚动
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // 模态框点击外部关闭
        document.getElementById('addUserModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddUserModal();
        });
        
        document.getElementById('resetPasswordModal').addEventListener('click', function(e) {
            if (e.target === this) closeResetPasswordModal();
        });
        
        document.getElementById('deleteUserModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
        
        document.getElementById('toggleStatusModal').addEventListener('click', function(e) {
            if (e.target === this) closeToggleStatusModal();
        });
    </script>
</body>
</html>    