<?php
session_start();

$db_host = 'localhost';
$db_user = 'u68532';
$db_pass = '9110579';
$db_name = 'u68532';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit();
}

if (!empty($_SESSION['login'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT * FROM Users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['login'] = $user['login'];
            $_SESSION['user_id'] = $user['id'];
            
            foreach ($_COOKIE as $name => $value) {
                if (strpos($name, 'value_') === 0 || strpos($name, 'success_') === 0) {
                    setcookie($name, '', time() - 3600, '/');
                }
            }
            
            header('Location: index.php');
            exit();
        } else {
            $_SESSION['error_message'] = 'Неверный логин или пароль';
            header('Location: login.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Ошибка системы. Попробуйте позже.';
        header('Location: login.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h1>Вход в систему</h1>
        
        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="error-message">
                <?= htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="login">Логин:</label>
                <input type="text" id="login" name="login" required>
            </div>
            
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Войти</button>
            
            <div class="form-footer">
                <a href="index.php" class="back-link">← Вернуться к форме</a>
            </div>
        </form>
    </div>
</body>
</html>