<?php
include 'functions.php';

$message = null;
$calculatedResult = null;

// 1. LOGIKA TAMBAH PENGAMAT BARU (POPUP)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_new_observer'])) {
    $msgObs = add_observer_person($_POST['new_observer_name']);
    echo "<script>alert('$msgObs');</script>";
}

// 2. AMBIL DATA
$athletes = get_all_athletes();
$definedObservers = get_all_observers_db(); // Ambil dari Database sekarang

// Fungsi bantu klasifikasi
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
            $calculatedResult = calculate_training_metrics($athleteId, $trainingDetails);
            
            // LOGIKA HITUNG MANUAL (Jika ada override)
            if (!isset($calculatedResult['error']) && isset($_POST['volRelatif']) && $_POST['volRelatif'] > 0) {
                $manualVolRelatif = (float)$_POST['volRelatif'];
                if (abs($manualVolRelatif - $calculatedResult['volRelatif']) > 0.01) {
                    $calculatedResult['volRelatif'] = $manualVolRelatif;
                    $calculatedResult['absoluteDensity'] = ($manualVolRelatif > 0) ? ($calculatedResult['volAbsolute'] / $manualVolRelatif) * 100 : 0;
                    $calculatedResult['iod'] = ($calculatedResult['volAbsolute'] * $calculatedResult['absoluteDensity'] * $calculatedResult['overallIntensity']) / 10000;
                    $calculatedResult['iodClass'] = getIodClassLocal($calculatedResult['iod']);
                }
            }

            if (!isset($calculatedResult['error'])) {
                foreach ($trainingDetails as $k => $v) {
                    $trainingDetails[$k]['partialIntensity'] = $calculatedResult['partialIntensities'][$k] ?? 0;
                }
            }

            // SIMPAN DATA
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
                    $athletes = get_all_athletes(); 
                    $definedObservers = get_all_observers_db(); // Refresh list observer
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
        .btn-remove { background-color: #ef4444; color: white; border: none; width: 30px; height: 30px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .btn-remove:hover { background-color: #dc2626; }
        .btn-add { background-color: #3b82f6; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 0.9rem; }
        .btn-add:hover { background-color: #2563eb; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 300px; border-radius: 8px; text-align: center; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .btn-small { padding: 2px 6px; font-size: 11px; margin-left: 5px; cursor: pointer; background: #e2e8f0; border: 1px solid #cbd5e1; border-radius: 4px; }
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
                        <label>Pengamat <button type="button" class="btn-small" onclick="openModal('modalObserver')">+ Tambah</button></label>
                        <select name="observer" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($definedObservers as $o): ?>
                                <option value="<?= htmlspecialchars($o['name']) ?>" <?= (isset($_POST['observer']) && $_POST['observer'] == $o['name']) ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                            <?php endforeach; ?>
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

    <div id="modalObserver" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('modalObserver')">&times;</span>
            <h3>Tambah Pengamat</h3>
            <form method="POST">
                <input type="text" name="new_observer_name" class="form-input" placeholder="Nama Coach / Pengamat" required style="margin-bottom: 10px;">
                <button type="submit" name="submit_new_observer" class="btn btn-primary">Simpan</button>
            </form>
        </div>
    </div>

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
        }

        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('calc-trigger')) calculateVolRelatif();
        });
        
        window.addEventListener('load', calculateVolRelatif);
        
        // Modal Scripts
        function openModal(id) { document.getElementById(id).style.display = "block"; }
        function closeModal(id) { document.getElementById(id).style.display = "none"; }
        window.onclick = function(event) { if (event.target.classList.contains('modal')) event.target.style.display = "none"; }
    </script>
</body>
</html>