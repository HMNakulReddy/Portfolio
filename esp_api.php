<?php
/**
 * ESP API for RFID/Fingerprint Authentication
 * Returns user details and ration quantity when RFID or fingerprint is scanned
 * 
 * Usage:
 * GET/POST: ?rfid_uid=D335B327
 * GET/POST: ?fingerprint_id=1
 */

// ======== DATABASE CONFIGURATION (InfinityFree) ========
// Replace these with your InfinityFree MySQL credentials
$DB_HOST = getenv('DB_HOST') ?: 'sql100.infinityfree.com'; // Your InfinityFree MySQL host
$DB_NAME = getenv('DB_NAME') ?: 'if0_40277811_next_gen_ration'; // Your database name
$DB_USER = getenv('DB_USER') ?: 'if0_40277811'; // Your database username
$DB_PASS = getenv('DB_PASS') ?: 'OWUZhz6Yg1O'; // Your database password
// ======================================================

// Set JSON response header
header('Content-Type: application/json');

// CORS headers for ESP access (optional, but useful for testing)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Send JSON response
 */
function sendResponse($success, $data = [], $message = '') {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (!$success) {
        http_response_code(404);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Database connection
 */
function getDB() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
            $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5
            ]);
        } catch (PDOException $e) {
            sendResponse(false, [], 'Database connection failed: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Get user by RFID UID
 */
function getUserByRFID($rfidUid) {
    $pdo = getDB();
    
    $sql = "
        SELECT 
            u.user_id,
            u.name,
            u.mobile,
            u.family_id,
            u.role,
            u.fingerprint_id,
            u.rfid_uid,
            f.family_name,
            f.address,
            f.ration_quota,
            f.last_ration_date
        FROM users u
        LEFT JOIN families f ON u.family_id = f.family_id
        WHERE u.rfid_uid = :rfid_uid
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':rfid_uid' => $rfidUid]);
    return $stmt->fetch();
}

/**
 * Get user by Fingerprint ID
 */
function getUserByFingerprint($fingerprintId) {
    $pdo = getDB();
    
    $sql = "
        SELECT 
            u.user_id,
            u.name,
            u.mobile,
            u.family_id,
            u.role,
            u.fingerprint_id,
            u.rfid_uid,
            f.family_name,
            f.address,
            f.ration_quota,
            f.last_ration_date
        FROM users u
        LEFT JOIN families f ON u.family_id = f.family_id
        WHERE u.fingerprint_id = :fingerprint_id
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':fingerprint_id' => (int)$fingerprintId]);
    return $stmt->fetch();
}

// Main request handling
$rfidUid = trim($_GET['rfid_uid'] ?? $_POST['rfid_uid'] ?? '');
$fingerprintId = trim($_GET['fingerprint_id'] ?? $_POST['fingerprint_id'] ?? '');

// Validate input
if (empty($rfidUid) && empty($fingerprintId)) {
    sendResponse(false, [], 'Missing parameter: rfid_uid or fingerprint_id required');
}

// Get user data
$user = null;
if (!empty($rfidUid)) {
    $user = getUserByRFID($rfidUid);
    if (!$user) {
        sendResponse(false, [], "No user found with RFID UID: {$rfidUid}");
    }
} elseif (!empty($fingerprintId)) {
    $user = getUserByFingerprint($fingerprintId);
    if (!$user) {
        sendResponse(false, [], "No user found with Fingerprint ID: {$fingerprintId}");
    }
}

// Parse ration quota JSON
$rationQuota = [];
if (!empty($user['ration_quota'])) {
    $quotaJson = json_decode($user['ration_quota'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($quotaJson)) {
        $rationQuota = $quotaJson;
    }
}

// Prepare response data
$responseData = [
    'user' => [
        'user_id' => (int)$user['user_id'],
        'name' => $user['name'],
        'mobile' => $user['mobile'],
        'role' => $user['role'],
        'fingerprint_id' => $user['fingerprint_id'] ? (int)$user['fingerprint_id'] : null,
        'rfid_uid' => $user['rfid_uid']
    ],
    'family' => [
        'family_id' => $user['family_id'] ? (int)$user['family_id'] : null,
        'family_name' => $user['family_name'],
        'address' => $user['address'],
        'last_ration_date' => $user['last_ration_date']
    ],
    'ration_quota' => $rationQuota
];

sendResponse(true, $responseData, 'User found successfully');

