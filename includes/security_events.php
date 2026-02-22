<?php
/**
 * Security Events Logger
 * - Persists suspicious/security-relevant events for anomaly detection
 */

require_once __DIR__ . '/config.php';

/**
 * Ensure the `security_events` table exists
 */
function ensure_security_events_table(mysqli $conn): void {
	$createSql = "CREATE TABLE IF NOT EXISTS security_events (
		id INT AUTO_INCREMENT PRIMARY KEY,
		event_type VARCHAR(64) NOT NULL,
		details TEXT NULL,
		ip_address VARCHAR(64) NULL,
		user_identifier VARCHAR(255) NULL,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		INDEX idx_event_type_created_at (event_type, created_at),
		INDEX idx_user_identifier_created_at (user_identifier, created_at),
		INDEX idx_ip_created_at (ip_address, created_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

	@$conn->query($createSql);
}

/**
 * Log a failed login attempt
 */
function log_failed_login_attempt(?string $email, string $ipAddress, string $reason = 'invalid_credentials'): void {
	global $conn;
	if (!$conn instanceof mysqli) {
		return;
	}

	ensure_security_events_table($conn);

	$emailSafe = $email ?? '';
	$ipSafe = $ipAddress ?: detect_client_ip();
	$eventType = 'failed_login';

	$sql = "INSERT INTO security_events (event_type, details, ip_address, user_identifier) VALUES (?, ?, ?, ?)";
	$stmt = $conn->prepare($sql);
	if ($stmt) {
		$details = json_encode([
			'reason' => $reason
		]);
		$stmt->bind_param('ssss', $eventType, $details, $ipSafe, $emailSafe);
		$stmt->execute();
		$stmt->close();
	}
}

/**
 * Detect client IP with proxy header fallbacks
 */
function detect_client_ip(): string {
	$keys = [
		'HTTP_X_FORWARDED_FOR',
		'HTTP_CLIENT_IP',
		'HTTP_X_REAL_IP',
		'REMOTE_ADDR'
	];
	foreach ($keys as $key) {
		if (!empty($_SERVER[$key])) {
			$raw = $_SERVER[$key];
			// X-Forwarded-For may have a list
			$parts = array_map('trim', explode(',', $raw));
			foreach ($parts as $ip) {
				if (filter_var($ip, FILTER_VALIDATE_IP)) {
					return $ip;
				}
			}
		}
	}
	return '0.0.0.0';
}

/**
 * Count failed login attempts for an identifier within a window (seconds)
 */
function count_failed_logins_window(?string $email, string $ipAddress, int $windowSeconds = 600): int { // default 10 minutes
	global $conn;
	if (!$conn instanceof mysqli) {
		return 0;
	}

	ensure_security_events_table($conn);

	$params = [];
	$types = '';
	$where = "event_type = 'failed_login' AND created_at >= (NOW() - INTERVAL ? SECOND)";
	$params[] = $windowSeconds; $types .= 'i';

	if (!empty($email)) {
		$where .= " AND user_identifier = ?";
		$params[] = $email; $types .= 's';
	}
	if (!empty($ipAddress)) {
		$where .= " AND ip_address = ?";
		$params[] = $ipAddress; $types .= 's';
	}

	$sql = "SELECT COUNT(*) AS cnt FROM security_events WHERE $where";
	$stmt = $conn->prepare($sql);
	if (!$stmt) { return 0; }
	$stmt->bind_param($types, ...$params);
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res ? $res->fetch_assoc() : ['cnt' => 0];
	$stmt->close();
	return (int)($row['cnt'] ?? 0);
}