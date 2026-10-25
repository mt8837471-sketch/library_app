<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Отмена заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $request_id = (int)$_POST['request_id'];
    $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$request_id, $user_id]);
    $message = 'Заявка отменена!';
}

// Продление срока (только для админа или если книга выдана)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extend_loan'])) {
    $loan_id = (int)$_POST['loan_id'];
    $stmt = $pdo->prepare("UPDATE loans SET due_date = DATE_ADD(due_date, INTERVAL 7 DAY) WHERE id = ? AND user_id = ?");
    $stmt->execute([$loan_id, $user_id]);
    $message = 'Срок продлён на 7 дней!';
}

// Заявки пользователя
$stmt = $pdo->prepare("SELECT r.*, b.title, b.author FROM requests r 
                       JOIN books b ON r.book_id = b.id 
                       WHERE r.user_id = ? ORDER BY r.request_date DESC");
$stmt->execute([$user_id]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Выданные книги
$stmt = $pdo->prepare("SELECT l.*, b.title, b.author FROM loans l 
                       JOIN books b ON l.book_id = b.id 
                       WHERE l.user_id = ? ORDER BY l.loan_date DESC");
$stmt->execute([$user_id]);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Подсчёт просроченных
$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status = 'overdue'");
$stmt->execute([$user_id]);
$overdue_count = $stmt->fetchColumn();

function getStatusName($status) {
    $names = [
        'pending' => 'Ожидает',
        'approved' => 'Одобрена',
        'rejected' => 'Отклонена',
        'active' => 'Выдана',
        'returned' => 'Возвращена',
        'overdue' => 'Просрочена'
    ];
    return $names[$status] ?? $status;
}

function getStatusColor($status) {
    $colors = [
        'pending' => '#ffc107',
        'approved' => '#17a2b8',
        'rejected' => '#dc3545',
        'active' => '#007BFF',
        'returned' => '#28a745',
        'overdue' => '#dc3545'
    ];
    return $colors[$status] ?? '#666';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мои книги - Библиотека</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; }
        .header { background: #333; color: white; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .nav { display: flex; gap: 15px; align-items: center; }
        .nav a { color: white; text-decoration: none; }
        .btn { padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .btn-danger { background: #dc3545; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-success { background: #28a745; }
        .container { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .message { padding: 10px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; }
        .overdue-badge { background: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .section-title { color: #333; border-bottom: 2px solid #007BFF; padding-bottom: 5px; margin-bottom: 15px; }
        .card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 10px; }
        .status-badge { padding: 4px 8px; border-radius: 4px; color: white; font-weight: bold; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>

<div class="header">
    <h1>Мои книги</h1>
    <div class="nav">
        <a href="index.php">Главная</a>
        <a href="catalog.php">Каталог</a>
        <a href="search.php">Расширенный поиск</a>
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

<!-- Заявки -->
<div class="container">
    <h2 class="section-title">Мои заявки (<?= count($requests) ?>)</h2>
    <?php if (empty($requests)): ?>
        <p>У вас нет заявок.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Книга</th>
                <th>Автор</th>
                <th>Дата заявки</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
            <?php foreach ($requests as $req): ?>
                <tr>
                    <td><?= htmlspecialchars($req['title']) ?></td>
                    <td><?= htmlspecialchars($req['author']) ?></td>
                    <td><?= date('d.m.Y', strtotime($req['request_date'])) ?></td>
                    <td>
                        <span class="status-badge" style="background: <?= getStatusColor($req['status']) ?>">
                            <?= getStatusName($req['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($req['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                <button type="submit" name="cancel_request" class="btn btn-danger" onclick="return confirm('Отменить заявку?')">Отменить</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<!-- Выданные книги -->
<div class="container">
    <h2 class="section-title">Выданные книги (<?= count($loans) ?>)</h2>
    <?php if (empty($loans)): ?>
        <p>У вас нет выданных книг.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Книга</th>
                <th>Автор</th>
                <th>Дата выдачи</th>
                <th>Срок возврата</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
            <?php foreach ($loans as $loan): ?>
                <tr>
                    <td><?= htmlspecialchars($loan['title']) ?></td>
                    <td><?= htmlspecialchars($loan['author']) ?></td>
                    <td><?= date('d.m.Y', strtotime($loan['loan_date'])) ?></td>
                    <td><?= date('d.m.Y', strtotime($loan['due_date'])) ?></td>
                    <td>
                        <span class="status-badge" style="background: <?= getStatusColor($loan['status']) ?>">
                            <?= getStatusName($loan['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($loan['status'] === 'active'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                <button type="submit" name="extend_loan" class="btn btn-warning">Продлить</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>