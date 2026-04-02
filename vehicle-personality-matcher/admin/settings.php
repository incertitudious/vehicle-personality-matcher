<?php
session_start();

require 'includes/auth.php';
require 'includes/db.php';
require 'includes/header.php';

/* =============================
   CHECK SESSION
============================= */

if(!isset($_SESSION['user_id'])){
die("Session expired. Please login again.");
}

$userId = (int)$_SESSION['user_id'];

/* =====================================
   UPDATE ADMIN PROFILE
===================================== */

if(isset($_POST['update_profile'])){

$name = $conn->real_escape_string($_POST['name']);
$email = $conn->real_escape_string($_POST['email']);

$conn->query("
UPDATE users
SET name='$name', email='$email'
WHERE user_id=$userId
");

$message = "Profile updated";

}

/* =====================================
   UPDATE PASSWORD
===================================== */

if(isset($_POST['update_password'])){

if(!empty($_POST['password'])){

$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

$conn->query("
UPDATE users
SET password='$password'
WHERE user_id=$userId
");

$message = "Password updated";

}

}

/* =====================================
   UPDATE SYSTEM SETTINGS
===================================== */

if(isset($_POST['update_settings'])){

if(isset($_POST['settings'])){

foreach($_POST['settings'] as $key=>$value){

$key = $conn->real_escape_string($key);
$value = $conn->real_escape_string($value);

$conn->query("
INSERT INTO system_settings (setting_key,setting_value)
VALUES ('$key','$value')
ON DUPLICATE KEY UPDATE setting_value='$value'
");

}

$message = "Settings saved";

}

}

/* =====================================
   SYSTEM TOOLS
===================================== */

if(isset($_POST['reset_scores'])){

$conn->query("
UPDATE vehicle
SET
performance_score=NULL,
comfort_score=NULL,
efficiency_score=NULL,
reliability_score=NULL,
practicality_score=NULL
");

$conn->query("
UPDATE bikes
SET
performance_score=NULL,
comfort_score=NULL,
efficiency_score=NULL,
reliability_score=NULL,
practicality_score=NULL
");

$message = "Scores reset";

}

/* =====================================
   FETCH SETTINGS
===================================== */

$settings = [];

$result = $conn->query("SELECT * FROM system_settings");

if($result){
while($row = $result->fetch_assoc()){
$settings[$row['setting_key']] = $row['setting_value'];
}
}

/* =====================================
   FETCH ADMIN DATA
===================================== */

$admin = $conn->query("
SELECT name,email
FROM users
WHERE user_id=$userId
")->fetch_assoc();

?>

<div class="admin-wrapper">

<?php require 'includes/sidebar.php'; ?>

<div class="main-area">

<?php
$pageTitle = "Settings";
require 'includes/topbar.php';
?>

<div class="content-area">

<?php if(!empty($message)): ?>

<div class="alert-success">
<?php echo $message; ?>
</div>

<?php endif; ?>

<!-- ADMIN PROFILE -->

<div class="card">

<h3>Admin Profile</h3>

<form method="POST">

<div class="vehicle-admin-grid-2">

<div class="vehicle-admin-field">
<label>Name</label>
<input type="text" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>">
</div>

<div class="vehicle-admin-field">
<label>Email</label>
<input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>">
</div>

</div>

<br>

<button class="btn-primary" name="update_profile">
Save Profile
</button>

</form>

</div>



<!-- PASSWORD -->

<div class="card">

<h3>Change Password</h3>

<form method="POST">

<div class="vehicle-admin-field">
<label>New Password</label>
<input type="password" name="password">
</div>

<br>

<button class="btn-primary" name="update_password">
Update Password
</button>

</form>

</div>



<!-- PERSONALITY ENGINE -->

<div class="card">

<h3>Personality Engine Weights</h3>

<form method="POST">

<div class="vehicle-admin-grid-2">

<div class="vehicle-admin-field">
<label>Performance Weight</label>
<input type="number" step="0.1" name="settings[performance_weight]"
value="<?php echo $settings['performance_weight'] ?? 0.6; ?>">
</div>

<div class="vehicle-admin-field">
<label>Comfort Weight</label>
<input type="number" step="0.1" name="settings[comfort_weight]"
value="<?php echo $settings['comfort_weight'] ?? 0.6; ?>">
</div>

<div class="vehicle-admin-field">
<label>Efficiency Weight</label>
<input type="number" step="0.1" name="settings[efficiency_weight]"
value="<?php echo $settings['efficiency_weight'] ?? 0.6; ?>">
</div>

<div class="vehicle-admin-field">
<label>Reliability Weight</label>
<input type="number" step="0.1" name="settings[reliability_weight]"
value="<?php echo $settings['reliability_weight'] ?? 0.6; ?>">
</div>

<div class="vehicle-admin-field">
<label>Practicality Weight</label>
<input type="number" step="0.1" name="settings[practicality_weight]"
value="<?php echo $settings['practicality_weight'] ?? 0.6; ?>">
</div>

</div>

<br>

<button class="btn-primary" name="update_settings">
Save Engine Settings
</button>

</form>

</div>



<!-- WEBSITE CONFIG -->

<div class="card">

<h3>Website Settings</h3>

<form method="POST">

<div class="vehicle-admin-grid-2">

<div class="vehicle-admin-field">
<label>Site Name</label>
<input type="text" name="settings[site_name]"
value="<?php echo $settings['site_name'] ?? 'Vehicle Personality Matcher'; ?>">
</div>

<div class="vehicle-admin-field">
<label>Contact Email</label>
<input type="email" name="settings[contact_email]"
value="<?php echo $settings['contact_email'] ?? ''; ?>">
</div>

<div class="vehicle-admin-field vehicle-admin-full">
<label>Site Description</label>
<textarea name="settings[site_description]"><?php echo $settings['site_description'] ?? ''; ?></textarea>
</div>

</div>

<br>

<button class="btn-primary" name="update_settings">
Save Website Settings
</button>

</form>

</div>



<!-- RECOMMENDATION SETTINGS -->

<div class="card">

<h3>Recommendation Settings</h3>

<form method="POST">

<div class="vehicle-admin-grid-2">

<div class="vehicle-admin-field">
<label>Minimum Match Score</label>
<input type="number" name="settings[min_match_score]"
value="<?php echo $settings['min_match_score'] ?? 60; ?>">
</div>

<div class="vehicle-admin-field">
<label>Vehicles To Show</label>
<input type="number" name="settings[recommendation_limit]"
value="<?php echo $settings['recommendation_limit'] ?? 5; ?>">
</div>

<div class="vehicle-admin-field">
<label>Maintenance Mode</label>

<select name="settings[maintenance_mode]">

<option value="0"
<?php if(($settings['maintenance_mode'] ?? 0)==0) echo "selected"; ?>>
OFF
</option>

<option value="1"
<?php if(($settings['maintenance_mode'] ?? 0)==1) echo "selected"; ?>>
ON
</option>

</select>

</div>

</div>

<br>

<button class="btn-primary" name="update_settings">
Save Recommendation Settings
</button>

</form>

</div>



<!-- SYSTEM TOOLS -->

<div class="card">

<h3>System Tools</h3>

<form method="POST">

<button class="btn-primary" name="reset_scores" style="background:#ef4444;">
Reset Vehicle Scores
</button>

</form>

</div>


</div>
</div>
</div>

<?php require 'includes/footer.php'; ?>