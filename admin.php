<?php
/**
 * Админ-панель с HTTP-аутентификацией
 */

// HTTP Authentication
if (empty($_SERVER['PHP_AUTH_USER']) ||
    empty($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != 'admin' ||
    md5($_SERVER['PHP_AUTH_PW']) != md5('123')) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Админ-панель"');
    echo '<!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>401 Требуется авторизация</title>
        <style>
            body { 
                font-family: "Segoe UI", system-ui, sans-serif; 
                background-color: #171717; 
                color: #e5e5e5; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                height: 100vh; 
                margin: 0;
            }
            h1 { 
                color: #d65454; 
                font-size: 2em;
            }
            .container { 
                background: #262626; 
                padding: 30px; 
                border-radius: 12px; 
                border: 1px solid #333; 
                box-shadow: 0 4px 20px rgba(0,0,0,0.25);
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>401 Требуется авторизация</h1>
            <p>Пожалуйста, авторизуйтесь для доступа к панели управления</p>
        </div>
    </body>
    </html>';
    exit();
}

// Database connection
$db_host = 'localhost';
$db_user = 'u68532';
$db_pass = '9110579';
$db_name = 'u68532';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Handle actions
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$errors = [];
$formData = [];

if ($action === 'delete' && $id) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM Application_Languages WHERE Application_ID = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("DELETE FROM Application WHERE ID = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        $message = "Заявка #$id успешно удалена";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Ошибка при удалении заявки: " . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $id) {
    $formData = [
        'FIO' => trim($_POST['FIO'] ?? ''),
        'Phone_number' => trim($_POST['Phone_number'] ?? ''),
        'Email' => trim($_POST['Email'] ?? ''),
        'Birth_day' => trim($_POST['Birth_day'] ?? ''),
        'Gender' => trim($_POST['Gender'] ?? ''),
        'Biography' => trim($_POST['Biography'] ?? ''),
        'Contract_accepted' => isset($_POST['Contract_accepted']) ? 1 : 0,
        'language' => $_POST['language'] ?? []
    ];

    // Validation (same as in form.php)
    if (empty($formData['FIO'])) {
        $errors['FIO'] = 'Поле ФИО обязательно для заполнения';
    } elseif (!preg_match('/^[A-Za-zА-Яа-яЁё\s]+$/u', $formData['FIO'])) {
        $errors['FIO'] = 'ФИО должно содержать только буквы и пробелы';
    }

    if (empty($formData['Phone_number'])) {
        $errors['Phone_number'] = 'Поле Телефон обязательно для заполнения';
    } elseif (!preg_match('/^\+?[0-9]{10,15}$/', $formData['Phone_number'])) {
        $errors['Phone_number'] = 'Телефон должен содержать 10-15 цифр, может начинаться с +';
    }

    if (empty($formData['Email'])) {
        $errors['Email'] = 'Поле Email обязательно для заполнения';
    } elseif (!filter_var($formData['Email'], FILTER_VALIDATE_EMAIL)) {
        $errors['Email'] = 'Введите корректный email';
    }

    if (empty($formData['Birth_day'])) {
        $errors['Birth_day'] = 'Поле Дата рождения обязательно для заполнения';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['Birth_day'])) {
        $errors['Birth_day'] = 'Введите дату в формате ГГГГ-ММ-ДД';
    }

    if (empty($formData['Gender'])) {
        $errors['Gender'] = 'Укажите ваш пол';
    } elseif (!in_array($formData['Gender'], ['male', 'female'])) {
        $errors['Gender'] = 'Выбран недопустимый пол';
    }

    if (empty($formData['language'])) {
        $errors['language'] = 'Выберите хотя бы один язык программирования';
    }

    if (empty($formData['Biography'])) {
        $errors['Biography'] = 'Поле Биография обязательно для заполнения';
    }

    if (!isset($formData['Contract_accepted'])) {
        $errors['Contract_accepted'] = 'Необходимо согласиться с условиями';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE Application SET 
                FIO = :fio, 
                Phone_number = :phone, 
                Email = :email, 
                Birth_day = :birth_day, 
                Gender = :gender, 
                Biography = :bio, 
                Contract_accepted = :contract
                WHERE ID = :id");
            
            $stmt->execute([
                ':fio' => $formData['FIO'],
                ':phone' => $formData['Phone_number'],
                ':email' => $formData['Email'],
                ':birth_day' => $formData['Birth_day'],
                ':gender' => $formData['Gender'],
                ':bio' => $formData['Biography'],
                ':contract' => $formData['Contract_accepted'],
                ':id' => $id
            ]);
            
            $stmt = $pdo->prepare("DELETE FROM Application_Languages WHERE Application_ID = ?");
            $stmt->execute([$id]);
            
            $stmt = $pdo->prepare("INSERT INTO Application_Languages (Application_ID, Language_ID) 
                                  SELECT ?, Language_ID FROM Programming_Languages WHERE Name = ?");
            
            foreach ($formData['language'] as $language) {
                $stmt->execute([$id, $language]);
            }
            
            $pdo->commit();
            $message = "Заявка #$id успешно обновлена";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Ошибка при обновлении заявки: " . $e->getMessage();
        }
    }
}

// Get all applications with their languages
$applications = [];
$stmt = $pdo->query("
    SELECT a.*, GROUP_CONCAT(p.Name) as languages 
    FROM Application a
    LEFT JOIN Application_Languages al ON a.ID = al.Application_ID
    LEFT JOIN Programming_Languages p ON al.Language_ID = p.Language_ID
    GROUP BY a.ID
    ORDER BY a.Created_at DESC
");
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get language statistics
$languageStats = [];
$stmt = $pdo->query("
    SELECT p.Name, COUNT(al.Application_ID) as user_count 
    FROM Programming_Languages p
    LEFT JOIN Application_Languages al ON p.Language_ID = al.Language_ID
    GROUP BY p.Name
    ORDER BY user_count DESC, p.Name
");
$languageStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get application for editing
$editApplication = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("
        SELECT a.*, GROUP_CONCAT(p.Name) as languages 
        FROM Application a
        LEFT JOIN Application_Languages al ON a.ID = al.Application_ID
        LEFT JOIN Programming_Languages p ON al.Language_ID = p.Language_ID
        WHERE a.ID = ?
        GROUP BY a.ID
    ");
    $stmt->execute([$id]);
    $editApplication = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fill form data if validation failed
    if (!empty($errors)) {
        $editApplication = array_merge($editApplication, $formData);
        $editApplication['languages'] = implode(',', $formData['language']);
    }
}

// Get all languages for select
$stmt = $pdo->query("SELECT Name FROM Programming_Languages ORDER BY Name");
$allLanguages = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <style>
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #171717;
            color: #e5e5e5;
        }

         .actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 25px;
            background: #262626;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            border: 1px solid #333;
        }

        h1, h2 {
            color: #f0f0f0;
            font-weight: 600;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            border-bottom: 1px solid #404040;
            padding-bottom: 10px;
        }

        h2 {
            font-size: 20px;
            margin-top: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            background: #333;
            border-radius: 8px;
            overflow: hidden;
            table-layout: fixed;
        }

         th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #404040;
            word-wrap: break-word;
        }

        th:nth-child(1), td:nth-child(1) { width: 50px; }
        th:nth-child(9), td:nth-child(9) { width: 180px; }
        
        th {
            background-color: #2e2e2e;
            color: #d1d1d1;
            font-weight: 500;
        }

        tr:nth-child(even) {
            background-color: #2a2a2a;
        }

        tr:hover {
            background-color: #3a3a3a;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #d1d1d1;
            font-size: 15px;
        }

        input[type="text"],
        input[type="tel"],
        input[type="email"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #404040;
            border-radius: 8px;
            background-color: #333;
            color: #f0f0f0;
            font-size: 15px;
        }

        select[multiple] {
            min-height: 150px;
            padding: 8px !important;
        }

        option {
            padding: 8px;
            background: #333;
        }

        option:checked {
            background-color: #1a73e8;
            color: white;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        button, .btn {
            background-color: #1a73e8;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: background-color 0.2s;
            margin-right: 10px;
        }

        .btn {
            width: 100%;
            box-sizing: border-box;
            text-align: center;
            padding: 8px 0;
            font-size: 14px;
        }

        button:hover, .btn:hover {
            background-color: #1765cc;
        }

        .btn-delete {
            background-color: #d65454;
        }

        .btn-delete:hover {
            background-color: #c04545;
        }

        .message {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .success {
            background-color: #1f2e1f;
            border-left: 4px solid #81c784;
            color: #a5d6a7;
        }

        .error {
            background-color: #2e1f1f;
            border-left: 4px solid #d65454;
            color: #ff8a80;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #2e2e2e;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #1a73e8;
        }

        .stat-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #d1d1d1;
            font-size: 16px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #f0f0f0;
        }

        .error-message {
            color: #d65454;
            font-size: 0.8em;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 15px;
            }
            
            th, td {
                padding: 8px 10px;
                font-size: 14px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>Админ-панель</h1>
        
        <?php if (isset($message)): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Всего заявок</h3>
                <div class="stat-value"><?= count($applications) ?></div>
            </div>
        </div>
        
        <h2>Статистика по языкам программирования</h2>
        <table>
            <thead>
                <tr>
                    <th>Язык</th>
                    <th>Пользователей</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($languageStats as $stat): ?>
                    <tr>
                        <td><?= htmlspecialchars($stat['Name']) ?></td>
                        <td><?= htmlspecialchars($stat['user_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h2>Заявки пользователей</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ФИО</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Дата рождения</th>
                    <th>Пол</th>
                    <th>Языки</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?= htmlspecialchars($app['ID']) ?></td>
                        <td><?= htmlspecialchars($app['FIO']) ?></td>
                        <td><?= htmlspecialchars($app['Phone_number']) ?></td>
                        <td><?= htmlspecialchars($app['Email']) ?></td>
                        <td><?= htmlspecialchars($app['Birth_day']) ?></td>
                        <td><?= htmlspecialchars($app['Gender'] === 'male' ? 'Мужской' : 'Женский') ?></td>
                        <td><?= htmlspecialchars($app['languages']) ?></td>
                        <td><?= htmlspecialchars($app['Created_at']) ?></td>
                        <td>
                            <a href="?action=edit&id=<?= $app['ID'] ?>" class="btn">Редактировать</a>
                            <a href="?action=delete&id=<?= $app['ID'] ?>" class="btn btn-delete" onclick="return confirm('Удалить эту заявку?')">Удалить</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($editApplication): ?>
            <h2>Редактирование заявки #<?= htmlspecialchars($editApplication['ID']) ?></h2>
            <form method="post" action="?action=edit&id=<?= $editApplication['ID'] ?>">
                <div class="form-group">
                    <label for="FIO">ФИО:</label>
                    <input type="text" id="FIO" name="FIO" value="<?= htmlspecialchars($editApplication['FIO']) ?>" required>
                    <?php if (isset($errors['FIO'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['FIO']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="Phone_number">Телефон:</label>
                    <input type="tel" id="Phone_number" name="Phone_number" value="<?= htmlspecialchars($editApplication['Phone_number']) ?>" required>
                    <?php if (isset($errors['Phone_number'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['Phone_number']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="Email">Email:</label>
                    <input type="email" id="Email" name="Email" value="<?= htmlspecialchars($editApplication['Email']) ?>" required>
                    <?php if (isset($errors['Email'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['Email']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="Birth_day">Дата рождения:</label>
                    <input type="date" id="Birth_day" name="Birth_day" value="<?= htmlspecialchars($editApplication['Birth_day']) ?>" required>
                    <?php if (isset($errors['Birth_day'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['Birth_day']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Пол:</label>
                    <label>
                        <input type="radio" name="Gender" value="male" <?= $editApplication['Gender'] === 'male' ? 'checked' : '' ?> required> Мужской
                    </label>
                    <label>
                        <input type="radio" name="Gender" value="female" <?= $editApplication['Gender'] === 'female' ? 'checked' : '' ?>> Женский
                    </label>
                    <?php if (isset($errors['Gender'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['Gender']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="language">Языки программирования:</label>
                    <select id="language" name="language[]" multiple required>
                        <?php foreach ($allLanguages as $lang): ?>
                            <option value="<?= htmlspecialchars($lang) ?>" <?= strpos($editApplication['languages'], $lang) !== false ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lang) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['language'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['language']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="Biography">Биография:</label>
                    <textarea id="Biography" name="Biography" required><?= htmlspecialchars($editApplication['Biography']) ?></textarea>
                    <?php if (isset($errors['Biography'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['Biography']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="Contract_accepted" value="1" <?= $editApplication['Contract_accepted'] ? 'checked' : '' ?> required>
                        Согласен с условиями
                    </label>
                    <?php if (isset($errors['Contract_accepted'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['Contract_accepted']) ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit">Сохранить изменения</button>
                <a href="admin.php" class="btn">Отмена</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>