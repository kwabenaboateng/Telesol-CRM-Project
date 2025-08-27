<?php
session_start();

// Fetch user info from session
$username = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? '';
$user_department = $_SESSION['user_department'] ?? '';

// Restrict access: only admin role OR users in Customer Service department
if ($user_role !== 'admin' && $user_department !== 'Customer Service') {
    http_response_code(403);
    echo '<h1>Access Denied</h1><p>You do not have permission to access this page.</p>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" >
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Telesol CRM Dashboard</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet" />

  <style>
    /* Unique theme with dark mode toggle */

    :root {
      --color-bg: #fefefe;
      --color-primary: #0077cc;
      --color-primary-dark: #004a8d;
      --color-text: #1e293b;
      --color-text-muted: #64748b;
      --color-accent: #ff5722;
      --color-card-bg: #ffffff;
      --transition-speed: 0.3s;
    }

    [data-theme="dark"] {
      --color-bg: #121212;
      --color-primary: #58a6ff;
      --color-primary-dark: #206ab6;
      --color-text: #f2f2f2;
      --color-text-muted: #8899a6;
      --color-accent: #ff7849;
      --color-card-bg: #1e1e1e;
    }

    body {
      margin: 0;
      font-family: 'Montserrat', sans-serif;
      background: #f0f2f5;
      /* background-color: var(--color-bg) */;
      color: var(--color-text);
      min-height: 100vh;
      transition: background-color var(--transition-speed), color var(--transition-speed);
      display: flex;
      flex-direction: column;
    }

    /* Navbar */
    .navbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 2rem;
      background-color: var(--color-card-bg);
      box-shadow: 0 2px 6px rgba(0,0,0,0.12);
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    .navbar-brand img {
      height: 44px;
      cursor: pointer;
      transition: transform 0.25s ease;
    }

    .navbar-brand img:hover {
      transform: rotate(-10deg) scale(1.1);
    }

    .user-greeting {
      font-weight: 700;
      font-size: 1.15rem;
    }

    /* Dark mode toggle */
    .dark-mode-toggle {
      cursor: pointer;
      background: var(--color-primary);
      border: none;
      color: #fff;
      padding: 0.4rem 1rem;
      border-radius: 30px;
      font-weight: 600;
      letter-spacing: 0.05em;
      user-select: none;
      transition: background-color var(--transition-speed);
    }

    .dark-mode-toggle:hover, .dark-mode-toggle:focus {
      background: var(--color-primary-dark);
      outline: none;
    }

    /* Main content */
    main {
      flex: 1;
      max-width: 1200px;
      margin: 3rem auto 4rem;
      padding: 0 2rem;
      width: 100%;
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 3rem;
    }

    /* Welcome section */
    .welcome-panel {
      background: var(--color-card-bg);
      padding: 3rem 2rem;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.12);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: flex-start;
    }

    .welcome-panel h1 {
      font-size: 2.8rem;
      font-weight: 800;
      margin-bottom: 1rem;
      color: var(--color-primary);
    }

    .welcome-panel p {
      font-size: 1.15rem;
      color: var(--color-text-muted);
      line-height: 1.6;
      max-width: 520px;
    }

    /* Action buttons panel */
    .actions-panel {
      background: var(--color-card-bg);
      padding: 2rem;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.12);
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }

    .action-btn {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.5rem;
      border-radius: 10px;
      font-weight: 700;
      font-size: 1.125rem;
      cursor: pointer;
      background: var(--color-primary);
      color: #fff;
      border: none;
      transition: background-color var(--transition-speed), box-shadow var(--transition-speed);
      box-shadow: 0 6px 12px rgba(0, 119, 204, 0.6);
      text-decoration: none;
      user-select: none;
    }
    .action-btn:hover, .action-btn:focus {
      background: var(--color-primary-dark);
      box-shadow: 0 10px 20px rgba(0, 74, 141, 0.8);
      outline: none;
      transform: scale(1.05);
    }

    .action-btn i {
      font-size: 1.8rem;
    }

    /* Footer-tips section */
    .tip-section {
      margin-top: 4rem;
      padding: 1.5rem 2rem;
      background: var(--color-primary);
      color: #fff;
      border-radius: 16px;
      font-weight: 600;
      letter-spacing: 0.03em;
      user-select: none;
      max-width: 728px;
      margin-left: auto;
      margin-right: auto;
      text-align: center;
      box-shadow: 0 8px 25px rgba(0,119,204,0.5);
    }
    .tip-section a {
      color: #ffe5d1;
      font-weight: 700;
      text-decoration: underline;
    }
    .tip-section a:hover,
    .tip-section a:focus {
      color: #fff0e6;
      outline: none;
    }

    /* Responsive adjustments */
    @media (max-width: 900px) {
      main {
        grid-template-columns: 1fr;
        gap: 3rem;
        padding: 0 1.5rem;
      }
      .welcome-panel {
        align-items: center;
        text-align: center;
      }
      .welcome-panel p {
        max-width: 100%;
      }
      .tip-section {
        max-width: 100%;
      }
    }
  </style>
</head>
<body data-theme="light">
  <nav class="navbar" role="banner" aria-label="Primary Navigation">
    <a href="#" class="navbar-brand" aria-label="Telesol Home">
      <img src="Telesol_logo.jpeg" alt="Telesol Logo" />
    </a>
    <div class="d-flex align-items-center gap-3">
      <div class="user-greeting" aria-live="polite" tabindex="0" title="Logged in user">
        Welcome, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <button id="darkModeToggle" aria-pressed="false" class="dark-mode-toggle" title="Toggle dark mode">
        <i class="bi bi-moon-fill" id="darkModeIcon" aria-hidden="true"></i> Dark Mode
      </button>
      <form action="logout.php" method="POST" class="m-0">
        <button type="submit" class="logout-btn" aria-label="Logout">
          <i class="bi bi-box-arrow-right"></i> Logout
        </button>
      </form>
    </div>
  </nav>

  <main role="main" tabindex="-1">
    <section class="welcome-panel" aria-label="Welcome section">
      <h1>Welcome to Telesol CRM</h1>
      <p>Empower your customer experience management with real-time insights, responsive ticket handling, and seamless team collaboration — all in one place.</p>
    </section>

    <section class="actions-panel" aria-label="Dashboard actions">
      <a href="log_issue.php" class="action-btn" role="button" tabindex="0" aria-label="Log Ticket">
        <i class="bi bi-journal-plus"></i> Log Ticket
      </a>
      <a href="view_tickets.php" class="action-btn" role="button" tabindex="0" aria-label="Ticket Overview">
        <i class="bi bi-folder-check"></i> View Tickets
      </a>
      <a href="customer_experience.php" class="action-btn" role="button" tabindex="0" aria-label="Customer Experience">
        <i class="bi bi-emoji-smile"></i> Customer Experience
      </a>
      <a href="field_installations.php" class="action-btn" role="button" tabindex="0" aria-label="Field Work Feedback">
        <i class="bi bi-wrench-adjustable"></i> Field Work Feedback
      </a>
      <a href="settings.php" class="action-btn" role="button" tabindex="0" aria-label="System Settings">
        <i class="bi bi-sliders"></i> System Settings
      </a>
    </section>

    <section class="tip-section" role="complimentary" tabindex="0">
      <strong>Tip:</strong> Use filters on any page to refine your data. Questions? Reach our <a href="mailto:support@telesol.com">Support Team</a> anytime.
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Dark mode toggle behavior
    const toggle = document.getElementById('darkModeToggle');
    const body = document.body;
    const icon = document.getElementById('darkModeIcon');

    function setDarkMode(enabled) {
      if (enabled) {
        body.setAttribute('data-theme', 'dark');
        icon.classList.replace('bi-moon-fill', 'bi-sun-fill');
        toggle.setAttribute('aria-pressed', 'true');
        toggle.textContent = ' Light Mode';
        toggle.prepend(icon);
        localStorage.setItem('telesolDarkMode', 'enabled');
      } else {
        body.setAttribute('data-theme', 'light');
        icon.classList.replace('bi-sun-fill', 'bi-moon-fill');
        toggle.setAttribute('aria-pressed', 'false');
        toggle.textContent = ' Dark Mode';
        toggle.prepend(icon);
        localStorage.setItem('telesolDarkMode', 'disabled');
      }
    }

    toggle.addEventListener('click', () => {
      const enabled = body.getAttribute('data-theme') === 'dark';
      setDarkMode(!enabled);
    });

    // Initialize from localStorage if saved preference exists
    if(localStorage.getItem('telesolDarkMode') === 'enabled') {
      setDarkMode(true);
    } else {
      setDarkMode(false);
    }
  </script>
</body>
</html>
