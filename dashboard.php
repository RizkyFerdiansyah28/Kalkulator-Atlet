<?php
include 'functions.php';

// --- Logika Data Dashboard & Chart ---
$stats = get_statistics($athletes); 

// Data untuk Bar Chart
$performanceData = array_map(function($a) {
    return ['name' => explode(' ', $a['name'])[0], 'performa' => (float)$a['lastPerformance']];
}, $athletes);

// Filter Data untuk Pie Chart
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

// Data Pendukung Dropdown Filter Chart
$uniqueSports = [];
if (!empty($athletes)) {
    $sports = array_column($athletes, 'sport');
    $uniqueSports = array_unique(array_filter($sports));
    sort($uniqueSports);
}

// Logic Top 5 Atlet
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
usort($topAthletes, function($a, $b) {
    return $b['max_iod'] <=> $a['max_iod'];
});
$top5Athletes = array_slice($topAthletes, 0, 5);
$top5ChartData = [['Atlet', 'Max IOD']];
foreach ($top5Athletes as $item) {
    $top5ChartData[] = [explode(' ', $item['name'])[0], $item['max_iod']];
}
$top5Json = json_encode($top5ChartData);

// Fungsi Helper Chart (Sama seperti sebelumnya)
function generate_google_chart_data($data) {
    $data_array = [['Atlet', 'IOD Terakhir']];
    foreach ($data as $item) { 
        $data_array[] = [explode(' ', $item['name'])[0], (float)$item['performa']]; 
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
    $monthlySums = [];
    $monthlyCounts = [];
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

// Logic Pengamat Global
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Manajemen Atlet</title>
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
            if (document.getElementById('pie_chart_div')) drawPieChart();
            if (document.getElementById('avg_iod_chart_div')) drawAvgIodChart();
            if (document.getElementById('top5_chart_div')) drawTop5Chart();
        }
        
        function drawBarChart() {
            var jsonData = <?php echo generate_google_chart_data($performanceData); ?>;
            var data = new google.visualization.arrayToDataTable(jsonData);
            var options = { 
                title: 'Indeks Kesulitan (IOD) Atlet Terakhir',
                titleTextStyle: { color: '#f1f5f9', fontSize: 16, bold: true },
                backgroundColor: chartBgColor,
                legend: { position: 'none' }, 
                colors: ['#8b5cf6'],
                hAxis: { textStyle: darkTextStyle, gridlines: gridlinesColor },
                vAxis: { title: 'Skor IOD', textStyle: darkTextStyle, titleTextStyle: darkTextStyle, gridlines: gridlinesColor, minValue: 0 }
            };
            var chart = new google.visualization.ColumnChart(document.getElementById('bar_chart_div'));
            chart.draw(data, options);
        }

        function drawPieChart() {
            var jsonData = <?php echo $pieChartData; ?>;
            var data = new google.visualization.arrayToDataTable(jsonData);
            var pieColors = ['#db2777', '#dc2626', '#f97316', '#facc15', '#34d399', '#94a3b8']; 
            var options = { 
                title: 'Distribusi Kategori IOD <?= $selectedSportFilterChart ? "($selectedSportFilterChart)" : "" ?>', 
                titleTextStyle: { color: '#f1f5f9', fontSize: 14 },
                backgroundColor: chartBgColor,
                sliceVisibilityThreshold: 0, 
                colors: pieColors,
                legend: { position: 'right', alignment: 'center', textStyle: darkTextStyle },
                pieSliceBorderColor: '#1e293b'
            };
            var chart = new google.visualization.PieChart(document.getElementById('pie_chart_div'));
            chart.draw(data, options);
        }

        function drawAvgIodChart() {
            var jsonData = <?php echo $monthlyAvgChartData; ?>;
            if (jsonData.length <= 1) {
                document.getElementById('avg_iod_chart_div').innerHTML = "<div style='text-align: center; padding: 4rem 1rem; color: #64748b;'><p>Belum ada cukup data.</p></div>";
                return;
            }
            var data = new google.visualization.arrayToDataTable(jsonData);
            var options = { 
                title: 'Tren Rata-rata IOD Global (Per Bulan)', 
                titleTextStyle: { color: '#f1f5f9', fontSize: 16 },
                backgroundColor: chartBgColor,
                curveType: 'function', 
                legend: { position: 'bottom', textStyle: darkTextStyle }, 
                colors: ['#10b981'], 
                hAxis: { textStyle: darkTextStyle, gridlines: gridlinesColor },
                vAxis: { title: 'Rata-rata IOD', textStyle: darkTextStyle, titleTextStyle: darkTextStyle, gridlines: gridlinesColor, minValue: 0 },
                pointSize: 5,
                areaOpacity: 0.1
            };
            var chart = new google.visualization.AreaChart(document.getElementById('avg_iod_chart_div'));
            chart.draw(data, options);
        }

        function drawTop5Chart() {
            var jsonData = <?php echo $top5Json; ?>;
            if (jsonData.length <= 1) {
                document.getElementById('top5_chart_div').innerHTML = "<div style='text-align: center; padding: 4rem 1rem; color: #64748b;'><p>Belum ada data latihan.</p></div>";
                return;
            }
            var data = new google.visualization.arrayToDataTable(jsonData);
            var options = { 
                title: 'Top 5 Atlet (Rekor IOD Tertinggi)',
                titleTextStyle: { color: '#f1f5f9', fontSize: 16 },
                backgroundColor: chartBgColor,
                legend: { position: 'none' }, 
                colors: ['#e11d48'], 
                hAxis: { title: 'Skor IOD Tertinggi', textStyle: darkTextStyle, titleTextStyle: darkTextStyle, gridlines: gridlinesColor, minValue: 0 },
                vAxis: { textStyle: darkTextStyle, gridlines: gridlinesColor }
            };
            var chart = new google.visualization.BarChart(document.getElementById('top5_chart_div'));
            chart.draw(data, options);
        }
    </script>
</head>
<body>
    <header><div class="container"><h1>Human Indicator Overview Device</h1></div></header>

    <nav>
        <div class="container">
            <div class="nav-buttons">
                <a href="dashboard.php" class="nav-button active">Dashboard</a>
                <a href="list_athlete.php" class="nav-button">Daftar Atlet</a>
                <a href="add_athlete.php" class="nav-button">Input Atlet</a>
                <a href="input_training.php" class="nav-button">Input Latihan</a>
            </div>
        </div>
    </nav>

    <main class="container">
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
                    <div style="text-align: center; padding: 4rem 1rem; color: #64748b;">
                        <p>Belum ada data latihan.</p>
                    </div>
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
    </main>
</body>
</html>