<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

// 1. ВЫДАЧА КНИГИ (по заявке)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request'])) {
    $request_id = (int)$_POST['request_id'];
    $user_id = (int)$_POST['user_id'];
    $book_id = (int)$_POST['book_id'];
    $days = (int)$_POST['days']; // срок выдачи в днях
    
    $pdo->beginTransaction();
    try {
        // Обновляем статус заявки
        $stmt = $pdo->prepare("UPDATE requests SET status = 'approved' WHERE id = ?");
        $stmt->execute([$request_id]);
        
        // Создаём запись о выдаче
        $due_date = date('Y-m-d', strtotime("+{$days} days"));
        $stmt = $pdo->prepare("INSERT INTO loans (user_id, book_id, loan_date, due_date, status) 
                               VALUES (?, ?, CURDATE(), ?, 'active')");
        $stmt->execute([$user_id, $book_id, $due_date]);
        
        // Помечаем книгу как недоступную
        $stmt = $pdo->prepare("UPDATE books SET available = FALSE WHERE id = ?");
        $stmt->execute([$book_id]);
        
        $pdo->commit();
        $message = "Книга выдана на {$days} дней!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при выдаче: ' . $e->getMessage();
    }
}

// 2. ОТКЛОНЕНИЕ ЗАЯВКИ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
    $request_id = (int)$_POST['request_id'];
    $stmt = $pdo->prepare("UPDATE requests SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$request_id]);
    $message = 'Заявка отклонена!';
}

// 3. ПРОДЛЕНИЕ СРОКА
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extend_loan'])) {
    $loan_id = (int)$_POST['loan_id'];
    $days = (int)$_POST['extend_days'];
    $stmt = $pdo->prepare("UPDATE loans SET due_date = DATE_ADD(due_date, INTERVAL ? DAY) WHERE id = ? AND status = 'active'");
    $stmt->execute([$days, $loan_id]);
    $message = "Срок продлён на {$days} дней!";
}

// 4. ВОЗВРАТ КНИГИ (принятие)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book'])) {
    $loan_id = (int)$_POST['loan_id'];
    
    $pdo->beginTransaction();
    try {
        // Получаем book_id
        $stmt = $pdo->prepare("SELECT book_id FROM loans WHERE id = ?");
        $stmt->execute([$loan_id]);
        $book_id = $stmt->fetchColumn();
        
        // Обновляем статус выдачи
        $stmt = $pdo->prepare("UPDATE loans SET status = 'returned', return_date = CURDATE() WHERE id = ?");
        $stmt->execute([$loan_id]);
        
        // Делаем книгу снова доступной
        $stmt = $pdo->prepare("UPDATE books SET available = TRUE WHERE id = ?");
        $stmt->execute([$book_id]);
        
        $pdo->commit();
        $message = 'Книга принята в библиотеку!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при возврате: ' . $e->getMessage();
    }
}

// Обновляем статусы просроченных книг
$pdo->query("UPDATE loans SET status = 'overdue' WHERE status = 'active' AND due_date < CURDATE()");

// Получаем все заявки
$requests = $pdo->query("SELECT r.*, u.name AS user_name, b.title AS book_title 
                         FROM requests r 
                         JOIN users u ON r.user_id = u.id 
                         JOIN books b ON r.book_id = b.id 
                         ORDER BY r.request_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Получаем все активные и просроченные выдачи
$loans = $pdo->query("SELECT l.*, u.name AS user_name, b.title AS book_title 
                      FROM loans l 
                      JOIN users u ON l.user_id = u.id 
                      JOIN books b ON l.book_id = b.id 
                      WHERE l.status IN ('active', 'overdue') 
                      ORDER BY l.due_date ASC")->fetchAll(PDO::FETCH_ASSOC);

// Подсчёт просроченных
$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status = 'overdue'");
$stmt->execute([$_SESSION['user_id']]);
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
        'rejected' => '#6c757d',
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
    <title>Управление выдачей - Библиотека</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; }
        .header { background: #333; color: white; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .nav { display: flex; gap: 15px; align-items: center; }
        .nav a { color: white; text-decoration: none; }
        .btn { padding: 8px 16px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
        .container { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .message { padding: 10px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; }
        .overdue-badge { background: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .section-title { color: #333; border-bottom: 2px solid #007BFF; padding-bottom: 5px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .status-badge { padding: 4px 8px; border-radius: 4px; color: white; font-weight: bold; font-size: 12px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; padding: 20px; border-radius: 8px; width: 400px; margin: 100px auto; }
        .modal-content h3 { margin-top: 0; }
        .modal-content input { width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; }
    </style>
</head>
<body>

<div class="header">
    <h1>Управление выдачей</h1>
    <div class="nav">
        <a href="index.php">Главная</a>
        <a href="catalog.php">Каталог</a>
        <a href="search.php">Расширенный поиск</a>
        <a href="my_books.php">Мои книги</a>
        <a href="admin_users.php">Читатели</a>
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

<!-- СЕКЦИЯ 1: ЗАЯВКИ НА ВЫДАЧУ -->
<div class="container">
    <h2 class="section-title">Заявки на выдачу книг (<?= count($requests) ?>)</h2>
    <?php if (empty($requests)): ?>
        <p>Заявок пока нет.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Читатель</th>
                <th>Книга</th>
                <th>Дата заявки</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
            <?php foreach ($requests as $req): ?>
                <tr>
                    <td><?= $req['id'] ?></td>
                    <td><?= htmlspecialchars($req['user_name']) ?></td>
                    <td><?= htmlspecialchars($req['book_title']) ?></td>
                    <td><?= date('d.m.Y', strtotime($req['request_date'])) ?></td>
                    <td>
                        <span class="status-badge" style="background: <?= getStatusColor($req['status']) ?>">
                            <?= getStatusName($req['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($req['status'] === 'pending'): ?>
                            <button class="btn btn-success" onclick="openIssueModal(<?= $req['id'] ?>, <?= $req['user_id'] ?>, <?= $req['book_id'] ?>, '<?= htmlspecialchars($req['book_title']) ?>')">Выдать</button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                <button type="submit" name="reject_request" class="btn btn-danger" onclick="return confirm('Отклонить заявку?')">Отклонить</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<!-- СЕКЦИЯ 2: АКТИВНЫЕ ВЫДАЧИ -->
<div class="container">
    <h2 class="section-title">Активные выдачи (<?= count($loans) ?>)</h2>
    <?php if (empty($loans)): ?>
        <p>Активных выдач нет.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Книга</th>
                <th>Читатель</th>
                <th>Дата выдачи</th>
                <th>Срок возврата</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
            <?php foreach ($loans as $loan): ?>
                <tr>
                    <td><?= $loan['id'] ?></td>
                    <td><?= htmlspecialchars($loan['book_title']) ?></td>
                    <td><?= htmlspecialchars($loan['user_name']) ?></td>
                    <td><?= date('d.m.Y', strtotime($loan['loan_date'])) ?></td>
                    <td><?= date('d.m.Y', strtotime($loan['due_date'])) ?></td>
                    <td>
                        <span class="status-badge" style="background: <?= getStatusColor($loan['status']) ?>">
                            <?= getStatusName($loan['status']) ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-warning" onclick="openExtendModal(<?= $loan['id'] ?>)">Продлить</button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                            <button type="submit" name="return_book" class="btn btn-success" onclick="return confirm('Принять книгу в библиотеку?')">Принять</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<!-- МОДАЛЬНОЕ ОКНО ВЫДАЧИ -->
<div id="issueModal" class="modal">
    <div class="modal-content">
        <h3>Выдача книги</h3>
        <p>Книга: <strong id="issueBookTitle"></strong></p>
        <form method="POST">
            <input type="hidden" name="request_id" id="issueRequestId">
            <input type="hidden" name="user_id" id="issueUserId">
            <input type="hidden" name="book_id" id="issueBookId">
            <label>Срок выдачи (дней):</label>
            <input type="number" name="days" value="14" min="1" max="365" required>
            <div class="modal-buttons">
                <button type="button" class="btn btn-danger" onclick="closeIssueModal()">Отмена</button>
                <button type="submit" name="approve_request" class="btn btn-success">Выдать</button>
            </div>
        </form>
    </div>
</div>

<!-- МОДАЛЬНОЕ ОКНО ПРОДЛЕНИЯ -->
<div id="extendModal" class="modal">
    <div class="modal-content">
        <h3>Продление срока</h3>
        <form method="POST">
            <input type="hidden" name="loan_id" id="extendLoanId">
            <label>Продлить на (дней):</label>
            <input type="number" name="extend_days" value="7" min="1" max="365" required>
            <div class="modal-buttons">
                <button type="button" class="btn btn-danger" onclick="closeExtendModal()">Отмена</button>
                <button type="submit" name="extend_loan" class="btn btn-warning">Продлить</button>
            </div>
        </form>
    </div>
</div>

<script>
function openIssueModal(requestId, userId, bookId, bookTitle) {
    document.getElementById('issueRequestId').value = requestId;
    document.getElementById('issueUserId').value = userId;
    document.getElementById('issueBookId').value = bookId;
    document.getElementById('issueBookTitle').textContent = bookTitle;
    document.getElementById('issueModal').style.display = 'block';
}

function closeIssueModal() {
    document.getElementById('issueModal').style.display = 'none';
}

function openExtendModal(loanId) {
    document.getElementById('extendLoanId').value = loanId;
    document.getElementById('extendModal').style.display = 'block';
}

function closeExtendModal() {
    document.getElementById('extendModal').style.display = 'none';
}

// Закрытие модалок по клику вне их
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

</body>
</html>