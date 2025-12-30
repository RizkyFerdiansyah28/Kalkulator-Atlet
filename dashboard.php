<?php
include 'functions.php';

$stats = get_statistics($athletes); 
$performanceData = array_map(function($a) {
    return ['name' => explode(' ', $a['name'])[0], 'performa' => (float)$a['lastPerformance']];
}, $athletes);

$selectedSportFilterChart = $_GET['chart_sport_filter'] ?? '';
$filteredIodCategories = count_iod_categories($selectedSportFilterChart);
$pieChartData = generate_pie_chart_data($filteredIodCategories);
$monthlyAvgChartData = generate_monthly_average_chart_data($athletes);

$uniqueSports = [];
if (!empty($athletes)) {
    $sports = array_column($athletes, 'sport');
    $uniqueSports = array_unique(array_filter($sports));
    sort($uniqueSports);
}

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
            $data_array[] = [$category, (int)$data[$category]];
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
        
        function drawCharts() {
            if (document.getElementById('bar_chart_div')) drawBarChart();
            if (document.getElementById('pie_chart_div')) drawPieChart();
            if (document.getElementById('avg_iod_chart_div')) drawAvgIodChart();
            if (document.getElementById('top5_chart_div')) drawTop5Chart();
        }
        
        // ANIMASI COUNTER UNTUK KPI CARDS
        function animateCounter(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const current = Math.floor(progress * (end - start) + start);
                element.textContent = current;
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    element.textContent = end;
                }
            };
            window.requestAnimationFrame(step);
        }

        // Animasi counter untuk angka desimal (seperti rata-rata IOD)
        function animateDecimalCounter(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const current = (progress * (end - start) + start).toFixed(2);
                element.textContent = current;
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        // Jalankan animasi saat halaman load
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('.stat-value');
            counters.forEach((counter, index) => {
                const target = parseFloat(counter.textContent);
                if (!isNaN(target)) {
                    counter.textContent = '0';
                    setTimeout(() => {
                        if (target % 1 === 0) {
                            animateCounter(counter, 0, target, 1500);
                        } else {
                            animateDecimalCounter(counter, 0, target, 1500);
                        }
                    }, index * 150); // Stagger animation
                }
            });
        });
        
        function drawBarChart() {
            var jsonData = <?php echo generate_google_chart_data($performanceData); ?>;
            var data = new google.visualization.arrayToDataTable(jsonData);
            var options = { 
                titleTextStyle: { color: '#0f172a', fontSize: 14, bold: true },
                backgroundColor: 'transparent',
                legend: { position: 'none' }, 
                colors: ['#1e3a8a'],
                hAxis: { textStyle: { color: '#64748b', fontSize: 11 }, gridlines: { color: '#f1f5f9' } },
                vAxis: { 
                    title: 'Skor IOD', 
                    textStyle: { color: '#64748b', fontSize: 11 }, 
                    titleTextStyle: { color: '#475569', fontSize: 12 }, 
                    gridlines: { color: '#f1f5f9' }, 
                    minValue: 0 
                },
                chartArea: { width: '85%', height: '70%' },
                bar: { groupWidth: '70%' }
            };
            var chart = new google.visualization.ColumnChart(document.getElementById('bar_chart_div'));
            chart.draw(data, options);
        }

        function drawPieChart() {
            var jsonData = <?php echo $pieChartData; ?>;
            var data = new google.visualization.arrayToDataTable(jsonData);
            var pieColors = ['#1e3a8a', '#dc2626', '#d97706', '#eab308', '#059669', '#64748b']; 
            var options = { 
                titleTextStyle: { color: '#0f172a', fontSize: 14, bold: true },
                backgroundColor: 'transparent',
                sliceVisibilityThreshold: 0, 
                colors: pieColors,
                legend: { position: 'right', alignment: 'center', textStyle: { color: '#475569', fontSize: 11 } },
                chartArea: { width: '90%', height: '80%' },
                pieSliceText: 'value'
            };
            var chart = new google.visualization.PieChart(document.getElementById('pie_chart_div'));
            chart.draw(data, options);
        }

        function drawAvgIodChart() {
            var jsonData = <?php echo $monthlyAvgChartData; ?>;
            if (jsonData.length <= 1) {
                document.getElementById('avg_iod_chart_div').innerHTML = "<div style='text-align: center; padding: 4rem 1rem; color: #64748b; font-size: 0.875rem;'><p>Data tidak tersedia</p></div>";
                return;
            }
            var data = new google.visualization.arrayToDataTable(jsonData);
            var options = { 
                titleTextStyle: { color: '#0f172a', fontSize: 14, bold: true },
                backgroundColor: 'transparent',
                curveType: 'function', 
                legend: { position: 'none' }, 
                colors: ['#059669'], 
                hAxis: { textStyle: { color: '#64748b', fontSize: 11 }, gridlines: { color: '#f1f5f9' } },
                vAxis: { 
                    title: 'Rata-rata IOD', 
                    textStyle: { color: '#64748b', fontSize: 11 }, 
                    titleTextStyle: { color: '#475569', fontSize: 12 }, 
                    gridlines: { color: '#f1f5f9' }, 
                    minValue: 0 
                },
                pointSize: 4,
                lineWidth: 3,
                chartArea: { width: '90%', height: '70%' }
            };
            var chart = new google.visualization.LineChart(document.getElementById('avg_iod_chart_div'));
            chart.draw(data, options);
        }

        function drawTop5Chart() {
            var jsonData = <?php echo $top5Json; ?>;
            if (jsonData.length <= 1) {
                document.getElementById('top5_chart_div').innerHTML = "<div style='text-align: center; padding: 4rem 1rem; color: #64748b; font-size: 0.875rem;'><p>Data tidak tersedia</p></div>";
                return;
            }
            var data = new google.visualization.arrayToDataTable(jsonData);
            var options = { 
                titleTextStyle: { color: '#0f172a', fontSize: 14, bold: true },
                backgroundColor: 'transparent',
                legend: { position: 'none' }, 
                colors: ['#dc2626'], 
                hAxis: { 
                    title: 'Skor IOD Tertinggi', 
                    textStyle: { color: '#64748b', fontSize: 11 }, 
                    titleTextStyle: { color: '#475569', fontSize: 12 }, 
                    gridlines: { color: '#f1f5f9' }, 
                    minValue: 0 
                },
                vAxis: { textStyle: { color: '#475569', fontSize: 11 }, gridlines: { color: 'transparent' } },
                chartArea: { width: '80%', height: '70%' },
                bar: { groupWidth: '60%' }
            };
            var chart = new google.visualization.BarChart(document.getElementById('top5_chart_div'));
            chart.draw(data, options);
        }
    </script>
</head>
<body>
    <header>
        <div class="container">
            <h1>Human Indicator Overview Device</h1>
        </div>
    </header>

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
        <!-- KPI CARDS -->
        <div class="stats-grid-colorful">
            <div class="stat-card-modern gradient-purple">
                <div class="stat-content">
                    <svg style="width: 32px; height: 32px; margin-bottom: 0.75rem; opacity: 0.9;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <p class="stat-label">Total Atlet</p>
                    <p class="stat-value"><?= $stats['totalAthletes'] ?></p>
                    <p class="stat-desc">
                        Atlet terdaftar
                        <span class="trend-badge trend-up" style="margin-left: 0.5rem;">
                            <svg style="width: 12px; height: 12px; display: inline;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </p>
                </div>
                <svg class="sparkline" width="100%" height="40" style="margin-top: 0.75rem; opacity: 0.3;">
                    <polyline fill="none" stroke="currentColor" stroke-width="2" points="0,30 20,25 40,28 60,20 80,22 100,15"></polyline>
                </svg>
            </div>

            <div class="stat-card-modern gradient-blue">
                <div class="stat-content">
                    <svg style="width: 32px; height: 32px; margin-bottom: 0.75rem; opacity: 0.9;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    <p class="stat-label">Total Pengamat</p>
                    <p class="stat-value"><?= count($allObservers) ?></p>
                    <p class="stat-desc">
                        Observer aktif
                        <span class="trend-badge trend-neutral" style="margin-left: 0.5rem;">
                            <svg style="width: 12px; height: 12px; display: inline;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </p>
                </div>
                <svg class="sparkline" width="100%" height="40" style="margin-top: 0.75rem; opacity: 0.3;">
                    <polyline fill="none" stroke="currentColor" stroke-width="2" points="0,25 20,25 40,25 60,25 80,25 100,25"></polyline>
                </svg>
            </div>

            <div class="stat-card-modern gradient-green">
                <div class="stat-content">
                    <svg style="width: 32px; height: 32px; margin-bottom: 0.75rem; opacity: 0.9;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <p class="stat-label">Rata-rata IOD</p>
                    <p class="stat-value"><?= $stats['avgIOD'] ?></p>
                    <p class="stat-desc">
                        Index of Difficulty
                        <span class="trend-badge trend-up" style="margin-left: 0.5rem;">
                            <svg style="width: 12px; height: 12px; display: inline;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </p>
                </div>
                <svg class="sparkline" width="100%" height="40" style="margin-top: 0.75rem; opacity: 0.3;">
                    <polyline fill="none" stroke="currentColor" stroke-width="2" points="0,35 20,32 40,28 60,25 80,20 100,15"></polyline>
                </svg>
            </div>

            <div class="stat-card-modern gradient-orange">
                <div class="stat-content">
                    <svg style="width: 32px; height: 32px; margin-bottom: 0.75rem; opacity: 0.9;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    <p class="stat-label">Total Latihan</p>
                    <p class="stat-value"><?= $stats['totalTrainings'] ?></p>
                    <p class="stat-desc">
                        Sesi tercatat
                        <span class="trend-badge trend-up" style="margin-left: 0.5rem;">
                            <svg style="width: 12px; height: 12px; display: inline;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </p>
                </div>
                <svg class="sparkline" width="100%" height="40" style="margin-top: 0.75rem; opacity: 0.3;">
                    <polyline fill="none" stroke="currentColor" stroke-width="2" points="0,30 20,28 40,25 60,22 80,18 100,12"></polyline>
                </svg>
            </div>
        </div>

        <!-- COMPARISON CARDS -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
            <?php
            // Hitung data minggu ini vs minggu lalu
            $thisWeekStart = date('Y-m-d', strtotime('monday this week'));
            $lastWeekStart = date('Y-m-d', strtotime('monday last week'));
            $lastWeekEnd = date('Y-m-d', strtotime('sunday last week'));
            
            $thisWeekTrainings = 0;
            $lastWeekTrainings = 0;
            $thisWeekAvgIOD = 0;
            $lastWeekAvgIOD = 0;
            $thisWeekIODCount = 0;
            $lastWeekIODCount = 0;
            
            foreach ($athletes as $a) {
                if (!empty($a['trainings'])) {
                    foreach ($a['trainings'] as $t) {
                        $tDate = $t['date'] ?? '';
                        $iodVal = isset($t['iod']) ? (float)$t['iod'] : ((isset($t['performance']) && is_numeric($t['performance'])) ? (float)$t['performance'] : 0);
                        
                        if ($tDate >= $thisWeekStart) {
                            $thisWeekTrainings++;
                            if ($iodVal > 0) {
                                $thisWeekAvgIOD += $iodVal;
                                $thisWeekIODCount++;
                            }
                        } elseif ($tDate >= $lastWeekStart && $tDate <= $lastWeekEnd) {
                            $lastWeekTrainings++;
                            if ($iodVal > 0) {
                                $lastWeekAvgIOD += $iodVal;
                                $lastWeekIODCount++;
                            }
                        }
                    }
                }
            }
            
            $thisWeekAvgIOD = $thisWeekIODCount > 0 ? $thisWeekAvgIOD / $thisWeekIODCount : 0;
            $lastWeekAvgIOD = $lastWeekIODCount > 0 ? $lastWeekAvgIOD / $lastWeekIODCount : 0;
            
            $trainingChange = $lastWeekTrainings > 0 ? (($thisWeekTrainings - $lastWeekTrainings) / $lastWeekTrainings) * 100 : 0;
            $iodChange = $lastWeekAvgIOD > 0 ? (($thisWeekAvgIOD - $lastWeekAvgIOD) / $lastWeekAvgIOD) * 100 : 0;
            
            $activeAthletes = 0;
            foreach ($athletes as $a) {
                if (!empty($a['trainings'])) {
                    foreach ($a['trainings'] as $t) {
                        if (isset($t['date']) && $t['date'] >= $thisWeekStart) {
                            $activeAthletes++;
                            break;
                        }
                    }
                }
            }
            ?>
            
            <!-- Comparison Card 1: Sesi Latihan -->
            <div class="comparison-card">
                <div class="comparison-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <div class="comparison-content">
                    <p class="comparison-label">Sesi Latihan Minggu Ini</p>
                    <div style="display: flex; align-items: baseline; gap: 0.5rem; margin: 0.5rem 0;">
                        <p class="comparison-value"><?= $thisWeekTrainings ?></p>
                        <span class="comparison-change <?= $trainingChange >= 0 ? 'positive' : 'negative' ?>">
                            <?= $trainingChange >= 0 ? '↑' : '↓' ?> <?= abs(number_format($trainingChange, 1)) ?>%
                        </span>
                    </div>
                    <p class="comparison-subtitle">vs minggu lalu: <?= $lastWeekTrainings ?> sesi</p>
                </div>
            </div>
            
            <!-- Comparison Card 2: Rata-rata IOD -->
            <div class="comparison-card">
                <div class="comparison-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div class="comparison-content">
                    <p class="comparison-label">Rata-rata IOD Minggu Ini</p>
                    <div style="display: flex; align-items: baseline; gap: 0.5rem; margin: 0.5rem 0;">
                        <p class="comparison-value"><?= number_format($thisWeekAvgIOD, 1) ?></p>
                        <span class="comparison-change <?= $iodChange >= 0 ? 'positive' : 'negative' ?>">
                            <?= $iodChange >= 0 ? '↑' : '↓' ?> <?= abs(number_format($iodChange, 1)) ?>%
                        </span>
                    </div>
                    <p class="comparison-subtitle">vs minggu lalu: <?= number_format($lastWeekAvgIOD, 1) ?></p>
                </div>
            </div>
            
            <!-- Comparison Card 3: Atlet Aktif -->
            <div class="comparison-card">
                <div class="comparison-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="comparison-content">
                    <p class="comparison-label">Atlet Aktif Minggu Ini</p>
                    <div style="display: flex; align-items: baseline; gap: 0.5rem; margin: 0.5rem 0;">
                        <p class="comparison-value"><?= $activeAthletes ?></p>
                        <span class="comparison-subtitle" style="font-size: 0.75rem; color: var(--text-muted);">
                            dari <?= $stats['totalAthletes'] ?> atlet
                        </span>
                    </div>
                    <div class="mini-progress-bar">
                        <div class="mini-progress-fill" style="width: <?= $stats['totalAthletes'] > 0 ? ($activeAthletes / $stats['totalAthletes']) * 100 : 0 ?>%; background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CHARTS GRID -->
        <div class="chart-grid">
            <div class="panel chart-panel chart-purple">
                <div class="panel-header">
                    <h3>
                        <svg style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Performa IOD Atlet
                    </h3>
                    <p>Skor IOD terakhir dari setiap atlet</p>
                </div>
                <div id="bar_chart_div" style="width: 100%; height: 320px;"></div>
            </div>
            
            <div class="panel chart-panel chart-blue">
                <div class="panel-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h3>
                            <svg style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                            </svg>
                            Distribusi Kategori IOD
                        </h3>
                        <p>Pembagian tingkat kesulitan latihan</p>
                    </div>
                    <form method="GET" action="" style="margin: 0;">
                        <select name="chart_sport_filter" onchange="this.form.submit()" class="form-select" style="padding: 0.5rem 2rem 0.5rem 0.75rem; font-size: 0.813rem; width: auto;">
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
                    <div id="pie_chart_div" style="width: 100%; height: 320px;"></div>
                <?php else: ?>
                    <div style="text-align: center; padding: 4rem 1rem; color: #64748b; font-size: 0.875rem;">
                        <p>Data tidak tersedia</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel chart-panel chart-green">
            <div class="panel-header">
                <h3>
                    <svg style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    Tren Perkembangan IOD
                </h3>
                <p>Rata-rata IOD global per bulan</p>
            </div>
            <div id="avg_iod_chart_div" style="width: 100%; height: 320px;"></div>
        </div>

        <div class="panel chart-panel chart-red">
            <div class="panel-header">
                <h3>
                    <svg style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                    </svg>
                    Top 5 Atlet Terbaik
                </h3>
                <p>Atlet dengan rekor IOD tertinggi</p>
            </div>
            <div id="top5_chart_div" style="width: 100%; height: 320px;"></div>
        </div>
    </main>
</body>
</html>