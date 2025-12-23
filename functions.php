<?php
session_start();

// --- Struktur Data Awal ---
function initialize_athletes() {
    if (!isset($_SESSION['athletes'])) {
        // Data awal yang lebih lengkap
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

// --- Fungsi Perhitungan (Update Klasifikasi IOD) ---
function calculate_training_metrics($athleteId, $volRelatif, $rests, $trainingRows) {
    if (empty($athleteId) || $volRelatif === '') {
        return ['error' => 'Data atlet dan Volume Relatif harus diisi'];
    }

    $athlete = $_SESSION['athletes'][$athleteId];
    $age = $athlete['age'];
    $volRelatif = (float)$volRelatif;

    // 1. Hitung HR Max
    $hrMax = 220 - $age;

    // 2. Hitung Recovery
    $totalRecovery = 0.0;
    foreach ($rests as $restTime) {
        $totalRecovery += (float)$restTime;
    }

    // 3. Hitung Volume Absolute
    $volAbsolute = $volRelatif - $totalRecovery;

    // 4. Hitung Absolute Density (%)
    if ($volRelatif > 0) {
        $absoluteDensity = (($volRelatif - $totalRecovery) / $volRelatif) * 100;
    } else {
        $absoluteDensity = 0;
    }

    // 5. Hitung Overall Intensity (%)
    $sigmaWeightedIntensity = 0;
    $sigmaVolumeExercise = 0;
    $partialIntensities = [];

    foreach ($trainingRows as $index => $row) {
        $durasi = (float)$row['duration']; 
        $hrp = (float)$row['hrp'];
        
        $partial = 0;
        if ($hrMax > 0) {
            $partial = ($hrp / $hrMax) * 100;
        }
        $partialIntensities[$index] = $partial;

        $sigmaWeightedIntensity += ($partial * $durasi);
        $sigmaVolumeExercise += $durasi;
    }

    $overallIntensity = 0;
    if ($sigmaVolumeExercise > 0) {
        $overallIntensity = $sigmaWeightedIntensity / $sigmaVolumeExercise;
    }

    // 6. Hitung IOD
    $iod = ($volAbsolute * $absoluteDensity * $overallIntensity) / 10000;

    // 7. Tentukan Klasifikasi IOD 
    $iodClass = '';
    if ($iod >= 100) {
        $iodClass = 'Super Maximal';
    } elseif ($iod >= 90) {
        $iodClass = 'Maximum';
    } elseif ($iod >= 80) {
        $iodClass = 'Hard';
    } elseif ($iod >= 70) {
        $iodClass = 'Medium';
    } elseif ($iod >= 50) {
        $iodClass = 'Low';
    } else {
        $iodClass = 'Very Low';
    }

    return [
        'hrMax' => $hrMax,
        'recovery' => $totalRecovery,
        'volAbsolute' => $volAbsolute,
        'absoluteDensity' => $absoluteDensity,
        'overallIntensity' => $overallIntensity,
        'iod' => $iod,
        'iodClass' => $iodClass, // Return klasifikasi
        'partialIntensities' => $partialIntensities
    ];
}

// --- Fungsi Submit Latihan ---
function submit_training_revision($athleteId, $formData, $calculatedMetrics, $trainingDetails) {
    if (!isset($_SESSION['athletes'][$athleteId])) {
        return 'Atlet tidak ditemukan';
    }

    // Ambil nilai IOD yang sudah dibulatkan sebagai float
    $iodValue = (float)number_format($calculatedMetrics['iod'], 2, '.', '');

    $newTraining = [
        'id' => time(),
        'date' => $formData['date'],
        'observer' => $formData['observer'],
        'volRelatif' => (float)$formData['volRelatif'],
        
        'recovery' => $calculatedMetrics['recovery'],
        'volAbsolute' => $calculatedMetrics['volAbsolute'],
        'hrMax' => $calculatedMetrics['hrMax'],
        'absoluteDensity' => $calculatedMetrics['absoluteDensity'],
        'overallIntensity' => $calculatedMetrics['overallIntensity'],
        'iod' => $calculatedMetrics['iod'], // Simpan nilai float IOD yang presisi
        'iodClass' => $calculatedMetrics['iodClass'], 
        
        'details' => $trainingDetails,
        'performance' => number_format($calculatedMetrics['iod'], 2), // Simpan format string untuk tampilan tabel
        'status' => 'Calculated'
    ];

    // KRITIS: Simpan performa terakhir sebagai float, BUKAN string format
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

// Data IOD Category untuk Pie Chart (Jika Anda menggunakannya)
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

$athletes = $_SESSION['athletes'];
$stats = get_statistics($athletes);
$performanceData = array_map(function($a) {
    // Pastikan performa sebagai float/numeric untuk Bar Chart
    return ['name' => explode(' ', $a['name'])[0], 'performa' => (float)$a['lastPerformance']]; 
}, $athletes);

$iodCategoriesData = count_iod_categories($athletes);
?>