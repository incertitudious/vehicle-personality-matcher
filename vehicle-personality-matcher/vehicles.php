<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'includes/header.php';
include 'includes/db.php';

$type = $_GET['type'] ?? 'car';
/* ===============================
   SIMILAR VEHICLES MODE
================================ */

$similar = $_GET['similar'] ?? null;

if ($similar) {

if ($type === 'bike') {

$stmt = $conn->prepare("SELECT category FROM bikes WHERE id=?");
$stmt->bind_param("i",$similar);
$stmt->execute();
$cat = $stmt->get_result()->fetch_assoc()['category'];

$stmt = $conn->prepare("
SELECT * FROM bikes
WHERE category=? AND id!=?
LIMIT 6
");
$stmt->bind_param("si",$cat,$similar);
$stmt->execute();
$result = $stmt->get_result();

$title = "Similar Bikes";

} else {

$stmt = $conn->prepare("SELECT body_type FROM vehicle WHERE id=?");
$stmt->bind_param("i",$similar);
$stmt->execute();
$body = $stmt->get_result()->fetch_assoc()['body_type'];

$stmt = $conn->prepare("
SELECT * FROM vehicle
WHERE body_type=? AND id!=?
LIMIT 6
");
$stmt->bind_param("si",$body,$similar);
$stmt->execute();
$result = $stmt->get_result();

$title = "Similar Cars";

}
}
if (!isset($title)) {
$title = ($type === 'bike') ? "Browse Bikes" : "Browse Cars";
}
$limit = 6;
?>

<div class="container vehicles-page">

  <div class="vehicles-header">
    <h1><?= $title ?></h1>
    <p>Explore our collection</p>
  </div>

  <!-- SEARCH -->
  <div class="search-filter">
    <input id="brandBox" class="search-box" placeholder="Brand">
    <input id="modelBox" class="search-box" placeholder="Model">
    <button id="searchBtn" class="btn btn-primary">Search</button>
  </div>

<div class="vehicle-grid" id="vehicleGrid">

<?php
if(isset($result)){
while($row = $result->fetch_assoc()){

if($type === "bike"){

$img = $conn->query("SELECT image_url FROM bike_images WHERE bike_id={$row['id']} AND image_type='main' LIMIT 1")->fetch_assoc()['image_url'] ?? '';

echo "
<div class='vehicle-card'>
  <a href='vehicle-details.php?type=bike&id={$row['id']}'>

    <div class='img-wrapper'>
      <img src='{$img}' class='vehicle-image'>

      <div class='overlay'>
        <h3>{$row['brand']} {$row['model']}</h3>
        <p>{$row['price_range']}</p>
        <span class='view-btn'>View Details →</span>
      </div>
    </div>

  </a>
</div>
";

}else{

$img = $conn->query("SELECT image_path FROM vehicle_images WHERE vehicle_id={$row['id']} LIMIT 1")->fetch_assoc()['image_path'] ?? '';

echo "
<div class='vehicle-card'>
  <a href='vehicle-details.php?type=car&id={$row['id']}'>

    <div class='img-wrapper'>
      <img src='{$img}' class='vehicle-image'>

      <div class='overlay'>
        <h3>{$row['make']} {$row['model']}</h3>
        <p>₹{$row['price_min']} - ₹{$row['price_max']}</p>
        <span class='view-btn'>View Details →</span>
      </div>
    </div>

  </a>
</div>
";

}

}
}
?>

</div>

  <div style="text-align:center;margin-top:40px">
    <?php if(!$similar){ ?>
<button id="loadMoreBtn" class="btn btn-outline">Load More</button>
<?php } ?>
  </div>

</div>

<script>
const grid     = document.getElementById("vehicleGrid");
const loadBtn  = document.getElementById("loadMoreBtn");
const brandBox = document.getElementById("brandBox");
const modelBox = document.getElementById("modelBox");
const searchBtn= document.getElementById("searchBtn");

let offset = 0;
let mode   = "browse"; // browse | preview | search

/* ===============================
   HARD RESET LOAD MORE
================================ */
function resetLoadMore() {
  loadBtn.style.display = "inline-block";
  loadBtn.disabled = false;
  loadBtn.innerText = "Load More";
}

/* ===============================
   INITIAL LOAD
================================ */
loadBrowse(true);

/* ===============================
   LOAD BROWSE
================================ */
function loadBrowse(reset = false) {
  mode = "browse";
  resetLoadMore();

  if (reset) {
    offset = 0;
    grid.innerHTML = "";
  }

  fetch("load-more.php", {
    method: "POST",
    headers: {"Content-Type":"application/x-www-form-urlencoded"},
    body: `offset=${offset}&type=<?= $type ?>`
  })
  .then(r => r.text())
  .then(html => {
    if (!html.trim()) {
      loadBtn.disabled = true;
      loadBtn.innerText = "No more vehicles";
      return;
    }

    grid.insertAdjacentHTML("beforeend", html);
    offset += <?= $limit ?>;
  });
}

/* ===============================
   LOAD MORE
================================ */
loadBtn.addEventListener("click", () => {
  if (mode !== "browse") return;
  loadBrowse();
});

/* ===============================
   LIVE PREVIEW (DB ONLY)
================================ */
let previewTimer = null;

function previewSearch() {
  const brand = brandBox.value.trim();
  const model = modelBox.value.trim();

  // 🔥 CLEAR INPUT → FULL BROWSE RESTORE
  if (!brand && !model) {
    loadBrowse(true);
    return;
  }

  clearTimeout(previewTimer);
  previewTimer = setTimeout(() => {
    mode = "preview";
    loadBtn.style.display = "none";

    fetch("search_preview.php", {
      method: "POST",
      headers: {"Content-Type":"application/x-www-form-urlencoded"},
   body: `brand=${encodeURIComponent(brand)}&model=${encodeURIComponent(model)}&type=<?= $type ?>`
    })
    .then(r => r.text())
    .then(html => {
      if (html.trim()) {
        grid.innerHTML = html;
      }
    });
  }, 300);
}

brandBox.addEventListener("input", previewSearch);
modelBox.addEventListener("input", previewSearch);

/* ===============================
   SEARCH BUTTON (API)
================================ */
searchBtn.addEventListener("click", () => {
  const brand = brandBox.value.trim();
  const model = modelBox.value.trim();

  // 🔥 EMPTY SEARCH → FULL RESET
  if (!brand && !model) {
    loadBrowse(true);
    return;
  }

  mode = "search";
  grid.innerHTML = "<p>🔍 Searching…</p>";
  loadBtn.style.display = "none";

  fetch("search_vehicles.php", {
    method: "POST",
    headers: {"Content-Type":"application/x-www-form-urlencoded"},
    body: `brand=${encodeURIComponent(brand)}&model=${encodeURIComponent(model)}&type=<?= $type ?>`
  })
  .then(r => r.json())
  .then(data => {
    if (data.status === "fetching") {
      grid.innerHTML = "<p>⚡ Fetching from API…</p>";
      setTimeout(() => searchBtn.click(), 2000);
      return;
    }

    if (data.status === "found") {
      grid.innerHTML = data.html;
    } else {
      grid.innerHTML = "<p>❌ No results found</p>";
    }
  });
});
</script>
<script>
/* ===============================
   🔥 FORCE RESET ON CLEAR (HARD)
================================ */

let wasTyping = false;

function forceReloadIfCleared() {
  const brand = brandBox.value.trim();
  const model = modelBox.value.trim();

  if (brand || model) {
    wasTyping = true;
    return;
  }

  // 🔥 both empty AND user had typed before
  if (wasTyping && !brand && !model) {
    // prevent infinite reload
    wasTyping = false;
    location.reload();
  }
}

// catch EVERYTHING
brandBox.addEventListener("input", forceReloadIfCleared);
modelBox.addEventListener("input", forceReloadIfCleared);
brandBox.addEventListener("keyup", forceReloadIfCleared);
modelBox.addEventListener("keyup", forceReloadIfCleared);
brandBox.addEventListener("change", forceReloadIfCleared);
modelBox.addEventListener("change", forceReloadIfCleared);
</script>

<?php include 'includes/footer.php'; ?>
