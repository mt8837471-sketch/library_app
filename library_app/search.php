<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Фильтры
$author = trim($_GET['author'] ?? '');
$year_from = (int)($_GET['year_from'] ?? 0);
$year_to = (int)($_GET['year_to'] ?? 0);
$genre = trim($_GET['genre'] ?? '');

// Строим запрос
$sql = "SELECT * FROM books WHERE 1=1";
$params = [];

if ($author) {
    $sql .= " AND author LIKE ?";
    $params[] = "%$author%";
}
if ($year_from > 0) {
    $sql .= " AND year >= ?";
    $params[] = $year_from;
}
if ($year_to > 0) {
    $sql .= " AND year <= ?";
    $params[] = $year_to;
}
if ($genre) {
    $sql .= " AND genre LIKE ?";
    $params[] = "%$genre%";
}

$sql .= " ORDER BY title";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем список жанров для фильтра
$genres = $pdo->query("SELECT DISTINCT genre FROM books ORDER BY genre")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Расширенный поиск - Библиотека</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; }
        .header { background: #333; color: white; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .nav { display: flex; gap: 15px; align-items: center; }
        .nav a { color: white; text-decoration: none; }
        .btn { padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .btn-danger { background: #dc3545; }
        .container { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .filter-form label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .filter-form input, .filter-form select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .books-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .book-card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; }
        .book-card h3 { margin-top: 0; color: #333; }
        .book-card p { margin: 5px 0; color: #666; }
        .available { color: #28a745; font-weight: bold; }
        .unavailable { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>

<div class="header">
    <h1>Расширенный поиск</h1>
    <div class="nav">
        <a href="index.php">Главная</a>
        <a href="catalog.php">Каталог</a>
        <a href="my_books.php">Мои книги</a>
        <a href="logout.php" class="btn btn-danger">Выйти</a>
    </div>
</div>

<div class="container">
    <h2>Фильтры</h2>
    <form method="GET" class="filter-form">
        <div>
            <label>Автор:</label>
            <input type="text" name="author" value="<?= htmlspecialchars($author) ?>" placeholder="Введите автора">
        </div>
        <div>
            <label>Год от:</label>
            <input type="number" name="year_from" value="<?= $year_from ?: '' ?>" placeholder="Например, 1800">
        </div>
        <div>
            <label>Год до:</label>
            <input type="number" name="year_to" value="<?= $year_to ?: '' ?>" placeholder="Например, 2000">
        </div>
        <div>
            <label>Жанр:</label>
            <select name="genre">
                <option value="">-- Все жанры --</option>
                <?php foreach ($genres as $g): ?>
                    <option value="<?= htmlspecialchars($g) ?>" <?= $genre === $g ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; align-items: end;">
            <button type="submit" class="btn">Найти</button>
        </div>
    </form>
</div>

<div class="container">
    <h2>Результаты поиска (<?= count($books) ?>)</h2>
    <?php if (empty($books)): ?>
        <p>Книги не найдены.</p>
    <?php else: ?>
        <div class="books-grid">
            <?php foreach ($books as $book): ?>
                <div class="book-card">
                    <h3><?= htmlspecialchars($book['title']) ?></h3>
                    <p><strong>Автор:</strong> <?= htmlspecialchars($book['author']) ?></p>
                    <p><strong>Год:</strong> <?= $book['year'] ?></p>
                    <p><strong>Жанр:</strong> <?= htmlspecialchars($book['genre']) ?></p>
                    <p class="<?= $book['available'] ? 'available' : 'unavailable' ?>">
                        <?= $book['available'] ? '✓ Доступна' : '✗ Недоступна' ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>