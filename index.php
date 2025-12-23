<?php
include 'functions.php';

// --- Kontrol Halaman ---
$currentPage = $_GET['page'] ?? 'dashboard';
$selectedAthlete = null;

// --- Logic Global untuk Data Pendukung ---
// 1. Ambil daftar Cabor unik dari data atlet
$uniqueSports = [];
if (!empty($athletes)) {
    $sports = array_column($athletes, 'sport');
    $uniqueSports = array_unique(array_filter($sports));
    sort($uniqueSports);
}

// 2. Daftar Observer (Bawaan + yang sudah tersimpan di history jika ada yang custom)
$definedObservers = ['Coach Budi', 'Coach Sarah', 'Coach Dimas'];
// (Opsional: jika ingin mengambil observer unik dari riwayat latihan yang sudah ada)
$historyObservers = [];
foreach ($athletes as $a) {
    if (!empty($a['trainings'])) {
        foreach ($a['trainings'] as $t) {
            if (!empty($t['observer'])) $historyObservers[] = $t['observer'];
        }
    }
}
$allObservers = array_unique(array_merge($definedObservers, $historyObservers));
sort($allObservers);

// --- Logic Detail Atlet ---
if (isset($_GET['athlete_id'])) {
    $athleteId = (int)$_GET['athlete_id'];
    if (isset($athletes[$athleteId])) {
        $selectedAthlete = $athletes[$athleteId];
        $currentPage = 'history';
    }
}

$message = null;
$calculatedResult = null;

function getBadgeClass($status) {
    $slug = strtolower(str_replace(' ', '-', $status));
    return "badge-$slug";
}

// --- HANDLER POST REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['submit_athlete'])) {
        $message = add_new_athlete($_POST);
        $athletes = $_SESSION['athletes']; // Refresh data
        // Redirect ke daftar atlet setelah input
        $currentPage = 'list_athlete';
    }

    elseif (isset($_POST['calculate']) || isset($_POST['submit_training'])) {
        $athleteId = $_POST['athleteId'];
        $volRelatif = $_POST['volRelatif'];
        $rests = $_POST['rest'] ?? [];
        
        $trainingRows = [];
        $phases = ['Warmup', 'Pemanasan Khusus', 'Beregu Full', 'Visualisasi', 'Cooling Down'];
        
        foreach ($phases as $index => $phase) {
            $trainingRows[$index] = [
                'duration' => $_POST['duration'][$index] ?? 0, 
                'hrp' => $_POST['hrp'][$index] ?? 0
            ];
        }

        $calculatedResult = calculate_training_metrics($athleteId, $volRelatif, $rests, $trainingRows);
        
        if (isset($_POST['submit_training']) && !isset($calculatedResult['error'])) {
            $trainingDetails = [];
            foreach ($phases as $index => $phase) {
                $trainingDetails[] = [
                    'phase' => $phase,
                    'duration' => (float)($_POST['duration'][$index] ?? 0),
                    'set' => $_POST['set'][$index] ?? 0,
                    'hrp' => $_POST['hrp'][$index] ?? 0,
                    'partialIntensity' => $calculatedResult['partialIntensities'][$index] ?? 0,
                    'rest_after' => (float)($_POST['rest'][$index] ?? 0)
                ];
            }

            $formData = [
                'date' => $_POST['date'],
                'observer' => $_POST['observer'],
                'volRelatif' => $volRelatif,
                'manual_category' => $_POST['manual_category']
            ];

            $message = submit_training_revision($athleteId, $formData, $calculatedResult, $trainingDetails);
            if ($message === 'Data latihan berhasil disimpan!') {
                $calculatedResult = null;
                $_POST = [];
                $athletes = $_SESSION['athletes']; // Refresh data
            }
        }
        $currentPage = 'form';
    }
}

// --- PERSIAPAN DATA DASHBOARD & CHART ---
// Update statistik setiap kali halaman dimuat
$stats = get_statistics($athletes); 
$performanceData = array_map(function($a) {
    return ['name' => explode(' ', $a['name'])[0], 'performa' => (float)$a['lastPerformance']];
}, $athletes);

// Filter Chart Dashboard (Hanya untuk Pie Chart)
$selectedSportFilterChart = $_GET['chart_sport_filter'] ?? '';
$athletesForPie = $athletes;
if (!empty($selectedSportFilterChart)) {
    $athletesForPie = array_filter($athletes, function($a) use ($selectedSportFilterChart) {
        return isset($a['sport']) && strcasecmp($a['sport'], $selectedSportFilterChart) === 0;
    });
}
$filteredIodCategories = count_iod_categories($athletesForPie);
$pieChartData = generate_pie_chart_data($filteredIodCategories);

// --- FUNGSI CHART HELPER ---
function generate_google_chart_data($data) {
    $data_array = [['Atlet', 'IOD Terakhir']];
    foreach ($data as $item) { 
        $data_array[] = [explode(' ', $item['name'])[0], (float)$item['performa']]; 
    }
    return json_encode($data_array);
}

function generate_history_chart_data($trainings) {
    $data_array = [['Tanggal', 'Skor IOD']];
    foreach ($trainings as $t) {
        $val = isset($t['iod']) ? (float)$t['iod'] : ((isset($t['performance']) && is_numeric($t['performance'])) ? (float)$t['performance'] : 0);
        $data_array[] = [$t['date'], $val];
    }
    return json_encode($data_array);
}

function generate_pie_chart_data($data) {
    $data_array = [['Kategori IOD', 'Jumlah Latihan']];
    $order = ['Super Maximal', 'Maximum', 'Hard', 'Medium', 'Low', 'Very Low'];
    foreach ($order as $category) {
        if (isset($data[$category]) && $data[$category] > 0) {
            $data_array[] = ["$category ({$data[$category]})", (int)$data[$category]];
        }
    }
    return json_encode($data_array);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Manajemen Latihan Atlet</title>
    <link rel="stylesheet" href="style.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart']});
        google.charts.setOnLoadCallback(drawCharts);
        
        function drawCharts() {
            if (document.getElementById('bar_chart_div')) drawBarChart();
            if (document.getElementById('line_chart_div')) drawLineChart();
            if (document.getElementById('pie_chart_div')) drawPieChart();
        }
        
        function drawBarChart() {
            var jsonData = <?php echo generate_google_chart_data($performanceData); ?>;
            var data = new google.visualization.arrayToDataTable(jsonData);
            
            var options = { 
                title: 'Indeks Kesulitan (IOD) Atlet Terakhir', 
                legend: { position: 'none' }, 
                colors: ['#7c3aed'],
                vAxis: { title: 'Skor IOD', minValue: 0 }
            };
            var chart = new google.visualization.ColumnChart(document.getElementById('bar_chart_div'));
            chart.draw(data, options);
        }
        
        function drawLineChart() {
            var jsonData = <?php echo generate_history_chart_data($selectedAthlete['trainings'] ?? []); ?>;
            var data = new google.visualization.arrayToDataTable(jsonData);
            
            var options = { 
                title: 'Perkembangan IOD (Index of Difficulty)', 
                curveType: 'function', 
                legend: { position: 'bottom' }, 
                colors: ['#2563eb'], 
                vAxis: { title: 'Skor IOD', minValue: 0 }
            };
            var chart = new google.visualization.LineChart(document.getElementById('line_chart_div'));
            chart.draw(data, options);
        }

        function drawPieChart() {
            var jsonData = <?php echo $pieChartData; ?>;
            var data = new google.visualization.arrayToDataTable(jsonData);
            var pieColors = ['#db2777', '#991b1b', '#c2410c', '#92400e', '#065f46', '#374151']; 
            var options = { 
                title: 'Distribusi Kategori IOD <?= $selectedSportFilterChart ? "($selectedSportFilterChart)" : "" ?>', 
                sliceVisibilityThreshold: 0, 
                colors: pieColors,
                legend: { position: 'right', alignment: 'center' }
            };
            var chart = new google.visualization.PieChart(document.getElementById('pie_chart_div'));
            chart.draw(data, options);
        }
    </script>
</head>
<body>

    <header><div class="container"><h1>Sistem Pelatihan Atlet</h1></div></header>

    <nav>
        <div class="container">
            <div class="nav-buttons">
                <a href="?page=dashboard" class="nav-button <?= $currentPage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="?page=list_athlete" class="nav-button <?= $currentPage === 'list_athlete' ? 'active' : '' ?>">Daftar Atlet</a>
                <a href="?page=add_athlete" class="nav-button <?= $currentPage === 'add_athlete' ? 'active' : '' ?>">Input Atlet</a>
                <a href="?page=form" class="nav-button <?= $currentPage === 'form' ? 'active' : '' ?>">Input Latihan</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <?php if ($message): ?><div class="alert-box"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <?php if ($currentPage === 'dashboard'): ?>
            <div class="chart-grid">
                <div class="panel">
                    <h3>Grafik IOD (Index of Difficulty) Atlet Terakhir</h3>
                    <div id="bar_chart_div" style="width: 100%; height: 300px;"></div>
                </div>
                
                <div class="panel">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3 style="margin-bottom: 0;">Total Kategori IOD</h3>
                        <form method="GET" action="" style="margin: 0;">
                            <input type="hidden" name="page" value="dashboard">
                            <select name="chart_sport_filter" onchange="this.form.submit()" class="form-select" style="padding: 0.25rem 2rem 0.25rem 0.5rem; font-size: 0.875rem; border-color: #cbd5e1; cursor: pointer;">
                                <option value="">Semua Cabor</option>
                                <?php foreach ($uniqueSports as $sport): ?>
                                    <option value="<?= htmlspecialchars($sport) ?>" <?= $selectedSportFilterChart === $sport ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sport) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <?php if (array_sum($filteredIodCategories) > 0): ?>
                        <div id="pie_chart_div" style="width: 100%; height: 300px;"></div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 4rem 1rem; color: #64748b;">
                            <p>Belum ada data latihan.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php elseif ($currentPage === 'list_athlete'): ?>
            
            <?php
            // --- LOGIC FILTER UNTUK HALAMAN DAFTAR ATLET ---
            $filterSport = $_GET['filter_sport'] ?? '';
            $filterObserver = $_GET['filter_observer'] ?? '';
            
            $filteredAthletes = $athletes;

            // 1. Filter by Cabor
            if (!empty($filterSport)) {
                $filteredAthletes = array_filter($filteredAthletes, function($a) use ($filterSport) {
                    return isset($a['sport']) && strcasecmp($a['sport'], $filterSport) === 0;
                });
            }

            // 2. Filter by Pengamat (Cek riwayat latihan atlet)
            if (!empty($filterObserver)) {
                $filteredAthletes = array_filter($filteredAthletes, function($a) use ($filterObserver) {
                    if (empty($a['trainings'])) return false; // Skip jika belum ada latihan
                    foreach ($a['trainings'] as $t) {
                        if (isset($t['observer']) && $t['observer'] === $filterObserver) {
                            return true; // Found match
                        }
                    }
                    return false;
                });
            }
            ?>

            <div class="panel">
                <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1.5rem; gap: 1rem;">
                    <h3>Daftar Atlet</h3>
                    
                    <form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="hidden" name="page" value="list_athlete">
                        
                        <div>
                            <select name="filter_sport" class="form-select" style="width: 150px;">
                                <option value="">- Semua Cabor -</option>
                                <?php foreach ($uniqueSports as $sport): ?>
                                    <option value="<?= htmlspecialchars($sport) ?>" <?= $filterSport === $sport ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sport) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <select name="filter_observer" class="form-select" style="width: 150px;">
                                <option value="">- Semua Pengamat -</option>
                                <?php foreach ($allObservers as $obs): ?>
                                    <option value="<?= htmlspecialchars($obs) ?>" <?= $filterObserver === $obs ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($obs) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">Filter</button>
                        <?php if($filterSport || $filterObserver): ?>
                            <a href="?page=list_athlete" class="btn" style="background: #e2e8f0; color: #333; padding: 0.5rem 1rem;">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Gender</th>
                                <th>Cabor</th>
                                <th>Asal</th>
                                <th>IOD Terakhir</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($filteredAthletes)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem;">Tidak ada data atlet yang sesuai filter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($filteredAthletes as $athlete): ?>
                                <tr>
                                    <td><?= htmlspecialchars($athlete['name']) ?></td>
                                    <td><?= htmlspecialchars($athlete['gender']) ?></td>
                                    <td><?= htmlspecialchars($athlete['sport']) ?></td>
                                    <td><?= htmlspecialchars($athlete['origin'] ?? '-') ?></td>
                                    <td>
                                        <?php if($athlete['lastPerformance'] > 0): ?>
                                            <strong><?= number_format((float)$athlete['lastPerformance'], 2) ?></strong>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="?athlete_id=<?= $athlete['id'] ?>" class="detail-button">Lihat Detail</a></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($currentPage === 'add_athlete'): ?>
            <div class="panel" style="max-width: 600px; margin: 0 auto;">
                <h2>Input Data Atlet</h2>
                <form method="POST">
                    <div class="form-group"><label>Nama</label><input type="text" name="name" class="form-input" required></div>
                    <div class="form-grid">
                        <div class="form-group"><label>Gender</label><select name="gender" class="form-select"><option>Laki-laki</option><option>Perempuan</option></select></div>
                        <div class="form-group"><label>Usia</label><input type="number" name="age" class="form-input" required></div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Asal</label><input type="text" name="origin" class="form-input"></div>
                        <div class="form-group"><label>Cabor</label><input type="text" name="sport" class="form-input"></div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Berat (kg)</label><input type="number" step="0.1" name="weight" class="form-input"></div>
                        <div class="form-group"><label>Tinggi (cm)</label><input type="number" name="height" class="form-input"></div>
                    </div>
                    <button type="submit" name="submit_athlete" class="btn btn-primary">Simpan</button>
                </form>
            </div>


        <?php elseif ($currentPage === 'form'): ?>
            <div class="panel">
                <h2>Input Latihan & Kalkulasi IOD</h2>
                <form method="POST" action="?page=form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Pilih Atlet</label>
                            <select name="athleteId" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($athletes as $athlete): ?>
                                    <option value="<?= $athlete['id'] ?>" <?= (isset($_POST['athleteId']) && $_POST['athleteId'] == $athlete['id']) ? 'selected' : '' ?>><?= $athlete['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Pengamat</label>
                            <select name="observer" class="form-select">
                                <?php foreach ($definedObservers as $o) echo "<option>$o</option>"; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Tanggal</label><input type="date" name="date" value="<?= $_POST['date'] ?? date('Y-m-d') ?>" class="form-input"></div>
                        <div class="form-group">
                            <label>Volume Relatif (Total Waktu Sesi)</label>
                            <input type="number" step="0.01" name="volRelatif" value="<?= $_POST['volRelatif'] ?? '' ?>" class="form-input" required placeholder="Contoh: 120.5">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Klasifikasi Latihan</label>
                            <select name="manual_category" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <option value="Ringan" <?= (isset($_POST['manual_category']) && $_POST['manual_category'] == 'Ringan') ? 'selected' : '' ?>>Ringan</option>
                                <option value="Sedang" <?= (isset($_POST['manual_category']) && $_POST['manual_category'] == 'Sedang') ? 'selected' : '' ?>>Sedang</option>
                                <option value="Berat" <?= (isset($_POST['manual_category']) && $_POST['manual_category'] == 'Berat') ? 'selected' : '' ?>>Berat</option>
                            </select>
                        </div>
                    </div>
                    <hr style="margin: 2rem 0;">

                    <div class="table-container">
                        <table class="input-table">
                            <thead>
                                <tr>
                                    <th width="30%">Bentuk Latihan</th>
                                    <th>Durasi Exercise (menit)</th>
                                    <th width="15%">Set</th>
                                    <th width="15%">HRP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $phases = ['Warmup', 'Pemanasan Khusus', 'Beregu Full', 'Visualisasi', 'Cooling Down'];
                                foreach ($phases as $index => $phase): 
                                ?>
                                    <tr>
                                        <td style="font-weight: bold; background:#f9fafb;"><?= $phase ?></td>
                                        <td><input type="number" step="0.01" name="duration[]" value="<?= $_POST['duration'][$index] ?? '' ?>" class="table-input" placeholder="mnt"></td>
                                        <td><input type="number" name="set[]" value="<?= $_POST['set'][$index] ?? '' ?>" class="table-input" placeholder="1"></td>
                                        <td><input type="number" name="hrp[]" value="<?= $_POST['hrp'][$index] ?? '' ?>" class="table-input"></td>
                                    </tr>
                                    <?php if ($index < count($phases)): ?>
                                    <tr class="rest-row">
                                        <td colspan="4">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <span style="color: #c2410c; font-weight:bold;">REST (mnt):</span>
                                                <input type="number" step="0.01" name="rest[]" value="<?= $_POST['rest'][$index] ?? 0 ?>" class="form-input" style="width: 80px;">
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                        <button type="submit" name="calculate" class="btn btn-primary">Hitung Rumus</button>
                        <?php if ($calculatedResult): ?>
                            <button type="submit" name="submit_training" class="btn btn-success">Simpan Riwayat</button>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($calculatedResult && !isset($calculatedResult['error'])): ?>
                    <div class="result-box">
                        <div style="text-align: center; margin-bottom: 2rem; border-bottom: 2px dashed #cbd5e1; padding-bottom: 1.5rem;">
                            <p style="font-size: 1rem; color: #64748b; margin-bottom: 0.5rem;">IOD (Index of Difficulty)</p>
                            <p class="iod-highlight"><?= number_format($calculatedResult['iod'], 2) ?></p>
                            
                            <span class="iod-badge <?= getBadgeClass($calculatedResult['iodClass']) ?>">
                                <?= $calculatedResult['iodClass'] ?>
                            </span>
                        </div>
                        <div class="form-grid">
                            <div class="result-item"><p class="label">Absolute Density</p><p class="value"><?= number_format($calculatedResult['absoluteDensity'], 2) ?>%</p></div>
                            <div class="result-item"><p class="label">Overall Intensity</p><p class="value"><?= number_format($calculatedResult['overallIntensity'], 2) ?>%</p></div>
                            <div class="result-item"><p class="label">Volume Absolute</p><p class="value"><?= number_format($calculatedResult['volAbsolute'], 2) ?> mnt</p></div>
                            <div class="result-item"><p class="label">Total Recovery</p><p class="value"><?= number_format($calculatedResult['recovery'], 2) ?> mnt</p></div>
                            <div class="result-item"><p class="label">HR Max</p><p class="value text-red-600"><?= $calculatedResult['hrMax'] ?></p></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($currentPage === 'history' && $selectedAthlete): ?>
            <a href="?page=list_athlete" class="back-button">← Kembali ke Daftar</a>
            <div class="panel">
                <h1><?= htmlspecialchars($selectedAthlete['name']) ?></h1>
                
                <div style="background: #f1f5f9; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
                    <div class="history-grid">
                        <div class="history-item"><p class="label">Gender</p><p class="value"><?= htmlspecialchars($selectedAthlete['gender'] ?? '-') ?></p></div>
                        <div class="history-item"><p class="label">Usia</p><p class="value"><?= htmlspecialchars($selectedAthlete['age'] ?? '-') ?> th</p></div>
                        <div class="history-item"><p class="label">Asal</p><p class="value"><?= htmlspecialchars($selectedAthlete['origin'] ?? '-') ?></p></div>
                        <div class="history-item"><p class="label">Cabor</p><p class="value"><?= htmlspecialchars($selectedAthlete['sport'] ?? '-') ?></p></div>
                        <div class="history-item"><p class="label">Berat</p><p class="value"><?= htmlspecialchars($selectedAthlete['weight'] ?? '-') ?> kg</p></div>
                        <div class="history-item"><p class="label">Tinggi</p><p class="value"><?= htmlspecialchars($selectedAthlete['height'] ?? '-') ?> cm</p></div>
                    </div>
                </div>
                
                <hr style="margin: 1rem 0;">
                
                <?php if (!empty($selectedAthlete['trainings'])): ?>
                    <div id="line_chart_div" style="width: 100%; height: 300px; margin-bottom: 2rem;"></div>
                <?php else: ?>
                    <p style="text-align: center; padding: 3rem; background: #fff7ed; border-radius: 0.5rem;">Belum ada riwayat latihan untuk atlet ini.</p>
                <?php endif; ?>

                <h3>Riwayat Latihan</h3>
                <?php foreach (array_reverse($selectedAthlete['trainings']) as $t): ?>
                    <div class="training-detail" style="margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <div>
                                <strong><?= $t['date'] ?></strong> | Observer: <?= $t['observer'] ?>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-size: 0.8rem; color: #64748b;">IOD SCORE</span><br>
                                <span style="font-size: 1.5rem; font-weight: bold; color: #1e293b;">
                                    <?= isset($t['iod']) ? number_format($t['iod'], 2) : ($t['performance'] ?? '-') ?>
                                </span>
                                <?php if(isset($t['iodClass'])): ?>
                                    <br><span class="iod-badge <?= getBadgeClass($t['iodClass']) ?>" style="font-size: 0.8rem; padding: 2px 8px;">
                                        <?= $t['iodClass'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (isset($t['details'])): ?>
                            <table class="input-table" style="font-size: 0.85rem;">
                                <tr style="background: #eee;">
                                    <th>Fase</th>
                                    <th>Durasi</th>
                                    <th>Set</th>
                                    <th>HRP</th>
                                    <th>Partial Int.</th>
                                    <th>Rest</th>
                                </tr>
                                <?php foreach ($t['details'] as $d): ?>
                                    <tr>
                                        <td><?= $d['phase'] ?></td>
                                        <td><?= $d['duration'] ?>'</td>
                                        <td><?= $d['set'] ?? '-' ?></td>
                                        <td><?= $d['hrp'] ?></td>
                                        <td><?= isset($d['partialIntensity']) ? number_format($d['partialIntensity'], 1) : '-' ?>%</td>
                                        <td style="color: #c2410c;"><?= $d['rest_after'] ?>'</td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>