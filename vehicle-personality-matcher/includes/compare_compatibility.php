<?php

function cosineSimilarity($A,$B){

$dot = 0;
$magA = 0;
$magB = 0;

foreach($A as $key=>$value){

$dot  += $A[$key] * $B[$key];
$magA += pow($A[$key],2);
$magB += pow($B[$key],2);

}

if($magA == 0 || $magB == 0){
return 0;
}

return $dot / (sqrt($magA) * sqrt($magB));

}


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


function calculateCompareCompatibility($user,$vehicle,$type){

    $maintenanceScore = calculateMaintenance($vehicle,$type);

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

    /* DISTANCE */
    $distance = 0;

    foreach($user as $key=>$value){

        $diff = abs($value - $vehicleVector[$key]);

        if ($diff > 40) {
            $distance += $diff * 1.2;
        } else {
            $distance += $diff;
        }
    }

    // 🔥 SAME penalties
    if (abs($user['performance'] - $vehicleVector['performance']) > 40) {
        $distance += 60;
    }

    if (abs($user['reliability'] - $vehicleVector['reliability']) > 40) {
        $distance += 50;
    }

    $maxDistance = count($user) * 180;

    $distanceScore = 100 - (($distance / $maxDistance) * 100);

    /* COSINE */
    $cosine = cosineSimilarity($user,$vehicleVector);
    $cosineScore = $cosine * 100;

    /* FINAL */
    $compatibility = round(
        ($distanceScore * 0.85) +
        ($cosineScore * 0.15)
    );

    // 🔥 HARD penalties
    if($user['performance'] < 50 && $vehicleVector['performance'] > 80){
        $compatibility -= 25;
    }

    if($user['practicality'] > 80 && $vehicleVector['practicality'] < 50){
        $compatibility -= 20;
    }

    if($user['efficiency'] > 80 && $vehicleVector['efficiency'] < 50){
        $compatibility -= 15;
    }

    // 🔥 SAME CLAMP ORDER
    $compatibility = max(0, min(100, $compatibility));

    if ($distance > 400) {
        $compatibility -= 10;
    }

    return $compatibility;
}