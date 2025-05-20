<?php
session_start();
require_once 'config.php';
require_once 'tencentcloudapi.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tencentCloudAPI = new TencentCloudAPI(SECRET_ID, SECRET_KEY, REGION);
$serverStatus = '关机';
$serverIP = '未开机';

try {
    $instanceId = INSTANCE_ID;
    $statusInfo = $tencentCloudAPI->describeInstancesStatus($instanceId);
    
    if ($statusInfo) {
        $serverStatus = $statusInfo['status'];
        
        if ($serverStatus === '运行中') {
            $instanceInfo = $tencentCloudAPI->describeInstances($instanceId);
            $serverIP = $instanceInfo['publicIpAddress'] ?: '未分配公网IP';
        }
    }
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN管理控制台</title>
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
            .status-pulse {
                animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            }
            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: 0.5;
                }
            }
            .spinner {
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-inter min-h-screen flex flex-col">
    <!-- 顶部导航 -->
    <header class="bg-white shadow-md fixed w-full z-10 transition-all duration-300" id="header">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <i class="fa fa-cloud text-primary text-2xl"></i>
                <h1 class="text-xl font-bold text-primary">VPN管理控制台</h1>
            </div>
            <div class="flex items-center space-x-6">
                <nav class="hidden md:flex space-x-6">
                    <a href="#" class="text-gray-700 hover:text-primary transition-colors duration-200 font-medium">控制台</a>
                    <a href="#" class="text-gray-500 hover:text-primary transition-colors duration-200">余量查询</a>
                </nav>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">欢迎，<?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <button id="logoutBtn" class="text-gray-500 hover:text-danger transition-colors duration-200">
                        <i class="fa fa-sign-out-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- 主内容区 -->
    <main class="flex-grow pt-20 pb-10">
        <div class="container mx-auto px-4">
            <!-- 状态卡片 -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl p-6 card-shadow transform transition-all duration-300 hover:translate-y-[-5px]">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-700">VPN状态</h3>
                        <span id="statusBadge" class="px-3 py-1 rounded-full text-sm font-medium 
                            <?php echo ($serverStatus === '运行中') ? 'bg-secondary/10 text-secondary' : 'bg-info/10 text-info'; ?>">
                            <?php echo $serverStatus; ?>
                        </span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center 
                            <?php echo ($serverStatus === '运行中') ? 'bg-secondary/20 text-secondary' : 'bg-info/20 text-info'; ?>">
                            <i class="fa fa-server text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">实例 ID</p>
                            <p class="font-semibold text-gray-800"><?php echo INSTANCE_ID; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-6 card-shadow transform transition-all duration-300 hover:translate-y-[-5px]">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-700">公网 IP</h3>
                        <span class="px-3 py-1 rounded-full bg-light text-gray-600 text-sm font-medium">
                            <?php echo ($serverStatus === '运行中') ? '在线' : '离线'; ?>
                        </span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center bg-primary/20 text-primary">
                            <i class="fa fa-globe text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">当前 IP</p>
                            <p id="serverIP" class="font-semibold text-gray-800 break-all"><?php echo $serverIP; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-6 card-shadow transform transition-all duration-300 hover:translate-y-[-5px]">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-700">账户余量</h3>
                        <span class="px-3 py-1 rounded-full bg-warning/10 text-warning text-sm font-medium">
                            开发中
                        </span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center bg-warning/20 text-warning">
                            <i class="fa fa-hourglass-half text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">剩余额度</p>
                            <p class="font-semibold text-gray-800">功能开发中</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 操作面板 -->
            <div class="bg-white rounded-xl p-6 card-shadow mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-6">VPN操作</h2>
                
                <div class="flex flex-col sm:flex-row gap-4 mb-6">
                    <button id="powerOnBtn" class="flex-1 py-3 px-6 bg-secondary text-white rounded-lg font-medium 
                        btn-hover disabled:opacity-50 disabled:cursor-not-allowed"
                        <?php echo ($serverStatus === '运行中') ? 'disabled' : ''; ?>>
                        <i class="fa fa-power-off mr-2"></i> 开机
                    </button>
                    
                    <button id="powerOffBtn" class="flex-1 py-3 px-6 bg-danger text-white rounded-lg font-medium 
                        btn-hover disabled:opacity-50 disabled:cursor-not-allowed"
                        <?php echo ($serverStatus !== '运行中') ? 'disabled' : ''; ?>>
                        <i class="fa fa-power-off mr-2"></i> 关机（不计费）
                    </button>
                    
                    <button id="refreshBtn" class="flex-1 py-3 px-6 bg-primary text-white rounded-lg font-medium 
                        btn-hover disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fa fa-refresh mr-2"></i> 刷新状态
                    </button>
                </div>
                
                <!-- 操作日志 -->
                <div class="bg-gray-50 rounded-lg p-4 max-h-60 overflow-y-auto" id="operationLog">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">操作日志</h4>
                    <div class="space-y-2 text-sm">
                        <p class="text-gray-600"><i class="fa fa-clock-o mr-1"></i> <span class="text-gray-500">加载中...</span></p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- 页脚 -->
    <footer class="bg-white border-t border-gray-200 py-6">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <a href="https://offs.inzoo.art/MIT.html"class="text-gray-500 text-sm">遵循MIT License 开发者：映筑视觉</a>
                </div>
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
                                    style="width: 1em; margin-inline-end: 0.5em" alt="ORCID iD icon" />
                                ORCID: 0009-0005-8345-6998  <!-- 优化显示文本 -->
                <div class="flex space-x-4">
                    <a href="#" class="text-gray-400 hover:text-primary transition-colors duration-200">
                        <i class="fa fa-question-circle"></i> 帮助中心
                    </a>
                    <a href="#" class="text-gray-400 hover:text-primary transition-colors duration-200">
                        <i class="fa fa-file-text-o"></i> 文档
                    </a>
                    <a href="#" class="text-gray-400 hover:text-primary transition-colors duration-200">
                        <i class="fa fa-shield"></i> 隐私政策
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- 操作结果提示框 -->
    <div id="toast" class="fixed top-20 right-4 p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full opacity-0 z-50">
        <div class="flex items-center">
            <i id="toastIcon" class="mr-2 text-lg"></i>
            <span id="toastMessage"></span>
        </div>
    </div>

    <!-- 加载中弹窗 -->
    <div id="loadingModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 transform transition-all duration-300 scale-95">
            <div class="flex flex-col items-center">
                <div class="w-16 h-16 rounded-full border-4 border-primary/30 border-t-primary spinner mb-4"></div>
                <h3 id="loadingMessage" class="text-xl font-bold text-gray-800 mb-2">正在操作VPN</h3>
                <p id="loadingSubMessage" class="text-gray-600 text-center">请稍候，这可能需要一些时间...</p>
                <div class="w-full bg-gray-200 rounded-full h-2 mt-4">
                    <div id="loadingProgress" class="bg-primary h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <p id="loadingTime" class="text-xs text-gray-500 mt-2">已用时: 0秒 / 60秒</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 页面加载完成后，加载最近的操作日志
            loadOperationLogs();
            
            // 登出按钮点击事件
            document.getElementById('logoutBtn').addEventListener('click', function() {
                if (confirm('确定要退出登录吗？')) {
                    fetch('logout.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast('success', '已成功登出');
                                setTimeout(() => {
                                    window.location.href = 'login.php';
                                }, 1500);
                            } else {
                                showToast('error', data.message || '登出失败');
                            }
                        })
                        .catch(error => {
                            showToast('error', '登出时发生网络错误');
                            console.error('登出错误:', error);
                        });
                }
            });
            
            // 开机按钮点击事件
            document.getElementById('powerOnBtn').addEventListener('click', function() {
                if (confirm('确定要启动VPN吗？')) {
                    operateServer('start');
                }
            });
            
            // 关机按钮点击事件
            document.getElementById('powerOffBtn').addEventListener('click', function() {
                if (confirm('确定要关闭VPN吗？这将停止计费。')) {
                    operateServer('stop');
                }
            });
            
            // 刷新按钮点击事件
            document.getElementById('refreshBtn').addEventListener('click', function() {
                refreshServerStatus();
            });
            
            // 滚动时改变导航栏样式
            window.addEventListener('scroll', function() {
                const header = document.getElementById('header');
                if (window.scrollY > 10) {
                    header.classList.add('py-2', 'shadow-lg');
                    header.classList.remove('py-3', 'shadow-md');
                } else {
                    header.classList.add('py-3', 'shadow-md');
                    header.classList.remove('py-2', 'shadow-lg');
                }
            });
        });
        
        // VPN操作函数
        function operateServer(action) {
            const btn = action === 'start' ? document.getElementById('powerOnBtn') : document.getElementById('powerOffBtn');
            const originalText = btn.innerHTML;
            const powerOnBtn = document.getElementById('powerOnBtn');
            const powerOffBtn = document.getElementById('powerOffBtn');

            // 禁用按钮
            powerOnBtn.disabled = true;
            powerOffBtn.disabled = true;
            btn.disabled = true;
            
            // 显示加载中弹窗
            const loadingModal = document.getElementById('loadingModal');
            const loadingMessage = document.getElementById('loadingMessage');
            const loadingSubMessage = document.getElementById('loadingSubMessage');
            const loadingProgress = document.getElementById('loadingProgress');
            const loadingTime = document.getElementById('loadingTime');
            
            loadingMessage.textContent = action === 'start' ? '正在启动VPN' : '正在关闭VPN';
            loadingSubMessage.textContent = action === 'start' ? '请稍候，VPN正在启动中...' : '请稍候，VPN正在关闭中...';
            
            loadingModal.classList.remove('opacity-0', 'pointer-events-none');
            loadingModal.querySelector('div').classList.remove('scale-95');
            loadingModal.querySelector('div').classList.add('scale-100');
            
            let startTime = new Date();
            let progressInterval = setInterval(() => {
                const elapsedTime = Math.floor((new Date() - startTime) / 1000);
                const progress = Math.min(Math.floor((elapsedTime / 60) * 100), 100);
                
                loadingProgress.style.width = `${progress}%`;
                loadingTime.textContent = `已用时: ${elapsedTime}秒 / 60秒`;
            }, 1000);

            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=${action}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', data.message || `VPN${action === 'start' ? '启动' : '关闭'}请求已提交`);
                    addOperationLog(`${action === 'start' ? '启动' : '关闭'}VPN`, 'success');

                    // 开始轮询VPN状态
                    startPollingServerStatus(action, progressInterval);
                } else {
                    // 隐藏加载中弹窗
                    loadingModal.classList.add('opacity-0', 'pointer-events-none');
                    loadingModal.querySelector('div').classList.remove('scale-100');
                    loadingModal.querySelector('div').classList.add('scale-95');
                    clearInterval(progressInterval);
                    
                    showToast('error', data.message || `VPN${action === 'start' ? '启动' : '关闭'}失败`);
                    addOperationLog(`${action === 'start' ? '启动' : '关闭'}VPN`, 'error');
                    powerOnBtn.disabled = false;
                    powerOffBtn.disabled = false;
                }
            })
            .catch(error => {
                // 隐藏加载中弹窗
                loadingModal.classList.add('opacity-0', 'pointer-events-none');
                loadingModal.querySelector('div').classList.remove('scale-100');
                loadingModal.querySelector('div').classList.add('scale-95');
                clearInterval(progressInterval);
                
                showToast('error', '操作VPN时发生网络错误');
                addOperationLog(`${action === 'start' ? '启动' : '关闭'}VPN`, 'error');
                console.error('VPN操作错误:', error);
                powerOnBtn.disabled = false;
                powerOffBtn.disabled = false;
            })
            .finally(() => {
                // 恢复按钮状态
                btn.innerHTML = originalText;
            });
        }
        
        // 刷新VPN状态
        function refreshServerStatus() {
            const refreshBtn = document.getElementById('refreshBtn');
            const originalText = refreshBtn.innerHTML;
            
            // 显示加载状态
            refreshBtn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> 刷新中...';
            refreshBtn.disabled = true;
            
            fetch('api.php?action=status')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.status) {
                        const statusBadge = document.getElementById('statusBadge');
                        const serverIP = document.getElementById('serverIP');
                        const powerOnBtn = document.getElementById('powerOnBtn');
                        const powerOffBtn = document.getElementById('powerOffBtn');
                        
                        // 处理 STARTING 状态
                        let displayStatus = data.status;
                        if (data.raw_status === 'STARTING') {
                            displayStatus = '开机中';
                        }

                        // 更新状态徽章
                        if (displayStatus === '运行中') {
                            statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-secondary/10 text-secondary';
                            statusBadge.textContent = '运行中';
                            serverIP.textContent = data.ip || '未分配公网IP';
                            
                            // 启用关机按钮，禁用开机按钮
                            powerOnBtn.disabled = true;
                            powerOffBtn.disabled = false;
                        } else if (displayStatus === '开机中') {
                            statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-warning/10 text-warning';
                            statusBadge.textContent = '开机中';
                            serverIP.textContent = '未开机';
                            
                            // 禁用开机和关机按钮
                            powerOnBtn.disabled = true;
                            powerOffBtn.disabled = true;
                        } else if (displayStatus === '关机中') {
                            statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-warning/10 text-warning';
                            statusBadge.textContent = '关机中';
                            serverIP.textContent = '未开机';
                            
                            // 禁用开机和关机按钮
                            powerOnBtn.disabled = true;
                            powerOffBtn.disabled = true;
                        } else {
                            statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-info/10 text-info';
                            statusBadge.textContent = displayStatus;
                            serverIP.textContent = '未开机';
                            
                            // 启用开机按钮，禁用关机按钮
                            powerOnBtn.disabled = false;
                            powerOffBtn.disabled = true;
                        }
                        
                        showToast('success', 'VPN状态已更新');
                        addOperationLog('刷新VPN状态', 'info');
                    } else {
                        showToast('error', data.message || '获取VPN状态失败');
                    }
                })
                .catch(error => {
                    showToast('error', '获取VPN状态时发生网络错误');
                    console.error('获取VPN状态错误:', error);
                })
                .finally(() => {
                    // 恢复按钮状态
                    refreshBtn.innerHTML = originalText;
                    refreshBtn.disabled = false;
                });
        }
        
        // 轮询VPN状态（用于开机或关机后等待VPN状态变化）
        function startPollingServerStatus(action, progressInterval) {
            const maxAttempts = 12; // 最多尝试 12 次，每次 5 秒，共 60 秒
            let attempts = 0;

            const interval = setInterval(() => {
                attempts++;

                fetch('api.php?action=status')
                    .then(response => response.json())
                    .then(data => {
                        if (action === 'start' && data.success && data.status === '运行中') {
                            // VPN已启动，更新状态
                            const statusBadge = document.getElementById('statusBadge');
                            const serverIP = document.getElementById('serverIP');
                            const powerOnBtn = document.getElementById('powerOnBtn');
                            const powerOffBtn = document.getElementById('powerOffBtn');

                            statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-secondary/10 text-secondary';
                            statusBadge.textContent = '运行中';
                            serverIP.textContent = data.ip || '未分配公网IP';

                            // 启用关机按钮，禁用开机按钮
                            powerOnBtn.disabled = true;
                            powerOffBtn.disabled = false;

                            showToast('success', 'VPN已成功启动');
                            addOperationLog('VPN已启动', 'success');

                            // 隐藏加载中弹窗
                            const loadingModal = document.getElementById('loadingModal');
                            loadingModal.classList.add('opacity-0', 'pointer-events-none');
                            loadingModal.querySelector('div').classList.remove('scale-100');
                            loadingModal.querySelector('div').classList.add('scale-95');
                            clearInterval(progressInterval);

                            clearInterval(interval);
                        } else if (action === 'stop' && data.success && data.status === '已关机') {
                            // VPN已关机，更新状态
                            const statusBadge = document.getElementById('statusBadge');
                            const serverIP = document.getElementById('serverIP');
                            const powerOnBtn = document.getElementById('powerOnBtn');
                            const powerOffBtn = document.getElementById('powerOffBtn');

                            statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-info/10 text-info';
                            statusBadge.textContent = '已关机';
                            serverIP.textContent = '未开机';

                            // 启用开机按钮，禁用关机按钮
                            powerOnBtn.disabled = false;
                            powerOffBtn.disabled = true;

                            showToast('success', 'VPN已成功关闭');
                            addOperationLog('VPN已关闭', 'success');

                            // 隐藏加载中弹窗
                            const loadingModal = document.getElementById('loadingModal');
                            loadingModal.classList.add('opacity-0', 'pointer-events-none');
                            loadingModal.querySelector('div').classList.remove('scale-100');
                            loadingModal.querySelector('div').classList.add('scale-95');
                            clearInterval(progressInterval);

                            clearInterval(interval);
                        } else if (attempts >= maxAttempts) {
                            // 超过最大尝试次数
                            showToast('error', `VPN${action === 'start' ? '启动' : '关闭'}超时，请手动刷新状态`);
                            addOperationLog(`VPN${action === 'start' ? '启动' : '关闭'}超时`, 'error');
                            
                            // 隐藏加载中弹窗
                            const loadingModal = document.getElementById('loadingModal');
                            loadingModal.classList.add('opacity-0', 'pointer-events-none');
                            loadingModal.querySelector('div').classList.remove('scale-100');
                            loadingModal.querySelector('div').classList.add('scale-95');
                            clearInterval(progressInterval);

                            clearInterval(interval);
                            location.reload(); // 刷新页面
                        }
                    })
                    .catch(error => {
                        console.error('轮询VPN状态错误:', error);
                        if (attempts >= maxAttempts) {
                            showToast('error', `VPN${action === 'start' ? '启动检查' : '关闭检查'}失败，请手动刷新状态`);
                            addOperationLog(`VPN${action === 'start' ? '启动检查' : '关闭检查'}失败`, 'error');
                            
                            // 隐藏加载中弹窗
                            const loadingModal = document.getElementById('loadingModal');
                            loadingModal.classList.add('opacity-0', 'pointer-events-none');
                            loadingModal.querySelector('div').classList.remove('scale-100');
                            loadingModal.querySelector('div').classList.add('scale-95');
                            clearInterval(progressInterval);

                            clearInterval(interval);
                            location.reload(); // 刷新页面
                        }
                    });
            }, 5000); // 每 5 秒检查一次
        }
        
        // 显示提示框
        function showToast(type, message) {
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastMessage = document.getElementById('toastMessage');
            
            // 设置提示框样式
            if (type === 'success') {
                toast.className = 'fixed top-20 right-4 p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-0 opacity-100 z-50 bg-green-50 border border-green-200 text-green-700';
                toastIcon.className = 'mr-2 text-lg fa fa-check-circle';
            } else if (type === 'error') {
                toast.className = 'fixed top-20 right-4 p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-0 opacity-100 z-50 bg-red-50 border border-red-200 text-red-700';
                toastIcon.className = 'mr-2 text-lg fa fa-exclamation-circle';
            } else if (type === 'warning') {
                toast.className = 'fixed top-20 right-4 p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-0 opacity-100 z-50 bg-yellow-50 border border-yellow-200 text-yellow-700';
                toastIcon.className = 'mr-2 text-lg fa fa-exclamation-triangle';
            } else if (type === 'info') {
                toast.className = 'fixed top-20 right-4 p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-0 opacity-100 z-50 bg-blue-50 border border-blue-200 text-blue-700';
                toastIcon.className = 'mr-2 text-lg fa fa-info-circle';
            }
            
            toastMessage.textContent = message;
            
            // 3秒后隐藏提示框
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
            }, 3000);
        }
        
        // 添加操作日志
        function addOperationLog(action, type) {
            const now = new Date();
            const timeString = now.toLocaleString('zh-CN', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            const operationLog = document.getElementById('operationLog');
            const logContainer = operationLog.querySelector('.space-y-2');
            
            // 创建新日志项
            const logItem = document.createElement('p');
            logItem.className = `text-sm ${type === 'success' ? 'text-green-600' : type === 'error' ? 'text-red-600' : type === 'warning' ? 'text-yellow-600' : 'text-gray-600'}`;
            
            let iconClass = '';
            if (type === 'success') iconClass = 'fa-check-circle';
            else if (type === 'error') iconClass = 'fa-exclamation-circle';
            else if (type === 'warning') iconClass = 'fa-exclamation-triangle';
            else iconClass = 'fa-clock-o';
            
            logItem.innerHTML = `<i class="fa ${iconClass} mr-1"></i> <span class="text-gray-500">${timeString}</span> ${action}`;
            
            // 添加到日志容器顶部
            if (logContainer.firstChild) {
                logContainer.insertBefore(logItem, logContainer.firstChild);
            } else {
                logContainer.appendChild(logItem);
            }
            
            // 保存日志到VPN
            saveOperationLog(action, type);
        }
        
        // 保存操作日志到VPN
        function saveOperationLog(action, type) {
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=log&operation=${encodeURIComponent(action)}&type=${type}`
            })
            .catch(error => {
                console.error('保存操作日志失败:', error);
            });
        }
        
        // 加载操作日志
        function loadOperationLogs() {
            fetch('api.php?action=logs')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.logs && data.logs.length > 0) {
                        const operationLog = document.getElementById('operationLog');
                        const logContainer = operationLog.querySelector('.space-y-2');
                        
                        // 清空加载中提示
                        logContainer.innerHTML = '';
                        
                        // 添加日志
                        data.logs.forEach(log => {
                            const logItem = document.createElement('p');
                            logItem.className = `text-sm ${log.type === 'success' ? 'text-green-600' : log.type === 'error' ? 'text-red-600' : log.type === 'warning' ? 'text-yellow-600' : 'text-gray-600'}`;
                            
                            let iconClass = '';
                            if (log.type === 'success') iconClass = 'fa-check-circle';
                            else if (log.type === 'error') iconClass = 'fa-exclamation-circle';
                            else if (log.type === 'warning') iconClass = 'fa-exclamation-triangle';
                            else iconClass = 'fa-clock-o';
                            
                            logItem.innerHTML = `<i class="fa ${iconClass} mr-1"></i> <span class="text-gray-500">${log.time}</span> ${log.operation}`;
                            logContainer.appendChild(logItem);
                        });
                    }
                })
                .catch(error => {
                    console.error('加载操作日志失败:', error);
                });
        }
    </script>
</body>
</html>