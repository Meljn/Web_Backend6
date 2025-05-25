<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

function getFieldValue($fieldName, $default = '') {
    if (isset($_SESSION['user_id'])) {
        try {
            $db_host = 'localhost';
            $db_user = 'u68532';
            $db_pass = '9110579';
            $db_name = 'u68532';
            
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT * FROM Application WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $application = $stmt->fetch();
            
            if ($application) {
                if ($fieldName === 'language') {
                    $stmt = $pdo->prepare("SELECT p.Name FROM Programming_Languages p 
                                        JOIN Application_Languages a ON p.Language_ID = a.Language_ID 
                                        WHERE a.Application_ID = ?");
                    $stmt->execute([$application['ID']]);
                    $languages = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    return implode(',', $languages);
                }
                
                if (isset($application[$fieldName])) {
                    return htmlspecialchars($application[$fieldName], ENT_QUOTES, 'UTF-8');
                }
            }
        } catch (PDOException $e) {
            error_log("Ошибка при загрузке данных: " . $e->getMessage());
        }
    }
    
    if (isset($_COOKIE["value_$fieldName"])) {
        return htmlspecialchars($_COOKIE["value_$fieldName"], ENT_QUOTES, 'UTF-8');
    }
    if (isset($_COOKIE["success_$fieldName"])) {
        return htmlspecialchars($_COOKIE["success_$fieldName"], ENT_QUOTES, 'UTF-8');
    }
    
    return $default;
}

function getFieldError($fieldName) {
    if (isset($_COOKIE["error_$fieldName"])) {
        return htmlspecialchars($_COOKIE["error_$fieldName"], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

$formErrors = [];
foreach ($_COOKIE as $name => $value) {
    if (strpos($name, 'error_') === 0) {
        $formErrors[] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма обратной связи</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="form-container">
        <div class="auth-links">
            <?php if (isset($_SESSION['login'])): ?>
                <div class="user-info">
                    Вы вошли как: <?= htmlspecialchars($_SESSION['login'], ENT_QUOTES, 'UTF-8') ?>
                    <a href="login.php?action=logout" class="logout-link">Выйти</a>
                </div>
            <?php else: ?>
                <a href="login.php" class="login-link">Вход в систему</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="user-data-info">
                <p>Загружены ваши сохранённые данные</p>
            </div>
        <?php endif; ?>

        <h1>Форма обратной связи</h1>

        <?php if (!empty($formErrors)): ?>
            <div class="error-messages">
                <h3>Ошибки при заполнении формы:</h3>
                <ul>
                    <?php foreach ($formErrors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                Данные успешно сохранены!
                <?php if (isset($_COOKIE['generated_login']) && isset($_COOKIE['generated_password'])): ?>
                    <div class="credentials">
                        <strong>Ваши данные для входа:</strong><br>
                        Логин: <?= htmlspecialchars($_COOKIE['generated_login'], ENT_QUOTES, 'UTF-8') ?><br>
                        Пароль: <?= htmlspecialchars($_COOKIE['generated_password'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form action="form.php" method="post">
            <div class="form-group <?= getFieldError('FIO') ? 'has-error' : '' ?>">
                <label for="FIO">ФИО:</label>
                <input type="text" id="FIO" name="FIO" value="<?= getFieldValue('FIO') ?>" required>
                <?php if ($error = getFieldError('FIO')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('Phone_number') ? 'has-error' : '' ?>">
                <label for="Phone_number">Телефон:</label>
                <input type="tel" id="Phone_number" name="Phone_number" value="<?= getFieldValue('Phone_number') ?>" required>
                <?php if ($error = getFieldError('Phone_number')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('Email') ? 'has-error' : '' ?>">
                <label for="Email">Email:</label>
                <input type="email" id="Email" name="Email" value="<?= getFieldValue('Email') ?>" required>
                <?php if ($error = getFieldError('Email')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('Birth_day') ? 'has-error' : '' ?>">
                <label for="Birth_day">Дата рождения:</label>
                <input type="date" id="Birth_day" name="Birth_day" value="<?= getFieldValue('Birth_day') ?>" required>
                <?php if ($error = getFieldError('Birth_day')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('Gender') ? 'has-error' : '' ?>">
                <label>Пол:</label>
                <label>
                    <input type="radio" name="Gender" value="male" <?= getFieldValue('Gender') === 'male' ? 'checked' : '' ?> required> Мужской
                </label>
                <label>
                    <input type="radio" name="Gender" value="female" <?= getFieldValue('Gender') === 'female' ? 'checked' : '' ?>> Женский
                </label>
                <?php if ($error = getFieldError('Gender')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('language') ? 'has-error' : '' ?>">
                <label for="language">Любимые языки программирования:</label>
                <select id="language" name="language[]" multiple required>
                    <?php
                    $languages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
                    $selected = explode(',', getFieldValue('language', ''));
                    foreach ($languages as $lang): ?>
                        <option value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($lang, $selected) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error = getFieldError('language')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('Biography') ? 'has-error' : '' ?>">
                <label for="Biography">Биография:</label>
                <textarea id="Biography" name="Biography" rows="5" required><?= getFieldValue('Biography') ?></textarea>
                <?php if ($error = getFieldError('Biography')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= getFieldError('Contract_accepted') ? 'has-error' : '' ?>">
                <label>
                    <input type="checkbox" name="Contract_accepted" value="1" <?= getFieldValue('Contract_accepted') === '1' ? 'checked' : '' ?> required>
                    Согласен с условиями
                </label>
                <?php if ($error = getFieldError('Contract_accepted')): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
            </div>
          
            <button type="submit"><?= isset($_SESSION['user_id']) ? 'Обновить данные' : 'Отправить' ?></button>
        </form>
    </div>
</body>
</html>