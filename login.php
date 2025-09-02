<?php
session_start();
include 'db_config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name && $password) {
        $stmt = $conn->prepare('SELECT id, name, password, role FROM users WHERE name = ? LIMIT 1');
        if (!$stmt) {
            $error = 'Database error: ' . $conn->error;
        } else {
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $name_db, $hashed_password, $role);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $id;
                    $_SESSION['user_name'] = $name_db;
                    $_SESSION['user_role'] = $role;

                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid username or password.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
            $stmt->close();
        }
    } else {
        $error = 'Please enter username and password.';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />

<title>Customer Experience Portal - Login</title>
<style>

    :root {
    --bg: #818fa4ff;
    --deep-bg: #425779ff;
    --white: #ffffff;
    --dark: #263f56ff;
    --subtitle: #364253ff;
    --border-line: #cccccc;
    --deep-blue: #0a234bff;
    }

    body {
        margin: 0;
        background:var(--bg);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .login-container {
        background: var(--white);
        padding: 40px 30px;
        box-shadow: 0 4px 18px rgba(0,0,0,0.1);
        max-width: 400px;
        width: 100%;
        border-radius: 8px;
        text-align: center;
    }

    .login-header img {
        width: 120px;
        height: 120px;
        margin-bottom: 20px;
    }

    h2 {
        font-size: 22px;
        margin: 0 0 10px;
        font-weight: 600;
        color: var(--dark);
        font-family: inherit;
    }

    .subtitle {
        font-size: 18px;
        font-weight: 400;
        margin-top: 10px;
        margin-bottom: 20px;
        color: var(--subtitle);
    }

    form {
        display: flex;
        flex-direction: column;
    }

    input[type="text"],
    input[type="password"] {
        font-size: 16px;
        border-radius: 5px;
        padding: 12px 15px;
        margin-bottom: 20px;
        transition: border-color 0.3s ease;
        border: 1.5px solid var(--border-line);
    }

    input[type="text"]:focus,
    input[type="password"]:focus {
        border-color: #4096ff;
        outline: none;
        /* box-shadow: 0 0 5px rgba(64, 150, 255, 0.5); */
    }

    button {
        padding: 10px;
        background-color: var(--bg)/* #4096ff */;
        color: var(--white);
        font-size: 20px;
        font-weight: 600;
        font-family: inherit;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    button:hover {
        background-color: var(--deep-bg);
    }

    .alert {
        padding: 12px;
        margin-bottom: 20px;
        border-radius: 5px;
        font-weight: 600;
    }

    .alert-error {
        background-color: #f8d7da;
        color: #842029;
        border: 1px solid #f5c2c7;
    }
</style>

</head>
<body>
<div class="login-container">
    <header class="login-header">
        <img src="/images/logo/Telesol_logo.jpeg" alt="Company Logo" />
    </header>

    <h2>Customer Service Experience Portal</h2>
    <p class="subtitle">Enter your credentials</p>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input
            type="text"
            name="name"
            placeholder="Username"
            required
            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
            autocomplete="username"
        />
        <input
            type="password"
            name="password"
            placeholder="Password"
            required
            autocomplete="current-password"
        />
        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
