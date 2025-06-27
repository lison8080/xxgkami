<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
header('X-Powered-By: 小小怪卡密系统');

require_once '../config.php';

// 检查API是否启用
try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("SELECT value FROM settings WHERE name = 'api_enabled'");
    $stmt->execute();
    $api_enabled = $stmt->fetchColumn();
    
    error_log("API Status: " . $api_enabled);
    
    if($api_enabled !== '1' && $api_enabled !== 1) {
        http_response_code(403);
        die(json_encode([
            'code' => 2,
            'message' => 'API接口未启用',
            'data' => null
        ], JSON_UNESCAPED_UNICODE));
    }
} catch(PDOException $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'code' => 3,
        'message' => '系统错误',
        'data' => null
    ], JSON_UNESCAPED_UNICODE));
}

// 验证API密钥（支持Header和GET参数）
$headers = getallheaders();
$api_key = '';

// 优先从Header获取API密钥（支持多种格式）
$header_keys = ['X-API-KEY', 'X-Api-Key', 'x-api-key', 'HTTP_X_API_KEY'];
foreach($header_keys as $key) {
    if(isset($headers[$key])) {
        $api_key = $headers[$key];
        break;
    }
}

// 如果Header中没有找到，尝试从$_SERVER获取
if(empty($api_key) && isset($_SERVER['HTTP_X_API_KEY'])) {
    $api_key = $_SERVER['HTTP_X_API_KEY'];
}

// 如果是GET请求且Header中没有API密钥，则从URL参数获取
if(empty($api_key) && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['api_key'])) {
    $api_key = $_GET['api_key'];
}

try {
    $stmt = $conn->prepare("SELECT id FROM api_keys WHERE api_key = ? AND status = 1");
    $stmt->execute([$api_key]);
    if(!$stmt->fetch()) {
        http_response_code(401);
        die(json_encode([
            'code' => 4,
            'message' => 'API密钥无效或已禁用',
            'data' => null
        ], JSON_UNESCAPED_UNICODE));
    }
    
    // 更新使用次数和最后使用时间
    $stmt = $conn->prepare("UPDATE api_keys SET use_count = use_count + 1, last_use_time = NOW() WHERE api_key = ?");
    $stmt->execute([$api_key]);
} catch(PDOException $e) {
    http_response_code(500);
    die(json_encode([
        'code' => 3,
        'message' => '系统错误',
        'data' => null
    ], JSON_UNESCAPED_UNICODE));
}

// 获取请求数据（支持GET和POST）
$card_key = '';
$device_id = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 处理POST请求
    $input = json_decode(file_get_contents('php://input'), true);
    if($input) {
        $card_key = isset($input['card_key']) ? trim($input['card_key']) : '';
        $device_id = isset($input['device_id']) ? trim($input['device_id']) : '';
    } else {
        $card_key = isset($_POST['card_key']) ? trim($_POST['card_key']) : '';
        $device_id = isset($_POST['device_id']) ? trim($_POST['device_id']) : '';
    }
} else if($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 处理GET请求
    $card_key = isset($_GET['card_key']) ? trim($_GET['card_key']) : '';
    $device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : '';
}

if(empty($card_key)) {
    http_response_code(400);
    die(json_encode([
        'code' => 1,
        'message' => '请提供卡密',
        'data' => new stdClass()
    ], JSON_UNESCAPED_UNICODE));
}

if(empty($device_id)) {
    http_response_code(400);
    die(json_encode([
        'code' => 1,
        'message' => '请提供设备ID',
        'data' => new stdClass()
    ], JSON_UNESCAPED_UNICODE));
}

// 添加卡密加密函数
function encryptCardKey($key) {
    $salt = 'xiaoxiaoguai_card_system_2024';
    return sha1($key . $salt);
}

// 修改验证卡密部分
try {
    // 对输入的卡密进行加密
    $encrypted_key = encryptCardKey($card_key);
    
    // 首先检查卡密是否存在
    $stmt = $conn->prepare("SELECT * FROM cards WHERE encrypted_key = ?");
    $stmt->execute([$encrypted_key]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$card) {
        http_response_code(400);
        die(json_encode([
            'code' => 1,
            'message' => '无效的卡密',
            'data' => new stdClass()
        ], JSON_UNESCAPED_UNICODE));
    }
    
    // 检查卡密是否被禁用
    if($card['status'] == 2) {
        http_response_code(403);
        die(json_encode([
            'code' => 5,
            'message' => '此卡密已被管理员禁用',
            'data' => new stdClass()
        ], JSON_UNESCAPED_UNICODE));
    }
    
    // 检查次数卡密的剩余次数
    if($card['card_type'] == 'count' && $card['status'] == 1 && $card['remaining_count'] <= 0) {
        http_response_code(403);
        die(json_encode([
            'code' => 7,
            'message' => '此卡密使用次数已用完',
            'data' => new stdClass()
        ], JSON_UNESCAPED_UNICODE));
    }
    
    // 检查卡密状态和设备绑定
    if($card['status'] == 1) {
        // 已使用的卡密
        if($card['device_id'] === $device_id) {
            // 同一设备重复验证
            // 检查是否允许重复验证
            if ($card['allow_reverify']) {
                // 次数卡密需要减少次数
                if($card['card_type'] == 'count' && $card['remaining_count'] > 0) {
                    // 更新剩余次数
                    $stmt = $conn->prepare("UPDATE cards SET remaining_count = remaining_count - 1 WHERE id = ?");
                    $stmt->execute([$card['id']]);
                    
                    // 重新获取最新的剩余次数
                    $stmt = $conn->prepare("SELECT remaining_count FROM cards WHERE id = ?");
                    $stmt->execute([$card['id']]);
                    $remaining = $stmt->fetchColumn();
                    
                    echo json_encode([
                        'code' => 0,
                        'message' => '验证成功，剩余次数：'.$remaining,
                        'data' => [
                            'card_key' => $card['card_key'],
                            'status' => 1,
                            'use_time' => $card['use_time'],
                            'expire_time' => $card['expire_time'],
                            'card_type' => $card['card_type'],
                            'remaining_count' => $remaining,
                            'total_count' => $card['total_count'],
                            'device_id' => $device_id,
                            'allow_reverify' => $card['allow_reverify']
                        ]
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    // 时间卡密正常返回
                echo json_encode([
                    'code' => 0,
                    'message' => '验证成功(重复验证)',
                    'data' => [
                        'card_key' => $card['card_key'],
                        'status' => 1,
                        'use_time' => $card['use_time'],
                        'expire_time' => $card['expire_time'],
                            'card_type' => $card['card_type'],
                        'duration' => $card['duration'],
                        'device_id' => $device_id,
                        'allow_reverify' => $card['allow_reverify']
                    ]
                ], JSON_UNESCAPED_UNICODE);
                }
            } else {
                // 不允许重复验证
                http_response_code(403); // Forbidden
                die(json_encode([
                    'code' => 6, // 新增错误码
                    'message' => '此卡密不允许重复验证',
                    'data' => new stdClass()
                ], JSON_UNESCAPED_UNICODE));
            }
        } else {
            // 其他设备尝试使用 或 卡密已被解绑，允许新设备绑定
            if (empty($card['device_id'])) {
                // 卡密已被解绑，允许当前设备重新绑定
                $expire_time = $card['expire_time']; // 保持原来的到期时间
                $use_time = $card['use_time']; // 保持原来的使用时间
                
                $verify_method = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'post' : 'get';
                $stmt = $conn->prepare("UPDATE cards SET device_id = ?, verify_method = ? WHERE id = ?");
                $stmt->execute([$device_id, $verify_method, $card['id']]);

                $response_data = [
                    'code' => 0,
                    'message' => '验证成功 (重新绑定设备)',
                    'data' => [
                        'card_key' => $card['card_key'],
                        'status' => 1,
                        'use_time' => $use_time,
                        'expire_time' => $expire_time,
                        'card_type' => $card['card_type'],
                        'device_id' => $device_id,
                        'allow_reverify' => $card['allow_reverify']
                    ]
                ];
                
                // 根据卡密类型添加不同的数据
                if($card['card_type'] == 'time') {
                    $response_data['data']['duration'] = $card['duration'];
                } else {
                    $response_data['data']['remaining_count'] = $card['remaining_count'];
                    $response_data['data']['total_count'] = $card['total_count'];
                }
                
                echo json_encode($response_data, JSON_UNESCAPED_UNICODE);

            } else {
                // 卡密已绑定到其他设备
                http_response_code(400);
                die(json_encode([
                    'code' => 1,
                    'message' => '此卡密已被其他设备使用',
                    'data' => new stdClass()
                ], JSON_UNESCAPED_UNICODE));
            }
        }
    } else if($card['status'] == 0) {
        // 新卡密激活
        $verify_method = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'post' : 'get';
        
        if($card['card_type'] == 'time') {
            // 时间卡密处理
        $expire_time = null;
        if($card['duration'] > 0) {
            $expire_time = date('Y-m-d H:i:s', strtotime("+{$card['duration']} days"));
        }
        
        $stmt = $conn->prepare("UPDATE cards SET status = 1, use_time = NOW(), expire_time = ?, verify_method = ?, device_id = ? WHERE id = ?");
        $stmt->execute([$expire_time, $verify_method, $device_id, $card['id']]);
        
            // 重新获取卡密信息
        $stmt = $conn->prepare("SELECT allow_reverify FROM cards WHERE id = ?");
        $stmt->execute([$card['id']]);
        $allow_reverify_status = $stmt->fetchColumn();

        echo json_encode([
            'code' => 0,
            'message' => '验证成功',
            'data' => [
                'card_key' => $card['card_key'],
                'status' => 1,
                'use_time' => date('Y-m-d H:i:s'),
                'expire_time' => $expire_time,
                    'card_type' => 'time',
                'duration' => $card['duration'],
                'device_id' => $device_id,
                'allow_reverify' => $allow_reverify_status
            ]
        ], JSON_UNESCAPED_UNICODE);
        } else {
            // 次数卡密处理，首次验证也消耗一次
            $remaining = $card['total_count'] - 1;
            
            $stmt = $conn->prepare("UPDATE cards SET status = 1, use_time = NOW(), verify_method = ?, device_id = ?, remaining_count = ? WHERE id = ?");
            $stmt->execute([$verify_method, $device_id, $remaining, $card['id']]);
            
            // 重新获取卡密信息
            $stmt = $conn->prepare("SELECT allow_reverify FROM cards WHERE id = ?");
            $stmt->execute([$card['id']]);
            $allow_reverify_status = $stmt->fetchColumn();

            echo json_encode([
                'code' => 0,
                'message' => '验证成功，剩余次数：'.$remaining,
                'data' => [
                    'card_key' => $card['card_key'],
                    'status' => 1,
                    'use_time' => date('Y-m-d H:i:s'),
                    'card_type' => 'count',
                    'total_count' => $card['total_count'],
                    'remaining_count' => $remaining,
                    'device_id' => $device_id,
                    'allow_reverify' => $allow_reverify_status
                ]
            ], JSON_UNESCAPED_UNICODE);
        }
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'code' => 3,
        'message' => '系统错误',
        'data' => new stdClass()
    ], JSON_UNESCAPED_UNICODE);
} 