<?php
// Minimal print-friendly page that renders document content same-origin
session_start();
require_once '../includes/config.php';

$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mode = isset($_GET['mode']) ? strtolower($_GET['mode']) : '';
$isPreview = ($mode === 'preview');
if (!$document_id) { die('Missing id'); }

// Fetch document
$stmt = $conn->prepare('SELECT * FROM documents WHERE document_id = ?');
$stmt->bind_param('i', $document_id);
$stmt->execute();
$res = $stmt->get_result();
$doc = $res->fetch_assoc();
if (!$doc) { die('Document not found'); }

// Resolve content
$title = htmlspecialchars($doc['title'] ?? 'Document');
$filePath = $doc['file_path'] ?? '';
$googleDocId = $doc['google_doc_id'] ?? '';
$content = '';

// 1) If plain text file on disk
if (!empty($filePath)) {
    $path = str_replace('\\', '/', $filePath);
    if (strpos($path, '../') !== 0 && strpos($path, '/') !== 0) {
        $path = '../' . $path;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'txt' && file_exists($path)) {
        $content = file_get_contents($path);
    }
}

// 2) Otherwise from DB content/description
if ($content === '') {
    if (!empty($doc['content'])) {
        $content = $doc['content'];
    } elseif (!empty($doc['description'])) {
        $content = $doc['description'];
    }
}

// 3) If Google Doc and still no content, try to fetch via API
if ($content === '' && !empty($googleDocId)) {
    try {
        require_once '../includes/google_docs_manager.php';
        $userId = $_SESSION['user_id'] ?? 0;
        if ($userId) {
            $mgr = new GoogleDocsManager($userId);
            $content = $mgr->getDocumentContent($googleDocId);
        }
    } catch (Throwable $e) {
        // ignore; will show message below
    }
}

// Basic header info
$created = isset($doc['created_at']) ? date('m/d/Y', strtotime($doc['created_at'])) : date('m/d/Y');
// Flag to render Google Doc preview iframe if we couldn't fetch plain text
$renderGooglePreview = ($content === '' && !empty($googleDocId));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo $title; ?><?php echo $isPreview ? ' - Preview' : ' - Print'; ?></title>
    <style>
        html, body { margin:0; padding:0; background:#fff; color:#000; font-family: Arial, sans-serif; }
        .page { padding: 20mm; }
        .letterhead { text-align:center; margin-bottom: 12mm; }
        .letterhead h1 { color: #1c5738; margin: 0 0 4px 0; font-size: 20px; }
        .letterhead p { margin: 2px 0; font-size: 12px; }
        .date { margin: 0 0 10mm 0; font-size: 12px; }
        .content { white-space: pre-wrap; line-height: 1.4; font-size: 12px; }
        .doc-frame { width: 100%; height: 1000px; border: 0; }
        @page { size: A4; margin: 12mm; }
    </style>
</head>
<body>
    <div class="page" style="<?php echo $renderGooglePreview ? 'padding:0;' : '';?>">
        <?php if (!$renderGooglePreview): ?>
            <div class="letterhead">
                <h1>SAINT COLUMBAN COLLEGE</h1>
                <p>Pagadian City, Zamboanga del Sur</p>
                <p>Tel. No. (062) 214-2174 | Email: scc@saintcolumban.edu.ph</p>
                <p>A Catholic Educational Institution</p>
            </div>
            <div class="date"><?php echo htmlspecialchars($created); ?></div>
        <?php endif; ?>
        <?php if ($content !== ''): ?>
            <div class="content"><?php echo nl2br(htmlspecialchars($content)); ?></div>
        <?php elseif ($renderGooglePreview): ?>
            <iframe class="doc-frame" src="https://docs.google.com/document/d/<?php echo htmlspecialchars($googleDocId); ?>/preview"></iframe>
        <?php else: ?>
            <div class="content">Document content is not available for printing.</div>
        <?php endif; ?>
    </div>
    <?php if (!$isPreview): ?>
    <script>
      // Delay print to allow iframe (if any) to render
      window.addEventListener('load', function() {
        setTimeout(function(){ window.print(); }, 600);
      });
    </script>
    <?php endif; ?>
</body>
</html>


