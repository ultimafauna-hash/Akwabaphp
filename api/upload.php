<?php
/**
 * Akwaba Info - Upload Endpoint
 */
require_once 'config.php';

$user = requireAuth($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendResponse(["error" => "Méthode non autorisée"], 405);

if (!isset($_FILES['file'])) sendResponse(["error" => "Aucun fichier envoyé"], 400);

$file = $_FILES['file'];
$uploadDir = __DIR__ . '/uploads/';

// Ensure directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Validation
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/quicktime'];
if (!in_array($mime, $allowedTypes)) {
    sendResponse(["error" => "Type de fichier non autorisé: " . $mime], 400);
}

// 5MB Max
if ($file['size'] > 5 * 1024 * 1024) {
    sendResponse(["error" => "Fichier trop volumineux (Max 5Mo)"], 400);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
$targetPath = $uploadDir . $newFilename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Relative URL for the frontend
    $url = '/uploads/' . $newFilename;
    
    // Save to media table
    $stmt = $pdo->prepare("INSERT INTO media (url, type, filename, size) VALUES (?, ?, ?, ?)");
    $type = strpos($file['type'], 'image') !== false ? 'image' : 'video';
    $stmt->execute([$url, $type, $file['name'], $file['size']]);
    
    sendResponse(["url" => $url, "id" => $pdo->lastInsertId()]);
} else {
    sendResponse(["error" => "Échec de l'upload"], 500);
}
