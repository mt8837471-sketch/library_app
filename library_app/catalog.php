<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Быстрый поиск
$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    $stmt = $pdo->prepare("SELECT * FROM books WHERE title LIKE ? OR author LIKE ? ORDER BY title");
    $stmt->execute(["%$search%", "%$search%"]);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $books = $pdo->query("SELECT * FROM books ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
}

// Подача заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'])) {
    $book_id = (int)$_POST['book_id'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT id FROM requests WHERE user_id = ? AND book_id = ? AND status = 'pending'");
    $stmt->execute([$user_id, $book_id]);
    
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO requests (user_id, book_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$user_id, $book_id]);
        $message = 'Заявка подана!';
    } else {
        $error = 'Заявка уже существует!';
    }
}

// Подсчёт просроченных книг
$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status = 'overdue'");
$stmt->execute([$_SESSION['user_id']]);
$overdue_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Каталог - Библиотека</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; }
        .header { background: #333; color: white; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .nav { display: flex; gap: 15px; align-items: center; }
        .nav a { color: white; text-decoration: none; }
        .btn { padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
        .container { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .message { padding: 10px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; }
        .search-form { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-form input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .books-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .book-card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; }
        .book-card h3 { margin-top: 0; color: #333; }
        .book-card p { margin: 5px 0; color: #666; }
        .available { color: #28a745; font-weight: bold; }
        .unavailable { color: #dc3545; font-weight: bold; }
        .overdue-badge { background: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>

<div class="header">
    <h1>Каталог книг</h1>
    <div class="nav">
        <a href="index.php">Главная</a>
        <a href="search.php">Расширенный поиск</a>
        <a href="my_books.php">Мои книги</a>
        <?php if ($overdue_count > 0): ?>
            <span class="overdue-badge">Просрочено: <?= $overdue_count ?></span>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-danger">Выйти</a>
    </div>
</div>

<?php if ($message): ?>
    <div class="message"><?= $message ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="message error"><?= $error ?></div>
<?php endif; ?>

<div class="container">
    <form method="GET" class="search-form">
        <input type="text" name="search" placeholder="Быстрый поиск по названию или автору..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn">Найти</button>
    </form>

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
                <?php if ($book['available']): ?>
                    <form method="POST">
                        <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                        <button type="submit" class="btn btn-success">Подать заявку</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>