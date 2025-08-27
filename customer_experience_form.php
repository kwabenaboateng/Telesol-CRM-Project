<?php
// Database connection parameters - configure these before use
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "telesol crm";

$errorMsg = '';
$showThankYou = false;

// Helper functions
function sanitize($data) {
    return htmlspecialchars(trim($data));
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Connect to DB
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    die('Technical difficulties. Please try again later.');
}

// Handle form submission
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
        $sql = 'INSERT INTO customer_feedback (name, email, ratings, team_helpful, recommend, suggestions) VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log('Prepare failed: ' . $conn->error);
            $errorMsg = 'Submission error. Please try again.';
        } else {
            $stmt->bind_param('ssssss', $name, $email, $ratings, $team_helpful, $recommend, $suggestions);
            if ($stmt->execute()) {
                $showThankYou = true;
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
<title>Submit Feedback</title>
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
    /* padding: 40px 20px; */
  }

  /* Card styling */
  .card {
    max-width: 850px;
    width: 800px;
    margin: auto;
    padding: 1.5rem 2.5rem;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    background-color: #ffffff;
  }

  /* Header styling */
  .form-header {
    text-align: center;
    margin-bottom: 2rem;
  }

  .form-header h3 {
    font-weight: 600;
    color: #113563;
    font-size: 1.4rem;
    margin-bottom: 0;
  }

  /* Form container: horizontal layout with flex */
  form {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: flex-start;
  }

  /* Each group occupies about half width */
  .form-group {
    flex: 1 1 45%;
    display: flex;
    flex-direction: column;
  }

  /* Full width for row container */
  .select-row {
    display: flex;
    gap: 20px;
    flex: 1 1 100%;
  }

  /* Each dropdown in the row shares equal width */
  .select-row .form-group {
    flex: 1 1 0;
  }

  .form-label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #113563;
    font-size: 14px;
  }

  .form-control,
  .form-select {
    border-radius: 10px;
    border: 1px solid #ccc;
    padding: 10px 12px;
    font-size: 1rem;
    font-family: inherit;
    color: #113563;
    transition: border-color 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
  }

  .form-control:focus,
  .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.10rem rgba(13, 110, 253, 0.25);
    outline: none;
  }

  textarea.form-control {
    resize: none;
    min-height: 100px;
  }

  /* Submit button: full width below inputs */
  .form-actions {
    flex: 1 1 100%;
    text-align: center;
    margin-top: 20px;
  }

  .btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
    padding: 0.75rem 3.5rem;
    font-weight: 600;
    border-radius: 50px;
    color: #fff;
    font-size: 1.1rem;
    cursor: pointer;
    transition: background-color 0.3s ease;
    border: none;
  }

  .btn-primary:hover {
    background-color: #084dbf;
    border-color: #084dbf;
  }

  /* Thank you card */
  .thank-you-card {
    background-color: #ffffff;
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(0, 123, 255, 0.2);
    padding: 2.5rem 3rem;
    max-width: 480px;
    margin: 3rem auto;
    color: #0f5132;
    text-align: center;
  }

  .thank-you-card h1 {
    font-weight: 700;
    font-size: 2.5rem;
    margin-bottom: 1rem;
  }

  .thank-you-card p {
    font-size: 1.25rem;
    margin-bottom: 2rem;
  }

  #return-btn {
    background-color: #0d6efd;
    color: #ffffff;
    border: none;
    padding: 0.75rem 1.75rem;
    font-size: 1rem;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: background-color 0.3s ease;
  }

  #return-btn:hover,
  #return-btn:focus {
    background-color: #0b5ed7;
    outline: none;
  }

  /* Alert styling */
  .alert-danger {
    max-width: 480px;
    margin: 1rem auto 2rem;
    border-radius: 6px;
    font-size: 1rem;
    padding: 1rem 1.5rem;
    background-color: #fdecea;
    color: #e74c3c;
    border-left: 5px solid #e74c3c;
  }

  /* Responsive adjustments */
  @media (max-width: 768px) {
    form {
      flex-direction: column;
    }
    .form-group, 
    .select-row {
      flex: 1 1 100% !important;
      display: block !important;
    }
    .select-row .form-group {
      margin-bottom: 1rem;
    }
  }
</style>
</head>
<body>

<div class="card">
<?php if ($showThankYou): ?>
  <div class="thank-you-card">
    <h1>Thank you!</h1>
    <p>Your feedback has been submitted successfully.</p>
    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="return-btn">Submit Another Feedback</a>
  </div>
<?php else: ?>
  <div class="form-header"><h3>Customer Experience Survey</h3></div>

  <?php if ($errorMsg): ?>
    <div class="alert-danger"><?php echo $errorMsg; ?></div>
  <?php endif; ?>

  <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
    <div class="form-group">
      <label for="name" class="form-label">Name *</label>
      <input type="text" id="name" name="name" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" />
    </div>

    <div class="form-group">
      <label for="email" class="form-label">Email *</label>
      <input type="email" id="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
    </div>

    <div class="select-row">
      <div class="form-group">
        <label for="ratings" class="form-label">Overall Ratings *</label>
        <select id="ratings" name="ratings" class="form-select" required>
          <option value="" disabled <?php echo empty($_POST['ratings']) ? 'selected' : ''; ?>>-- Select Rating --</option>
          <?php
            $ratings_options = ['Excellent', 'Good', 'Neutral', 'Average', 'Poor'];
            $selected_rating = $_POST['ratings'] ?? '';
            foreach ($ratings_options as $option):
          ?>
            <option value="<?php echo $option; ?>" <?php echo ($selected_rating === $option) ? 'selected' : ''; ?>><?php echo $option; ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="team_helpful" class="form-label">Was the team helpful? *</label>
        <select id="team_helpful" name="team_helpful" class="form-select" required>
          <option value="" disabled <?php echo empty($_POST['team_helpful']) ? 'selected' : ''; ?>>-- Select Option --</option>
          <?php
            $helpful_options = ['Yes', 'No', 'Somehow'];
            $selected_helpful = $_POST['team_helpful'] ?? '';
            foreach ($helpful_options as $option):
          ?>
            <option value="<?php echo $option; ?>" <?php echo ($selected_helpful === $option) ? 'selected' : ''; ?>><?php echo $option; ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="recommend" class="form-label">Would you recommend us? *</label>
        <select id="recommend" name="recommend" class="form-select" required>
          <option value="" disabled <?php echo empty($_POST['recommend']) ? 'selected' : ''; ?>>-- Select Option --</option>
          <?php
            $recommend_options = ['Yes', 'No', 'Maybe'];
            $selected_recommend = $_POST['recommend'] ?? '';
            foreach ($recommend_options as $option):
          ?>
            <option value="<?php echo $option; ?>" <?php echo ($selected_recommend === $option) ? 'selected' : ''; ?>><?php echo $option; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group" style="flex: 1 1 100%;">
      <label for="suggestions" class="form-label">Suggestions</label>
      <textarea id="suggestions" name="suggestions" class="form-control"><?php echo htmlspecialchars($_POST['suggestions'] ?? ''); ?></textarea>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-primary">Submit Feedback</button>
    </div>
  </form>
<?php endif; ?>
</div>

</body>
</html>
