<?php
include 'functions.php';

$selectedAthlete = null;
if (isset($_GET['athlete_id'])) {
    $athleteId = (int)$_GET['athlete_id'];
    // Panggil fungsi baru dari functions.php yang mengambil data dari DB
    $selectedAthlete = get_athlete_full_detail($athleteId);
}

if (!$selectedAthlete) {
    echo "Atlet tidak ditemukan. <a href='list_athlete.php'>Kembali</a>";
    exit;
}

// HAPUS function getBadgeClass() dari sini karena sudah ada di functions.php
// (Baris yang menyebabkan error telah dihapus)

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

        function drawCharts() {
            drawLineChart();
        }
        
        function drawLineChart() {
            var jsonData = <?php echo generate_history_chart_data($selectedAthlete['trainings'] ?? []); ?>;
            var data = new google.visualization.arrayToDataTable(jsonData);
            
            var options = { 
                title: 'Perkembangan IOD (Index of Difficulty)', 
                titleTextStyle: { 
                    color: '#1e293b', 
                    fontSize: 18, 
                    bold: true 
                },
                backgroundColor: { fill: 'white' },
                curveType: 'function', 
                legend: { 
                    position: 'bottom', 
                    textStyle: { 
                        color: '#475569', 
                        fontSize: 13, 
                        bold: true 
                    } 
                }, 
                colors: ['#2563eb'], 
                lineWidth: 3,
                pointSize: 7,
                pointShape: 'circle',
                hAxis: { 
                    textStyle: { 
                        color: '#1e293b', 
                        fontSize: 12, 
                        bold: true 
                    }, 
                    gridlines: { 
                        color: '#94a3b8', 
                        count: -1 
                    },
                    baselineColor: '#475569'
                },
                vAxis: { 
                    title: 'Skor IOD', 
                    textStyle: { 
                        color: '#1e293b', 
                        fontSize: 12, 
                        bold: true 
                    }, 
                    titleTextStyle: { 
                        color: '#475569', 
                        fontSize: 13, 
                        bold: true 
                    }, 
                    gridlines: { 
                        color: '#94a3b8', 
                        count: 6 
                    },
                    baselineColor: '#475569',
                    minValue: 0,
                    format: '#'
                },
                chartArea: {
                    width: '85%',
                    height: '70%',
                    backgroundColor: 'white'
                },
                tooltip: {
                    textStyle: { 
                        fontSize: 13,
                        bold: false
                    },
                    showColorCode: true
                }
            };
            var chart = new google.visualization.LineChart(document.getElementById('line_chart_div'));
            chart.draw(data, options);
        }
    </script>
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
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <a href="list_athlete.php" class="back-button" style="margin-bottom: 0;">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Kembali ke Daftar
            </a>
            
            <a href="cetak_pdf.php?athlete_id=<?= $selectedAthlete['id'] ?>" target="_blank" class="btn btn-danger" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Download PDF Report
            </a>
        </div>

        <!-- ATHLETE PROFILE HEADER -->
        <div class="athlete-profile-header">
            <div class="profile-avatar">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <div class="profile-info">
                <h1 class="profile-name"><?= htmlspecialchars($selectedAthlete['name']) ?></h1>
                <div class="profile-meta">
                    <span class="meta-item">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <?= htmlspecialchars($selectedAthlete['sport'] ?? '-') ?>
                    </span>
                    <span class="meta-item">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <?= htmlspecialchars($selectedAthlete['origin'] ?? '-') ?>
                    </span>
                    <span class="meta-item">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <?= count($selectedAthlete['trainings'] ?? []) ?> Sesi Latihan
                    </span>
                </div>
            </div>
            <div class="profile-iod-display">
                <div class="iod-circle">
                    <div class="iod-number"><?= !empty($selectedAthlete['trainings']) ? number_format((float)end($selectedAthlete['trainings'])['iod'], 1) : '0' ?></div>
                    <div class="iod-text">IOD Score</div>
                </div>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="detail-stats-grid">
            <div class="detail-stat-card">
                <div class="stat-icon-circle" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <div>
                    <p class="stat-label">Gender</p>
                    <p class="stat-value"><?= htmlspecialchars($selectedAthlete['gender'] ?? '-') ?></p>
                </div>
            </div>
        
            <div class="detail-stat-card">
                <div class="stat-icon-circle" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <div>
                    <p class="stat-label">HR Max</p>
                    <?php 
                        // Tampilkan HR Max dari DB, jika kosong gunakan rumus 220-Usia
                        echo isset($selectedAthlete['hr_max']) && $selectedAthlete['hr_max'] > 0 
                             ? htmlspecialchars($selectedAthlete['hr_max']) 
                             : (220 - ($selectedAthlete['age'] ?? 20)); 
                        ?> 
                        <span style="font-size: 1rem; font-weight: 500;">bpm</span>
                </div>
            </div>
            
            <div class="detail-stat-card">
                <div class="stat-icon-circle" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <p class="stat-label">Usia</p>
                    <p class="stat-value"><?= htmlspecialchars($selectedAthlete['age'] ?? '-') ?> <span style="font-size: 1rem; font-weight: 500;">tahun</span></p>
                </div>
            </div>
            
            <div class="detail-stat-card">
                <div class="stat-icon-circle" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                    </svg>
                </div>
                <div>
                    <p class="stat-label">Berat Badan</p>
                    <p class="stat-value"><?= htmlspecialchars($selectedAthlete['weight'] ?? '-') ?> <span style="font-size: 1rem; font-weight: 500;">kg</span></p>
                </div>

                <div class="stat-icon-circle" style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                </div>
            </div>
            
            <div class="detail-stat-card">
                <div class="stat-icon-circle" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                    </svg>
                </div>
                <div>
                    <p class="stat-label">Tinggi Badan</p>
                    <p class="stat-value"><?= htmlspecialchars($selectedAthlete['height'] ?? '-') ?> <span style="font-size: 1rem; font-weight: 500;">cm</span></p>
                </div>
            </div>
        </div>

        <!-- CHART SECTION -->
        <?php if (!empty($selectedAthlete['trainings'])): ?>
        <div class="panel" style="margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">
                <div style="width: 4px; height: 2rem; background: linear-gradient(180deg, #1e3a8a 0%, #3b82f6 100%); border-radius: 2px;"></div>
                <h2 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">Grafik Perkembangan IOD</h2>
            </div>
            <div id="line_chart_div" style="width: 100%; height: 350px;"></div>
        </div>
        <?php else: ?>
        <div class="panel empty-state" style="margin-bottom: 2rem;">
            <svg style="width: 64px; height: 64px; opacity: 0.3; color: var(--text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            <p style="font-size: 1.125rem; font-weight: 600; margin: 1rem 0 0.5rem 0; color: var(--text-primary);">Belum Ada Data Latihan</p>
            <p style="color: var(--text-muted); font-size: 0.938rem;">Mulai tambahkan riwayat latihan untuk melihat grafik perkembangan</p>
        </div>
        <?php endif; ?>

        <!-- TRAINING HISTORY -->
        <div class="panel">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">
                <div style="width: 4px; height: 2rem; background: linear-gradient(180deg, #059669 0%, #10b981 100%); border-radius: 2px;"></div>
                <h2 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">Riwayat Latihan</h2>
            </div>
            
            <?php if (empty($selectedAthlete['trainings'])): ?>
            <div class="empty-state">
                <svg style="width: 64px; height: 64px; opacity: 0.3; color: var(--text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <p style="font-size: 1.125rem; font-weight: 600; margin: 1rem 0 0.5rem 0; color: var(--text-primary);">Tidak Ada Riwayat Latihan</p>
                <p style="color: var(--text-muted); font-size: 0.938rem;">Belum ada data latihan yang tercatat untuk atlet ini</p>
            </div>
            <?php else: ?>
            <?php 
            // Warna SOLID BOLD DARK untuk header (baris 1) - LEBIH GELAP
            $headerColors = [
                '#1e3a8a', // Blue dark bold
                '#15803d', // Green dark bold
                '#c2410c', // Orange dark bold
                '#be185d', // Pink dark bold
                '#4338ca', // Indigo dark bold
                '#b91c1c', // Red dark bold
            ];
            
            // Warna SOFT untuk table header (baris 2)
            $tableHeaderColors = [
                '#dbeafe', // Blue light
                '#dcfce7', // Green light
                '#fef3c7', // Yellow light
                '#fce7f3', // Pink light
                '#e0e7ff', // Indigo light
                '#ffedd5', // Orange light
            ];
            
            foreach (array_reverse($selectedAthlete['trainings']) as $index => $t): 
                $colorIndex = $index % count($headerColors);
            ?>
                <div class="training-history-card" style="border-left: 3px solid <?= $headerColors[$colorIndex] ?>;">
                    <!-- BARIS 1: SOLID COLOR -->
                    <div class="training-header" style="background: <?= $headerColors[$colorIndex] ?>;">
                        <div class="training-header-left">
                            <div class="training-date" style="color: white;">
                                <svg style="width: 16px; height: 16px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <?= date('d M Y', strtotime($t['date'])) ?>
                            </div>
                            <div class="training-observer" style="color: rgba(255, 255, 255, 0.9);">
                                <svg style="width: 14px; height: 14px; color: rgba(255, 255, 255, 0.9);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <?= $t['observer'] ?>
                            </div>
                            <span class="training-type-badge" style="background: white; border: 2px solid white; color: <?= $headerColors[$colorIndex] ?>;">
                                ● <?= strtoupper($t['manual_category'] ?? '-') ?>
                            </span>
                        </div>
                        <div class="training-iod-badge">
                            <div class="iod-score-label" style="color: rgba(255, 255, 255, 0.9);">IOD SCORE</div>
                            <div class="iod-score-number" style="color: white;">
                                <?= isset($t['iod']) ? number_format($t['iod'], 2) : ($t['performance'] ?? '-') ?>
                            </div>
                            <?php if(isset($t['iodClass'])): ?>
                                <span class="iod-badge <?= getBadgeClass($t['iodClass']) ?>" style="background: white; color: <?= $headerColors[$colorIndex] ?>; border: none;">
                                    <?= $t['iodClass'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (isset($t['details'])): ?>
                    <div class="training-details-table">
                        <table>
                            <!-- BARIS 2: SOFT COLOR -->
                            <thead>
                                <tr style="background: <?= $tableHeaderColors[$colorIndex] ?>;">
                                    <th style="color: <?= $headerColors[$colorIndex] ?>;">FASE</th>
                                    <th style="color: <?= $headerColors[$colorIndex] ?>;">DURASI</th>
                                    <th style="color: <?= $headerColors[$colorIndex] ?>;">SET</th>
                                    <th style="color: <?= $headerColors[$colorIndex] ?>;">HRP</th>
                                    <th style="color: <?= $headerColors[$colorIndex] ?>;">PARTIAL INT.</th>
                                    <th style="color: <?= $headerColors[$colorIndex] ?>;">REST</th>
                                </tr>
                            </thead>
                            <!-- BARIS 3: WHITE BACKGROUND -->
                            <tbody>
                                <?php foreach ($t['details'] as $d): ?>
                                <tr style="background: white;">
                                    <td><strong style="color: <?= $headerColors[$colorIndex] ?>;"><?= strtoupper($d['phase']) ?></strong></td>
                                    <td><?= $d['duration'] ?>'</td>
                                    <td><?= $d['set'] ?? '-' ?></td>
                                    <td><?= $d['hrp'] ?></td>
                                    <td><?= isset($d['partialIntensity']) ? number_format($d['partialIntensity'], 1) : '-' ?>%</td>
                                    <td style="color: #ea580c; font-weight: 700;"><?= $d['rest_after'] ?>'</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>