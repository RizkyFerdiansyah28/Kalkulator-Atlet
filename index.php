<?php
include 'functions.php';

// --- Kontrol Halaman ---
$currentPage = $_GET['page'] ?? 'dashboard';
$selectedAthlete = null;

if (isset($_GET['athlete_id'])) {
    $athleteId = (int)$_GET['athlete_id'];
    if (isset($athletes[$athleteId])) {
        $selectedAthlete = $athletes[$athleteId];
        $currentPage = 'history';
    }
}

$observers = ['Coach Budi', 'Coach Sarah', 'Coach Dimas'];
$message = null;
$calculatedResult = null;

// Helper untuk Class CSS berdasarkan string Status
function getBadgeClass($status) {
    $slug = strtolower(str_replace(' ', '-', $status));
    return "badge-$slug";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle Add Athlete
    if (isset($_POST['submit_athlete'])) {
        $message = add_new_athlete($_POST);
        $athletes = $_SESSION['athletes'];
        $currentPage = 'dashboard';
    }

    // Handle Calculate & Submit
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
                'volRelatif' => $volRelatif
            ];

            $message = submit_training_revision($athleteId, $formData, $calculatedResult, $trainingDetails);
            if ($message === 'Data latihan berhasil disimpan!') {
                $calculatedResult = null;
                $_POST = [];
                $athletes = $_SESSION['athletes'];
            }
        }
        $currentPage = 'form';
    }
}

// Chart Functions (Sama seperti sebelumnya)
function generate_google_chart_data($data) {
    $rows = [];
    foreach ($data as $item) { $rows[] = "['{$item['name']}', {$item['performa']}]"; }
    return '[' . implode(', ', $rows) . ']';
}
function generate_history_chart_data($trainings) {
    $rows = [];
    foreach ($trainings as $t) {
        $val = isset($t['iod']) ? $t['iod'] : ($t['performance'] ?? 0);
        $rows[] = "['{$t['date']}', {$val}]";
    }
    return '[' . implode(', ', $rows) . ']';
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
        }
        function drawBarChart() {
            var data = new google.visualization.arrayToDataTable([['Atlet', 'IOD Terakhir'], <?php echo generate_google_chart_data($performanceData); ?>]);
            var options = { title: 'Indeks Kesulitan (IOD) Atlet', legend: { position: 'none' }, colors: ['#7c3aed'] };
            var chart = new google.visualization.ColumnChart(document.getElementById('bar_chart_div'));
            chart.draw(data, options);
        }
        function drawLineChart() {
            var data = new google.visualization.arrayToDataTable([['Tanggal', 'Skor IOD'], <?php echo generate_history_chart_data($selectedAthlete['trainings'] ?? []); ?>]);
            var options = { title: 'Perkembangan IOD', curveType: 'function', legend: { position: 'bottom' }, colors: ['#7c3aed'] };
            var chart = new google.visualization.LineChart(document.getElementById('line_chart_div'));
            chart.draw(data, options);
        }
    </script>
</head>
<body>

    <header><div class="container"><h1>Human Interval Output Data (HIOD)</h1></div></header>

    <nav>
        <div class="container">
            <div class="nav-buttons">
                <a href="?page=dashboard" class="nav-button <?= $currentPage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="?page=add_athlete" class="nav-button <?= $currentPage === 'add_athlete' ? 'active' : '' ?>">Input Atlet</a>
                <a href="?page=form" class="nav-button <?= $currentPage === 'form' ? 'active' : '' ?>">Input Latihan</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <?php if ($message): ?><div class="alert-box"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <?php if ($currentPage === 'dashboard'): ?>
            <div class="panel">
                <h3>Grafik IOD (Index of Difficulty)</h3>
                <div id="bar_chart_div" style="width: 100%; height: 300px;"></div>
            </div>
            <div class="panel" style="margin-top: 1.5rem;">
                <h3>Daftar Atlet</h3>
                <div class="table-container">
                    <table>
                        <thead><tr><th>Nama</th><th>Cabor</th><th>IOD Terakhir</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($athletes as $athlete): ?>
                            <tr>
                                <td><?= htmlspecialchars($athlete['name']) ?></td>
                                <td><?= htmlspecialchars($athlete['sport']) ?></td>
                                <td><strong><?= number_format($athlete['lastPerformance'], 2) ?></strong></td>
                                <td><a href="?athlete_id=<?= $athlete['id'] ?>" class="detail-button">Lihat Detail</a></td>
                            </tr>
                            <?php endforeach; ?>
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
                        <div class="form-group"><label>Pengamat</label><select name="observer" class="form-select"><?php foreach ($observers as $o) echo "<option>$o</option>"; ?></select></div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Tanggal</label><input type="date" name="date" value="<?= $_POST['date'] ?? date('Y-m-d') ?>" class="form-input"></div>
                        <div class="form-group">
                            <label>Volume Relatif (Total Waktu Sesi)</label>
                            <input type="number" step="0.01" name="volRelatif" value="<?= $_POST['volRelatif'] ?? '' ?>" class="form-input" required placeholder="Contoh: 120.5">
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
                                        <td><input type="number" name="set[]" value="<?= $_POST['set'][$index] ?? '' ?>" class="table-input" placeholder=""></td>
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
                            <div class="result-item">
                                <p class="label">Absolute Density</p>
                                <p class="value"><?= number_format($calculatedResult['absoluteDensity'], 2) ?>%</p>
                            </div>
                            <div class="result-item">
                                <p class="label">Overall Intensity</p>
                                <p class="value"><?= number_format($calculatedResult['overallIntensity'], 2) ?>%</p>
                            </div>
                            <div class="result-item">
                                <p class="label">Volume Absolute</p>
                                <p class="value"><?= number_format($calculatedResult['volAbsolute'], 2) ?> mnt</p>
                            </div>
                            <div class="result-item">
                                <p class="label">Total Recovery</p>
                                <p class="value"><?= number_format($calculatedResult['recovery'], 2) ?> mnt</p>
                            </div>
                            <div class="result-item">
                                <p class="label">HR Max</p>
                                <p class="value text-red-600"><?= $calculatedResult['hrMax'] ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($currentPage === 'history' && $selectedAthlete): ?>
            <a href="?page=dashboard" class="back-button">← Kembali</a>
            <div class="panel">
                <h1><?= htmlspecialchars($selectedAthlete['name']) ?></h1>
                <hr style="margin: 1rem 0;">
                
                <div id="line_chart_div" style="width: 100%; height: 300px; margin-bottom: 2rem;"></div>

                <h3>Riwayat Latihan</h3>
                <?php foreach (array_reverse($selectedAthlete['trainings']) as $t): ?>
                    <div class="training-detail">
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
                                    <br><span class="iod-badge <?= getBadgeClass($t['iodClass']) ?>" style="font-size: 1.2rem; padding: 2px 8px;">
                                        <?= $t['iodClass'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if(isset($t['iod'])): ?>
                        <div class="history-grid" style="margin-bottom: 1rem;">
                            <div class="history-item"><p class="label">Abs. Density</p><p class="value"><?= number_format($t['absoluteDensity'], 2) ?>%</p></div>
                            <div class="history-item"><p class="label">Ov. Intensity</p><p class="value"><?= number_format($t['overallIntensity'], 2) ?>%</p></div>
                            <div class="history-item"><p class="label">Vol Absolute</p><p class="value"><?= number_format($t['volAbsolute'], 2) ?></p></div>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($t['details'])): ?>
                            <table class="input-table" style="font-size: 0.85rem;">
                                <tr style="background: #eee;">
                                    <th>Fase</th>
                                    <th>Durasi Exercise</th>
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