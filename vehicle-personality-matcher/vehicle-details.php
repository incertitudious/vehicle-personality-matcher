<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'includes/db.php';
require_once 'includes/bike_personality_engine.php';



$type = $_GET['type'] ?? 'car';
$id   = (int)$_GET['id'];

$isSaved = false;

if (isset($_SESSION['user_id'])) {

    $checkFav = $conn->prepare("
        SELECT id FROM saved_vehicles 
        WHERE user_id=? AND vehicle_id=? AND type=?
    ");
    $checkFav->bind_param("iis", $_SESSION['user_id'], $id, $type);
    $checkFav->execute();
    $checkFav->store_result();

    $isSaved = $checkFav->num_rows > 0;
}

/* ===============================
   PRICE RANGE CALCULATOR
================================ */
function calculateBikePriceRange(?int $cc, ?float $hp, ?float $torque): string
{
    $cc     = $cc ?? 0;
    $hp     = $hp ?? 0;
    $torque = $torque ?? 0;

    $score = ($cc * 0.4) + ($hp * 4) + ($torque * 2);

    if ($score < 400) return 'Budget';
    if ($score < 900) return 'Mid';
    return 'Premium';
}

/* ===============================
   INPUT
================================ */
if (!isset($_GET['id'])) {
    die("Vehicle ID missing");
}

$type = $_GET['type'] ?? 'car';
$id   = (int)$_GET['id'];

/* =========================================================
   ===================== BIKE SECTION ======================
   ========================================================= */
if ($type === 'bike' || $type === 'bikes') {

    include 'includes/header.php';
$quizTaken = isset($_SESSION['performance']);
    $stmt = $conn->prepare("
        SELECT *
        FROM bikes
        WHERE id = ?
");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) die("Bike not found");
    $bike = $res->fetch_assoc();
    
    if (empty($bike['price_range'])) {

        $calculated = calculateBikePriceRange(
            (int)$bike['displacement_cc'],
            (float)$bike['power_hp'],
            (float)$bike['torque_nm']
        );

        $update = $conn->prepare("
            UPDATE bikes
            SET price_range = ?
            WHERE id = ?
        ");
        $update->bind_param("si", $calculated, $id);
        $update->execute();

        $bike['price_range'] = $calculated;
    }
    if(
$bike['performance_score'] === NULL ||
$bike['comfort_score'] === NULL ||
$bike['efficiency_score'] === NULL ||
$bike['reliability_score'] === NULL ||
$bike['practicality_score'] === NULL
){

$scores = calculateBikeScores($conn,$bike);

$stmt = $conn->prepare("
UPDATE bikes
SET
performance_score=?,
comfort_score=?,
efficiency_score=?,
reliability_score=?,
practicality_score=?
WHERE id=?
");

$stmt->bind_param(
"iiiiii",
$scores['performance_score'],
$scores['comfort_score'],
$scores['efficiency_score'],
$scores['reliability_score'],
$scores['practicality_score'],
$bike['id']
);

$stmt->execute();

/* reload bike data */

$stmt = $conn->prepare("SELECT * FROM bikes WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();

$res = $stmt->get_result();
$bike = $res->fetch_assoc();

}
    $extraSpecs = json_decode($bike['extra_specs'], true);
    $ninjasSpecs = $extraSpecs['api_ninjas'] ?? [];

    $imgs = $conn->prepare("
        SELECT image_url
        FROM bike_images
        WHERE bike_id = ?
        ORDER BY image_type='main' DESC
        LIMIT 3
    ");
    $imgs->bind_param("i", $id);
    $imgs->execute();
    $imgRes = $imgs->get_result();

    $images = [];
    while ($r = $imgRes->fetch_assoc()) {
        $images[] = $r['image_url'];
    }

    if (empty($images) && $bike['image_url']) {
        $images[] = $bike['image_url'];
    }
    if (empty($images)) {

require_once __DIR__ . '/includes/image_fetcher.php';



$result = fetchBikeImagesSmart($bike['brand'], $bike['model']);
$fetched = $result['images'] ?? [];

$index = 0;

foreach ($fetched as $img) {

$typeImg = ($index === 0) ? 'main' : 'gallery';

$insert = $conn->prepare("
INSERT INTO bike_images (bike_id, image_url, image_type)
VALUES (?, ?, ?)
");

$insert->bind_param("iss", $id, $img, $typeImg);
$insert->execute();

/* also store main image in bikes table */

if ($index === 0) {

$update = $conn->prepare("
UPDATE bikes
SET image_url = ?
WHERE id = ?
");

$update->bind_param("si", $img, $id);
$update->execute();

}

$images[] = $img;

$index++;

}



}
?>
<div class="container vehicle-details">
  <a href="vehicles.php?type=bike">← Back</a>

  <div class="details-top">
    <div class="vehicle-gallery">

<div class="main-frame">

<img
id="mainImage"
src="<?= htmlspecialchars($images[0] ?? 'assets/images/bikes/placeholder.jpg') ?>"
class="main-img"
>

<div class="img-count">
1/<?= count($images) ?>
</div>

</div>


<div class="thumb-row">

    <!-- LEFT: THUMB IMAGES -->
    <div class="thumb-images">
        <?php foreach ($images as $index => $img): ?>

        <img
        src="<?= htmlspecialchars($img) ?>"
        class="thumb-img <?= $index === 0 ? 'active-thumb' : '' ?>"
        data-src="<?= htmlspecialchars($img) ?>"
        >

        <?php endforeach; ?>
    </div>

    <!-- RIGHT: BUTTONS -->
    <div class="action-buttons">

        <?php if(isset($_SESSION['user_id'])): ?>

        <button 
            class="fav-btn <?= $isSaved ? 'active' : '' ?>" 
            data-id="<?= $id ?>"
            data-type="<?= $type ?>"
        >
            ♥
        </button>

        <?php else: ?>

        <a href="login.php" class="fav-login">
            ♥ Login
        </a>

        <?php endif; ?>

        <button class="comment-btn" onclick="openReviews()">
            <svg class="comment-svg" xmlns="http://www.w3.org/2000/svg" fill="none" 
            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">

              <path stroke-linecap="round" stroke-linejoin="round" 
              d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.77 9.77 0 01-4-.8L3 20l1.8-3.6A7.9 7.9 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />

            </svg>
        </button>

    </div>

</div>

</div>


    <div class="basic-info">
  
      <h1><?= htmlspecialchars($bike['brand'].' '.$bike['model']) ?></h1>

      <?php if ($bike['category']): ?>
        <p class="segment"><?= htmlspecialchars($bike['category']) ?></p>
      <?php endif; ?>

      <?php if (!empty($bike['price_range'])): ?>
        <p class="price">PRICE RANGE:<?= htmlspecialchars($bike['price_range']) ?></p>
      <?php endif; ?>
       <div class="attribute-summary">

    <h3>Attribute Summary</h3>

    <?php
$attributes = [
    "Comfort" => $bike['comfort_score'] ?? 0,
    "Performance" => $bike['performance_score'] ?? 0,
    "Reliability" => $bike['reliability_score'] ?? 0,
    "Practicality" => $bike['practicality_score'] ?? 0,
    "Efficiency" => $bike['efficiency_score'] ?? 0
];

foreach ($attributes as $name => $value) {
?>
    
<div class="attr-row">
    <div class="attr-header">
        <span><?= $name ?></span>
        <span><?= $value ?>/100</span>
    </div>

    <div class="attr-bar">
        <div class="attr-fill" style="width: <?= $value ?>%"></div>
    </div>
</div>

<?php } ?>
    </div>
      <a href="quiz.php?type=bike&id=<?= $bike['id'] ?>" class="btn btn-primary full">
        Check Compatibility
      </a>
      
  <?php if($quizTaken){ ?>

<a href="compare_bikes.php?v1=<?= $bike['id'] ?>" class="btn btn-primary full">
Compare
</a>

<?php } else { ?>

<button class="btn btn-primary full" disabled>
Compare (Take Quiz First)
</button>

<?php } ?>
      

    
  </div>


</div>
 <div class="section">
<h2>Specifications</h2>
<div class="spec-highlights">

<div class="spec-box engine">
<div class="spec-value"><?= $bike['displacement_cc'] ?> <span>cc</span></div>
<div class="spec-label">Engine</div>
</div>

<div class="spec-box power">
<div class="spec-value"><?= $bike['power_hp'] ?> <span>HP</span></div>
<div class="spec-label">Power</div>
</div>

<div class="spec-box torque">
<div class="spec-value"><?= $bike['torque_nm'] ?> <span>Nm</span></div>
<div class="spec-label">Torque</div>
</div>

<div class="spec-box weight">
<div class="spec-value"><?= $bike['weight_kg'] ?> <span>kg</span></div>
<div class="spec-label">Weight</div>
</div>

<div class="spec-box speed">
<div class="spec-value"><?= $bike['seat_height_mm'] ?? '-' ?> <span>mm</span></div>
<div class="spec-label">Seat height</div>
</div>

</div>
<div class="spec-card">

<table class="spec-table">

<?php

$hiddenFields = [
'id',
'created_at',
'status',
'api_cached_at',
'image_url',
'extra_specs',
'performance_score',
'comfort_score',
'efficiency_score',
'reliability_score',
'practicality_score',
'price_range'
];

$labelMap = [
'brand' => 'Brand',
'model' => 'Model',
'category' => 'Segment',
'year' => 'Year',
'displacement_cc' => 'Engine Displacement',
'power_hp' => 'Power',
'torque_nm' => 'Torque',
'weight_kg' => 'Weight',
'seat_height_mm' => 'Seat Height'
];

foreach ($bike as $key => $value) {

if ($value === null || $value === '' || in_array($key,$hiddenFields)) continue;

$label = $labelMap[$key] ?? ucwords(str_replace('_',' ',$key));

?>

<tr>
<td><?= htmlspecialchars($label) ?></td>
<td><?= htmlspecialchars($value) ?></td>
</tr>

<?php } ?>

</table>

</div>


<?php if (!empty($ninjasSpecs)): ?>

<div class="section">

<h2>Additional Specifications</h2>

<button class="toggle-specs-btn" onclick="toggleSpecs()">
Show Additional Specs
</button>

<div id="extraSpecs" style="display:none">

<div class="extra-specs-container">

<div class="advanced-specs">

<?php

foreach ($ninjasSpecs as $key => $value) {

if (!$value) continue;

$label = ucwords(str_replace('_',' ',$key));

?>

<div class="adv-spec-row">
    <div class="adv-spec-name">
        <?= htmlspecialchars($label) ?>
    </div>

    <div class="adv-spec-value">
        <?= htmlspecialchars(is_array($value) ? json_encode($value) : $value) ?>
    </div>
</div>

<?php } ?>


</div>

</div>

</div>

<?php endif; ?>

</div>

<script>
document.querySelectorAll(".thumb-img").forEach((thumb)=>{

thumb.addEventListener("click",()=>{

document.getElementById("mainImage").src=thumb.dataset.src;

document.querySelectorAll(".thumb-img")
.forEach(t=>t.classList.remove("active-thumb"));

thumb.classList.add("active-thumb");

});

});

</script>
<script>

function toggleSpecs(){

const box = document.getElementById("extraSpecs");
const btn = document.querySelector(".toggle-specs-btn");

if(box.style.display === "none"){
box.style.display = "block";
btn.innerText = "Hide Additional Specs";
}
else{
box.style.display = "none";
btn.innerText = "Show Additional Specs";
}

}

</script>
<script>
    document.addEventListener("click", () => console.log("CLICK DETECTED"));
document.addEventListener("click", function(e) {

    const btn = e.target.closest(".fav-btn");

    if (btn) {

        console.log("clicked");

        const id = btn.dataset.id;
        const type = btn.dataset.type;

        fetch("/vehicle-personality-matcher/save-vehicle.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: "vehicle_id=" + id + "&type=" + type
        })
        .then(res => res.text())
        .then(data => {

            console.log("response:", data);

            if (data === "added") {
                btn.classList.add("active");
            } 
            else if (data === "removed") {
                btn.classList.remove("active");
            } 
            else if (data === "login") {
                alert("Login first");
            }

        })
        .catch(err => console.error(err));

    }

});
</script>
<div id="reviewOverlay" class="review-overlay">

    <div class="review-box">

        <div class="review-header">
            <h3>Reviews</h3>
            <span onclick="closeReviews()">✖</span>
        </div>

        <!-- ADD REVIEW -->
        <textarea id="reviewInput" placeholder="Write a review..."></textarea>
        <button onclick="submitReview()">Post</button>

        <!-- REVIEWS -->
        <div id="reviewList"></div>

    </div>

</div>
<script>

function openReviews(){
    document.getElementById("reviewOverlay").style.display = "flex";
    loadReviews();
}

function closeReviews(){
    document.getElementById("reviewOverlay").style.display = "none";
}

</script>
<script>

function loadReviews(){

    fetch(`/vehicle-personality-matcher/user/actions/get-reviews.php?vehicle_id=<?= $id ?>&type=<?= $type ?>`)
    .then(res => res.text())
    .then(html => {
        document.getElementById("reviewList").innerHTML = html;
    });

}

</script>
<script>

function submitReview(){

    const text = document.getElementById("reviewInput").value.trim();

    if(!text){
        alert("Write something bro");
        return;
    }

    const data = new URLSearchParams();
    data.append("content", text);
    data.append("vehicle_id", <?= $id ?>);
    data.append("type", "<?= $type ?>");

    fetch("/vehicle-personality-matcher/user/actions/add-review.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: data.toString()
    })
    .then(res => res.text())
    .then(res => {
        if(res.trim() === "success"){
            document.getElementById("reviewInput").value = "";
            loadReviews();
        } else {
            console.log(res);
        }
    });

}

</script>
<script>

/* ===== REPLY BOX ===== */
function reply(id){

    const box = document.getElementById("replyBox"+id);

    box.innerHTML = `
        <textarea id="replyText${id}" placeholder="Write reply..." style="width:100%;margin-top:8px;"></textarea>
        <button onclick="submitReply(${id})">Send</button>
    `;
}

/* ===== SUBMIT REPLY ===== */
function submitReply(parentId){

    const text = document.getElementById("replyText"+parentId).value.trim();

    if(!text){
        alert("Write something");
        return;
    }

    const data = new URLSearchParams();
    data.append("content", text);
    data.append("parent_id", parentId);
    data.append("vehicle_id", <?= $id ?>);
    data.append("type", "<?= $type ?>");

    fetch("/vehicle-personality-matcher/user/actions/add-review.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: data.toString()
    })
    .then(res => res.text())
    .then(res => {
        if(res.trim() === "success"){
            loadReviews();
        } else {
            console.log(res);
        }
    });

}

</script>
<script>

/* OPEN MODAL */
function openReviews(){
    document.getElementById("reviewOverlay").style.display = "flex";
    loadReviews();
}

/* CLOSE */
function closeReviews(){
    document.getElementById("reviewOverlay").style.display = "none";
}

/* LOAD REVIEWS */
function loadReviews(){
    fetch(`/vehicle-personality-matcher/user/actions/get-reviews.php?vehicle_id=<?= $id ?>&type=<?= $type ?>`)
    .then(res => res.text())
    .then(html => {
        document.getElementById("reviewList").innerHTML = html;
    });
}

/* REPLY BOX */
function reply(parentId, username){

    const box = document.getElementById("replyBox"+parentId);

    box.innerHTML = `
        <textarea id="replyText${parentId}" style="width:100%;margin-top:8px;">@${username} </textarea>
        <button onclick="submitReply(${parentId})">Send</button>
    `;
}

/* ADD COMMENT */
function submitReview(){

    const text = document.getElementById("reviewInput").value.trim();

    if(!text){
        alert("Write something");
        return;
    }

    const data = new URLSearchParams();
    data.append("content", text);
    data.append("vehicle_id", <?= $id ?>);
    data.append("type", "<?= $type ?>");

    fetch("/vehicle-personality-matcher/user/actions/add-review.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: data.toString()
    })
    .then(res => res.text())
    .then(res => {
        if(res.trim() === "success"){
            document.getElementById("reviewInput").value = "";
            loadReviews();
        }
    });
}

/* ADD REPLY */
function submitReply(parentId){

    const text = document.getElementById("replyText"+parentId).value.trim();

    if(!text){
        alert("Write something");
        return;
    }

    const data = new URLSearchParams();
    data.append("content", text);
    data.append("parent_id", parentId);
    data.append("vehicle_id", <?= $id ?>);
    data.append("type", "<?= $type ?>");

    fetch("/vehicle-personality-matcher/user/actions/add-review.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: data.toString()
    })
    .then(res => res.text())
    .then(res => {
        if(res.trim() === "success"){
            loadReviews();
        }
    });
}

</script>
<script>

function toggleReplies(id){

    const box = document.getElementById("replies"+id);
    const btn = event.target;

    if(box.style.display === "none"){
        box.style.display = "block";
        btn.innerText = "Hide replies";
    } else {
        box.style.display = "none";
        btn.innerText = btn.innerText.replace("Hide replies", "View replies");
    }

}

</script>
<?php
include 'includes/footer.php';


} else {

/* ================= CAR SECTION ================= */


include 'includes/header.php';

$quizTaken = isset($_SESSION['performance']);
/* ===============================
   CAR BUDGET CALCULATOR
================================ */
function calculateCarBudgetRange(array $car): string
{
    $score = 0;

    if (!empty($car['acc_0_60'])) {
        $score += (10 - min($car['acc_0_60'], 10)) * 8;
    }

    if (!empty($car['quarter_mile'])) {
        $score += (20 - min($car['quarter_mile'], 20)) * 3;
    }

    if (!empty($car['braking_distance'])) {
        $score += (200 - min($car['braking_distance'], 200)) * 0.5;
    }

    if (!empty($car['size_class'])) {
        if ($car['size_class'] === 'Large') $score += 80;
        if ($car['size_class'] === 'Midsize') $score += 50;
        if ($car['size_class'] === 'Small') $score += 20;
    }

    if (!empty($car['weight_kg'])) {
        $score += min($car['weight_kg'], 3000) * 0.02;
    }

    if ($score < 150) return 'Budget';
    if ($score < 350) return 'Mid';
    return 'Premium';
}

$stmt = $conn->prepare("SELECT * FROM vehicle WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) die("Vehicle not found");
$vehicle = $result->fetch_assoc();
/* ===============================
   AUTO INSERT scores IF EMPTY
================================ */
require_once __DIR__ . '/includes/personality_engine.php';

if (
    $vehicle['performance_score'] === null ||
    $vehicle['comfort_score'] === null ||
    $vehicle['efficiency_score'] === null ||
    $vehicle['reliability_score'] === null ||
    $vehicle['practicality_score'] === null
) {

    updateVehiclePersonality($conn, $vehicle['id']);

    // Refetch updated row
    $stmt = $conn->prepare("SELECT * FROM vehicle WHERE id = ?");
    $stmt->bind_param("i", $vehicle['id']);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();
}
/* ===============================
   AUTO INSERT BUDGET IF EMPTY
================================ */
if (empty($vehicle['budget_range'])) {

    $calculated = calculateCarBudgetRange($vehicle);

    $update = $conn->prepare("
        UPDATE vehicle
        SET budget_range = ?
        WHERE id = ?
    ");
    $update->bind_param("si", $calculated, $id);
    $update->execute();

    $vehicle['budget_range'] = $calculated;
}

/* ===============================
   FETCH IMAGES
================================ */
$imgStmt = $conn->prepare("
    SELECT image_path 
    FROM vehicle_images 
    WHERE vehicle_id = ?
    ORDER BY created_at ASC
    LIMIT 3
");
$imgStmt->bind_param("i", $id);
$imgStmt->execute();
$imgRes = $imgStmt->get_result();

$images = [];
while ($row = $imgRes->fetch_assoc()) {
    $images[] = $row['image_path'];
}

if (empty($images)) {

    require_once __DIR__ . '/includes/image_fetcher.php';

    if (function_exists('fetchCarImages')) {

        $fetched = fetchCarImages($vehicle['make'], $vehicle['model']);

        foreach ($fetched as $img) {

            $insert = $conn->prepare("
                INSERT INTO vehicle_images (vehicle_id, image_path)
                VALUES (?, ?)
            ");
            $insert->bind_param("is", $id, $img);
            $insert->execute();

            $images[] = $img;
        }
    }
}
?>

<div class="container vehicle-details">
  <a href="vehicles.php?type=car">← Back</a>

  <div class="details-top">
    <div class="vehicle-gallery">

<div class="main-frame">

<img
id="mainImage"
src="<?= htmlspecialchars($images[0] ?? 'assets/images/cars/placeholder.jpg') ?>"
class="main-img"
>

<div class="img-count">
1/<?= count($images) ?>
</div>

</div>


<div class="thumb-row">

    <!-- LEFT: IMAGES -->
    <div class="thumb-images">
        <?php foreach ($images as $index => $img): ?>

        <img
        src="<?= htmlspecialchars($img) ?>"
        class="thumb-img <?= $index === 0 ? 'active-thumb' : '' ?>"
        data-src="<?= htmlspecialchars($img) ?>"
        >

        <?php endforeach; ?>
    </div>

    <!-- RIGHT: BUTTONS -->
    <div class="action-buttons">

        <?php if(isset($_SESSION['user_id'])): ?>

        <button 
            class="fav-btn <?= $isSaved ? 'active' : '' ?>" 
            data-id="<?= $id ?>"
            data-type="<?= $type ?>"
        >
            ♥
        </button>

        <button class="comment-btn" onclick="openReviews()">
            <img src="/vehicle-personality-matcher/assets/images/comment-2-svgrepo-com.svg" class="comment-svg">
        </button>

        <?php else: ?>

        <a href="login.php" class="fav-login">
            ♥ Login
        </a>

        <?php endif; ?>

    </div>

</div>
        </div>

    <div class="basic-info">
      <h1><?= htmlspecialchars($vehicle['make'].' '.$vehicle['model']) ?></h1>

      <?php if (!empty($vehicle['body_type'])): ?>
        <p class="segment"><?= htmlspecialchars($vehicle['body_type']) ?></p>
      <?php endif; ?>

      <?php if (!empty($vehicle['budget_range'])): ?>
        <p class="price">PRICE RANGE:<?= htmlspecialchars($vehicle['budget_range']) ?></p>
      <?php endif; ?>

      
      
      <h3>Attribute Summary</h3>

<?php
$attributes = [
    "Comfort" => $vehicle['comfort_score'] ?? 0,
    "Performance" => $vehicle['performance_score'] ?? 0,
    "Reliability" => $vehicle['reliability_score'] ?? 0,
    "Practicality" => $vehicle['practicality_score'] ?? 0,
    "Efficiency" => $vehicle['efficiency_score'] ?? 0
];

foreach ($attributes as $name => $value) {
?>

<div class="attr-row">
    <div class="attr-header">
        <span><?= $name ?></span>
        <span><?= $value ?>/100</span>
    </div>

    <div class="attr-bar">
        <div class="attr-fill" style="width: <?= $value ?>%"></div>
    </div>
</div>

<?php } ?>
<a href="quiz.php?type=car&id=<?= $vehicle['id'] ?>" class="btn btn-primary full">
        Check Compatibility
      </a>
  <?php if($quizTaken){ ?>

<a href="compare.php?v1=<?= $vehicle['id'] ?>" class="btn btn-primary full">
Compare Vehicle
</a>

<?php } else { ?>

<button class="btn btn-primary full" disabled>
Compare (Take Quiz First)
</button>

<?php } ?>
    </div>
    
  </div>

  <div class="section">
    <div class="spec-highlights">

<div class="spec-box fuel">
<div class="spec-value"><?= $vehicle['city_mpg'] ?> <span>MPG</span></div>
<div class="spec-label">City Mileage</div>
</div>

<div class="spec-box highway">
<div class="spec-value"><?= $vehicle['highway_mpg'] ?> <span>MPG</span></div>
<div class="spec-label">Highway Mileage</div>
</div>

<div class="spec-box seats">
<div class="spec-value"><?= $vehicle['seating_capacity'] ?></div>
<div class="spec-label">Seats</div>
</div>

<div class="spec-box drive">
<div class="spec-value"><?= $vehicle['drive_type'] ?></div>
<div class="spec-label">Drive Type</div>
</div>

<div class="spec-box fuel">
<div class="spec-value"><?= $vehicle['fuel_capacity'] ?> <span>L</span></div>
<div class="spec-label">Fuel Tank</div>
</div>

<div class="spec-box weight">
<div class="spec-value"><?= $vehicle['weight_kg'] ?> <span>kg</span></div>
<div class="spec-label">Weight</div>
</div>

</div>
    <h2>Specifications</h2>
    <table class="spec-table">
      <?php
        $hiddenFields = [
            'id',
            'created_at',
            'data_source',
            'status',
            'performance_score',
            'comfort_score',
            'efficiency_score',
            'reliability_score',
            'practicality_score',
            'budget_range'
        ];

        $labelMap = [
            'make' => 'Brand',
            'model' => 'Model',
            'body_type' => 'Body Type',
            'price_min' => 'Minimum Price',
            'price_max' => 'Maximum Price',
            'city_mpg' => 'City Mileage (MPG)',
            'highway_mpg' => 'Highway Mileage (MPG)',
            'seating_capacity' => 'Seating Capacity',
            'drive_type' => 'Drive Type',
            'acc_0_30' => '0–30 mph',
            'acc_0_60' => '0–60 mph',
            'quarter_mile' => 'Quarter Mile Time',
            'braking_distance' => 'Braking Distance',
            'fuel_capacity' => 'Fuel Capacity',
            'length_mm' => 'Length (mm)',
            'width_mm' => 'Width (mm)',
            'height_mm' => 'Height (mm)',
            'wheelbase_mm' => 'Wheelbase (mm)',
            'u_turn_ft' => 'U-Turn Radius (ft)',
            'weight_kg' => 'Weight (kg)',
            'size_class' => 'Size Class'
        ];

        foreach ($vehicle as $key => $value):

            if ($value === null || $value === '' || in_array($key, $hiddenFields)) continue;

            $label = $labelMap[$key] ?? ucwords(str_replace('_',' ', $key));
      ?>
        <tr>
          <td><?= htmlspecialchars($label) ?></td>
          <td><?= htmlspecialchars($value) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<script>
document.querySelectorAll(".thumb-img").forEach((thumb,i)=>{

thumb.addEventListener("click",()=>{

document.getElementById("mainImage").src=thumb.dataset.src;

document.querySelectorAll(".thumb-img")
.forEach(t=>t.classList.remove("active-thumb"));

thumb.classList.add("active-thumb");

document.querySelector(".img-count").innerText =
(i+1) + "/" + document.querySelectorAll(".thumb-img").length;

});

});
</script>
<script>
    document.addEventListener("click", () => console.log("CLICK DETECTED"));
document.addEventListener("click", function(e) {

    const btn = e.target.closest(".fav-btn");

    if (btn) {

        console.log("clicked");

        const id = btn.dataset.id;
        const type = btn.dataset.type;

        fetch("/vehicle-personality-matcher/save-vehicle.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: "vehicle_id=" + id + "&type=" + type
        })
        .then(res => res.text())
        .then(data => {

            console.log("response:", data);

            if (data === "added") {
                btn.classList.add("active");
            } 
            else if (data === "removed") {
                btn.classList.remove("active");
            } 
            else if (data === "login") {
                alert("Login first");
            }

        })
        .catch(err => console.error(err));

    }

});
</script>
<div id="reviewOverlay" class="review-overlay">

    <div class="review-box">

        <div class="review-header">
            <h3>Reviews</h3>
            <span onclick="closeReviews()">✖</span>
        </div>

        <!-- ADD REVIEW -->
        <textarea id="reviewInput" placeholder="Write a review..."></textarea>
        <button onclick="submitReview()">Post</button>

        <!-- REVIEWS -->
        <div id="reviewList"></div>

    </div>

</div>
<script>

function openReviews(){
    document.getElementById("reviewOverlay").style.display = "flex";
    loadReviews();
}

function closeReviews(){
    document.getElementById("reviewOverlay").style.display = "none";
}

</script>
<script>

function loadReviews(){

    fetch(`/vehicle-personality-matcher/user/actions/get-reviews.php?vehicle_id=<?= $id ?>&type=<?= $type ?>`)
    .then(res => res.text())
    .then(html => {
        document.getElementById("reviewList").innerHTML = html;
    });

}

</script>
<script>

function submitReview(){

    const text = document.getElementById("reviewInput").value.trim();

    if(!text){
        alert("Write something bro");
        return;
    }

    const data = new URLSearchParams();
    data.append("content", text);
    data.append("vehicle_id", <?= $id ?>);
    data.append("type", "<?= $type ?>");

    fetch("/vehicle-personality-matcher/user/actions/add-review.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: data.toString()
    })
    .then(res => res.text())
    .then(res => {
        if(res.trim() === "success"){
            document.getElementById("reviewInput").value = "";
            loadReviews();
        } else {
            console.log(res);
        }
    });

}

</script>
<script>

/* ===== REPLY BOX ===== */
function reply(id){

    const box = document.getElementById("replyBox"+id);

    box.innerHTML = `
        <textarea id="replyText${id}" placeholder="Write reply..." style="width:100%;margin-top:8px;"></textarea>
        <button onclick="submitReply(${id})">Send</button>
    `;
}

/* ===== SUBMIT REPLY ===== */
function submitReply(parentId){

    const text = document.getElementById("replyText"+parentId).value.trim();

    if(!text){
        alert("Write something");
        return;
    }

    const data = new URLSearchParams();
    data.append("content", text);
    data.append("parent_id", parentId);
    data.append("vehicle_id", <?= $id ?>);
    data.append("type", "<?= $type ?>");

    fetch("/vehicle-personality-matcher/user/actions/add-review.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: data.toString()
    })
    .then(res => res.text())
    .then(res => {
        if(res.trim() === "success"){
            loadReviews();
        } else {
            console.log(res);
        }
    });

}

</script>
<script>

/* OPEN MODAL */
function openReviews(){
    document.getElementById("reviewOverlay").style.display = "flex";
    loadReviews();
}

/* CLOSE */
function closeReviews(){
    document.getElementById("reviewOverlay").style.display = "none";
}

/* LOAD REVIEWS */
function loadReviews(){
    fetch(`/vehicle-personality-matcher/user/actions/get-reviews.php?vehicle_id=<?= $id ?>&type=<?= $type ?>`)
    .then(res => res.text())
    .then(html => {
        document.getElementById("reviewList").innerHTML = html;
    });
}

/* REPLY BOX */
function reply(parentId, username){

    const box = document.getElementById("replyBox"+parentId);

    box.innerHTML = `
        <textarea id="replyText${parentId}" style="width:100%;margin-top:8px;">@${username} </textarea>
        <button onclick="submitReply(${parentId})">Send</button>
    `;
}

/* ADD COMMENT */
function submitReview(){

    const text = document.getElementById("reviewInput").value.trim();

    if(!text){
        alert("Write something");
        return;
    }

    const data = new URLSearchParams();
    data.append("content", text);
    data.append("vehicle_id", <?= $id ?>);
    data.append("type", "<?= $type ?>");

    fetch("/vehicle-personality-matcher/user/actions/add-review.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: data.toString()
    })
    .then(res => res.text())
    .then(res => {
        if(res.trim() === "success"){
            document.getElementById("reviewInput").value = "";
            loadReviews();
        }
    });
}

/* ADD REPLY */
function submitReply(parentId){

    const text = document.getElementById("replyText"+parentId).value.trim();

    if(!text){
        alert("Write something");
        return;
    }

    const data = new URLSearchParams();
    data.append("content", text);
    data.append("parent_id", parentId);
    data.append("vehicle_id", <?= $id ?>);
    data.append("type", "<?= $type ?>");

    fetch("/vehicle-personality-matcher/user/actions/add-review.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: data.toString()
    })
    .then(res => res.text())
    .then(res => {
        if(res.trim() === "success"){
            loadReviews();
        }
    });
}

</script>
<script>

function toggleReplies(id){

    const box = document.getElementById("replies"+id);
    const btn = event.target;

    if(box.style.display === "none"){
        box.style.display = "block";
        btn.innerText = "Hide replies";
    } else {
        box.style.display = "none";
        btn.innerText = btn.innerText.replace("Hide replies", "View replies");
    }

}

</script>
        <?php include 'includes/footer.php'; ?>

<?php } ?>
    