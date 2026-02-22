<?php
/**
 * AI-Powered Reminder System
 * 
 * This endpoint handles AI-powered document reminders and urgent notifications
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/enhanced_notification_system.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? 'check_stuck_documents';

try {
    switch ($action) {
        case 'check_stuck_documents':
            $reminders_created = check_stuck_documents();
            echo json_encode([
                'success' => true,
                'message' => 'Stuck document check completed',
                'reminders_created' => $reminders_created
            ]);
            break;
            
        case 'check_urgent_documents':
            $urgent_reminders = check_urgent_documents();
            echo json_encode([
                'success' => true,
                'message' => 'Urgent document check completed',
                'urgent_reminders' => $urgent_reminders
            ]);
            break;
            
        case 'check_deadline_documents':
            $deadline_reminders = check_deadline_documents();
            echo json_encode([
                'success' => true,
                'message' => 'Deadline check completed',
                'deadline_reminders' => $deadline_reminders
            ]);
            break;
            
        case 'generate_ai_reminder':
            $document_id = $_POST['document_id'] ?? null;
            $reminder_type = $_POST['reminder_type'] ?? 'general';
            
            if (!$document_id) {
                throw new Exception('Document ID is required');
            }
            
            $ai_reminder = generate_ai_reminder($document_id, $reminder_type);
            echo json_encode([
                'success' => true,
                'message' => 'AI reminder generated',
                'reminder' => $ai_reminder
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Check for urgent documents that need immediate attention
 */
function check_urgent_documents() {
    global $conn;
    
    $urgent_query = "SELECT d.document_id, d.title, d.is_urgent, d.created_at,
                            DATEDIFF(NOW(), d.created_at) as days_old,
                            u.full_name as creator_name,
                            o.office_name as current_office
                     FROM documents d
                     LEFT JOIN users u ON d.creator_id = u.user_id
                     LEFT JOIN document_workflow dw ON d.document_id = dw.document_id AND dw.status = 'CURRENT'
                     LEFT JOIN offices o ON dw.office_id = o.office_id
                     WHERE d.status = 'pending'
                     AND (d.is_urgent = 1 OR DATEDIFF(NOW(), d.created_at) >= 5)
                     AND d.document_id NOT IN (
                         SELECT DISTINCT document_id FROM notifications 
                         WHERE event_type = 'urgent_reminder' 
                         AND created_at >= DATE_SUB(NOW(), INTERVAL 6 HOURS)
                     )";
    
    $urgent_result = $conn->query($urgent_query);
    $urgent_reminders = 0;
    
    while ($document = $urgent_result->fetch_assoc()) {
        $days_stuck = $document['days_old'];
        $additional_data = [
            'days_stuck' => $days_stuck,
            'current_office' => $document['current_office'] ?? 'Unknown Office',
            'is_urgent' => $document['is_urgent']
        ];
        
        // Create urgent reminder
        if (create_enhanced_notification($document['document_id'], $document['creator_id'] ?? 0, 'urgent_reminder', $additional_data)) {
            $urgent_reminders++;
        }
        
        // Also notify current office
        if ($document['current_office']) {
            $office_query = "SELECT office_id FROM document_workflow 
                            WHERE document_id = ? AND status = 'CURRENT'";
            $office_stmt = $conn->prepare($office_query);
            $office_stmt->bind_param("i", $document['document_id']);
            $office_stmt->execute();
            $office_result = $office_stmt->get_result();
            
            if ($office_result->num_rows > 0) {
                $office = $office_result->fetch_assoc();
                notify_office_users($office['office_id'], $document['document_id'], 'stuck_document', $additional_data);
            }
        }
    }
    
    return $urgent_reminders;
}

/**
 * Check for documents approaching deadlines
 */
function check_deadline_documents() {
    global $conn;
    
    // Check for documents with deadlines in the next 3 days
    $deadline_query = "SELECT d.document_id, d.title, d.deadline_date,
                              DATEDIFF(d.deadline_date, NOW()) as days_left,
                              u.full_name as creator_name
                       FROM documents d
                       LEFT JOIN users u ON d.creator_id = u.user_id
                       WHERE d.deadline_date IS NOT NULL
                       AND d.deadline_date > NOW()
                       AND DATEDIFF(d.deadline_date, NOW()) <= 3
                       AND d.status != 'completed'
                       AND d.document_id NOT IN (
                           SELECT DISTINCT document_id FROM notifications 
                           WHERE event_type = 'deadline_approaching' 
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                       )";
    
    $deadline_result = $conn->query($deadline_query);
    $deadline_reminders = 0;
    
    while ($document = $deadline_result->fetch_assoc()) {
        $additional_data = [
            'days_left' => $document['days_left'],
            'deadline_date' => $document['deadline_date']
        ];
        
        // Notify document creator
        if (create_enhanced_notification($document['document_id'], $document['creator_id'] ?? 0, 'deadline_approaching', $additional_data)) {
            $deadline_reminders++;
        }
        
        // Also notify current office
        $office_query = "SELECT office_id FROM document_workflow 
                        WHERE document_id = ? AND status = 'CURRENT'";
        $office_stmt = $conn->prepare($office_query);
        $office_stmt->bind_param("i", $document['document_id']);
        $office_stmt->execute();
        $office_result = $office_stmt->get_result();
        
        if ($office_result->num_rows > 0) {
            $office = $office_result->fetch_assoc();
            notify_office_users($office['office_id'], $document['document_id'], 'deadline_approaching', $additional_data);
        }
    }
    
    return $deadline_reminders;
}

/**
 * Generate AI-powered reminder message
 */
function generate_ai_reminder($document_id, $reminder_type = 'general') {
    global $conn;
    
    // Get document details
    $doc_query = "SELECT d.*, u.full_name as creator_name, dt.type_name,
                         o.office_name as current_office,
                         DATEDIFF(NOW(), d.created_at) as days_old
                  FROM documents d
                  LEFT JOIN users u ON d.creator_id = u.user_id
                  LEFT JOIN document_types dt ON d.type_id = dt.type_id
                  LEFT JOIN document_workflow dw ON d.document_id = dw.document_id AND dw.status = 'CURRENT'
                  LEFT JOIN offices o ON dw.office_id = o.office_id
                  WHERE d.document_id = ?";
    
    $doc_stmt = $conn->prepare($doc_query);
    $doc_stmt->bind_param("i", $document_id);
    $doc_stmt->execute();
    $doc_result = $doc_stmt->get_result();
    
    if ($doc_result->num_rows === 0) {
        throw new Exception('Document not found');
    }
    
    $document = $doc_result->fetch_assoc();
    
    // Generate AI-powered reminder based on document context
    $ai_reminder = generate_contextual_reminder($document, $reminder_type);
    
    return $ai_reminder;
}

/**
 * Generate contextual reminder message using AI logic
 */
function generate_contextual_reminder($document, $reminder_type) {
    $title = $document['title'];
    $type = $document['type_name'] ?? 'Document';
    $creator = $document['creator_name'] ?? 'Unknown';
    $current_office = $document['current_office'] ?? 'Unknown Office';
    $days_old = $document['days_old'];
    $is_urgent = $document['is_urgent'] ?? false;
    
    $urgency_level = 'normal';
    if ($is_urgent || $days_old >= 7) {
        $urgency_level = 'critical';
    } elseif ($days_old >= 3) {
        $urgency_level = 'high';
    }
    
    $reminder_templates = [
        'critical' => [
            'title' => "🚨 CRITICAL: Immediate Action Required",
            'message' => "Document \"$title\" has been pending for $days_old days and requires IMMEDIATE attention. This $type from $creator is currently stuck in $current_office and may be blocking other important processes."
        ],
        'high' => [
            'title' => "⚠️ High Priority: Document Needs Attention",
            'message' => "Document \"$title\" has been pending for $days_old days and needs prompt attention. This $type from $creator is currently in $current_office and should be processed soon to avoid delays."
        ],
        'normal' => [
            'title' => "📋 Reminder: Document Pending Review",
            'message' => "Document \"$title\" has been pending for $days_old days. This $type from $creator is currently in $current_office and requires your review."
        ]
    ];
    
    $template = $reminder_templates[$urgency_level] ?? $reminder_templates['normal'];
    
    // Add specific recommendations based on document type
    $recommendations = generate_document_recommendations($document);
    if ($recommendations) {
        $template['message'] .= "\n\nRecommendations: $recommendations";
    }
    
    return $template;
}

/**
 * Generate document-specific recommendations
 */
function generate_document_recommendations($document) {
    $type = $document['type_name'] ?? '';
    $days_old = $document['days_old'];
    $is_urgent = $document['is_urgent'] ?? false;
    
    $recommendations = [];
    
    // Type-specific recommendations
    switch (strtolower($type)) {
        case 'memorandum':
            $recommendations[] = "Memorandums typically require quick distribution and acknowledgment";
            break;
        case 'leave request':
            $recommendations[] = "Leave requests should be processed promptly to allow proper planning";
            break;
        case 'budget request':
            $recommendations[] = "Budget requests may have financial implications and should be prioritized";
            break;
        case 'report':
            $recommendations[] = "Reports may contain time-sensitive information";
            break;
    }
    
    // Time-based recommendations
    if ($days_old >= 7) {
        $recommendations[] = "Consider escalating to supervisor or department head";
    } elseif ($days_old >= 3) {
        $recommendations[] = "Please prioritize this document to avoid further delays";
    }
    
    // Urgency-based recommendations
    if ($is_urgent) {
        $recommendations[] = "This document is marked as urgent and requires immediate attention";
    }
    
    return implode('. ', $recommendations);
}
?>
