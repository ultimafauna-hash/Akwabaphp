<?php
/**
 * Akwaba Info - Events Endpoint
 */
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$data = getJSONInput();

switch ($method) {
    case 'GET':
        $stmt = $pdo->query("SELECT * FROM events ORDER BY created_at DESC");
        sendResponse($stmt->fetchAll());
        break;

    case 'POST':
        try {
            $user = requireAdmin($pdo);
            $event = $data;
            $id = $event['id'] ?? null;

            if ($id && !is_numeric($id)) {
                $id = null; // Treat UUID as new for events too to be safe with numerical IDs
            }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE events SET title = ?, description = ?, date = ?, time = ?, location = ?, category = ?, image = ?, video = ?, isurgent = ?, status = ? WHERE id = ?");
                $stmt->execute([
                    $event['title'] ?? '', $event['description'] ?? '', $event['date'] ?? '', $event['time'] ?? '', $event['location'] ?? '',
                    $event['category'] ?? 'Événement Culturel', $event['image'] ?? null, $event['video'] ?? null, (isset($event['isurgent']) && $event['isurgent']) ? 1 : 0, $event['status'] ?? 'upcoming', $id
                ]);
                sendResponse(["success" => true, "id" => $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO events (title, description, date, time, location, category, image, video, isurgent, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $event['title'] ?? '', $event['description'] ?? '', $event['date'] ?? '', $event['time'] ?? '', $event['location'] ?? '',
                    $event['category'] ?? 'Événement Culturel', $event['image'] ?? null, $event['video'] ?? null, (isset($event['isurgent']) && $event['isurgent']) ? 1 : 0, $event['status'] ?? 'upcoming'
                ]);
                sendResponse(["success" => true, "id" => $pdo->lastInsertId()]);
            }
        } catch (Throwable $e) {
            sendResponse(["error" => "Erreur lors de l'enregistrement de l'événement: " . $e->getMessage()], 500);
        }
        break;

    case 'DELETE':
        $user = requireAdmin($pdo);
        $id = $_GET['id'] ?? null;
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(["success" => true]);
        break;
}
