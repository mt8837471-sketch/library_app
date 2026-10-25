<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    
    // Проверка, существует ли email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $error = 'Пользователь с таким email уже существует!';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'reader')");
        if ($stmt->execute([$name, $email, $phone, $hashed_password])) {
            $message = 'Регистрация успешна! Теперь вы можете войти.';
        } else {
            $error = 'Ошибка при регистрации. Попробуйте позже.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация - Библиотека</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .register-form { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 350px; }
        h2 { text-align: center; color: #333; margin-top: 0; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 10px; }
        button:hover { background: #45a049; }
        .error { color: red; text-align: center; margin-bottom: 15px; }
        .success { color: green; text-align: center; margin-bottom: 15px; }
        .login-link { text-align: center; margin-top: 15px; }
        .login-link a { color: #007BFF; text-decoration: none; }
    </style>
</head>
<body>
    <div class="register-form">
        <h2>Регистрация читателя</h2>
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
      <?php if ($message): ?>
    <div class="success"><?= $message ?></div>
    <div class="login-link">
        <a href="login.php" class="btn">Войти в систему</a>
    </div>
<?php endif; ?>

<?php if (!$message): ?>
<form method="POST">
            <div class="form-group">
                <label>ФИО:</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Телефон:</label>
                <input type="text" name="phone" required>
            </div>
            <div class="form-group">
                <label>Пароль:</label>
                <input type="password" name="password" required minlength="6">
            </div>
            <button type="submit">Зарегистрироваться</button>
        </form>
        <div class="login-link">
            Уже есть аккаунт? <a href="login.php">Войти</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>