<?php
// Unified settings API: GET (fetch), POST (save), POST action=clear_logs
ob_start();
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/settings_helper.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $keys = [
            // AI
            'gemini_api_key','ai_model','ai_temperature','ai_enable_summarize','ai_enable_analyze','ai_enable_generator',
            // Security
            'session_timeout_minutes','anomaly_attempts_threshold','anomaly_window_minutes','force_https',
            // Google
            'gdocs_default_folder','gdocs_sharing_default',
            // Documents
            'numbering_format','default_routing_template','required_fields_by_type',
            // Search & UX
            'live_search_enabled','search_debounce_ms','rows_per_page',
            // Branding
            'institution_name','institution_short_name','accent_color',
            // Logging
            'log_level'
        ];
        $values = [];
        foreach ($keys as $k) { $values[$k] = get_setting($k, ''); }
        echo json_encode(['success'=>true,'settings'=>$values]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) { $input = $_POST; }
        if (isset($input['action']) && $input['action'] === 'clear_logs') {
            $logsDir = realpath(__DIR__ . '/../logs');
            if ($logsDir && is_dir($logsDir)) {
                foreach (glob($logsDir . '/*.log') as $file) { @unlink($file); }
            }
            echo json_encode(['success'=>true]);
            exit;
        }

        foreach ($input as $k=>$v) {
            if (!is_array($v)) {
                set_setting($k, (string)$v);
            } else {
                set_setting($k, json_encode($v));
            }
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

?>


