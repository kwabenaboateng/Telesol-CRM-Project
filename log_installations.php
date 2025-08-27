<?php
session_start();
$username = $_SESSION['username'] ?? 'User';

$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "telesol crm";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

$errors = [];
$success = false;

// Installation packages and their prices (server authoritative)
$package_options = [
    'Budget' => 250,
    'Standard' => 350,
    'Family' => 830,
    'Bespoke B' => 1400,
    'Bespoke A' => 2500,
];

// Allowed payment modes
$payment_modes = [
    'Mobile Money',
    'Bank Transfer/Payment',
    'Cash',
];

$transaction_id = '';
$customer_name = '';
$package = '';
$mode_of_payment = '';
$location = '';
$email = '';
$comment = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim and fetch inputs safely
    $transaction_id = trim($_POST['transaction_id'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $package = $_POST['package'] ?? '';
    $mode_of_payment = $_POST['mode_of_payment'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $comment = trim($_POST['comment'] ?? '');

    // Validate Mode of Payment FIRST since transaction_id requirement depends on it
    if (!in_array($mode_of_payment, $payment_modes, true)) {
        $errors[] = "Please select a valid mode of payment.";
    }

    // Transaction ID required if mode of payment is Mobile Money
    if ($mode_of_payment === 'Mobile Money') {
        if ($transaction_id === '') {
            $errors[] = "Transaction ID is required when payment mode is Mobile Money.";
        } elseif (strlen($transaction_id) > 100) {
            $errors[] = "Transaction ID should not exceed 100 characters.";
        }
    } else {
        // Transaction ID is optional otherwise, but validate length if provided
        if ($transaction_id !== '' && strlen($transaction_id) > 100) {
            $errors[] = "Transaction ID should not exceed 100 characters.";
        }
    }

    // Validate Customer Name
    if ($customer_name === '') {
        $errors[] = "Customer name is required.";
    } elseif (strlen($customer_name) > 255) {
        $errors[] = "Customer name should not exceed 255 characters.";
    }

    // Validate Package
    if (!array_key_exists($package, $package_options)) {
        $errors[] = "Please select a valid installation package.";
    }

    // Validate Location
    if ($location === '') {
        $errors[] = "Location is required.";
    } elseif (strlen($location) > 255) {
        $errors[] = "Location should not exceed 255 characters.";
    }

    // Validate Email
    if ($email === '') {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid email address.";
    } elseif (strlen($email) > 255) {
        $errors[] = "Email address should not exceed 255 characters.";
    }

    // Sanitize comment length (optional, max 1000 chars)
    if (strlen($comment) > 1000) {
        $errors[] = "Comment should not exceed 1000 characters.";
    }

    // If validation passed, proceed to insert into database
    if (empty($errors)) {
        $amount_paid = $package_options[$package];

        $stmt = $conn->prepare(
            "INSERT INTO installations 
            (transaction_id, customer_name, amount_paid, mode_of_payment, location, email, comment, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        if ($stmt === false) {
            $errors[] = "An internal error occurred. Please try again later.";
        } else {
            $stmt->bind_param(
                "ssdsssss",
                $transaction_id,
                $customer_name,
                $amount_paid,
                $mode_of_payment,
                $location,
                $email,
                $comment,
                $username
            );

            if ($stmt->execute()) {
                $success = true;

                // Clear form values
                $transaction_id = $customer_name = $location = $email = $comment = $package = $mode_of_payment = '';
            } else {
                $errors[] = "Failed to log installation. Please try again.";
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Log Installations</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<style>
:root {
  --primary: #4a6bff;
  --accent: #ff4a6b;
  --dark: #2c3e50;
  --light: #f8f9fa;
  --gray: #e9ecef;
  --success: #28a745;
  --error: #dc3545;
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f8f9fa;
  color: #333;
  line-height: 1.6;
  min-height: 100vh;
  display: flex;
  flex-direction: row;
}

/* Sidebar Styles */
.sidebar {
  width: 280px;
  background: var(--dark);
  color: white;
  padding: 1rem;
  height: 100vh;
  position: fixed;
  display: flex;
  flex-direction: column;
  box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
  z-index: 100;
}

.sidebar-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.company-logo {
  width: 140px;
  height: 70px;
  background: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.1rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.company-logo img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.company-slogan {
  font-size: 1rem;
  color: rgba(255, 255, 255, 0.7);
  margin-top: 0.25rem;
  text-align: center;
}

.nav-menu {
  flex-grow: 1;
}

.nav-link {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  border-radius: 6px;
  margin-bottom: 0.5rem;
  transition: all 0.3s ease;
}

.nav-link i {
  margin-right: 0.75rem;
  font-size: 1rem;
  color: var(--accent);
  margin-bottom: 0.010rem;
}

.nav-link:hover, 
.nav-link.active {
  background-color: rgba(255, 255, 255, 0.1);
  color: white;
}

.nav-link.active {
  font-weight: 600;
}

.sidebar-footer {
  margin-top: auto;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
}

.btn-back {
  background: white;
  color: var(--dark);
  width: 100%;
  margin-bottom: 1rem;
}

.btn-back:hover {
  background: var(--gray);
}

.btn-logout {
  background: var(--error);
  color: white;
  width: 100%;
}

.btn-logout:hover {
  background: #c82333;
}

/* Main Content */
.main-content {
  flex: 1;
  margin-left: 120px;
  padding: 2rem;
}

.card {
  background: white;
  border-radius: 10px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  padding: 1.5rem;
  max-width: 1000px;
  margin: 0 auto;
}

.card-header {
  margin-bottom: 1rem;
}

.card-title {
  color: var(--dark);
  font-size: 1.5rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.card-subtitle {
  color: #6c757d;
  font-size: 1rem;
  margin-bottom: 0.1rem;
}

/* Form Styles */
.form-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
}

.form-group {
  margin-bottom: -0.7rem;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  display: block;
  margin-bottom: 0.4rem;
  font-weight: 500;
  color: #495057;
}

.form-control {
  width: 100%;
  box-sizing: border-box;
  height: 2.3rem;
  padding: 0.4rem 0.6rem;
  font-size: 1rem;
  border: 1px solid var(--gray);
  border-radius: 6px;
  background-color: #f8f9fa;
  color: #333;
  transition: border-color 0.3s ease;
}

.form-control:focus {
  outline: none;
  border-color: var(--primary);
  background-color: white;
  box-shadow: 0 0 0 3px rgba(74, 107, 255, 0.1);
}

textarea.form-control {
  height: auto;
  min-height: 120px;
  padding-top: 0.5rem;
  resize: vertical;
}

.btn-submit {
  background: linear-gradient(135deg, var(--primary), var(--accent));
  color: white;
  font-weight: 600;
  padding: 1rem;
  font-size: 1.1rem;
  margin-top: 1rem;
  width: 100%;
  grid-column: 1 / -1;
  border: none;
  cursor: pointer;
}

.btn-submit:hover {
  opacity: 0.9;
  transform: translateY(-2px);
}

/* Messages */
.alert {
  padding: 1rem;
  border-radius: 6px;
  margin-bottom: 1.5rem;
  font-weight: 500;
}

.alert-error {
  background-color: #f8d7da;
  color: var(--error);
  border-left: 4px solid var(--error);
}

.alert-success {
  background-color: #d4edda;
  color: var(--success);
  border-left: 4px solid var(--success);
}

.required {
  color: var(--error);
}

/* Responsive */
@media (max-width: 1024px) {
  .form-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  body {
    flex-direction: column;
  }
  .sidebar {
    position: relative;
    width: 100%;
    height: auto;
  }
  .main-content {
    margin-left: 0;
    padding: 1.5rem;
  }
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body>
<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
      <div class="company-logo">
        <img src="Telesol_logo.jpeg" alt="Company Logo" />
      </div>
      <div class="company-slogan">Customer Relationship Management</div>
    </div>

    <nav class="nav-menu">
      <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'main-menu.php' ? 'active' : '' ?>">
        <i class="bi bi-list"></i> Menu
      </a>

      <a href="log_issue.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'customer_experience.php' ? 'active' : '' ?>">
        <i class="bi bi-journal-plus"></i> Log Issue
      </a>

      <a href="view_tickets.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'customer_experience.php' ? 'active' : '' ?>">
        <i class="bi bi-journal-plus"></i> View Ticke
      </a>

      <a href="log_installations.php" class="nav-link active <?= basename($_SERVER['PHP_SELF']) == 'customer_experience.php' ? 'active' : '' ?>">
        <i class="bi bi-journal-plus"></i> Log Installation
      </a>

      <a href="customer_experience.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'customer_experience.php' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Customer Experience
      </a>

      <a href="field_installations.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'field_installations.php' ? 'active' : '' ?>">
        <i class="bi bi-hdd-network"></i> Field Installations
      </a>
    </nav>

    <div class="sidebar-footer">
      <button class="btn btn-back" onclick="window.history.back()">
        <i class="bi bi-arrow-left"></i> Back
      </button>

      <form action="logout.php" method="POST">
        <button type="submit" class="btn btn-logout">
          <i class="bi bi-box-arrow-right"></i> Logout
        </button>
      </form>
    </div>
</aside>

<!-- Main Content -->
<main class="main-content">
  <div class="card">
    <div class="card-header">
      <h1 class="card-title">Log New Installation</h1>
      <p class="card-subtitle">Please fill in the details below to log a new installation</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $error): ?>
          <div><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
      </div>
    <?php elseif ($success): ?>
      <div class="alert alert-success" role="alert">
        Installation logged successfully!
      </div>
    <?php endif; ?>

    <form method="post" class="form-grid" novalidate>
      <div class="form-group">
        <label for="transaction_id">Transaction ID <?php if($mode_of_payment === 'Mobile Money'): ?><span class="required">*</span><?php endif; ?></label>
        <input type="text" id="transaction_id" name="transaction_id" class="form-control"
               value="<?= htmlspecialchars($transaction_id ?? '') ?>" />
      </div>

      <div class="form-group">
        <label for="customer_name">Customer Name <span class="required">*</span></label>
        <input type="text" id="customer_name" name="customer_name" class="form-control" required
               value="<?= htmlspecialchars($customer_name ?? '') ?>" />
      </div>

      <div class="form-group">
        <label for="package">Installation Package <span class="required">*</span></label>
        <select id="package" name="package" class="form-control" required onchange="updateAmountPaid()">
            <option value="" disabled <?= empty($package) ? 'selected' : '' ?>>Select Package</option>
            <?php foreach ($package_options as $pkg => $amt): ?>
                <option value="<?= $pkg ?>" <?= (isset($package) && $package === $pkg) ? 'selected' : '' ?>>
                    <?= $pkg ?> - GHC <?= number_format($amt, 2) ?>
                </option>
            <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="amount_paid">Amount Paid (GHC)</label>
        <input type="text" id="amount_paid" name="amount_paid" class="form-control" readonly
               value="<?= isset($package) && isset($package_options[$package]) ? $package_options[$package] : '' ?>" />
      </div>

      <div class="form-group">
        <label for="mode_of_payment">Mode of Payment <span class="required">*</span></label>
        <select id="mode_of_payment" name="mode_of_payment" class="form-control" required onchange="toggleTransactionIdRequirement()">
            <option value="" disabled <?= empty($mode_of_payment) ? 'selected' : '' ?>>Select Mode of Payment</option>
            <?php foreach ($payment_modes as $mode): ?>
                <option value="<?= $mode ?>" <?= (isset($mode_of_payment) && $mode_of_payment === $mode) ? 'selected' : '' ?>>
                    <?= $mode ?>
                </option>
            <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="location">Location <span class="required">*</span></label>
        <input type="text" id="location" name="location" class="form-control" required
               value="<?= htmlspecialchars($location ?? '') ?>" />
      </div>

      <div class="form-group">
        <label for="email">Email Address <span class="required">*</span></label>
        <input type="email" id="email" name="email" class="form-control" required
               value="<?= htmlspecialchars($email ?? '') ?>" />
      </div>

      <div class="form-group full-width">
        <label for="comment">Comment</label>
        <textarea id="comment" name="comment" class="form-control"><?= htmlspecialchars($comment ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn btn-submit">
        <i class="bi bi-send-fill"></i> Submit Installation
      </button>
    </form>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Sidebar dropdown toggle
    const logTicketBtn = document.getElementById('logTicketBtn');
    const logTicketMenu = document.getElementById('logTicketMenu');

    if (logTicketBtn && logTicketMenu) {
        logTicketBtn.addEventListener('click', (e) => {
            e.preventDefault();
            logTicketMenu.style.display = logTicketMenu.style.display === 'block' ? 'none' : 'block';
        });

        document.addEventListener('click', (e) => {
            if (!logTicketBtn.contains(e.target) && !logTicketMenu.contains(e.target)) {
                logTicketMenu.style.display = 'none';
            }
        });
    }

    // Package price auto-fill
    const packagePrices = <?= json_encode($package_options); ?>;
    window.updateAmountPaid = function() {
        const pkg = document.getElementById('package').value;
        const amountInput = document.getElementById('amount_paid');
        amountInput.value = pkg && packagePrices[pkg] ? packagePrices[pkg] : '';
    };

    // Toggle Transaction ID required indicator for Mobile Money
    window.toggleTransactionIdRequirement = function() {
        const modeSelect = document.getElementById('mode_of_payment');
        const transactionLabel = document.querySelector('label[for="transaction_id"]');
        if (modeSelect.value === 'Mobile Money') {
            if (!transactionLabel.querySelector('.required')) {
                const span = document.createElement('span');
                span.className = 'required';
                span.textContent = '*';
                transactionLabel.appendChild(span);
            }
        } else {
            const span = transactionLabel.querySelector('.required');
            if (span) {
                transactionLabel.removeChild(span);
            }
        }
    };

    updateAmountPaid();
    toggleTransactionIdRequirement();
});
</script>
</body>
</html>
