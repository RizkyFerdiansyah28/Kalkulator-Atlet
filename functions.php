<?php
// functions.php - FULL VERSION (REVISI FINAL)

// 1. KONEKSI DATABASE
$host = 'localhost';
$user = 'root';     // Sesuaikan username database
$pass = '';         // Sesuaikan password database
$db   = 'db_kalkulator_atlet';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Mulai session hanya untuk pesan flash/notifikasi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- FUNGSI DATABASE (GET DATA) ---

// Ambil semua atlet LENGKAP dengan riwayat latihan & performa terakhir
// (Diperlukan untuk Dashboard agar grafik muncul)
function get_all_athletes() {
    global $conn;
    $athletes = [];
    
    // 1. Ambil data dasar atlet
    $result = mysqli_query($conn, "SELECT * FROM athletes ORDER BY name ASC");
    
    while ($row = mysqli_fetch_assoc($result)) {
        $athleteId = $row['id'];
        
        // 2. Ambil riwayat sesi latihan untuk atlet ini (perlu untuk grafik dashboard)
        $trainings = [];
        $queryTrainings = "SELECT * FROM training_sessions WHERE athlete_id = $athleteId ORDER BY date ASC, id ASC";
        $resTrainings = mysqli_query($conn, $queryTrainings);
        
        while ($t = mysqli_fetch_assoc($resTrainings)) {
            $trainings[] = $t;
        }
        
        // Masukkan ke array atlet agar dashboard bisa membacanya
        $row['trainings'] = $trainings;

        // 3. Tentukan Last Performance (IOD Terakhir) untuk mengatasi Error Undefined Key
        if (!empty($trainings)) {
            // Ambil elemen terakhir dari array trainings
            $lastSession = end($trainings);
            $row['lastPerformance'] = $lastSession['iod'] ?? 0;
        } else {
            $row['lastPerformance'] = 0;
        }
        
        $athletes[] = $row;
    }
    
    return $athletes;
}

// Ambil 1 atlet beserta riwayat latihannya (Untuk Detail Page)
function get_athlete_full_detail($id) {
    global $conn;
    
    // 1. Ambil Data Profil
    $stmt = mysqli_prepare($conn, "SELECT * FROM athletes WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $athlete = mysqli_fetch_assoc($result);
    
    if (!$athlete) return null;

    // 2. Ambil Data Sesi Latihan
    $athlete['trainings'] = [];
    $querySessions = "SELECT * FROM training_sessions WHERE athlete_id = ? ORDER BY date ASC, id ASC"; 
    $stmtSession = mysqli_prepare($conn, $querySessions);
    mysqli_stmt_bind_param($stmtSession, "i", $id);
    mysqli_stmt_execute($stmtSession);
    $resSessions = mysqli_stmt_get_result($stmtSession);

    while ($session = mysqli_fetch_assoc($resSessions)) {
        // 3. Ambil Detail Latihan per Sesi
        $sessionId = $session['id'];
        $queryDetails = "SELECT 
                            phase_name as phase, 
                            duration, 
                            sets as `set`, 
                            hrp, 
                            rest_after, 
                            partial_intensity as partialIntensity 
                         FROM training_details WHERE training_session_id = $sessionId";
        $resDetails = mysqli_query($conn, $queryDetails);
        
        $details = [];
        while ($d = mysqli_fetch_assoc($resDetails)) {
            $details[] = $d;
        }
        
        $session['details'] = $details;
        $athlete['trainings'][] = $session;
    }

    return $athlete;
}

// Tambah Atlet Baru
function add_new_athlete($data) {
    global $conn;
    
    $name = $data['name'];
    $gender = $data['gender'];
    $origin = $data['origin'];
    $sport = $data['sport'];
    $weight = (float)$data['weight'];
    $height = (float)$data['height'];
    $age = (int)$data['age'];

    $stmt = mysqli_prepare($conn, "INSERT INTO athletes (name, gender, age, origin, sport, weight, height) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssissdd", $name, $gender, $age, $origin, $sport, $weight, $height);
    
    if (mysqli_stmt_execute($stmt)) {
        return "Atlet berhasil ditambahkan!";
    } else {
        return "Error: " . mysqli_error($conn);
    }
}

// --- FUNGSI HELPER TAMPILAN ---
function getBadgeClass($status) {
    $slug = strtolower(str_replace(' ', '-', $status));
    return "badge-$slug";
}

// --- FUNGSI PERHITUNGAN & SIMPAN LATIHAN ---
function calculate_training_metrics($athleteId, $trainingDetails) {
    global $conn;

    if (empty($athleteId) || empty($trainingDetails)) {
        return ['error' => 'Data atlet dan Detail Latihan harus diisi'];
    }

    // Ambil data atlet dari DB untuk hitung HR Max
    $stmt = mysqli_prepare($conn, "SELECT age FROM athletes WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $athleteId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $athlete = mysqli_fetch_assoc($res);

    if (!$athlete) {
        return ['error' => 'Atlet tidak ditemukan di Database'];
    }

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
    global $conn;

    // 1. Insert ke Tabel Training Sessions (Header)
    $stmtSession = mysqli_prepare($conn, "INSERT INTO training_sessions 
        (athlete_id, date, observer, manual_category, vol_relatif, vol_absolute, recovery, hr_max, absolute_density, overall_intensity, iod, iod_class) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    mysqli_stmt_bind_param($stmtSession, "isssdddiddds", 
        $athleteId, 
        $formData['date'], 
        $formData['observer'], 
        $formData['manual_category'],
        $calculatedMetrics['volRelatif'],
        $calculatedMetrics['volAbsolute'],
        $calculatedMetrics['recovery'],
        $calculatedMetrics['hrMax'],
        $calculatedMetrics['absoluteDensity'],
        $calculatedMetrics['overallIntensity'],
        $calculatedMetrics['iod'],
        $calculatedMetrics['iodClass']
    );

    if (!mysqli_stmt_execute($stmtSession)) {
        return "Gagal menyimpan sesi: " . mysqli_error($conn);
    }

    $sessionId = mysqli_insert_id($conn); // Ambil ID sesi yang baru dibuat

    // 2. Insert ke Tabel Training Details (Rincian Baris)
    $stmtDetail = mysqli_prepare($conn, "INSERT INTO training_details 
        (training_session_id, phase_name, duration, sets, hrp, rest_after, partial_intensity) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($trainingDetails as $k => $row) {
        $partialInt = $calculatedMetrics['partialIntensities'][$k] ?? 0;
        
        mysqli_stmt_bind_param($stmtDetail, "isddidd", 
            $sessionId,
            $row['phase'],
            $row['duration'],
            $row['set'],
            $row['hrp'],
            $row['rest_after'],
            $partialInt
        );
        mysqli_stmt_execute($stmtDetail);
    }

    return 'Data latihan berhasil disimpan!';
}

// --- FUNGSI STATISTIK  ---

function get_statistics($athletes = null) {
    global $conn;

    // Hitung Total Atlet
    $queryAthletes = mysqli_query($conn, "SELECT COUNT(*) as total FROM athletes");
    $totalAthletes = mysqli_fetch_assoc($queryAthletes)['total'];

    // Hitung Total Latihan & Rata-rata IOD
    $queryTrainings = mysqli_query($conn, "SELECT COUNT(*) as total, AVG(iod) as avg_iod FROM training_sessions");
    $dataTrainings = mysqli_fetch_assoc($queryTrainings);

    $totalTrainings = $dataTrainings['total'];
    
    // Perbaikan: Pastikan avg_iod memiliki nilai default 0 jika null
    $avgIOD = isset($dataTrainings['avg_iod']) ? number_format($dataTrainings['avg_iod'], 2) : 0;

    return compact('totalAthletes', 'avgIOD', 'totalTrainings');
}

function count_iod_categories($sportFilter = null) {
    global $conn;
    
    $categories = [
        'Super Maximal' => 0, 
        'Maximum' => 0, 
        'Hard' => 0, 
        'Medium' => 0, 
        'Low' => 0, 
        'Very Low' => 0
    ];

    // ambil tabel athletes
    $sql = "SELECT t.iod_class, COUNT(*) as count 
            FROM training_sessions t
            JOIN athletes a ON t.athlete_id = a.id";
            
    // Jika ada filter sport, tambahkan WHERE clause
    if (!empty($sportFilter)) {
        // Escape string untuk keamanan
        $safeSport = mysqli_real_escape_string($conn, $sportFilter);
        $sql .= " WHERE a.sport = '$safeSport'";
    }

    $sql .= " GROUP BY t.iod_class";

    $result = mysqli_query($conn, $sql);

    while($row = mysqli_fetch_assoc($result)) {
        if (array_key_exists($row['iod_class'], $categories)) {
            $categories[$row['iod_class']] = $row['count'];
        }
    }
    
    return array_filter($categories);
}

// Variabel Global (Agar kompatibel dengan halaman yang include file ini)
$athletes = get_all_athletes(); 

// ... kode yang sudah ada sebelumnya ...

// --- FUNGSI HAPUS ATLET ---
function delete_athlete($id) {
    global $conn;
    $id = (int)$id; // Pastikan ID berupa integer untuk keamanan

    // 1. Hapus detail latihan (training_details) yang terkait dengan atlet ini
    // Kita perlu mencari ID sesi latihan atlet ini terlebih dahulu
    $query_sessions = "SELECT id FROM training_sessions WHERE athlete_id = $id";
    $result_sessions = mysqli_query($conn, $query_sessions);
    
    while ($row = mysqli_fetch_assoc($result_sessions)) {
        $sessionId = $row['id'];
        // Hapus detail berdasarkan session ID
        mysqli_query($conn, "DELETE FROM training_details WHERE training_session_id = $sessionId");
    }

    // 2. Hapus sesi latihan (training_sessions) milik atlet ini
    mysqli_query($conn, "DELETE FROM training_sessions WHERE athlete_id = $id");

    // 3. Terakhir, hapus data atlet dari tabel athletes
    $query_delete_athlete = "DELETE FROM athletes WHERE id = $id";
    
    if (mysqli_query($conn, $query_delete_athlete)) {
        return mysqli_affected_rows($conn);
    } else {
        return 0;
    }
}
?>