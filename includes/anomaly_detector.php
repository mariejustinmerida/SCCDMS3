<?php
/**
 * Rule-based anomaly detection for security and workflow
 */

require_once __DIR__ . '/config.php';

class AnomalyDetector {
	private mysqli $conn;

	public function __construct(mysqli $conn) {
		$this->conn = $conn;
	}

	/**
	 * Get recent anomalies
	 * - Suspicious access attempts (failed login bursts)
	 * - Spikes in rejections or revisions
	 */
	public function getAnomalies(): array {
		$anomalies = [];

		// 1) Failed login bursts by IP OR by email (last 5 minutes)
		$failedLoginThreshold = 3; // lower threshold for easier detection/testing
		$windowMinutes = 5;
		// Group by IP
		$failedByIp = $this->query(
			"SELECT ip_address, COUNT(*) AS attempts
			 FROM security_events
			 WHERE event_type = 'failed_login'
			   AND created_at >= (NOW() - INTERVAL ? MINUTE)
			 GROUP BY ip_address
			 HAVING attempts >= ?",
			[$windowMinutes, $failedLoginThreshold],
			'ii'
		);
		foreach ($failedByIp as $row) {
			$anomalies[] = [
				'type' => 'suspicious_access',
				'severity' => 'high',
				'message' => 'Burst of failed login attempts detected (by IP)',
				'data' => $row
			];
		}

		// Group by email
		$failedByEmail = $this->query(
			"SELECT user_identifier, COUNT(*) AS attempts
			 FROM security_events
			 WHERE event_type = 'failed_login'
			   AND created_at >= (NOW() - INTERVAL ? MINUTE)
			 GROUP BY user_identifier
			 HAVING attempts >= ?",
			[$windowMinutes, $failedLoginThreshold],
			'ii'
		);
		foreach ($failedByEmail as $row) {
			$anomalies[] = [
				'type' => 'suspicious_access',
				'severity' => 'high',
				'message' => 'Burst of failed login attempts detected (by email)',
				'data' => $row
			];
		}

		// 2) Spikes in rejections or revisions in last hour vs baseline (previous 24h average per hour)
		$spikeMultiplier = 3.0; // 3x baseline considered spike
		$recent = $this->query(
			"SELECT action, COUNT(*) AS cnt
			 FROM document_logs
			 WHERE action IN ('reject', 'request_revision')
			   AND created_at >= (NOW() - INTERVAL 60 MINUTE)
			 GROUP BY action",
			[], ''
		);
		$baseline = $this->query(
			"SELECT action, COUNT(*)/24.0 AS avg_per_hour
			 FROM document_logs
			 WHERE action IN ('reject', 'request_revision')
			   AND created_at >= (NOW() - INTERVAL 1 DAY)
			 GROUP BY action",
			[], ''
		);
		$baselineMap = [];
		foreach ($baseline as $b) { $baselineMap[$b['action']] = (float)$b['avg_per_hour']; }
		foreach ($recent as $r) {
			$act = $r['action'];
			$cnt = (int)$r['cnt'];
			$avg = $baselineMap[$act] ?? 0.0;
			if ($avg > 0 && $cnt >= ($avg * $spikeMultiplier)) {
				$anomalies[] = [
					'type' => 'workflow_spike',
					'severity' => 'medium',
					'message' => strtoupper($act) . ' spike detected in the last hour',
					'data' => ['recent_count' => $cnt, 'baseline_avg_per_hour' => $avg]
				];
			}
		}

		return $anomalies;
	}

	private function query(string $sql, array $params, string $types): array {
		$resultData = [];
		$stmt = $this->conn->prepare($sql);
		if (!$stmt) { return $resultData; }
		if (!empty($params)) {
			$stmt->bind_param($types, ...$params);
		}
		if (!$stmt->execute()) { $stmt->close(); return $resultData; }
		$res = $stmt->get_result();
		if ($res) {
			while ($row = $res->fetch_assoc()) { $resultData[] = $row; }
		}
		$stmt->close();
		return $resultData;
	}
}

?>


