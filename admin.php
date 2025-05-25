<?php
/**
 * Admin page with HTTP authentication for managing user applications
 */

// HTTP Authentication
if (empty($_SERVER['PHP_AUTH_USER']) ||
    empty($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != 'admin' ||
    md5($_SERVER['PHP_AUTH_PW']) != md5('123')) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Application Admin"');
    echo '<h1>401 Authorization Required</h1>';
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
    die("Database connection failed: " . $e->getMessage());
}

// Handle actions
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action === 'delete' && $id) {
    // Delete application
    try {
        $pdo->beginTransaction();
        
        // Delete from Application_Languages first due to foreign key constraint
        $stmt = $pdo->prepare("DELETE FROM Application_Languages WHERE Application_ID = ?");
        $stmt->execute([$id]);
        
        // Then delete from Application
        $stmt = $pdo->prepare("DELETE FROM Application WHERE ID = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        $message = "Application #$id deleted successfully";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error deleting application: " . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $id) {
    // Update application
    $formData = [
        'FIO' => trim($_POST['FIO']),
        'Phone_number' => trim($_POST['Phone_number']),
        'Email' => trim($_POST['Email']),
        'Birth_day' => trim($_POST['Birth_day']),
        'Gender' => trim($_POST['Gender']),
        'Biography' => trim($_POST['Biography']),
        'Contract_accepted' => isset($_POST['Contract_accepted']) ? 1 : 0
    ];
    
    try {
        $pdo->beginTransaction();
        
        // Update Application table
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
        
        // Update languages - first delete existing, then insert new
        $stmt = $pdo->prepare("DELETE FROM Application_Languages WHERE Application_ID = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("INSERT INTO Application_Languages (Application_ID, Language_ID) 
                              SELECT ?, Language_ID FROM Programming_Languages WHERE Name = ?");
        
        foreach ($_POST['language'] as $language) {
            $stmt->execute([$id, $language]);
        }
        
        $pdo->commit();
        $message = "Application #$id updated successfully";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error updating application: " . $e->getMessage();
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
}

$stmt = $pdo->query("SELECT Name FROM Programming_Languages ORDER BY Name");
$allLanguages = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <style>
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #171717;
            color: #e5e5e5;
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
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #404040;
        }

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
    <div class="container">
        <h1>Admin Panel</h1>
        
        <?php if (isset($message)): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <h3>Total Applications</h3>
                <div class="stat-value"><?= count($applications) ?></div>
            </div>
        </div>
        
        <h2>Programming Language Statistics</h2>
        <table>
            <thead>
                <tr>
                    <th>Language</th>
                    <th>Users</th>
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
        
        <h2>Applications</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Birth Date</th>
                    <th>Gender</th>
                    <th>Languages</th>
                    <th>Created At</th>
                    <th>Actions</th>
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
                        <td><?= htmlspecialchars($app['Gender'] === 'male' ? 'Male' : 'Female') ?></td>
                        <td><?= htmlspecialchars($app['languages']) ?></td>
                        <td><?= htmlspecialchars($app['Created_at']) ?></td>
                        <td class="actions">
                            <a href="?action=edit&id=<?= $app['ID'] ?>" class="edit">Edit</a>
                            <a href="?action=delete&id=<?= $app['ID'] ?>" class="delete" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($editApplication): ?>
            <h2>Edit Application #<?= htmlspecialchars($editApplication['ID']) ?></h2>
            <form method="post" action="?action=edit&id=<?= $editApplication['ID'] ?>">
                <div class="form-group">
                    <label for="FIO">Full Name:</label>
                    <input type="text" id="FIO" name="FIO" value="<?= htmlspecialchars($editApplication['FIO']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="Phone_number">Phone:</label>
                    <input type="tel" id="Phone_number" name="Phone_number" value="<?= htmlspecialchars($editApplication['Phone_number']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="Email">Email:</label>
                    <input type="email" id="Email" name="Email" value="<?= htmlspecialchars($editApplication['Email']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="Birth_day">Birth Date:</label>
                    <input type="date" id="Birth_day" name="Birth_day" value="<?= htmlspecialchars($editApplication['Birth_day']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Gender:</label>
                    <label>
                        <input type="radio" name="Gender" value="male" <?= $editApplication['Gender'] === 'male' ? 'checked' : '' ?> required> Male
                    </label>
                    <label>
                        <input type="radio" name="Gender" value="female" <?= $editApplication['Gender'] === 'female' ? 'checked' : '' ?>> Female
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="language">Programming Languages:</label>
                    <select id="language" name="language[]" multiple required>
                        <?php foreach ($allLanguages as $lang): ?>
                            <option value="<?= htmlspecialchars($lang) ?>" <?= strpos($editApplication['languages'], $lang) !== false ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lang) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="Biography">Biography:</label>
                    <textarea id="Biography" name="Biography" required><?= htmlspecialchars($editApplication['Biography']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="Contract_accepted" value="1" <?= $editApplication['Contract_accepted'] ? 'checked' : '' ?> required>
                        Contract Accepted
                    </label>
                </div>
                
                <button type="submit">Update Application</button>
                <a href="admin.php">Cancel</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>