<?php
include 'functions.php';

// --- Logic Data Filter ---
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
            if (isset($t['observer']) && $t['observer'] === $filterObserver) {
                return true;
            }
        }
        return false;
    });
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Atlet - Sistem Manajemen Atlet</title>
    <link rel="stylesheet" href="style.css">
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
                <a href="dashboard.php" class="nav-button">Dashboard</a>
                <a href="list_athlete.php" class="nav-button active">Daftar Atlet</a>
                <a href="add_athlete.php" class="nav-button">Input Atlet</a>
                <a href="input_training.php" class="nav-button">Input Latihan</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="panel">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 2rem; gap: 1rem;">
                <div>
                    <h2 style="margin: 0 0 0.25rem 0; font-size: 1.75rem; font-weight: 800;">Daftar Atlet</h2>
                    <p style="color: #64748b; font-size: 0.938rem; margin: 0;">
                        <strong style="color: #1e40af;"><?= count($filteredAthletes) ?></strong> atlet terdaftar
                    </p>
                </div>
                
                <form method="GET" action="" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                    <select name="filter_sport" class="form-select" style="width: 200px;">
                        <option value="">🏅 Semua Cabang</option>
                        <?php foreach ($uniqueSports as $sport): ?>
                            <option value="<?= htmlspecialchars($sport) ?>" <?= $filterSport === $sport ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sport) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="filter_observer" class="form-select" style="width: 200px;">
                        <option value="">👤 Semua Pengamat</option>
                        <?php foreach ($allObservers as $obs): ?>
                            <option value="<?= htmlspecialchars($obs) ?>" <?= $filterObserver === $obs ? 'selected' : '' ?>>
                                <?= htmlspecialchars($obs) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                        Filter
                    </button>
                    <?php if($filterSport || $filterObserver): ?>
                        <a href="list_athlete.php" class="btn" style="background: #64748b; color: white;">
                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if(empty($filteredAthletes)): ?>
                <div style="text-align: center; padding: 5rem 2rem; color: #94a3b8;">
                    <svg style="width: 80px; height: 80px; margin: 0 auto 1.5rem; opacity: 0.4;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <p style="font-size: 1.25rem; font-weight: 700; margin: 0; color: #475569;">Tidak Ada Data Atlet</p>
                    <p style="font-size: 0.938rem; margin-top: 0.5rem; color: #94a3b8;">Belum ada atlet yang sesuai dengan filter yang dipilih</p>
                    <a href="add_athlete.php" class="btn btn-primary" style="margin-top: 1.5rem; display: inline-flex;">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Tambah Atlet Baru
                    </a>
                </div>
            <?php else: ?>
                <div class="athlete-grid">
                    <?php foreach ($filteredAthletes as $athlete): ?>
                    <?php 
                    // Tentukan status badge berdasarkan IOD dan aktivitas
                    $lastIOD = (float)$athlete['lastPerformance'];
                    $totalTrainings = !empty($athlete['trainings']) ? count($athlete['trainings']) : 0;
                    
                    if ($lastIOD >= 90) {
                        $statusBadge = '<span class="status-badge status-elite">🏆 Elite</span>';
                    } elseif ($lastIOD >= 70) {
                        $statusBadge = '<span class="status-badge status-active">💪 Active</span>';
                    } elseif ($lastIOD > 0) {
                        $statusBadge = '<span class="status-badge status-training">🎯 Training</span>';
                    } else {
                        $statusBadge = '<span class="status-badge status-new">⭐ New</span>';
                    }
                    ?>
                    <div class="athlete-card">
                        <div class="athlete-card-header">
                            <div class="header-content">
                                <h3 class="athlete-name">
                                     <img src="IMG/IKON.png" alt="icon" class="athlete-icon">
                                    <?= htmlspecialchars($athlete['name']) ?>
                                    
                                </h3>
                                <div class="sport-label">
                                    <?= htmlspecialchars($athlete['sport']) ?>
                                    <?= $statusBadge ?>
                                </div>
                            </div>
                            <div class="iod-display">
                                <div class="iod-score">
                                    <?php if($athlete['lastPerformance'] > 0): ?>
                                        <?= number_format((float)$athlete['lastPerformance'], 1) ?>
                                    <?php else: ?>
                                        <span style="opacity: 0.5;">—</span>
                                    <?php endif; ?>
                                </div>
                                <div class="iod-label">IOD Score</div>
                            </div>
                        </div>

                        <div class="athlete-card-body">
                            <div class="athlete-info-grid">
                                <div class="info-item">
                                    <span class="label">
                                        <svg style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px; opacity: 0.7;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        Gender
                                    </span>
                                    <span class="value"><?= htmlspecialchars($athlete['gender']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label">
                                        <svg style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px; opacity: 0.7;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        Usia
                                    </span>
                                    <span class="value"><?= htmlspecialchars($athlete['age'] ?? '-') ?> tahun</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">
                                        <svg style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px; opacity: 0.7;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        Asal
                                    </span>
                                    <span class="value"><?= htmlspecialchars($athlete['origin'] ?? '-') ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="label">
                                        <svg style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px; opacity: 0.7;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                        Postur
                                    </span>
                                    <span class="value">
                                        <?= htmlspecialchars($athlete['weight'] ?? '-') ?> kg / 
                                        <?= htmlspecialchars($athlete['height'] ?? '-') ?> cm
                                    </span>
                                </div>
                            </div>

                            <?php if($totalTrainings > 0): ?>
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.813rem;">
                                    <span style="color: #64748b; font-weight: 600;">
                                        <svg style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                        Total Sesi Latihan
                                    </span>
                                    <span style="font-weight: 800; color: #1e40af; font-size: 1.125rem;">
                                        <?= $totalTrainings ?>
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="athlete-card-footer">
                            <a href="detail_atlet.php?athlete_id=<?= $athlete['id'] ?>" class="btn btn-primary" style="flex: 1;">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                Detail
                            </a>
                            <a href="hapus_atlet.php?id=<?= $athlete['id'] ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('⚠️ PERINGATAN\n\nYakin ingin menghapus <?= htmlspecialchars($athlete['name']) ?>?\n\n• Semua data profil akan dihapus\n• Seluruh riwayat latihan akan hilang\n• Aksi ini tidak dapat dibatalkan\n\nKetik OK untuk melanjutkan.');">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Hapus
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>