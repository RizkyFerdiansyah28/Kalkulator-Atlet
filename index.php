<?php
include 'functions.php';

// --- Kontrol Halaman ---
$currentPage = $_GET['page'] ?? 'dashboard';
$selectedAthlete = null;

// --- Logic Global untuk Data Pendukung ---
$uniqueSports = [];
if (!empty($athletes)) {
    $sports = array_column($athletes, 'sport');
    $uniqueSports = array_unique(array_filter($sports));
    sort($uniqueSports);
}

$definedObservers = ['Coach Budi', 'Coach Sarah', 'Coach Dimas'];
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
$tempTrainingData = [];

// --- HANDLER POST REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['submit_athlete'])) {
        $message = add_new_athlete($_POST);
        $athletes = $_SESSION['athletes']; // Refresh data
        $currentPage = 'list_athlete';
    }

    elseif (isset($_POST['calculate']) || isset($_POST['submit_training'])) {
        $athleteId = $_POST['athleteId'];
        
        $trainingDetails = [];
        if (isset($_POST['phase'])) {
            foreach ($_POST['phase'] as $index => $phaseName) {
                if (trim($phaseName) === '') continue;

                $trainingDetails[] = [
                    'phase' => $phaseName,
                    'duration' => (float)($_POST['duration'][$index] ?? 0),
                    'set' => $_POST['set'][$index] ?? 0,
                    'hrp' => $_POST['hrp'][$index] ?? 0,
                    'rest_after' => (float)($_POST['rest'][$index] ?? 0)
                ];
            }
        }
        $tempTrainingData = $trainingDetails;

        $calculatedResult = calculate_training_metrics($athleteId, $trainingDetails);
        
        foreach ($trainingDetails as $k => $v) {
            $trainingDetails[$k]['partialIntensity'] = $calculatedResult['partialIntensities'][$k] ?? 0;
        }

        if (isset($_POST['submit_training']) && !isset($calculatedResult['error'])) {
            $formData = [
                'date' => $_POST['date'],
                'observer' => $_POST['observer'],
                'manual_category' => $_POST['manual_category']
            ];

            $message = submit_training_revision($athleteId, $formData, $calculatedResult, $trainingDetails);
            if ($message === 'Data latihan berhasil disimpan!') {
                $calculatedResult = null;
                $tempTrainingData = [];
                $_POST = [];
                $athletes = $_SESSION['athletes']; 
            }
        }
        $currentPage = 'form';
    }
}

// --- PERSIAPAN DATA CHART ---
$stats = get_statistics($athletes); 
$performanceData = array_map(function($a) {
    return ['name' => explode(' ', $a['name'])[0], 'performa' => (float)$a['lastPerformance']];
}, $athletes);

$selectedSportFilterChart = $_GET['chart_sport_filter'] ?? '';
$athletesForPie = $athletes;
if (!empty($selectedSportFilterChart)) {
    $athletesForPie = array_filter($athletes, function($a) use ($selectedSportFilterChart) {
        return isset($a['sport']) && strcasecmp($a['sport'], $selectedSportFilterChart) === 0;
    });
}
$filteredIodCategories = count_iod_categories($athletesForPie);
$pieChartData = generate_pie_chart_data($filteredIodCategories);
$monthlyAvgChartData = generate_monthly_average_chart_data($athletes);

$topAthletes = [];
foreach ($athletes as $a) {
    $maxIod = 0;
    if (!empty($a['trainings'])) {
        foreach ($a['trainings'] as $t) {
            $val = isset($t['iod']) ? (float)$t['iod'] : ((isset($t['performance']) && is_numeric($t['performance'])) ? (float)$t['performance'] : 0);
            if ($val > $maxIod) $maxIod = $val;
        }
    }
    $lastPerf = (float)$a['lastPerformance'];
    if ($lastPerf > $maxIod) $maxIod = $lastPerf;
    if ($maxIod > 0) {
        $topAthletes[] = ['name' => $a['name'], 'max_iod' => $maxIod];
    }
}
usort($topAthletes, function($a, $b) { return $b['max_iod'] <=> $a['max_iod']; });
$top5Athletes = array_slice($topAthletes, 0, 5);
$top5ChartData = [['Atlet', 'Max IOD']];
foreach ($top5Athletes as $item) { $top5ChartData[] = [explode(' ', $item['name'])[0], $item['max_iod']]; }
$top5Json = json_encode($top5ChartData);

// --- FUNGSI CHART HELPER ---
function generate_google_chart_data($data) {
    $data_array = [['Atlet', 'IOD Terakhir']];
    foreach ($data as $item) { $data_array[] = [explode(' ', $item['name'])[0], (float)$item['performa']]; }
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
function generate_monthly_average_chart_data($athletes) {
    $monthlySums = []; $monthlyCounts = [];
    foreach ($athletes as $athlete) {
        if (!empty($athlete['trainings'])) {
            foreach ($athlete['trainings'] as $t) {
                $val = isset($t['iod']) ? (float)$t['iod'] : ((isset($t['performance']) && is_numeric($t['performance'])) ? (float)$t['performance'] : 0);
                if (isset($t['date']) && $val > 0) {
                    $monthKey = date('Y-m', strtotime($t['date']));
                    if (!isset($monthlySums[$monthKey])) { $monthlySums[$monthKey] = 0; $monthlyCounts[$monthKey] = 0; }
                    $monthlySums[$monthKey] += $val;
                    $monthlyCounts[$monthKey]++;
                }
            }
        }
    }
    ksort($monthlySums);
    $data_array = [['Bulan', 'Rata-rata IOD']];
    foreach ($monthlySums as $month => $sum) {
        $avg = $sum / $monthlyCounts[$month];
        $label = date('M Y', strtotime($month . '-01'));
        $data_array[] = [$label, $avg];
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
        const darkTextStyle = { color: '#94a3b8', fontName: 'Plus Jakarta Sans', fontSize: 12 };
        const chartBgColor = { fill: 'transparent' };
        const gridlinesColor = { color: '#334155' };
        
        function drawCharts() {
            if (document.getElementById('bar_chart_div')) drawBarChart();
            if (document.getElementById('line_chart_div')) drawLineChart();
            if (document.getElementById('pie_chart_div')) drawPieChart();
            if (document.getElementById('avg_iod_chart_div')) drawAvgIodChart();
            if (document.getElementById('top5_chart_div')) drawTop5Chart();
        }
        function drawBarChart() {
            var jsonData = <?php echo generate_google_chart_data($performanceData); ?>;
            var data = new google.visualization.arrayToDataTable(jsonData);
            var options = { title: 'Indeks Kesulitan (IOD) Atlet Terakhir', titleTextStyle: { color: '#f1f5f9', fontSize: 16, bold: true }, backgroundColor: chartBgColor, legend: { position: 'none' }, colors: ['#8b5cf6'], hAxis: { textStyle: darkTextStyle, gridlines: gridlinesColor }, vAxis: { title: 'Skor IOD', textStyle: darkTextStyle, titleTextStyle: darkTextStyle, gridlines: gridlinesColor, minValue: 0 } };
            var chart = new google.visualization.ColumnChart(document.getElementById('bar_chart_div'));
            chart.draw(data, options);
        }
        function drawLineChart() {
            var jsonData = <?php echo generate_history_chart_data($selectedAthlete['trainings'] ?? []); ?>;
            var data = new google.visualization.arrayToDataTable(jsonData);
            var options = { title: 'Perkembangan IOD (Index of Difficulty)', titleTextStyle: { color: '#f1f5f9', fontSize: 16 }, backgroundColor: chartBgColor, curveType: 'function', legend: { position: 'bottom', textStyle: darkTextStyle }, colors: ['#38bdf8'], hAxis: { textStyle: darkTextStyle, gridlines: gridlinesColor }, vAxis: { title: 'Skor IOD', textStyle: darkTextStyle, titleTextStyle: darkTextStyle, gridlines: gridlinesColor, minValue: 0 }, pointSize: 6 };
            var chart = new google.visualization.LineChart(document.getElementById('line_chart_div'));
            chart.draw(data, options);
        }
        function drawPieChart() {
            var jsonData = <?php echo $pieChartData; ?>;
            var data = new google.visualization.arrayToDataTable(jsonData);
            var options = { title: 'Distribusi Kategori IOD <?= $selectedSportFilterChart ? "($selectedSportFilterChart)" : "" ?>', titleTextStyle: { color: '#f1f5f9', fontSize: 14 }, backgroundColor: chartBgColor, sliceVisibilityThreshold: 0, colors: ['#db2777', '#dc2626', '#f97316', '#facc15', '#34d399', '#94a3b8'], legend: { position: 'right', alignment: 'center', textStyle: darkTextStyle }, pieSliceBorderColor: '#1e293b' };
            var chart = new google.visualization.PieChart(document.getElementById('pie_chart_div'));
            chart.draw(data, options);
        }
        function drawAvgIodChart() {
            var jsonData = <?php echo $monthlyAvgChartData; ?>;
            if (jsonData.length <= 1) { document.getElementById('avg_iod_chart_div').innerHTML = "<div style='text-align: center; padding: 4rem 1rem; color: #64748b;'><p>Belum ada cukup data.</p></div>"; return; }
            var data = new google.visualization.arrayToDataTable(jsonData);
            var options = { title: 'Tren Rata-rata IOD Global (Per Bulan)', titleTextStyle: { color: '#f1f5f9', fontSize: 16 }, backgroundColor: chartBgColor, curveType: 'function', legend: { position: 'bottom', textStyle: darkTextStyle }, colors: ['#10b981'], hAxis: { textStyle: darkTextStyle, gridlines: gridlinesColor }, vAxis: { title: 'Rata-rata IOD', textStyle: darkTextStyle, titleTextStyle: darkTextStyle, gridlines: gridlinesColor, minValue: 0 }, pointSize: 5, areaOpacity: 0.1 };
            var chart = new google.visualization.AreaChart(document.getElementById('avg_iod_chart_div'));
            chart.draw(data, options);
        }
        function drawTop5Chart() {
            var jsonData = <?php echo $top5Json; ?>;
            if (jsonData.length <= 1) { document.getElementById('top5_chart_div').innerHTML = "<div style='text-align: center; padding: 4rem 1rem; color: #64748b;'><p>Belum ada data latihan.</p></div>"; return; }
            var data = new google.visualization.arrayToDataTable(jsonData);
            var options = { title: 'Top 5 Atlet (Rekor IOD Tertinggi)', titleTextStyle: { color: '#f1f5f9', fontSize: 16 }, backgroundColor: chartBgColor, legend: { position: 'none' }, colors: ['#e11d48'], hAxis: { title: 'Skor IOD Tertinggi', textStyle: darkTextStyle, titleTextStyle: darkTextStyle, gridlines: gridlinesColor, minValue: 0 }, vAxis: { textStyle: darkTextStyle, gridlines: gridlinesColor } };
            var chart = new google.visualization.BarChart(document.getElementById('top5_chart_div'));
            chart.draw(data, options);
        }

        // --- JS UNTUK DYNAMIC TABLE ROW ---
        function addTrainingRow() {
            const tableBody = document.getElementById('training-rows');
            const rowCount = tableBody.rows.length;
            const row = document.createElement('tr');
            
            row.innerHTML = `
                <td>
                    <input type="text" name="phase[]" class="table-input" placeholder="Nama Latihan" required>
                </td>
                <td><input type="number" step="0.01" name="duration[]" class="table-input" placeholder="mnt" required></td>
                <td><input type="number" name="set[]" class="table-input" placeholder="1"></td>
                <td><input type="number" name="hrp[]" class="table-input"></td>
                <td><input type="number" step="0.01" name="rest[]" class="table-input" placeholder="mnt" value="0"></td>
                <td style="text-align:center;">
                    <button type="button" onclick="removeRow(this)" style="background:none; border:none; color:#ef4444; cursor:pointer; font-weight:bold;">X</button>
                </td>
            `;
            tableBody.appendChild(row);
        }

        function removeRow(btn) {
            const row = btn.parentNode.parentNode;
            const tableBody = document.getElementById('training-rows');
            if(tableBody.rows.length > 1) {
                row.parentNode.removeChild(row);
            } else {
                alert("Minimal satu baris latihan diperlukan.");
            }
        }
    </script>
</head>
<body>

    <header><div class="container"><h1>Human Indicator Overview Device</h1></div></header>

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
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="panel" style="margin-bottom: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                    <h3 style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; margin-bottom: 0.5rem;">Total Atlet</h3>
                    <p style="font-size: 2.5rem; font-weight: 800; margin: 0; color: var(--text-main); line-height: 1;"><?= $stats['totalAthletes'] ?></p>
                </div>
                <div class="panel" style="margin-bottom: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                    <h3 style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; margin-bottom: 0.5rem;">Total Pengamat</h3>
                    <p style="font-size: 2.5rem; font-weight: 800; margin: 0; color: var(--text-main); line-height: 1;"><?= count($allObservers) ?></p>
                </div>
                <div class="panel" style="margin-bottom: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                    <h3 style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; margin-bottom: 0.5rem;">Rata-rata IOD Global</h3>
                    <p style="font-size: 2.5rem; font-weight: 800; margin: 0; color: var(--primary); line-height: 1;"><?= $stats['avgIOD'] ?></p>
                </div>
            </div>
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
                            <select name="chart_sport_filter" onchange="this.form.submit()" class="form-select" style="padding: 0.25rem 2rem 0.25rem 0.5rem; font-size: 0.875rem;">
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
                        <div style="text-align: center; padding: 4rem 1rem; color: #64748b;"><p>Belum ada data latihan.</p></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="panel" style="margin-top: 1.5rem;">
                <h3>Perkembangan Rata-rata IOD Global</h3>
                <div id="avg_iod_chart_div" style="width: 100%; height: 350px;"></div>
            </div>
            <div class="panel" style="margin-top: 1.5rem;">
                <h3>Top 5 Atlet (Rekor IOD Tertinggi)</h3>
                <div id="top5_chart_div" style="width: 100%; height: 350px;"></div>
            </div>
            
        <?php elseif ($currentPage === 'list_athlete'): ?>
            <?php
            $filterSport = $_GET['filter_sport'] ?? '';
            $filterObserver = $_GET['filter_observer'] ?? '';
            $filteredAthletes = $athletes;
            if (!empty($filterSport)) {
                $filteredAthletes = array_filter($filteredAthletes, function($a) use ($filterSport) {
                    return isset($a['sport']) && strcasecmp($a['sport'], $filterSport) === 0;
                });
            }
            if (!empty($filterObserver)) {
                $filteredAthletes = array_filter($filteredAthletes, function($a) use ($filterObserver) {
                    if (empty($a['trainings'])) return false; 
                    foreach ($a['trainings'] as $t) {
                        if (isset($t['observer']) && $t['observer'] === $filterObserver) { return true; }
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
                                    <option value="<?= htmlspecialchars($sport) ?>" <?= $filterSport === $sport ? 'selected' : '' ?>><?= htmlspecialchars($sport) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <select name="filter_observer" class="form-select" style="width: 150px;">
                                <option value="">- Semua Pengamat -</option>
                                <?php foreach ($allObservers as $obs): ?>
                                    <option value="<?= htmlspecialchars($obs) ?>" <?= $filterObserver === $obs ? 'selected' : '' ?>><?= htmlspecialchars($obs) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">Filter</button>
                        <?php if($filterSport || $filterObserver): ?>
                            <a href="?page=list_athlete" class="btn" style="background: #334155; color: white; padding: 0.5rem 1rem;">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="table-container">
                    <table>
                        <thead><tr><th>Nama</th><th>Gender</th><th>Cabor</th><th>Asal</th><th>IOD Terakhir</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php if(empty($filteredAthletes)): ?>
                                <tr><td colspan="6" style="text-align: center; padding: 2rem;">Tidak ada data atlet yang sesuai filter.</td></tr>
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
                                            <span style="color: #64748b;">-</span>
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
                    <button type="submit" name="submit_athlete" class="btn btn-primary" style="margin-top: 1rem;">Simpan</button>
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
                            <label>Klasifikasi Latihan (Manual)</label>
                            <select name="manual_category" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <option value="Ringan" <?= (isset($_POST['manual_category']) && $_POST['manual_category'] == 'Ringan') ? 'selected' : '' ?>>Ringan</option>
                                <option value="Sedang" <?= (isset($_POST['manual_category']) && $_POST['manual_category'] == 'Sedang') ? 'selected' : '' ?>>Sedang</option>
                                <option value="Berat" <?= (isset($_POST['manual_category']) && $_POST['manual_category'] == 'Berat') ? 'selected' : '' ?>>Berat</option>
                            </select>
                        </div>
                    </div>
                    
                 

                    <hr style="margin: 2rem 0; border: 0; border-top: 1px solid var(--border);">

                    <div class="table-container">
                        <table class="input-table">
                            <thead>
                                <tr>
                                    <th width="30%">Bentuk Latihan (Fase)</th>
                                    <th>Durasi Exercise (mnt)</th>
                                    <th width="10%">Set</th>
                                    <th width="10%">HRP</th>
                                    <th width="15%">Rest (mnt)</th>
                                    <th width="5%"></th>
                                </tr>
                            </thead>
                            <tbody id="training-rows">
                                <?php 
                                $rowsToRender = !empty($tempTrainingData) ? $tempTrainingData : [['phase'=>'','duration'=>'','set'=>'','hrp'=>'','rest_after'=>'']];
                                foreach ($rowsToRender as $row): 
                                ?>
                                    <tr>
                                        <td>
                                            <input type="text" name="phase[]" value="<?= htmlspecialchars($row['phase']) ?>" class="table-input" placeholder="Nama Latihan" required>
                                        </td>
                                        <td><input type="number" step="0.01" name="duration[]" value="<?= $row['duration'] ?>" class="table-input" placeholder="mnt" required></td>
                                        <td><input type="number" name="set[]" value="<?= $row['set'] ?>" class="table-input" placeholder="1"></td>
                                        <td><input type="number" name="hrp[]" value="<?= $row['hrp'] ?>" class="table-input"></td>
                                        <td><input type="number" step="0.01" name="rest[]" value="<?= $row['rest_after'] ?? $row['rest'] ?? 0 ?>" class="table-input" placeholder="mnt"></td>
                                        <td style="text-align:center;">
                                            <button type="button" onclick="removeRow(this)" style="background:none; border:none; color:#ef4444; cursor:pointer; font-weight:bold;">X</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div style="margin-top: 10px;">
                            <button type="button" onclick="addTrainingRow()" class="btn" style="background-color: #475569; color: white; padding: 0.5rem 1rem; font-size: 0.875rem;">+ Tambah Baris</button>
                        </div>
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
                        <div style="text-align: center; margin-bottom: 2rem; border-bottom: 1px dashed var(--border); padding-bottom: 1.5rem;">
                            <p style="font-size: 1rem; color: var(--text-muted); margin-bottom: 0.5rem;">IOD (Index of Difficulty)</p>
                            <p class="iod-highlight"><?= number_format($calculatedResult['iod'], 2) ?></p>
                            
                            <span class="iod-badge <?= getBadgeClass($calculatedResult['iodClass']) ?>">
                                <?= $calculatedResult['iodClass'] ?>
                            </span>
                        </div>
                        <div class="form-grid">
                            <div class="result-item" style="border: 2px solid #3b82f6; background-color: #eff6ff;">
                                <p class="label" style="color: #1d4ed8;">Volume Relatif (Total Time)</p>
                                <p class="value" style="color: #1e40af;"><?= number_format($calculatedResult['volRelatif'], 2) ?> mnt</p>
                            </div>
                            
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
                
                <div style="background: rgba(15, 23, 42, 0.5); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid var(--border);">
                    <div class="history-grid">
                        <div class="history-item"><p class="label">Gender</p><p class="value"><?= htmlspecialchars($selectedAthlete['gender'] ?? '-') ?></p></div>
                        <div class="history-item"><p class="label">Usia</p><p class="value"><?= htmlspecialchars($selectedAthlete['age'] ?? '-') ?> th</p></div>
                        <div class="history-item"><p class="label">Asal</p><p class="value"><?= htmlspecialchars($selectedAthlete['origin'] ?? '-') ?></p></div>
                        <div class="history-item"><p class="label">Cabor</p><p class="value"><?= htmlspecialchars($selectedAthlete['sport'] ?? '-') ?></p></div>
                        <div class="history-item"><p class="label">Berat</p><p class="value"><?= htmlspecialchars($selectedAthlete['weight'] ?? '-') ?> kg</p></div>
                        <div class="history-item"><p class="label">Tinggi</p><p class="value"><?= htmlspecialchars($selectedAthlete['height'] ?? '-') ?> cm</p></div>
                    </div>
                </div>
                
                <hr style="margin: 1rem 0; border: 0; border-top: 1px solid var(--border);">
                
                <?php if (!empty($selectedAthlete['trainings'])): ?>
                    <div id="line_chart_div" style="width: 100%; height: 300px; margin-bottom: 2rem;"></div>
                <?php else: ?>
                    <p style="text-align: center; padding: 3rem; background: rgba(15, 23, 42, 0.3); border-radius: 0.5rem; color: var(--text-muted);">Belum ada riwayat latihan untuk atlet ini.</p>
                <?php endif; ?>

                <h3>Riwayat Latihan</h3>
                <?php foreach (array_reverse($selectedAthlete['trainings']) as $t): ?>
                    <div class="training-detail" style="margin-bottom: 1.5rem; border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
                        <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(30, 41, 59, 0.5); padding: 1rem; border-bottom: 1px solid var(--border);">
                           <div>
                            <strong style="color: var(--primary); font-size: 1.1rem;">
                                <?= $t['date'] ?>
                            </strong> 
                            <span style="color: var(--text-muted); margin-left: 5px;">
                                | Observer: <?= $t['observer'] ?>
                            </span>

                            <br>

                            <?php 
                                $manualLabel = $t['manual_category'] ?? '-';
                                
                                // Logika Warna Badge
                                $badgeColorClass = 'badge-very-low'; // Default
                                if ($manualLabel === 'Berat') {
                                    $badgeColorClass = 'badge-hard';
                                } elseif ($manualLabel === 'Sedang') {
                                    $badgeColorClass = 'badge-medium';
                                } elseif ($manualLabel === 'Ringan') {
                                    $badgeColorClass = 'badge-low';
                                }
                            ?>
                            <span class="iod-badge <?= $badgeColorClass ?>" style="font-size: 0.75rem; padding: 3px 8px; margin-top: 5px;">
                                <?= htmlspecialchars($manualLabel) ?>
                            </span>
                        </div>
                            <div style="text-align: right;">
                                <span style="font-size: 0.8rem; color: var(--text-muted);">IOD SCORE</span><br>
                                <span style="font-size: 1.5rem; font-weight: bold; color: white;">
                                    <?= isset($t['iod']) ? number_format($t['iod'], 2) : ($t['performance'] ?? '-') ?>
                                </span>
                                <?php if(isset($t['iodClass'])): ?>
                                    <br><span class="iod-badge <?= getBadgeClass($t['iodClass']) ?>" style="font-size: 0.7rem; padding: 2px 8px; margin-top: 0;">
                                        <?= $t['iodClass'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                         <?php if(isset($t['iod'])): ?>
                        <div class="history-grid" style="margin-bottom: 1rem;">
                             <div class="history-item"><p class="label">Vol Relatif</p><p class="value" style="color:#2563eb;"><?= isset($t['volRelatif']) ? number_format($t['volRelatif'], 2) : '-' ?></p></div>
                            <div class="history-item"><p class="label">Abs. Density</p><p class="value"><?= number_format($t['absoluteDensity'], 2) ?>%</p></div>
                            <div class="history-item"><p class="label">Ov. Intensity</p><p class="value"><?= number_format($t['overallIntensity'], 2) ?>%</p></div>
                            <div class="history-item"><p class="label">Vol Absolute</p><p class="value"><?= number_format($t['volAbsolute'], 2) ?></p></div>
                        </div>
                        
                        <?php endif; ?>
                        <?php if (isset($t['details'])): ?>
                            <div class="table-container" style="border: none; border-radius: 0;">
                                <table class="input-table" style="font-size: 0.85rem; border: none;">
                                    <tr style="background: rgba(15, 23, 42, 0.5);">
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
                                            <td style="color: #fb923c; font-weight: bold;"><?= isset($d['rest_after']) ? $d['rest_after'] : 0 ?>'</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>