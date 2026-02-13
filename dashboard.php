<?php
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

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
  <title>Telesol CRM Dashboard</title>
  <meta name="description" content="Telesol CRM Dashboard - Access comprehensive customer feedback and service analytics">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="preload" href="Telesol_logo.jpeg" as="image">
</head>

<style>
  :root {
    --primary: #083b6e;
    --primary-dark: #1e2d3b;
    --secondary: #3498db;
    --success: #00b44bff;
    --warning: #f39c12;
    --danger: #e74c3c;
    --light: #ecf0f1;
    --dark: #2c3e50;
    --gray: #95a5a6;
    --light-gray: #ddd;
    --sidebar-width: 220px;
    --background: #f5f7fa;
    --purple: #00e5ffff;
    --white: #ffffff;
    --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-hover: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    --transition: all 0.3s ease;
  }

  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    font-family: 'Inter', sans-serif;
    background: var(--background);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    line-height: 1.5;
  }

  /* Focus styles for accessibility */
  a:focus-visible,
  button:focus-visible,
  .department-card:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
  }

  /* Skip link for accessibility */
  .skip-link {
    position: absolute;
    top: -40px;
    left: 0;
    background: var(--primary);
    color: white;
    padding: 8px;
    z-index: 1000;
    text-decoration: none;
  }
  
  .skip-link:focus {
    top: 0;
  }

  /* Navbar */
  .navbar {
    background: var(--dark);
    height: 60px;
    padding: 0 1.5rem;
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
    position: sticky;
    top: 0;
    z-index: 100;
  }

  .navbar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    color: white;
    font-weight: 600;
    font-size: 1.25rem;
  }

  .navbar-brand img {
    height: 40px;
    width: auto;
    filter: brightness(0) invert(1);
  }

  .user-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    flex-shrink: 0;
  }

  .user-details {
    display: flex;
    flex-direction: column;
  }

  .user-greeting {
    font-size: 0.875rem;
    color: var(--white)/* #e2e8f0 */;
  }

  .user-role {
    font-size: 0.75rem;
    color: #94a3b8;
    text-transform: capitalize;
  }

  /* Main content container */
  main {
    flex: 1;
    max-width: 1200px;
    margin: 2rem auto 3rem;
    padding: 0 1.5rem;
    width: 100%;
  }

  /* Header */
  .page-header {
    margin-bottom: 2.5rem;
  }

  .page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.5rem;
  }

  .page-description {
    color: var(--text-light);
    font-size: 1rem;
    max-width: 600px;
    line-height: 1.4;
  }

  /* Stats overview */
  .stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.2rem;
    margin-bottom: 2rem;
  }

  .stat-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    transition: var(--transition);
  }

  .stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-hover);
  }

  .stat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
  }

  .stat-title {
    font-size: 0.875rem;
    color: var(--text-light);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(37, 99, 235, 0.1);
    color: var(--primary);
    flex-shrink: 0;
  }

  .stat-value {
    font-size: 1.875rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.25rem;
  }

  .stat-change {
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
  }

  .positive {
    color: var(--success);
  }

  .negative {
    color: var(--danger);
  }

  /* Department cards grid */
  .department-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
  }

  /* Individual card */
  .department-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow);
    padding: 1.75rem 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: var(--text);
    transition: var(--transition);
    user-select: none;
    cursor: pointer;
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
  }

  .department-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--primary);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
  }

  .department-card:hover,
  .department-card:focus {
    box-shadow: var(--shadow-hover);
    transform: translateY(-5px);
    border-color: var(--primary);
  }

  .department-card:hover::before,
  .department-card:focus::before {
    transform: scaleX(1);
  }

  /* Icon in card */
  .department-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(37, 99, 235, 0.1);
    color: var(--primary);
    font-size: 2rem;
    margin-bottom: 1.25rem;
    transition: var(--transition);
  }

  .department-card:hover .department-icon {
    background: var(--primary);
    color: white;
    transform: scale(1.1);
  }

  /* Department name */
  .department-name {
    font-weight: 600;
    font-size: 1.25rem;
    text-align: center;
    margin-bottom: 0.75rem;
  }

  .department-desc {
    font-size: 0.875rem;
    color: var(--text-light);
    text-align: center;
    line-height: 1.5;
  }

  /* Support section */
  .support-section {
    text-align: center;
    padding: 2rem 1.5rem;
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow);
  }

  .support-title {
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: var(--dark);
    font-size: 1.25rem;
  }

  .support-text {
    color: var(--text-light);
    margin-bottom: 1.25rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
  }

  .support-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    transition: var(--transition);
    background: rgba(37, 99, 235, 0.1);
  }

  .support-link:hover,
  .support-link:focus {
    background: rgba(37, 99, 235, 0.2);
    outline: none;
  }

  /* Loading animation for cards */
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }
  
  .department-card {
    animation: fadeIn 0.5s ease forwards;
  }
  
  .department-card:nth-child(1) { animation-delay: 0.1s; }
  .department-card:nth-child(2) { animation-delay: 0.2s; }
  .department-card:nth-child(3) { animation-delay: 0.3s; }
  .department-card:nth-child(4) { animation-delay: 0.4s; }
  .department-card:nth-child(5) { animation-delay: 0.5s; }
  .department-card:nth-child(6) { animation-delay: 0.6s; }
  .department-card:nth-child(7) { animation-delay: 0.7s; }

  /* Reduced motion for accessibility */
  @media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
      animation-duration: 0.01ms !important;
      animation-iteration-count: 1 !important;
      transition-duration: 0.01ms !important;
      scroll-behavior: auto !important;
    }
    
    .department-card {
      animation: none;
    }
  }

  /* Responsive adjustments */
  @media (max-width: 992px) {
    .department-grid {
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
  }

  @media (max-width: 768px) {
    .navbar {
      padding: 0 1rem;
      height: 60px;
    }
    
    .navbar-brand span {
      display: none;
    }
    
    .user-details {
      display: none;
    }
    
    main {
      margin: 1.5rem auto;
      padding: 0 1rem;
    }
    
    .page-title {
      font-size: 1.75rem;
    }
    
    .stats-container {
      grid-template-columns: 1fr;
      gap: 1rem;
    }
    
    .department-grid {
      grid-template-columns: 1fr;
      gap: 1rem;
    }
    
    .department-card {
      padding: 1.5rem;
    }
    
    .stat-card {
      padding: 1.25rem;
    }
  }

  @media (max-width: 480px) {
    .department-icon {
      width: 60px;
      height: 60px;
      font-size: 1.75rem;
    }
    
    .department-name {
      font-size: 1.125rem;
    }
    
    .support-section {
      padding: 1.5rem 1rem;
    }
  }
</style>
</head>

<body>
  <a href="#main-content" class="skip-link">Skip to main content</a>
  
  <nav class="navbar" role="banner" aria-label="Main navigation">
    <a href="#" class="navbar-brand" aria-label="Telesol CRM">
      <img src="Telesol_logo.jpeg" alt="Telesol Logo" width="40" height="40" />
      <!-- <span>Telesol CRM</span> -->
    </a>
    <div class="user-info">
      <div class="user-avatar" aria-hidden="true">
        <?= strtoupper(substr($username, 0, 1)) ?>
      </div>
      <div class="user-details">
        <div class="user-greeting">Welcome, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="user-role"><?= htmlspecialchars($user_role) ? htmlspecialchars($user_role) : 'User' ?></div>
      </div>
    </div>
  </nav>

  <main id="main-content" role="main" tabindex="-1">
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
          ['name' => 'Admin', 'link' => 'task_overview.php', 'icon' => 'bi-gear-fill', 'desc' => 'Daily task management  and administrative duties'],
          ['name' => 'Customer Service', 'link' => 'ticket_mgt.php', 'icon' => 'bi-headset', 'desc' => 'Handle customer inquiries and support tickets'],
          ['name' => 'Finance', 'link' => 'csmenu.php', 'icon' => 'bi-cash-stack', 'desc' => 'Billing, payments and financial reports'],
          ['name' => 'Sales', 'link' => 'sales.php', 'icon' => 'bi-graph-up', 'desc' => 'Sales pipeline and customer acquisition'],
          ['name' => 'Systems', 'link' => 'system.php', 'icon' => 'bi-server', 'desc' => 'Infrastructure and network management'],
          ['name' => 'Technology', 'link' => 'tech_dashboard.php', 'icon' => 'bi-cpu', 'desc' => 'Product development and R&D'],
          ['name' => 'Transport', 'link' => 'transport.php', 'icon' => 'bi-grid-fill', 'desc' => 'Cross-departmental overview and reports'],
          ['name' => 'All Departments', 'link' => 'alldepartments.php', 'icon' => 'bi-grid-fill', 'desc' => 'Cross-departmental overview and reports'],
          ['name' => 'All Departments', 'link' => 'alldepartments.php', 'icon' => 'bi-grid-fill', 'desc' => 'Cross-departmental overview and reports'],
        ];
        
        foreach ($departments as $index => $dept): ?>
          <a href="<?= htmlspecialchars($dept['link']) ?>"
             class="department-card"
             role="button"
             tabindex="0"
             aria-label="<?= htmlspecialchars($dept['name']) ?> Department"
             data-department="<?= htmlspecialchars(strtolower(str_replace(' ', '-', $dept['name']))) ?>">
            <div class="department-icon" aria-hidden="true"><i class="bi <?= htmlspecialchars($dept['icon']) ?>"></i></div>
            <div class="department-name"><?= htmlspecialchars($dept['name']) ?></div>
            <div class="department-desc"><?= htmlspecialchars($dept['desc']) ?></div>
          </a>
      <?php endforeach; ?>
    </section>

    <div class="support-section" role="contentinfo" aria-label="Support information">
      <div class="support-title">Need assistance?</div>
      <p class="support-text">Our support team is available Monday-Friday, 8am-6pm EST</p>
      <a href="mailto:support@telesol.com" class="support-link">
        <i class="bi bi-envelope" aria-hidden="true"></i> support@telesol.com
      </a>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Add subtle interaction improvements
    document.addEventListener('DOMContentLoaded', function() {
      // Add focus management for cards
      const cards = document.querySelectorAll('.department-card');
      cards.forEach(card => {
        card.addEventListener('keypress', function(e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.click();
          }
        });
      });
      
      // Add loading state for cards (simulated)
      setTimeout(() => {
        document.body.classList.add('content-loaded');
      }, 300);
    });
  </script>
</body>
</html>