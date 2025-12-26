<?php
include 'functions.php';

$message = null;
$calculatedResult = null;
$definedObservers = ['Coach Budi', 'Coach Sarah', 'Coach Dimas'];

function getBadgeClass($status) {
    $slug = strtolower(str_replace(' ', '-', $status));
    return "badge-$slug";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['calculate']) || isset($_POST['submit_training'])) {
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
                // Refresh data session
                $athletes = $_SESSION['athletes']; 
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Latihan - Sistem Manajemen Atlet</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header><div class="container"><h1>Sistem Pelatihan Atlet</h1></div></header>

    <nav>
        <div class="container">
            <div class="nav-buttons">
                <a href="dashboard.php" class="nav-button">Dashboard</a>
                <a href="list_athlete.php" class="nav-button">Daftar Atlet</a>
                <a href="add_athlete.php" class="nav-button">Input Atlet</a>
                <a href="input_training.php" class="nav-button active">Input Latihan</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <?php if ($message): ?><div class="alert-box"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <div class="panel">
            <h2>Input Latihan & Kalkulasi IOD</h2>
            <form method="POST" action="">
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
                <hr style="margin: 2rem 0; border: 0; border-top: 1px solid var(--border);">

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
                                    <td class="phase-name"><?= $phase ?></td>
                                    <td><input type="number" step="0.01" name="duration[]" value="<?= $_POST['duration'][$index] ?? '' ?>" class="table-input" placeholder="mnt"></td>
                                    <td><input type="number" name="set[]" value="<?= $_POST['set'][$index] ?? '' ?>" class="table-input" placeholder="1"></td>
                                    <td><input type="number" name="hrp[]" value="<?= $_POST['hrp'][$index] ?? '' ?>" class="table-input"></td>
                                </tr>
                                <?php if ($index < count($phases)): ?>
                                <tr class="rest-row">
                                    <td colspan="4">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>REST (mnt):</span>
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
                    <div style="text-align: center; margin-bottom: 2rem; border-bottom: 1px dashed var(--border); padding-bottom: 1.5rem;">
                        <p style="font-size: 1rem; color: var(--text-muted); margin-bottom: 0.5rem;">IOD (Index of Difficulty)</p>
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
    </main>
</body>
</html>