<?php
/**
 * Akwaba Info - Articles Endpoint
 */
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$data = getJSONInput();

switch ($method) {
    case 'GET':
        if (isset($_GET['slug'])) {
            $stmt = $pdo->prepare("SELECT * FROM articles WHERE slug = ?");
            $stmt->execute([$_GET['slug']]);
            $article = $stmt->fetch();
            if ($article) {
                // Decode JSON fields
                $article['gallery'] = json_decode($article['gallery'] ?? '[]', true);
                $article['reactions'] = json_decode($article['reactions'] ?? '{}', true);
                $article['tags'] = json_decode($article['tags'] ?? '[]', true);
                sendResponse($article);
            }
            sendResponse(["error" => "Article non trouvé"], 404);
        }

        $query = "SELECT * FROM articles WHERE status = 'published'";
        $params = [];

        if (isset($_GET['category']) && $_GET['category'] !== 'Tout') {
            $query .= " AND category = ?";
            $params[] = $_GET['category'];
        }

        if (isset($_GET['search'])) {
            $query .= " AND (title LIKE ? OR content LIKE ?)";
            $searchTerm = "%" . $_GET['search'] . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $query .= " ORDER BY created_at DESC";
        
        if (isset($_GET['limit'])) {
            $query .= " LIMIT " . intval($_GET['limit']);
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $articles = $stmt->fetchAll();

        // Process JSON fields
        foreach ($articles as &$art) {
            $art['gallery'] = json_decode($art['gallery'] ?? '[]', true);
            $art['reactions'] = json_decode($art['reactions'] ?? '{}', true);
            $art['tags'] = json_decode($art['tags'] ?? '[]', true);
        }

        sendResponse($articles);
        break;

    case 'POST':
        if ($action === 'like') {
            $user = requireAuth($pdo);
            $id = $data['id'] ?? null;
            if (!$id) sendResponse(["error" => "ID requis"], 400);
            
            $stmt = $pdo->prepare("INSERT IGNORE INTO article_likes (article_id, user_id) VALUES (?, ?)");
            $stmt->execute([$id, $user['id']]);
            
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE articles SET likes = likes + 1 WHERE id = ?");
                $stmt->execute([$id]);
            }
            sendResponse(["success" => true]);
        }

        if ($action === 'view') {
            $id = $data['id'] ?? null;
            if (!$id) sendResponse(["error" => "ID requis"], 400);
            $stmt = $pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?");
            $stmt->execute([$id]);
            sendResponse(["success" => true]);
        }

        // Save article (Create/Update by Admin)
        try {
            $user = requireAdmin($pdo);
            $article = $data;
            $id = $article['id'] ?? null;
            
            // Prevent UUIDs from being treated as numerical IDs if they are not in the DB
            // If ID is not numeric, we check if it exists. If not, we treat it as a new article (null ID)
            if ($id && !is_numeric($id)) {
                $checkStmt = $pdo->prepare("SELECT id FROM articles WHERE id = ?");
                try {
                    $checkStmt->execute([$id]);
                    if (!$checkStmt->fetch()) {
                        $id = null; // Article with this string ID doesn't exist in DB, treat as new
                    }
                } catch (Exception $e) {
                    $id = null;
                }
            }

            // Prepare JSON fields
            $gallery = json_encode($article['gallery'] ?? []);
            $reactions = json_encode($article['reactions'] ?? (object)[]);
            $tags = json_encode($article['tags'] ?? []);

            if ($id) {
                // Update
                $sql = "UPDATE articles SET 
                    slug = ?, title = ?, content = ?, date = ?, category = ?, 
                    rubric = ?, country = ?, is_featured = ?,
                    image = ?, video = ?, audiourl = ?, gallery = ?, author = ?, 
                    authorrole = ?, excerpt = ?, readingtime = ?, imagecredit = ?, source = ?, 
                    tags = ?, status = ?, ispremium = ?, seotitle = ?, seodescription = ?, socialimage = ?,
                    reactions = ?
                    WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $article['slug'] ?? '', 
                    $article['title'] ?? '', 
                    $article['content'] ?? '', 
                    $article['date'] ?? date('Y-m-d H:i:s'), 
                    $article['category'] ?? '',
                    $article['rubric'] ?? null, 
                    $article['country'] ?? null, 
                    (isset($article['is_featured']) && $article['is_featured']) ? 1 : 0,
                    $article['image'] ?? null, 
                    $article['video'] ?? null, 
                    $article['audiourl'] ?? null, 
                    $gallery, 
                    $article['author'] ?? 'Rédaction',
                    $article['authorrole'] ?? 'Journaliste', 
                    $article['excerpt'] ?? '', 
                    $article['readingtime'] ?? '4 min', 
                    $article['imagecredit'] ?? '', 
                    $article['source'] ?? '',
                    $tags, 
                    $article['status'] ?? 'published', 
                    (isset($article['ispremium']) && $article['ispremium']) ? 1 : 0, 
                    $article['seotitle'] ?? '', 
                    $article['seodescription'] ?? '', 
                    $article['socialimage'] ?? '',
                    $reactions,
                    $id
                ]);
                sendResponse(["success" => true, "id" => $id]);
            } else {
                // Create
                $sql = "INSERT INTO articles (
                    slug, title, content, date, category, rubric, country, is_featured, image, video, audiourl, gallery, author, 
                    authorrole, excerpt, readingtime, imagecredit, source, tags, status, ispremium, 
                    seotitle, seodescription, socialimage, reactions
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $article['slug'] ?? '', 
                    $article['title'] ?? '', 
                    $article['content'] ?? '', 
                    $article['date'] ?? date('Y-m-d H:i:s'), 
                    $article['category'] ?? '',
                    $article['rubric'] ?? null, 
                    $article['country'] ?? null, 
                    (isset($article['is_featured']) && $article['is_featured']) ? 1 : 0,
                    $article['image'] ?? null, 
                    $article['video'] ?? null, 
                    $article['audiourl'] ?? null, 
                    $gallery, 
                    $article['author'] ?? 'Rédaction',
                    $article['authorrole'] ?? 'Journaliste', 
                    $article['excerpt'] ?? '', 
                    $article['readingtime'] ?? '4 min', 
                    $article['imagecredit'] ?? '', 
                    $article['source'] ?? '',
                    $tags, 
                    $article['status'] ?? 'published', 
                    (isset($article['ispremium']) && $article['ispremium']) ? 1 : 0, 
                    $article['seotitle'] ?? '', 
                    $article['seodescription'] ?? '', 
                    $article['socialimage'] ?? '',
                    $reactions
                ]);
                sendResponse(["success" => true, "id" => $pdo->lastInsertId()]);
            }
        } catch (Throwable $e) {
            sendResponse(["error" => "Erreur lors de l'enregistrement de l'article: " . $e->getMessage()], 500);
        }
        break;

    case 'DELETE':
        $user = requireAdmin($pdo);
        $id = $_GET['id'] ?? null;
        if (!$id) sendResponse(["error" => "ID requis"], 400);
        
        $stmt = $pdo->prepare("SELECT id FROM articles WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) sendResponse(["error" => "Article non trouvé"], 404);

        $stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(["success" => true]);
        break;

    default:
        sendResponse(["error" => "Méthode non autorisée"], 405);
}
