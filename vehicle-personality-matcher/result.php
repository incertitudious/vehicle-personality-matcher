<?php
session_start();
ini_set('display_errors',1);
error_reporting(E_ALL);

include 'includes/db.php';
include 'includes/header.php';

/* ===============================
   RECEIVE QUIZ DATA
================================ */

if(!isset($_GET['show'])){

$type = $_POST['vehicle_type'] ?? '';
$id   = (int)($_POST['vehicle_id'] ?? 0);

if(!$type || !$id){
    die("Invalid request");
}

/* ===============================
   USER PERSONALITY VECTOR
================================ */

/* ===============================
   ROAD TYPE NORMALIZATION
================================ */

$roadScore = match((int)($_POST['road_type'] ?? 3)){
    1 => 40,
    2 => 60,
    3 => 75,
    4 => 90,
    default => 70
};

/* ===============================
   USER PERSONALITY VECTOR (FIXED)
================================ */

$user = [

'performance' => (
    (int)$_POST['performance'] * 0.7 +
    (int)$_POST['usage'] * 0.3
),

'comfort' => (
    (int)$_POST['comfort'] * 0.7 +
    $roadScore * 0.3
),

'efficiency' => (
    (int)$_POST['mileage'] * 0.6 +
    (int)$_POST['usage'] * 0.4
),

'practicality' => (
    (int)$_POST['practicality'] * 0.5 +
    (int)$_POST['passengers'] * 0.3 +
    $roadScore * 0.2
),

'reliability' => (
    (int)$_POST['maintenance'] * 0.4 +
    (int)$_POST['ownership'] * 0.3 +
    (int)$_POST['cost_sensitivity'] * 0.3
)

];

$_SESSION['performance']  = $user['performance'];
$_SESSION['comfort']      = $user['comfort'];
$_SESSION['efficiency']   = $user['efficiency'];
$_SESSION['reliability']  = $user['reliability'];
$_SESSION['practicality'] = $user['practicality'];

$_SESSION['quiz_vehicle_type'] = $type;
$_SESSION['quiz_vehicle_id']   = $id;

/* redirect once */



}

$type = $_SESSION['quiz_vehicle_type'] ?? '';
$id   = $_SESSION['quiz_vehicle_id'] ?? 0;

$user = [
'performance'  => $_SESSION['performance'],
'comfort'      => $_SESSION['comfort'],
'efficiency'   => $_SESSION['efficiency'],
'reliability'  => $_SESSION['reliability'],
'practicality' => $_SESSION['practicality']
];
/* ===============================
   FETCH VEHICLE
================================ */

if($type === 'bike'){

$stmt = $conn->prepare("SELECT * FROM bikes WHERE id=?");
$stmt->bind_param("i",$id);

}else{

$stmt = $conn->prepare("SELECT * FROM vehicle WHERE id=?");
$stmt->bind_param("i",$id);

}

$stmt->execute();
$res = $stmt->get_result();

if($res->num_rows === 0){
die("Vehicle not found");
}

$vehicle = $res->fetch_assoc();

/* ===============================
   REAL TIME MAINTENANCE SCORE
================================ */

function calculateMaintenance($vehicle,$type){

if($type === 'bike'){

$cc = $vehicle['displacement_cc'] ?? 150;
$hp = $vehicle['power_hp'] ?? 10;
$weight = $vehicle['weight_kg'] ?? 150;

$score = 100 - (
($cc * 0.03) +
($hp * 0.8) +
($weight * 0.05)
);

}else{

$mpg = $vehicle['city_mpg'] ?? 25;
$weight = $vehicle['weight_kg'] ?? 1500;

$score = ($mpg * 2) - ($weight * 0.01);

}

return max(20,min(100,round($score)));

}

$maintenanceScore = calculateMaintenance($vehicle,$type);

/* ===============================
   VEHICLE PERSONALITY VECTOR
================================ */

$vehicleVector = [

'performance'  => $vehicle['performance_score'] ?? 50,
'comfort'      => $vehicle['comfort_score'] ?? 50,
'efficiency'   => $vehicle['efficiency_score'] ?? 50,
'practicality' => $vehicle['practicality_score'] ?? 50,

'reliability'  => (
    ($vehicle['reliability_score'] ?? 50) +
    $maintenanceScore
)/2

];

/* ===============================
   DISTANCE SCORE
================================ */

$distance = 0;

foreach($user as $key=>$value){

    $diff = abs($value - $vehicleVector[$key]);

    if ($diff > 40) {
    $distance += $diff * 1.3;   // strong penalty
}
elseif ($diff > 20) {
    $distance += $diff * 1.15;  // 🔥 NEW: medium penalty
}
else {
    $distance += $diff;
}
}

// penalties
if (abs($user['performance'] - $vehicleVector['performance']) > 40) {
    $distance += 60;
}

if (abs($user['reliability'] - $vehicleVector['reliability']) > 40) {
    $distance += 50;
}

$maxDistance = count($user) * 180;
$maxDistance = count($user) * 180;

$distanceScore = 100 - (($distance / $maxDistance) * 100);

/* ===============================
   COSINE SIMILARITY
================================ */

function cosineSimilarity($A,$B){

$dot = 0;
$magA = 0;
$magB = 0;

foreach($A as $key=>$value){

$dot  += $A[$key] * $B[$key];
$magA += pow($A[$key],2);
$magB += pow($B[$key],2);

}

return $dot / (sqrt($magA) * sqrt($magB));

}


/* ===============================
   FINAL COMPATIBILITY
================================ */

$compatibility = round($distanceScore);

// 🔥 HARD MISMATCH PENALTIES

// performance mismatch (user wants low, bike is high)
if($user['performance'] < 50 && $vehicleVector['performance'] > 80){
    $compatibility -= 25;
}

// practicality mismatch
if($user['practicality'] > 80 && $vehicleVector['practicality'] < 50){
    $compatibility -= 20;
}

// efficiency mismatch
if($user['efficiency'] > 80 && $vehicleVector['efficiency'] < 50){
    $compatibility -= 15;
}

// 🔥 FINAL CLAMP (VERY IMPORTANT)
$compatibility = max(0, min(100, $compatibility));



// 🔥 extra clamp for unrealistic matches
if ($distance > 400) {
    $compatibility -= 10;
}
/* ===============================
   SAVE QUIZ RESULT
================================ */


/* VEHICLE NAME FIRST */
$name = ($type === 'bike')
? $vehicle['brand']." ".$vehicle['model']
: $vehicle['make']." ".$vehicle['model'];

/* SAVE RESULT */
if(!isset($_GET['show'])){

    // ... your calculations

    $user_id = $_SESSION['user_id'] ?? null;

    if($user_id){

        $name = ($type === 'bike')
        ? $vehicle['brand']." ".$vehicle['model']
        : $vehicle['make']." ".$vehicle['model'];

        $stmt = $conn->prepare("
            INSERT INTO quiz_results (user_id, personality, vehicle_name, match_score)
            VALUES (?, ?, ?, ?)
        ");

        $personality = json_encode($user);
        $stmt->bind_param("issi", $user_id, $personality, $name, $compatibility);
        $stmt->execute();
    }

    header("Location: result.php?show=1");
    exit;
}

/* ===============================
   REGRET PREDICTION
================================ */

if($compatibility > 80){
$regret = "Low";
}
elseif($compatibility > 60){
$regret = "Moderate";
}
else{
$regret = "High";
}

/* ===============================
   VEHICLE NAME
================================ */

$name = ($type === 'bike')
? $vehicle['brand']." ".$vehicle['model']
: $vehicle['make']." ".$vehicle['model'];

?>

<div class="quiz-wrapper">

<h2 class="quiz-title">Compatibility Results</h2>
<p class="quiz-sub">For: <?= htmlspecialchars($name) ?></p>

<div class="result-card">

<div class="result-icon">✔</div>

<div class="score">
<?= $compatibility ?>
</div>

<p class="score-label">Compatibility Score</p>

<div class="progress-bar">
<div class="progress-fill" style="width:<?= $compatibility ?>%"></div>
</div>

<p class="regret">
Regret Prediction:
<span class="tag"><?= $regret ?></span>
</p>

</div>


<div class="result-box success">

<h3>What Matches Your Profile</h3>

<ul>

<?php

foreach($user as $key=>$value){

$vehicleValue = $vehicleVector[$key];

if(abs($value - $vehicleValue) <= 20){

echo "<li>".ucfirst($key)." matches your preference</li>";

}

}

?>

</ul>

</div>


<div class="result-box warning">

<h3>Potential Concerns</h3>

<ul>


<?php
$concerns = false;

foreach($user as $key=>$value){

$vehicleValue = $vehicleVector[$key];

if(abs($value - $vehicleValue) > 30){

$concerns = true;

echo "<li>".ucfirst($key)." may not fully match your expectation</li>";

}

}

if(!$concerns){
echo "<li>No major concerns detected.</li>";
}
?>
</ul>

</div>


<div class="result-actions">

<a href="vehicle-details.php?type=<?= $type ?>&id=<?= $id ?>" class="btn blue">
View Vehicle Details
</a>

<a href="vehicles.php?type=<?= $type ?>&similar=<?= $id ?>" class="btn green">
View Similar Vehicles
</a>

<a href="quiz.php?type=<?= $type ?>&id=<?= $id ?>" class="btn grey">
Retake Quiz
</a>

</div>

</div>

<?php include 'includes/footer.php'; ?>