<?php
// Return current security anomalies (JSON)

// Start output buffering early
ob_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/anomaly_detector.php';

// Require login
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
	echo json_encode(['success' => false, 'error' => 'Unauthorized']);
	exit;
}

try {
	$detector = new AnomalyDetector($conn);
	$anomalies = $detector->getAnomalies();
	echo json_encode(['success' => true, 'anomalies' => $anomalies]);
} catch (Throwable $e) {
	echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>


