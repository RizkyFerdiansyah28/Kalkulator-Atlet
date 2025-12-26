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

// --- Logic Filtering ---
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
    <header><div class="container"><h1>Human Indicator Overview Device</h1></div></header>

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
            <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1.5rem; gap: 1rem;">
                <h3>Daftar Atlet</h3>
                
                <form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center;">
                    
                    <div>
                        <select name="filter_sport" class="form-select" style="width: 150px;">
                            <option value="">- Semua Cabor -</option>
                            <?php foreach ($uniqueSports as $sport): ?>
                                <option value="<?= htmlspecialchars($sport) ?>" <?= $filterSport === $sport ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sport) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <select name="filter_observer" class="form-select" style="width: 150px;">
                            <option value="">- Semua Pengamat -</option>
                            <?php foreach ($allObservers as $obs): ?>
                                <option value="<?= htmlspecialchars($obs) ?>" <?= $filterObserver === $obs ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($obs) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">Filter</button>
                    <?php if($filterSport || $filterObserver): ?>
                        <a href="list_athlete.php" class="btn" style="background: #334155; color: white; padding: 0.5rem 1rem;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Gender</th>
                            <th>Cabor</th>
                            <th>Asal</th>
                            <th>IOD Terakhir</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($filteredAthletes)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">Tidak ada data atlet yang sesuai filter.</td>
                            </tr>
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
                                <td><a href="detail_atlet.php?athlete_id=<?= $athlete['id'] ?>" class="detail-button">Lihat Detail</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>