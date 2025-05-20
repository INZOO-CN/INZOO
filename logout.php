<?php
session_start();

// 销毁会话
session_unset();
session_destroy();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => '已成功登出'
]);
exit;
?>    