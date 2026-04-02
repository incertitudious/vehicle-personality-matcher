<?php
include 'includes/header.php';

$type = $_GET['type'] ?? '';
$id   = $_GET['id'] ?? '';

if (!$type || !$id) {
    die("Invalid vehicle selection.");
}
?>

<div class="quiz-wrapper">

  <h2 class="quiz-title">Compatibility Quiz</h2>
  <p class="quiz-sub">Answer a few questions to check compatibility with this vehicle</p>

  <!-- Progress -->
  <div class="progress">
    <span id="progressText">Question 1 of 10</span>
    <div class="progress-bar">
      <div id="progressFill"></div>
    </div>
  </div>

  <form method="POST" action="result.php" id="quizForm">

    <!-- Hidden vehicle info -->
    <input type="hidden" name="vehicle_type" value="<?= htmlspecialchars($type) ?>">
    <input type="hidden" name="vehicle_id" value="<?= htmlspecialchars($id) ?>">

    <!-- QUESTION 1 -->
    <div class="quiz-step active">
      <h3>How will you primarily use this vehicle?</h3>

      <label><input type="radio" name="usage" value="80"> Daily city commute</label>
      <label><input type="radio" name="usage" value="60"> Frequent highway trips</label>
      <label><input type="radio" name="usage" value="70"> Mixed usage</label>
      <label><input type="radio" name="usage" value="40"> Weekend use</label>
    </div>

    <!-- QUESTION 2 -->
    <div class="quiz-step">
      <h3>What matters more to you?</h3>

      <label><input type="radio" name="mileage" value="90"> Fuel efficiency</label>
      <label><input type="radio" name="mileage" value="70"> Balanced</label>
      <label><input type="radio" name="mileage" value="30"> Performance</label>
      <label><input type="radio" name="mileage" value="50"> Don’t care</label>
    </div>

    <!-- QUESTION 3 -->
    <div class="quiz-step">
      <h3>How important is comfort?</h3>

      <label><input type="radio" name="comfort" value="95"> Extremely important</label>
      <label><input type="radio" name="comfort" value="80"> Important</label>
      <label><input type="radio" name="comfort" value="60"> Neutral</label>
      <label><input type="radio" name="comfort" value="40"> Not important</label>
    </div>

    <!-- QUESTION 4 -->
    <div class="quiz-step">
      <h3>Maintenance cost tolerance?</h3>

      <label><input type="radio" name="maintenance" value="90"> Low only</label>
      <label><input type="radio" name="maintenance" value="70"> Moderate</label>
      <label><input type="radio" name="maintenance" value="40"> High ok</label>
      <label><input type="radio" name="maintenance" value="20"> Doesn’t matter</label>
    </div>

    <!-- QUESTION 5 -->
    <div class="quiz-step">
      <h3>Preferred driving experience?</h3>

      <label><input type="radio" name="performance" value="40"> Calm</label>
      <label><input type="radio" name="performance" value="70"> Balanced</label>
      <label><input type="radio" name="performance" value="85"> Sporty</label>
      <label><input type="radio" name="performance" value="95"> Aggressive</label>
    </div>

    <!-- QUESTION 6 -->
    <div class="quiz-step">
      <h3>How practical should it be?</h3>

      <label><input type="radio" name="practicality" value="90"> Very practical</label>
      <label><input type="radio" name="practicality" value="70"> Moderate</label>
      <label><input type="radio" name="practicality" value="50"> Slight</label>
      <label><input type="radio" name="practicality" value="30"> Not needed</label>
    </div>

    
 <!-- QUESTION 7 -->
<div class="quiz-step">

<?php if($type === 'bike'){ ?>

<h3>How often do you ride with a pillion?</h3>

<label><input type="radio" name="passengers" value="30"> Always solo</label>
<label><input type="radio" name="passengers" value="60"> Sometimes with pillion</label>
<label><input type="radio" name="passengers" value="80"> Often with pillion</label>
<label><input type="radio" name="passengers" value="90"> Mostly with pillion</label>

<?php } else { ?>

<h3>How many people usually travel with you?</h3>

<label><input type="radio" name="passengers" value="40"> Just me</label>
<label><input type="radio" name="passengers" value="60"> Two people</label>
<label><input type="radio" name="passengers" value="80"> 3–4 people</label>
<label><input type="radio" name="passengers" value="90"> Family / group</label>

<?php } ?>

</div>

    <!-- QUESTION 8 -->
    <div class="quiz-step">
      <h3>How long will you keep it?</h3>

      <label><input type="radio" name="ownership" value="20"> &lt; 2 years</label>
      <label><input type="radio" name="ownership" value="40"> 2–5 years</label>
      <label><input type="radio" name="ownership" value="70"> 5–8 years</label>
      <label><input type="radio" name="ownership" value="90"> 8+ years</label>
    </div>

    <!-- QUESTION 9 -->
    <div class="quiz-step">
      <h3>Unexpected costs would make you feel?</h3>

      <label><input type="radio" name="cost_sensitivity" value="90"> Very unhappy</label>
      <label><input type="radio" name="cost_sensitivity" value="70"> Concerned</label>
      <label><input type="radio" name="cost_sensitivity" value="40"> Acceptable</label>
      <label><input type="radio" name="cost_sensitivity" value="20"> Expected</label>
    </div>
<!-- QUESTION 10 -->
<div class="quiz-step">
  <h3>What kind of roads do you mostly drive on?</h3>

  <label><input type="radio" name="road_type" value="1"> Smooth city roads</label>
  <label><input type="radio" name="road_type" value="2"> Highways</label>
  <label><input type="radio" name="road_type" value="3"> Mixed roads</label>
  <label><input type="radio" name="road_type" value="4"> Rough / uneven roads</label>
</div>
    <!-- NAVIGATION -->
    <div class="quiz-nav">
      <button type="button" id="backBtn" disabled>← Back</button>
      <button type="button" id="nextBtn" disabled>Next →</button>
    </div>

  </form>

</div>

<?php include 'includes/footer.php'; ?>

<script>

let current = 0;
const steps = document.querySelectorAll('.quiz-step');
const nextBtn = document.getElementById('nextBtn');
const backBtn = document.getElementById('backBtn');
const progressFill = document.getElementById('progressFill');
const progressText = document.getElementById('progressText');

function updateUI(){

  steps.forEach(step => step.classList.remove('active'));
  steps[current].classList.add('active');

  backBtn.disabled = current === 0;

  nextBtn.innerText = current === steps.length - 1 ? 'Finish' : 'Next →';

  progressText.innerText = `Question ${current + 1} of ${steps.length}`;

  progressFill.style.width = ((current + 1) / steps.length * 100) + '%';

  nextBtn.disabled = true;

}

document.querySelectorAll('input[type=radio]').forEach(input => {

  input.addEventListener('change', () => {
    nextBtn.disabled = false;
  });

});

nextBtn.onclick = () => {

  if(current < steps.length - 1){

    current++;
    updateUI();

  }else{

    document.getElementById('quizForm').submit();

  }

};

backBtn.onclick = () => {

  current--;
  updateUI();

};

updateUI();

</script>