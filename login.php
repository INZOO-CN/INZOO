<?php
session_start();
require_once 'config.php';

// 如果已经登录，重定向到首页
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $activationCode = trim($_POST['activation_code'] ?? '');

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

        // 检查用户是否存在
        $stmt = $pdo->prepare("SELECT id, username, password_hash, is_active FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            // 用户存在，验证密码
            if (password_verify($password, $user['password_hash'])) {
                if ($user['is_active'] == 1) {
                    // 用户已激活，登录成功
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header('Location: index.php');
                    exit;
                } else {
                    // 用户未激活，检查激活码
                    if (!empty($activationCode)) {
                        // 验证激活码
                        $stmt = $pdo->prepare("SELECT id FROM activation_codes WHERE code = ? AND user_id = ? AND is_used = 0");
                        $stmt->execute([$activationCode, $user['id']]);
                        $code = $stmt->fetch();

                        if ($code) {
                            // 激活码有效，激活用户并标记激活码为已使用
                            $pdo->beginTransaction();
                            
                            try {
                                // 激活用户
                                $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                                $stmt->execute([$user['id']]);
                                
                                // 标记激活码为已使用
                                $stmt = $pdo->prepare("UPDATE activation_codes SET is_used = 1, used_at = NOW() WHERE id = ?");
                                $stmt->execute([$code['id']]);
                                
                                $pdo->commit();
                                
                                // 登录用户
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['username'] = $user['username'];
                                header('Location: index.php');
                                exit;
                            } catch (Exception $e) {
                                $pdo->rollBack();
                                $errorMessage = '激活过程中发生错误，请重试';
                            }
                        } else {
                            $errorMessage = '激活码无效';
                        }
                    } else {
                        $errorMessage = '您的账户需要激活，请输入激活码';
                    }
                }
            } else {
                $errorMessage = '密码错误';
            }
        } else {
            // 用户不存在，检查激活码注册
            if (!empty($activationCode)) {
                // 验证激活码是否存在且未使用
                $stmt = $pdo->prepare("SELECT id, user_id FROM activation_codes WHERE code = ? AND is_used = 0");
                $stmt->execute([$activationCode]);
                $code = $stmt->fetch();

                if ($code) {
                    if ($code['user_id'] !== null) {
                        $errorMessage = '此激活码已分配给其他用户';
                    } else {
                        // 激活码有效且未分配，创建新用户
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        
                        $pdo->beginTransaction();
                        
                        try {
                            // 创建用户
                            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, is_active, created_at) VALUES (?, ?, 1, NOW())");
                            $stmt->execute([$username, $passwordHash]);
                            $userId = $pdo->lastInsertId();
                            
                            // 关联激活码到用户
                            $stmt = $pdo->prepare("UPDATE activation_codes SET user_id = ?, is_used = 1, used_at = NOW() WHERE id = ?");
                            $stmt->execute([$userId, $code['id']]);
                            
                            $pdo->commit();
                            
                            // 登录用户
                            $_SESSION['user_id'] = $userId;
                            $_SESSION['username'] = $username;
                            header('Location: index.php');
                            exit;
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $errorMessage = '注册过程中发生错误，请重试';
                        }
                    }
                } else {
                    $errorMessage = '激活码无效';
                }
            } else {
                $errorMessage = '用户不存在';
            }
        }
    } catch (PDOException $e) {
        $errorMessage = '数据库连接错误，请稍后再试';
        error_log('PDOException: ' . $e->getMessage());
    } catch (Exception $e) {
        $errorMessage = '登录过程中发生错误，请重试';
        error_log('Exception: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN管理控制台 - 登录</title>
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
            .form-input-focus {
                @apply focus:ring-2 focus:ring-primary/50 focus:border-primary transition duration-200;
            }
            .btn-hover {
                @apply transition-all duration-300 transform hover:scale-105 hover:shadow-lg;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen flex flex-col font-inter">
    <!-- 主要内容区 - 使用flex实现完全居中 -->
    <main class="flex-grow flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden transform transition-all duration-500 hover:shadow-2xl">
                <div class="p-6 md:p-8">
                    <div class="text-center mb-8">
                        <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fa fa-cloud text-primary text-2xl"></i>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-800">VPN管理系统</h1>
                        <p class="text-gray-500 mt-2">请登录您的账户</p>
                    </div>
                    
                    <?php if (!empty($errorMessage)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <i class="fa fa-exclamation-circle text-red-500 mr-2"></i>
                            <p class="text-red-700 text-sm"><?php echo htmlspecialchars($errorMessage); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="login.php">
                        <div class="space-y-4">
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">用户名</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" id="username" name="username" class="pl-10 w-full rounded-lg border border-gray-300 py-3 px-4 focus:outline-none form-input-focus" placeholder="请输入用户名" required>
                                </div>
                            </div>
                            
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa fa-lock text-gray-400"></i>
                                    </div>
                                    <input type="password" id="password" name="password" class="pl-10 w-full rounded-lg border border-gray-300 py-3 px-4 focus:outline-none form-input-focus" placeholder="请输入密码" required>
                                </div>
                            </div>
                            
                            <div>
                                <label for="activation_code" class="block text-sm font-medium text-gray-700 mb-1">激活码（新用户注册或账户激活时需要）</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa fa-key text-gray-400"></i>
                                    </div>
                                    <input type="text" id="activation_code" name="activation_code" class="pl-10 w-full rounded-lg border border-gray-300 py-3 px-4 focus:outline-none form-input-focus" placeholder="请输入激活码（可选）">
                                </div>
                            </div>
                            
                            <button type="submit" class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg flex items-center justify-center">
                                <i class="fa fa-sign-in mr-2"></i> 登录
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-6 text-center text-sm text-gray-500">
                        <p>没有账户？请联系管理员获取激活码</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- 页脚 - 保持在页面底部 -->
    <footer class="bg-white border-t border-gray-200 py-6 w-full">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <a href="https://offs.inzoo.art/MIT.html" class="text-gray-500 text-sm hover:text-primary transition-colors duration-200">
                        遵循MIT License 开发者：映筑视觉
                    </a>
                </div>
                <div class="flex space-x-4">
                    <a
                        id="cy-effective-orcid-url"
                        class="underline text-gray-400 hover:text-primary transition-colors duration-200 flex items-center"
                        href="https://orcid.org/0009-0005-8345-6998"
                        target="orcid.widget"
                        rel="me noopener noreferrer"
                        style="vertical-align: top">
                        <img
                            src="https://orcid.org/sites/default/files/images/orcid_16x16.png"
                            style="width: 1em; margin-inline-end: 0.5em"
                            alt="ORCID iD icon" />
                        ORCID: 0009-0005-8345-6998
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // 添加表单输入动画效果
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', () => {
                input.parentElement.classList.add('scale-[1.02]');
                input.parentElement.classList.add('transition-all', 'duration-200');
            });
            
            input.addEventListener('blur', () => {
                input.parentElement.classList.remove('scale-[1.02]');
            });
        });
        
        // 滚动时改变导航栏样式（如果有导航栏的话）
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (header) {
                if (window.scrollY > 10) {
                    header.classList.add('py-2', 'shadow-lg');
                    header.classList.remove('py-3', 'shadow-md');
                } else {
                    header.classList.add('py-3', 'shadow-md');
                    header.classList.remove('py-2', 'shadow-lg');
                }
            }
        });
    </script>
</body>
</html>    