<?php
// 包含配置文件
require_once 'config.php';
require_once 'tencentcloudapi.php';

try {
    // 初始化腾讯云 API 类
    $tencentCloudAPI = new TencentCloudAPI(SECRET_ID, SECRET_KEY, REGION);

    // 关闭服务器（停止计费模式）
    $tencentCloudAPI->stopInstance(INSTANCE_ID);

    // 返回成功响应
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => '服务器关机请求已提交，停止计费'
    ]);
} catch (Exception $e) {
    // 记录错误日志
    error_log('关闭服务器错误: ' . $e->getMessage());

    // 返回错误响应
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '关闭服务器时发生错误: ' . $e->getMessage()
    ]);
}
?>