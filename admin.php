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

// Get all languages for select
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
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="tel"],
        input[type="email"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 100px;
        }
        select[multiple] {
            height: 150px;
        }
        button {
            padding: 8px 15px;
            background-color: #5cb85c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #4cae4c;
        }
        .actions {
            white-space: nowrap;
        }
        .actions a {
            display: inline-block;
            margin-right: 5px;
            padding: 3px 8px;
            text-decoration: none;
            border-radius: 3px;
        }
        .edit {
            background-color: #337ab7;
            color: white;
        }
        .delete {
            background-color: #d9534f;
            color: white;
        }
        .stats {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            flex: 1;
            min-width: 200px;
            background: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            margin-top: 0;
            color: #555;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #337ab7;
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