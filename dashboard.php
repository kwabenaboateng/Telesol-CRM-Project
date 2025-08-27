<?php
session_start();

// Fetch user info from session, use defaults if missing
$username = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? '';
$user_department = $_SESSION['user_department'] ?? '';

// Page accessible to all roles and departments, no restrictions here
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Telesol Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
<style>
  /* Reset and base styles */
  body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    /* background: #f7fafc; */
    background: #f0f2f5;
    color: #334155;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  /* Navbar */
  .navbar {
    background: #334155;
    /* background: #1e293b; */
    height: 60px;
    padding: 1rem 1rem;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .navbar-brand img {
    height: 44px;
    filter: brightness(0) invert(1);
  }

  .user-greeting {
    font-weight: 500;
    font-size: 1rem;
    padding: -2px;
    align-items: center;
  }

  /* Main content container */
  main {
    flex: 1;
    max-width: 1140px;
    margin: 2rem auto 3rem;
    padding: 0 1rem;
    width: 100%;
  }

  /* Header */
  .page-header {
    text-align: center;
    margin-bottom: 2.5rem;
  }

  .page-title {
    font-size: 2rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 0.5rem;
  }

  .page-description {
    color: #64748b;
    font-size: 1.125rem;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.5;
  }

  /* Department cards grid */
  .department-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.6rem;
    margin-top: 2rem;
  }

  /* Individual card */
  .department-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 6px 12px rgb(0 0 0 / 0.12);
    border: 1px solid #e2e8f0;
    padding: 1rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #334155;
    transition: box-shadow 0.3s ease, transform 0.3s ease;
    user-select: none;
    cursor: pointer;
  }
  .department-card:hover,
  .department-card:focus {
    box-shadow: 0 12px 25px rgb(0 0 0 / 0.18);
    transform: translateY(-6px);
    background-color: #334155;
    color: white;
    /* border-color: #2563eb; */
    outline: none;
  }

  /* Icon in card */
  .department-icon {
    font-size: 3rem;
    color: #2563eb;
    margin-bottom: 1.25rem;
  }

  /* Department name */
  .department-name {
    font-weight: 700;
    font-size: 1.4rem;
    text-align: center;
  }

  /* Support section tone */
  .support-section {
    margin-top: 1.5rem;
    text-align: center;
    font-size: 1rem;
    color: #64748b;
  }
  .support-link {
    color: #2563eb;
    font-weight: 600;
    text-decoration: none;
  }
  .support-link:hover,
  .support-link:focus {
    text-decoration: underline;
  }

  /* Responsive adjustments */
  @media (max-width: 480px) {
    .page-title {
      font-size: 2rem;
    }
    main {
      margin: 1rem auto 2rem;
    }
    .department-card {
      padding: 1.5rem 1rem;
    }
  }
</style>
</head>
<body>
  <nav class="navbar" role="banner">
    <a href="#" class="navbar-brand" aria-label="Telesol Home">
      <img src="Telesol_logo.jpeg" alt="Telesol Logo" />
    </a>
    <div class="user-greeting" aria-live="polite">
      Welcome, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
    </div>
  </nav>

  <main role="main" tabindex="-1">
    <header class="page-header">
      <h1 class="page-title">Telesol Dashboard</h1>
      <p class="page-description">
        Access comprehensive customer feedback and service analytics across all departments.
        Select a department below to continue.
      </p>
    </header>

    <section class="department-grid" aria-label="Departments">
      <?php
        $departments = [
          ['name' => 'Admin', 'link' => 'adminmenu.php', 'icon' => 'bi-gear-fill'],
          ['name' => 'Customer Service', 'link' => 'log_ticket.php', 'icon' => 'bi-headset'],
          ['name' => 'Finance', 'link' => 'csmenu.php', 'icon' => 'bi-cash-stack'],
          ['name' => 'Sales', 'link' => 'sales.php', 'icon' => 'bi-graph-up'],
          ['name' => 'Systems', 'link' => 'system.php', 'icon' => 'bi-server'],
          ['name' => 'Technology', 'link' => 'technical.php', 'icon' => 'bi-cpu'],
          /* ['name' => 'All Departments', 'link' => 'alldepartments.php', 'icon' => 'bi-grid-fill'], */
        ];
        foreach ($departments as $dept): ?>
          <a href="<?= htmlspecialchars($dept['link']) ?>"
             class="department-card"
             role="button"
             tabindex="0"
             aria-label="<?= htmlspecialchars($dept['name']) ?> Department">
            <div class="department-icon"><i class="bi <?= htmlspecialchars($dept['icon']) ?>"></i></div>
            <div class="department-name"><?= htmlspecialchars($dept['name']) ?></div>
          </a>
      <?php endforeach; ?>
    </section>

    <div class="support-section" role="contentinfo">
      Need assistance? Contact <a href="mailto:support@telesol.com" class="support-link">support@telesol.com</a>
    </div>
  </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
