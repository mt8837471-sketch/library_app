<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $year = (int)$_POST['year'];
    $genre = trim($_POST['genre']);
    
    if ($title && $author && $year > 0) {
        $stmt = $pdo->prepare("INSERT INTO books (title, author, year, genre, available) VALUES (?, ?, ?, ?, TRUE)");
        if ($stmt->execute([$title, $author, $year, $genre])) {
            $message = "Книга '{$title}' добавлена!";
        } else {
            $error = 'Ошибка при добавлении книги';
        }
    } else {
        $error = 'Заполните все обязательные поля';
    }
}

// Подсчёт просроченных
$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status = 'overdue'");
$stmt->execute([$_SESSION['user_id']]);
$overdue_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавление книг - Библиотека</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; }
        .header { background: #333; color: white; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .nav { display: flex; gap: 15px; align-items: center; }
        .nav a { color: white; text-decoration: none; }
        .btn { padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .btn-danger { background: #dc3545; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
        .message { padding: 10px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .overdue-badge { background: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>

<div class="header">
    <h1>Добавление книги</h1>
    <div class="nav">
        <a href="index.php">Главная</a>
        <a href="catalog.php">Каталог</a>
        <a href="admin_users.php">Читатели</a>
        <a href="admin_loans.php">Выдача</a>
        <?php if ($overdue_count > 0): ?>
            <span class="overdue-badge">Просрочено: <?= $overdue_count ?></span>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-danger">Выйти</a>
    </div>
</div>

<div class="container">
    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?= $error ?></div>
    <?php endif; ?>

    <h2>Добавить новую книгу</h2>
    <form method="POST">
        <div class="form-group">
            <label>Название *</label>
            <input type="text" name="title" required>
        </div>
        <div class="form-group">
            <label>Автор *</label>
            <input type="text" name="author" required>
        </div>
        <div class="form-group">
            <label>Год издания *</label>
            <input type="number" name="year" min="1000" max="2099" required>
        </div>
        <div class="form-group">
            <label>Жанр</label>
            <input type="text" name="genre">
        </div>
        <button type="submit">Добавить книгу</button>
    </form>
</div>

</body>
</html>