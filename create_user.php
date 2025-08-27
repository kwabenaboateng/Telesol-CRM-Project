<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'manager'])) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied. You do not have permissions to access this page.';
    exit;
}

$error = '';
$success = '';

$valid_roles = ['user', 'admin', 'supervisor', 'manager'];
$valid_departments = ['Admin', 'Customer Service', 'Finance', 'Systems', 'Technical', 'Transport', 'All'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $role = $_POST['role'] ?? '';
    $department = $_POST['department'] ?? '';

    if (!$name || !$password || !$password_confirm || !$role || !$department) {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $name)) {
        $error = 'Username must be 3-50 characters long and contain only letters, numbers, and underscores.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!in_array($role, $valid_roles)) {
        $error = 'Invalid role selected.';
    } elseif (!in_array($department, $valid_departments)) {
        $error = 'Invalid department selected.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE name = ?');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'Username already exists.';
        } else {
            $stmt->close();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare('INSERT INTO users (name, password, role, department) VALUES (?, ?, ?, ?)');
            if (!$stmt) {
                $error = 'Database error: ' . $conn->error;
            } else {
                $stmt->bind_param('ssss', $name, $hashed_password, $role, $department);
                if ($stmt->execute()) {
                    $success = 'User <strong>"' . htmlspecialchars($name) . '"</strong> created with role <strong>"' . htmlspecialchars($role) . '"</strong> in department <strong>"' . htmlspecialchars($department) . '"</strong>.';
                    $_POST = [];
                } else {
                    $error = 'Error creating user: ' . $stmt->error;
                }
            }
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Create New User</title>
<style>
    /* Reset and base */
    * {
        box-sizing: border-box;
    }
    body, html {
        margin: 0; 
        padding: 0; 
        height: 100%;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        background: #f0f2f5;
        /* background: linear-gradient(135deg, #3a8dff 0%, #005bea 100%); */
        color: #34495e;
        overflow: hidden; /* prevent scrolling */
    }
    /* Flex container - center vertically + horizontally */
    body {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100%;
        padding: 0 20px;
    }

    /* Card container */
    .container {
        background: #fff;
        width: 480px;
        max-width: 100%;
        border-radius: 8px;
        padding: 40px 48px;
        box-shadow: 0 16px 40px rgba(0,0,0,0.25);
        display: flex;
        flex-direction: column;
        justify-content: center;
        height: fit-content;
        max-height: 90vh; /* max height to prevent overflow */
        overflow: auto; /* if small screen, allow scroll inside container */
    }

    /* Header */
    h1 {
        font-weight: 500;
        font-size: 2rem;
        margin: 0 0 36px;
        text-align: center;
        color: #22356f;
        letter-spacing: 0.04em;
    }

    /* Alerts */
    .alert {
        border-radius: 10px;
        padding: 16px 20px;
        margin-bottom: 28px;
        font-weight: 600;
        font-size: 1rem;
        line-height: 1.3;
        text-align: center;
        user-select: none;
    }
    .alert-error {
        background-color: #fce8e6;
        color: #d93025;
        border: 1px solid #f5c6c3;
        box-shadow: 0 0 8px rgba(217,48,37,0.3);
    }
    .alert-success {
        background-color: #e6f4ea;
        color: #188038;
        border: 1px solid #badbcc;
        box-shadow: 0 0 8px rgba(24,128,56,0.3);
    }

    form {
        display: flex;
        flex-direction: column;
    }

    label {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 8px;
        color: #2a3a72;
    }

    input[type="text"],
    input[type="password"],
    select {
        padding: 8px 10px;
        font-size: 1rem;
        border-radius: 7px;
        border: 1.8px solid #ced4da;
        margin-bottom: 7px;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
        color: #22356f;
        font-family: inherit;
    }
    input[type="text"]:focus,
    input[type="password"]:focus,
    select:focus {
        border-color: #005bea;
        outline: none;
        box-shadow: 0 0 12px rgba(0,91,234,0.5);
    }

    /* Button */
    button {
        padding: 16px 0;
        background-color: #005bea;
        color: white;
        font-weight: 700;
        border: none;
        border-radius: 12px;
        font-size: 1.15rem;
        cursor: pointer;
        transition: background-color 0.3s ease;
        box-shadow: 0 8px 20px rgba(0,91,234,0.5);
        user-select: none;
    }
    button:hover,
    button:focus {
        background-color: #003f9f;
        box-shadow: 0 10px 28px rgba(0,63,159,0.7);
        outline: none;
    }

    /* Responsive adjustments */
    @media (max-height: 700px) {
        .container {
            max-height: 80vh;
            padding: 30px 36px;
        }
        form {
            overflow-y: auto;
        }
    }

    @media (max-width: 500px) {
        .container {
            padding: 30px 28px;
            width: 100%;
            max-width: 400px;
        }
        h1 {
            font-size: 1.8rem;
            margin-bottom: 28px;
        }
    }
</style>
</head>
<body>
<div class="container" role="main" aria-labelledby="pageTitle">
    <h1 id="pageTitle">Create New User</h1>

    <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success" role="alert"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
        <label for="name">Username</label>
        <input
            type="text"
            id="name"
            name="name"
            placeholder="Choose a unique username"
            required
            pattern="[a-zA-Z0-9_]{3,50}"
            title="3 to 50 characters: letters, numbers and underscores only"
            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
            autocomplete="username"
        />

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter a strong password"
            required
            minlength="8"
            autocomplete="new-password"
        />

        <label for="password_confirm">Confirm Password</label>
        <input
            type="password"
            id="password_confirm"
            name="password_confirm"
            placeholder="Re-enter your password"
            required
            minlength="8"
            autocomplete="new-password"
        />

        <label for="role">Role</label>
        <select id="role" name="role" required aria-required="true">
            <option value="" disabled <?= !isset($_POST['role']) ? 'selected' : '' ?>>Select role</option>
            <?php foreach ($valid_roles as $r): ?>
                <option value="<?= htmlspecialchars($r) ?>" <?= (($_POST['role'] ?? '') === $r) ? 'selected' : '' ?>>
                    <?= ucfirst(htmlspecialchars($r)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="department">Department</label>
        <select id="department" name="department" required aria-required="true">
            <option value="" disabled <?= !isset($_POST['department']) ? 'selected' : '' ?>>Select department</option>
            <?php foreach ($valid_departments as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= (($_POST['department'] ?? '') === $d) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Create User</button>
    </form>
</div>
</body>
</html>
