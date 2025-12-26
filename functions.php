<?php
// Cek status session sebelum memulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Struktur Data Awal ---
function initialize_athletes() {
    if (!isset($_SESSION['athletes'])) {
        $_SESSION['athletes'] = [
            1 => ['id' => 1, 'name' => 'Ahmad Rifai', 'gender' => 'Laki-laki', 'origin' => 'Jakarta', 'sport' => 'Atletik', 'weight' => 65, 'height' => 175, 'age' => 24, 'lastPerformance' => 0, 'trainings' => []],
            2 => ['id' => 2, 'name' => 'Siti Nurhaliza', 'gender' => 'Perempuan', 'origin' => 'Bandung', 'sport' => 'Renang', 'weight' => 55, 'height' => 165, 'age' => 22, 'lastPerformance' => 0, 'trainings' => []]
        ];
    }
}
initialize_athletes();

function add_new_athlete($data) {
    $newId = count($_SESSION['athletes']) + 1;
    $_SESSION['athletes'][$newId] = [
        'id' => $newId,
        'name' => $data['name'],
        'gender' => $data['gender'],
        'origin' => $data['origin'],
        'sport' => $data['sport'],
        'weight' => (float)$data['weight'],
        'height' => (float)$data['height'],
        'age' => (int)$data['age'],
        'lastPerformance' => 0,
        'trainings' => []
    ];
    return "Atlet berhasil ditambahkan!";
}

// --- Fungsi Helper ---
function getBadgeClass($status) {
    $slug = strtolower(str_replace(' ', '-', $status));
    return "badge-$slug";
}

// --- Fungsi Perhitungan ---
function calculate_training_metrics($athleteId, $trainingDetails) {
    if (empty($athleteId) || empty($trainingDetails)) {
        return ['error' => 'Data atlet dan Detail Latihan harus diisi'];
    }

    // Pastikan atlet ada
    if (!isset($_SESSION['athletes'][$athleteId])) {
        return ['error' => 'Atlet tidak ditemukan'];
    }

    $athlete = $_SESSION['athletes'][$athleteId];
    $age = $athlete['age'];
    $hrMax = 220 - $age;

    $sigmaWeightedIntensity = 0;
    $sigmaVolumeExercise = 0; 
    $totalRecovery = 0.0;
    $partialIntensities = [];

    foreach ($trainingDetails as $index => $row) {
        $durasi = (float)($row['duration'] ?? 0); 
        $hrp = (float)($row['hrp'] ?? 0);
        $rest = (float)($row['rest_after'] ?? 0);

        $sigmaVolumeExercise += $durasi;
        $totalRecovery += $rest;

        $partial = 0;
        if ($hrMax > 0) {
            $partial = ($hrp / $hrMax) * 100;
        }
        $partialIntensities[$index] = $partial;
        $sigmaWeightedIntensity += ($partial * $durasi);
    }

    $volRelatif = $sigmaVolumeExercise + $totalRecovery;
    $volAbsolute = $sigmaVolumeExercise;

    $absoluteDensity = 0;
    if ($volRelatif > 0) {
        $absoluteDensity = ($volAbsolute / $volRelatif) * 100;
    }

    $overallIntensity = 0;
    if ($sigmaVolumeExercise > 0) {
        $overallIntensity = $sigmaWeightedIntensity / $sigmaVolumeExercise;
    }

    $iod = ($volAbsolute * $absoluteDensity * $overallIntensity) / 10000;

    $iodClass = '';
    if ($iod >= 100) $iodClass = 'Super Maximal';
    elseif ($iod >= 90) $iodClass = 'Maximum';
    elseif ($iod >= 80) $iodClass = 'Hard';
    elseif ($iod >= 70) $iodClass = 'Medium';
    elseif ($iod >= 50) $iodClass = 'Low';
    else $iodClass = 'Very Low';

    return [
        'hrMax' => $hrMax,
        'recovery' => $totalRecovery,
        'volAbsolute' => $volAbsolute,
        'volRelatif' => $volRelatif,
        'absoluteDensity' => $absoluteDensity,
        'overallIntensity' => $overallIntensity,
        'iod' => $iod,
        'iodClass' => $iodClass,
        'partialIntensities' => $partialIntensities
    ];
}

function submit_training_revision($athleteId, $formData, $calculatedMetrics, $trainingDetails) {
    if (!isset($_SESSION['athletes'][$athleteId])) {
        return 'Atlet tidak ditemukan';
    }

    $iodValue = (float)number_format($calculatedMetrics['iod'], 2, '.', '');

    $newTraining = [
        'id' => time(),
        'date' => $formData['date'],
        'observer' => $formData['observer'],
        'volRelatif' => $calculatedMetrics['volRelatif'],
        'manual_category' => $formData['manual_category'] ?? '-',
        'recovery' => $calculatedMetrics['recovery'],
        'volAbsolute' => $calculatedMetrics['volAbsolute'],
        'hrMax' => $calculatedMetrics['hrMax'],
        'absoluteDensity' => $calculatedMetrics['absoluteDensity'],
        'overallIntensity' => $calculatedMetrics['overallIntensity'],
        'iod' => $calculatedMetrics['iod'],
        'iodClass' => $calculatedMetrics['iodClass'],
        'details' => $trainingDetails, // Detail per fase disimpan di sini
        'performance' => number_format($calculatedMetrics['iod'], 2),
        'status' => 'Calculated'
    ];

    $_SESSION['athletes'][$athleteId]['lastPerformance'] = $iodValue; 
    $_SESSION['athletes'][$athleteId]['trainings'][] = $newTraining;

    return 'Data latihan berhasil disimpan!';
}

function get_statistics($athletes) {
    $totalAthletes = count($athletes);
    $totalTrainings = 0;
    $totalIOD = 0;
    foreach ($athletes as $athlete) {
        $totalIOD += (float)$athlete['lastPerformance'];
        $totalTrainings += count($athlete['trainings']);
    }
    $avgIOD = $totalAthletes > 0 ? number_format($totalIOD / $totalAthletes, 2) : 0;
    return compact('totalAthletes', 'avgIOD', 'totalTrainings');
}

function count_iod_categories($athletes) {
    $categories = ['Super Maximal' => 0, 'Maximum' => 0, 'Hard' => 0, 'Medium' => 0, 'Low' => 0, 'Very Low' => 0];
    foreach ($athletes as $athlete) {
        foreach ($athlete['trainings'] as $training) {
            if (isset($training['iodClass']) && array_key_exists($training['iodClass'], $categories)) {
                $categories[$training['iodClass']]++;
            }
        }
    }
    return array_filter($categories);
}

// Export data
$athletes = $_SESSION['athletes'];
$stats = get_statistics($athletes);
?>