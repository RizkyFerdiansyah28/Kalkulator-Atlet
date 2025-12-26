<?php
include 'functions.php';

$selectedAthlete = null;
if (isset($_GET['athlete_id'])) {
    $athleteId = (int)$_GET['athlete_id'];
    if (isset($athletes[$athleteId])) {
        $selectedAthlete = $athletes[$athleteId];
    }
}

if (!$selectedAthlete) {
    echo "Atlet tidak ditemukan. <a href='list_athlete.php'>Kembali</a>";
    exit;
}

function getBadgeClass($status) {
    $slug = strtolower(str_replace(' ', '-', $status));
    return "badge-$slug";
}

function generate_history_chart_data($trainings) {
    $data_array = [['Tanggal', 'Skor IOD']];
    foreach ($trainings as $t) {
        $val = isset($t['iod']) ? (float)$t['iod'] : ((isset($t['performance']) && is_numeric($t['performance'])) ? (float)$t['performance'] : 0);
        $data_array[] = [$t['date'], $val];
    }
    return json_encode($data_array);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Atlet - <?= htmlspecialchars($selectedAthlete['name']) ?></title>
    <link rel="stylesheet" href="style.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart']});
        google.charts.setOnLoadCallback(drawCharts);
        
        const darkTextStyle = { color: '#94a3b8', fontName: 'Plus Jakarta Sans', fontSize: 12 };
        const chartBgColor = { fill: 'transparent' };
        const gridlinesColor = { color: '#334155' };

        function drawCharts() {
            drawLineChart();
        }
        
        function drawLineChart() {
            var jsonData = <?php echo generate_history_chart_data($selectedAthlete['trainings'] ?? []); ?>;
            var data = new google.visualization.arrayToDataTable(jsonData);
            
            var options = { 
                title: 'Perkembangan IOD (Index of Difficulty)', 
                titleTextStyle: { color: '#f1f5f9', fontSize: 16 },
                backgroundColor: chartBgColor,
                curveType: 'function', 
                legend: { position: 'bottom', textStyle: darkTextStyle }, 
                colors: ['#38bdf8'], 
                hAxis: { textStyle: darkTextStyle, gridlines: gridlinesColor },
                vAxis: { title: 'Skor IOD', textStyle: darkTextStyle, titleTextStyle: darkTextStyle, gridlines: gridlinesColor, minValue: 0 },
                pointSize: 6
            };
            var chart = new google.visualization.LineChart(document.getElementById('line_chart_div'));
            chart.draw(data, options);
        }
    </script>
</head>
<body>
    <header><div class="container"><h1>Sistem Pelatihan Atlet</h1></div></header>

    <nav>
        <div class="container">
            <div class="nav-buttons">
                <a href="dashboard.php" class="nav-button">Dashboard</a>
                <a href="list_athlete.php" class="nav-button active">Daftar Atlet</a>
                <a href="add_athlete.php" class="nav-button">Input Atlet</a>
                <a href="input_training.php" class="nav-button">Input Latihan</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <a href="list_athlete.php" class="back-button">← Kembali ke Daftar</a>
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
                            <strong style="color: var(--primary);"><?= $t['date'] ?></strong> | Observer: <span style="color: var(--text-muted);"><?= $t['observer'] ?></span>
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
                                        <td style="color: #fb923c; font-weight: bold;"><?= $d['rest_after'] ?>'</td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>