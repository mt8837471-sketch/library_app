<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

// Удаление читателя
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    // Не даём удалить самого себя
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'reader'");
        if ($stmt->execute([$user_id])) {
            $message = 'Читатель удалён!';
        } else {
            $error = 'Ошибка при удалении!';
        }
    } else {
        $error = 'Нельзя удалить самого себя!';
    }
}

// Получаем всех читателей
$readers = $pdo->query("SELECT * FROM users WHERE role = 'reader' ORDER BY registration_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Подсчёт просроченных
$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status = 'overdue'");
$stmt->execute([$_SESSION['user_id']]);
$overdue_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление читателями - Библиотека</title>
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
        .overdue-badge { background: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .section-title { color: #333; border-bottom: 2px solid #007BFF; padding-bottom: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="header">
    <h1>Управление читателями</h1>
    <div class="nav">
        <a href="index.php">Главная</a>
        <a href="catalog.php">Каталог</a>
        <a href="search.php">Расширенный поиск</a>
        <a href="my_books.php">Мои книги</a>
        <a href="admin_loans.php">Управление выдачей</a>
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
    <h2 class="section-title">Список читателей (<?= count($readers) ?>)</h2>
    <?php if (empty($readers)): ?>
        <p>Читателей пока нет.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>ID</th>
                <th>ФИО</th>
                <th>Email</th>
                <th>Телефон</th>
                <th>Дата регистрации</th>
                <th>Действия</th>
            </tr>
            <?php foreach ($readers as $reader): ?>
                <tr>
                    <td><?= $reader['id'] ?></td>
                    <td><?= htmlspecialchars($reader['name']) ?></td>
                    <td><?= htmlspecialchars($reader['email']) ?></td>
                    <td><?= htmlspecialchars($reader['phone']) ?></td>
                    <td><?= date('d.m.Y', strtotime($reader['registration_date'])) ?></td>
                    <td>
                        <a href="?delete=<?= $reader['id'] ?>" class="btn btn-danger" onclick="return confirm('Удалить читателя?')">Удалить</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>