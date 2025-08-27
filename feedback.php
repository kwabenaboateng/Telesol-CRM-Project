<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'telesol_crm';  // Removed spaces

$showThankYou = false;
$errorMsg = '';

function sanitize(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    die('Technical difficulties. Please try again later.');
}

// Initialize variables for sticky form
$name = '';
$email = '';
$ratings = '';
$team_helpful = '';
$recommend = '';
$suggestions = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $ratings = sanitize($_POST['ratings'] ?? '');
    $team_helpful = sanitize($_POST['team_helpful'] ?? '');
    $recommend = sanitize($_POST['recommend'] ?? '');
    $suggestions = sanitize($_POST['suggestions'] ?? '');

    if (!$name || !$email || !$ratings || !$team_helpful || !$recommend) {
        $errorMsg = 'All required fields must be filled.';
    } elseif (!isValidEmail($email)) {
        $errorMsg = 'Invalid email address.';
    } else {
        $sql = 'INSERT INTO customer_feedback
            (name, email, ratings, team_helpful, recommend, suggestions) 
            VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log('Prepare failed: ' . $conn->error);
            $errorMsg = 'Submission error. Please try again.';
        } else {
            $stmt->bind_param('ssssss', $name, $email, $ratings, $team_helpful, $recommend, $suggestions);
            if ($stmt->execute()) {
                $showThankYou = true;
                // Clear form fields on success
                $name = $email = $ratings = $team_helpful = $recommend = $suggestions = '';
            } else {
                error_log('Execute failed: ' . $stmt->error);
                $errorMsg = 'Could not submit feedback. Try again.';
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
<title>Customer Feedback Submission</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
  body, html {
   height: 100%;
   margin: 0;
   font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
   background: #899bbc;
   color: #212529;
   display: flex;
   justify-content: center;
   align-items: flex-start;
   padding: 40px 20px;
 }
  .card {
    max-width: 850px;
    width: 800px;
    margin: auto;
    padding: 2rem;
    border: none;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    background-color: #ffffff;
    }
    .form-header {
      text-align: center;
      margin-bottom: 2rem;
    }
    .form-header h3 {
      font-weight: 600;
      color: #113563;
      font-size: 1.3rem;
    }
    .form-label {
      font-weight: 500;
      margin-bottom: 0.5rem;
      color: #113563;
      font-size: 14px;
    }
    .form-control,
    .form-select {
      border-radius: 10px;
      border: 1px solid #ccc;
      transition: border-color 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
    }
    .form-control:focus,
    .form-select:focus {
      border-color: #0d6efd;
      box-shadow: 0 0 0 0.10rem rgba(13, 110, 253, 0.25);
    }
    .btn-primary {
      background-color: #0d6efd;
      border-color: #0d6efd;
      padding: 0.6rem 2rem;
      font-weight: 500;
      border-radius: 50px;
      transition: background-color 0.3s ease;
    }
    .btn-primary:hover {
      background-color: #084dbf;
      border-color: #084dbf;
    }
    textarea.form-control {
      resize: none;
    }
    @media (max-width: 576px) {
      .form-header h3 {
        font-size: 1.25rem;
      }
      .card {
        padding: 1.5rem;
      }
    }
    form, #thankYouMessage {
      transition: all 0.4s ease-in-out;
    }
    #thankYouMessage .card {
      border-radius: 16px;
      background-color: #f9fdfb;
      border-left: 6px solid #198754;
    }
    /* Modal styles */
    .modal {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1050;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    .modal.show {
      display: flex;
      opacity: 1;
    }
    .modal-content {
      background: #fff;
      border-radius: 8px;
      padding: 2.5rem 3rem;
      max-width: 450px;
      width: 90%;
      box-shadow: 0 8px 24px rgba(0,0,0,0.2);
      text-align: center;
      position: relative;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .modal-content h2 {
      color: #2c3e50;
      margin-bottom: 1rem;
      font-weight: 700;
      font-size: 1.8rem;
    }
    .modal-content p {
      font-size: 1.1rem;
      color: #34495e;
      margin-bottom: 2rem;
    }
    .modal-content svg {
      width: 60px;
      height: 60px;
      margin-bottom: 1rem;
      fill: #27ae60;
    }
    #return-btn {
      background-color: #27ae60;
      border: none;
      color: white;
      padding: 0.75rem 1.5rem;
      font-size: 1rem;
      border-radius: 4px;
      cursor: pointer;
      transition: background-color 0.25s ease;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
    }
    #return-btn:hover,
    #return-btn:focus {
      background-color: #219150;
      outline: none;
      box-shadow: 0 0 8px #219150;
    }
    .error-message {
      margin-bottom: 1rem;
      color: #dc3545;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="form-header">
        <h3>Customer Service Experience Feedback</h3>
      </div>

      <?php if ($errorMsg): ?>
        <div class="error-message" role="alert" aria-live="assertive"><?= $errorMsg ?></div>
      <?php endif; ?>

      <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" novalidate>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="name" class="form-label">Your Name</label>
            <input type="text" id="name" name="name" class="form-control" placeholder="Enter your name" required value="<?= $name ?>" />
          </div>
          <div class="col-md-6 mb-3">
            <label for="email" class="form-label">Your Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required value="<?= $email ?>" />
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Rate your experience with us</label>
            <select class="form-select" name="ratings" required>
              <option value="" disabled <?= $ratings === '' ? 'selected' : '' ?>>Select rating</option>
              <?php
                $options = ['Very Good', 'Good', 'Neutral', 'Bad', 'Very Bad'];
                foreach ($options as $opt) {
                    $selected = ($ratings === $opt) ? 'selected' : '';
                    echo "<option value=\"$opt\" $selected>$opt</option>";
                }
              ?>
            </select>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">Was our team helpful?</label>
            <select class="form-select" name="team_helpful" required>
              <option value="" disabled <?= $team_helpful === '' ? 'selected' : '' ?>>Select your answer</option>
              <?php
                $options = ['Yes', 'No', 'Somehow'];
                foreach ($options as $opt) {
                    $selected = ($team_helpful === $opt) ? 'selected' : '';
                    echo "<option value=\"$opt\" $selected>$opt</option>";
                }
              ?>
            </select>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">Will you recommend us to others?</label>
            <select class="form-select" name="recommend" required>
              <option value="" disabled <?= $recommend === '' ? 'selected' : '' ?>>Select your answer</option>
              <?php
                $options = ['Yes', 'No', 'Maybe'];
                foreach ($options as $opt) {
                    $selected = ($recommend === $opt) ? 'selected' : '';
                    echo "<option value=\"$opt\" $selected>$opt</option>";
                }
              ?>
            </select>
          </div>
        </div>

        <div class="row">
          <div class="col-md-12 mb-3">
            <label class="form-label">Suggestions/Comments?</label>
            <textarea class="form-control" id="suggestions" name="suggestions" rows="3"
              placeholder="Share your thoughts..."><?= $suggestions ?></textarea>
          </div>
        </div>

        <div class="text-center mt-4">
          <button type="submit" class="btn btn-primary">Submit Feedback</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Thank You Modal -->
  <div class="modal<?= $showThankYou ? ' show' : '' ?>" role="dialog" aria-modal="true" aria-labelledby="thankYouTitle" tabindex="-1">
    <div class="modal-content" role="document">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M9 16.2l-3.5-3.5L4 14.2l5 5 12-12-1.4-1.4z"/>
      </svg>
      <h2 id="thankYouTitle">Thank You!</h2>
      <p>Your feedback has been successfully submitted. We appreciate your time and effort in helping us improve.</p>
      <button id="return-btn" type="button" aria-label="Return to feedback form">Return to Form</button>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const modal = document.querySelector('.modal');
      const returnBtn = document.getElementById('return-btn');

      if (modal.classList.contains('show')) {
        returnBtn.focus();

        returnBtn.addEventListener('click', () => {
          modal.classList.remove('show');
          window.location.href = window.location.pathname; // Reload form page without query
        });

        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') {
            modal.classList.remove('show');
            window.location.href = window.location.pathname;
          }
        });
      }
    });
  </script>
</body>
</html>
