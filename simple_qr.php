<?php
/**
 * Simple QR Code Generator
 * 
 * This is a standalone QR code generator that doesn't rely on any PHP extensions
 */

// Get parameters from URL
$text = isset($_GET['text']) ? $_GET['text'] : 'Hello World';
$size = isset($_GET['size']) ? intval($_GET['size']) : 300;

// Redirect to Google Charts API for QR code generation
$google_charts_url = 'https://chart.googleapis.com/chart?cht=qr&chs=' . $size . 'x' . $size . '&chl=' . urlencode($text);
header('Location: ' . $google_charts_url);
exit;
