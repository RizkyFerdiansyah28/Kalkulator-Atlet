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
        .btn-remove { 
            background-color: #ef4444; 
            color: white; 
            border: none; 
            width: 32px; 
            height: 32px; 
            border-radius: 6px; 
            cursor: pointer; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: bold; 
            font-size: 20px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
        }
        .btn-remove:hover { 
            background-color: #dc2626; 
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }
        .btn-add { 
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        .btn-add:hover { 
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 300px; border-radius: 8px; text-align: center; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .btn-small { 
            padding: 4px 10px; 
            font-size: 11px; 
            margin-left: 8px; 
            cursor: pointer; 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 5px;
            transition: all 0.2s ease;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
        }
        .btn-small:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(16, 185, 129, 0.3);
        }
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
            <div style="border-bottom: 3px solid var(--primary); padding-bottom: 1rem; margin-bottom: 2rem;">
                <h2 style="margin: 0;">Input Latihan</h2>
                <p style="color: var(--text-muted); font-size: 0.938rem; margin: 0.5rem 0 0 0;">
                    Masukkan detail latihan dan hitung IOD secara otomatis
                </p>
            </div>
            
            <form method="POST" action="" id="trainingForm">
                <!-- Section: Informasi Dasar -->
                <div style="background: var(--bg-main); padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--primary); margin-bottom: 2rem;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: var(--primary); margin: 0 0 1.25rem 0; text-transform: uppercase; letter-spacing: 0.05em;">
                        Informasi Dasar
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Pilih Atlet <span style="color: var(--danger);">*</span></label>
                            <select name="athleteId" class="form-select" required>
                                <option value="">-- Pilih Atlet --</option>
                                <?php foreach ($athletes as $athlete): ?>
                                    <option value="<?= $athlete['id'] ?>" <?= (isset($_POST['athleteId']) && $_POST['athleteId'] == $athlete['id']) ? 'selected' : '' ?>><?= $athlete['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Pengamat <span style="color: var(--danger);">*</span>
                                <button type="button" class="btn-small" onclick="openModal('modalObserver')">+ Baru</button>
                            </label>
                            <select name="observer" class="form-select" required>
                                <option value="">-- Pilih Pengamat --</option>
                                <?php foreach ($definedObservers as $o): ?>
                                    <option value="<?= htmlspecialchars($o['name']) ?>" <?= (isset($_POST['observer']) && $_POST['observer'] == $o['name']) ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tanggal <span style="color: var(--danger);">*</span></label>
                            <input type="date" name="date" value="<?= $_POST['date'] ?? date('Y-m-d') ?>" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Klasifikasi Latihan <span style="color: var(--danger);">*</span></label>
                            <select name="manual_category" class="form-select" required>
                                <option value="">-- Pilih Klasifikasi --</option>
                                <option value="Ringan" <?= (isset($_POST['manual_category']) && $_POST['manual_category'] == 'Ringan') ? 'selected' : '' ?>>🟢 Ringan</option>
                                <option value="Sedang" <?= (isset($_POST['manual_category']) && $_POST['manual_category'] == 'Sedang') ? 'selected' : '' ?>>🟡 Sedang</option>
                                <option value="Berat" <?= (isset($_POST['manual_category']) && $_POST['manual_category'] == 'Berat') ? 'selected' : '' ?>>🔴 Berat</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Section: Detail Latihan -->
                <div style="background: var(--bg-main); padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--accent); margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: var(--accent); margin: 0 0 1.25rem 0; text-transform: uppercase; letter-spacing: 0.05em;">
                        Detail Latihan
                    </h3>

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

                <div style="margin-top: 1rem;">
                    <button type="button" class="btn-add" onclick="addRow()">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Tambah Baris
                    </button>
                </div>
                </div>

                <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="submit" name="calculate" class="btn btn-primary" style="min-width: 160px;">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        Hitung IOD
                    </button>
                    <?php if ($calculatedResult && !isset($calculatedResult['error'])): ?>
                        <button type="submit" name="submit_training" class="btn btn-success" style="min-width: 160px;">
                            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Simpan Data
                        </button>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($calculatedResult && !isset($calculatedResult['error'])): ?>
                <div class="result-box" style="margin-top: 2rem; border: 2px solid var(--success); background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                    <div style="text-align: center; margin-bottom: 2rem; border-bottom: 2px dashed var(--success); padding-bottom: 1.5rem;">
                        <div style="display: inline-block; background: white; padding: 1.5rem 2.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.15);">
                            <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">IOD Score</p>
                            <p class="iod-highlight" style="color: var(--success);"><?= number_format($calculatedResult['iod'], 2) ?></p>
                            
                            <span class="iod-badge <?= getBadgeClass($calculatedResult['iodClass']) ?>" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                                <?= $calculatedResult['iodClass'] ?>
                            </span>
                        </div>
                    </div>
                    <div class="form-grid" style="gap: 1rem;">
                        <div class="result-item">
                            <p class="label">Volume Relatif</p>
                            <p class="value" style="color: #d97706;"><?= number_format($calculatedResult['volRelatif'], 2) ?> <span style="font-size: 1rem; font-weight: 600;">mnt</span></p>
                        </div>
                        <div class="result-item">
                            <p class="label">Volume Absolute</p>
                            <p class="value" style="color: var(--primary);"><?= number_format($calculatedResult['volAbsolute'], 2) ?> <span style="font-size: 1rem; font-weight: 600;">mnt</span></p>
                        </div>
                        <div class="result-item">
                            <p class="label">Total Istirahat</p>
                            <p class="value" style="color: #64748b;"><?= number_format($calculatedResult['recovery'], 2) ?> <span style="font-size: 1rem; font-weight: 600;">mnt</span></p>
                        </div>
                        
                        <div class="result-item">
                            <p class="label">Absolute Density</p>
                            <p class="value" style="color: var(--accent);"><?= number_format($calculatedResult['absoluteDensity'], 2) ?><span style="font-size: 1rem; font-weight: 600;">%</span></p>
                        </div>
                        <div class="result-item">
                            <p class="label">Overall Intensity</p>
                            <p class="value" style="color: var(--success);"><?= number_format($calculatedResult['overallIntensity'], 2) ?><span style="font-size: 1rem; font-weight: 600;">%</span></p>
                        </div>
                        <div class="result-item">
                            <p class="label">HR Max</p>
                            <p class="value" style="color: var(--danger);"><?= $calculatedResult['hrMax'] ?> <span style="font-size: 1rem; font-weight: 600;">bpm</span></p>
                        </div>
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