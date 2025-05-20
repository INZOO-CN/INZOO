<?php
session_start();
require_once 'config.php';
require_once 'tencentcloudapi.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '未登录，请先登录'
    ]);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$result = ['success' => false];

try {
    switch ($action) {
        case 'status':
            // 获取服务器状态
            $tencentCloudAPI = new TencentCloudAPI(SECRET_ID, SECRET_KEY, REGION);
            $statusInfo = $tencentCloudAPI->describeInstancesStatus(INSTANCE_ID);
            
            if ($statusInfo) {
                $result['success'] = true;
                $result['status'] = $statusInfo['status'];
                $result['raw_status'] = $statusInfo['raw_status'];
                
                if ($statusInfo['status'] === '运行中') {
                    $instanceInfo = $tencentCloudAPI->describeInstances(INSTANCE_ID);
                    $result['ip'] = $instanceInfo['publicIpAddress'] ?: '未分配公网IP';
                }
            } else {
                $result['message'] = '获取服务器状态失败';
            }
            break;
            
        case 'start':
            // 启动服务器
            $tencentCloudAPI = new TencentCloudAPI(SECRET_ID, SECRET_KEY, REGION);
            $tencentCloudAPI->startInstance(INSTANCE_ID);
            $result['success'] = true;
            $result['message'] = '服务器启动请求已提交';
            break;
            
        case 'stop':
            // 关闭服务器
            $tencentCloudAPI = new TencentCloudAPI(SECRET_ID, SECRET_KEY, REGION);
            $tencentCloudAPI->stopInstance(INSTANCE_ID);
            $result['success'] = true;
            $result['message'] = '服务器关机请求已提交';
            break;
            
        case 'logs':
            // 获取操作日志
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
            
            $stmt = $pdo->prepare("
                SELECT operation, type, 
                       DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as time 
                FROM operation_logs 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $logs = $stmt->fetchAll();
            
            $result['success'] = true;
            $result['logs'] = $logs;
            break;
            
        case 'log':
            // 记录操作日志
            $operation = $_POST['operation'] ?? '';
            $type = $_POST['type'] ?? 'info';
            
            if (!empty($operation)) {
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
                
                $stmt = $pdo->prepare("
                    INSERT INTO operation_logs (user_id, operation, type, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $operation, $type]);
                
                $result['success'] = true;
            }
            break;
            
        default:
            $result['message'] = '未知操作';
    }
} catch (Exception $e) {
    $result['message'] = $e->getMessage();
    error_log('API 错误: ' . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode($result);
exit;
?>