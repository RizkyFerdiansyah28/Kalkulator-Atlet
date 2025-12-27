<?php
include 'functions.php';

$message = null;
$calculatedResult = null;
$definedObservers = ['Coach Budi', 'Coach Sarah', 'Coach Dimas'];

// UPDATE: Ambil data atlet terbaru dari Database (bukan Session lagi)
$athletes = get_all_athletes();

// Fungsi bantu klasifikasi (untuk update realtime jika manual input)
function getIodClassLocal($iod) {
    if ($iod >= 100) return 'Super Maximal';
    if ($iod >= 90) return 'Maximum';
    if ($iod >= 80) return 'Hard';
    if ($iod >= 70) return 'Medium';
    if ($iod >= 50) return 'Low';
    return 'Very Low';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['calculate']) || isset($_POST['submit_training'])) {
        $athleteId = $_POST['athleteId'];
        
        $trainingDetails = [];
        $inputPhases = $_POST['phase'] ?? [];
        $inputDurations = $_POST['duration'] ?? [];
        $inputSets = $_POST['set'] ?? [];
        $inputHrps = $_POST['hrp'] ?? [];
        $inputRests = $_POST['rest'] ?? [];

        // 1. LOOP DATA INPUT DINAMIS
        foreach ($inputPhases as $index => $pName) {
            if (trim($pName) === '') continue;
            $trainingDetails[] = [
                'phase' => htmlspecialchars($pName),
                'duration' => (float)($inputDurations[$index] ?? 0), 
                'hrp' => (float)($inputHrps[$index] ?? 0),
                'rest_after' => (float)($inputRests[$index] ?? 0),
                'set' => $inputSets[$index] ?? 0
            ];
        }

        if (empty($trainingDetails)) {
            $calculatedResult = ['error' => 'Mohon isi minimal satu baris latihan.'];
        } else {
            // 2. HITUNG STANDAR DARI FUNGSI
            $calculatedResult = calculate_training_metrics($athleteId, $trainingDetails);
            
            // 3. CEK OVERRIDE MANUAL VOLUME RELATIF
            if (!isset($calculatedResult['error']) && isset($_POST['volRelatif']) && $_POST['volRelatif'] > 0) {
                $manualVolRelatif = (float)$_POST['volRelatif'];
                
                // Cek apakah beda dengan hasil hitungan sistem (toleransi float kecil)
                if (abs($manualVolRelatif - $calculatedResult['volRelatif']) > 0.01) {
                    $calculatedResult['volRelatif'] = $manualVolRelatif;
                    
                    // Hitung ulang Absolute Density: (Vol Abs / Vol Relatif) * 100
                    if ($manualVolRelatif > 0) {
                        $calculatedResult['absoluteDensity'] = ($calculatedResult['volAbsolute'] / $manualVolRelatif) * 100;
                    } else {
                        $calculatedResult['absoluteDensity'] = 0;
                    }

                    // Hitung ulang IOD: (VolAbs * Density * Intensity) / 10000
                    $calculatedResult['iod'] = ($calculatedResult['volAbsolute'] * $calculatedResult['absoluteDensity'] * $calculatedResult['overallIntensity']) / 10000;
                    
                    // Update Kelas IOD
                    $calculatedResult['iodClass'] = getIodClassLocal($calculatedResult['iod']);
                }
            }

            // Simpan Intensitas Parsial ke array detail
            if (!isset($calculatedResult['error'])) {
                foreach ($trainingDetails as $k => $v) {
                    $trainingDetails[$k]['partialIntensity'] = $calculatedResult['partialIntensities'][$k] ?? 0;
                }
            }

            // 4. SIMPAN KE DATABASE
            if (isset($_POST['submit_training']) && !isset($calculatedResult['error'])) {
                $formData = [
                    'date' => $_POST['date'],
                    'observer' => $_POST['observer'],
                    'volRelatif' => $calculatedResult['volRelatif'],
                    'manual_category' => $_POST['manual_category']
                ];

                $message = submit_training_revision($athleteId, $formData, $calculatedResult, $trainingDetails);
                
                if ($message === 'Data latihan berhasil disimpan!') {
                    $calculatedResult = null;
                    $_POST = []; 
                    // UPDATE: Refresh data atlet dari database lagi
                    $athletes = get_all_athletes(); 
                }
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
    <title>Input Latihan Custom - Sistem Manajemen Atlet</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .btn-remove {
            background-color: #ef4444; color: white; border: none; 
            width: 30px; height: 30px; border-radius: 4px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-weight: bold;
        }
        .btn-remove:hover { background-color: #dc2626; }
        .btn-add {
            background-color: #3b82f6; color: white; border: none;
            padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 0.9rem;
        }
        .btn-add:hover { background-color: #2563eb; }
        .manual-input { border-color: #f59e0b; background-color: #fffbeb; }
    </style>
</head>
<body>
    <header><div class="container"><h1>Human Indicator Overview Device</h1></div></header>

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
        <?php if (isset($calculatedResult['error'])): ?><div class="alert-box" style="background-color:#fee2e2; color:#991b1b;"><?= htmlspecialchars($calculatedResult['error']) ?></div><?php endif; ?>

        <div class="panel">
            <h2>Input Latihan Custom</h2>
            <form method="POST" action="" id="trainingForm">
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
                            <?php foreach ($definedObservers as $o) echo "<option " . ((isset($_POST['observer']) && $_POST['observer'] == $o) ? 'selected' : '') . ">$o</option>"; ?>
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
                    <table class="input-table" id="dynamicTable">
                        <thead>
                            <tr>
                                <th width="30%">Nama Latihan</th>
                                <th>Durasi (mnt)</th>
                                <th width="12%">Set</th>
                                <th width="12%">HRP</th>
                                <th width="15%">Rest (mnt)</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $previousData = $_POST['phase'] ?? ['']; 
                            foreach ($previousData as $index => $val):
                            ?>
                            <tr>
                                <td><input type="text" name="phase[]" value="<?= $_POST['phase'][$index] ?? '' ?>" class="table-input" placeholder="Contoh: Lari" required></td>
                                <td><input type="number" step="0.01" name="duration[]" value="<?= $_POST['duration'][$index] ?? '' ?>" class="table-input calc-trigger" placeholder="0" required></td>
                                <td><input type="number" name="set[]" value="<?= $_POST['set'][$index] ?? '' ?>" class="table-input" placeholder="1" required></td>
                                <td><input type="number" name="hrp[]" value="<?= $_POST['hrp'][$index] ?? '' ?>" class="table-input" placeholder="0" required></td>
                                <td><input type="number" step="0.01" name="rest[]" value="<?= $_POST['rest'][$index] ?? '' ?>" class="table-input calc-trigger" placeholder="0" required></td>
                                <td style="text-align: center;">
                                    <?php if($index > 0): ?>
                                        <button type="button" class="btn-remove" onclick="removeRow(this)">×</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 10px; margin-bottom: 20px;">
                    <button type="button" class="btn-add" onclick="addRow()">+ Tambah Baris</button>
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                    <button type="submit" name="calculate" class="btn btn-primary">Hitung Rumus</button>
                    <?php if ($calculatedResult && !isset($calculatedResult['error'])): ?>
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
                        <div class="result-item"><p class="label">Volume Relatif</p><p class="value" style="color: #f59e0b;"><?= number_format($calculatedResult['volRelatif'], 2) ?> mnt</p></div>
                        <div class="result-item"><p class="label">Volume Absolute</p><p class="value"><?= number_format($calculatedResult['volAbsolute'], 2) ?> mnt</p></div>
                        <div class="result-item"><p class="label">Total Istirahat</p><p class="value" style="color: #64748b;"><?= number_format($calculatedResult['recovery'], 2) ?> mnt</p></div>
                        
                        <div class="result-item"><p class="label">Absolute Density</p><p class="value"><?= number_format($calculatedResult['absoluteDensity'], 2) ?>%</p></div>
                        <div class="result-item"><p class="label">Overall Intensity</p><p class="value"><?= number_format($calculatedResult['overallIntensity'], 2) ?>%</p></div>
                        <div class="result-item"><p class="label">HR Max</p><p class="value text-red-600"><?= $calculatedResult['hrMax'] ?></p></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function addRow() {
            const tableBody = document.querySelector('#dynamicTable tbody');
            const newRow = document.createElement('tr');
            
            newRow.innerHTML = `
                <td><input type="text" name="phase[]" class="table-input" placeholder="Nama Latihan" required></td>
                <td><input type="number" step="0.01" name="duration[]" class="table-input calc-trigger" placeholder="0"></td>
                <td><input type="number" name="set[]" class="table-input" placeholder="1"></td>
                <td><input type="number" name="hrp[]" class="table-input" placeholder="0"></td>
                <td><input type="number" step="0.01" name="rest[]" class="table-input calc-trigger" placeholder="0"></td>
                <td style="text-align: center;">
                    <button type="button" class="btn-remove" onclick="removeRow(this)">×</button>
                </td>
            `;
            
            tableBody.appendChild(newRow);
        }

        function removeRow(button) {
            const row = button.closest('tr');
            row.remove();
            calculateVolRelatif();
        }

        function calculateVolRelatif() {
            let totalDuration = 0;
            let totalRest = 0;
            
            const durations = document.querySelectorAll('input[name="duration[]"]');
            durations.forEach(input => {
                const val = parseFloat(input.value);
                if (!isNaN(val)) totalDuration += val;
            });
            
            const rests = document.querySelectorAll('input[name="rest[]"]');
            rests.forEach(input => {
                const val = parseFloat(input.value);
                if (!isNaN(val)) totalRest += val;
            });
            
            const volRelatif = totalDuration + totalRest;
            // Jika ada input manual volRelatif (opsional, jika Anda menambahkannya di masa depan)
        }

        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('calc-trigger')) {
                calculateVolRelatif();
            }
        });
        
        window.addEventListener('load', calculateVolRelatif);
    </script>
</body>
</html>