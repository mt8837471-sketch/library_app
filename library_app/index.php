<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['user_role'];
$name = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Главная - Библиотека</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; }
        .header { background: #333; color: white; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn { padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; margin-left: 10px; }
        .btn-danger { background: #dc3545; }
        .container { background: white; padding: 20px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Библиотека</h1>
        <div>
            Привет, <?= htmlspecialchars($name) ?> (<?= $role == 'admin' ? 'Администратор' : 'Читатель' ?>)
            <a href="logout.php" class="btn btn-danger">Выйти</a>
        </div>
    </div>
    <div class="container">
        <h2>Доступные действия:</h2>
        <a href="catalog.php" class="btn">Каталог книг</a>
        <a href="search.php" class="btn">Расширенный поиск</a>
        <a href="my_books.php" class="btn">Мои книги</a>
<?php if ($role == 'admin'): ?>
    <a href="admin_users.php" class="btn">Управление читателями</a>
    <a href="admin_loans.php" class="btn">Управление выдачей</a>
    <a href="admin_add_book.php" class="btn">Добавить книгу</a>
<?php endif; ?>
    </div>
</body>
</html>