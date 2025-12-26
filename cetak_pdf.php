<?php
// Pastikan path vendor/autoload.php sesuai dengan instalasi Anda
require 'vendor/autoload.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

include 'functions.php'; // Sertakan data & session

$selectedAthlete = null;
if (isset($_GET['athlete_id'])) {
    $athleteId = (int)$_GET['athlete_id'];
    if (isset($athletes[$athleteId])) {
        $selectedAthlete = $athletes[$athleteId];
    }
}

if (!$selectedAthlete) {
    echo "Data atlet tidak ditemukan.";
    exit;
}

// 1. SIAPKAN DATA GRAFIK UNTUK QUICKCHART.IO
// Kita perlu mengubah data array PHP menjadi format Chart.js untuk QuickChart
$chartLabels = [];
$chartData = [];

if (!empty($selectedAthlete['trainings'])) {
    foreach ($selectedAthlete['trainings'] as $t) {
        $chartLabels[] = date('d/m', strtotime($t['date'])); // Format tgl pendek
        $val = isset($t['iod']) ? (float)$t['iod'] : ((isset($t['performance']) && is_numeric($t['performance'])) ? (float)$t['performance'] : 0);
        $chartData[] = $val;
    }
}

// Konversi ke JSON string untuk URL
$labelsJson = json_encode($chartLabels);
$dataJson = json_encode($chartData);

// Buat URL QuickChart (Line Chart)
$chartConfig = "{
    type: 'line',
    data: {
        labels: $labelsJson,
        datasets: [{
            label: 'IOD Score',
            data: $dataJson,
            borderColor: 'blue',
            fill: false,
            tension: 0.1
        }]
    },
    options: {
        title: { display: true, text: 'Perkembangan IOD' },
        scales: {
            yAxes: [{ ticks: { beginAtZero: true } }]
        }
    }
}";
$encodedConfig = urlencode($chartConfig);
$chartUrl = "https://quickchart.io/chart?c=" . $encodedConfig . "&w=500&h=300";

// 2. SIAPKAN HTML UNTUK PDF
// Gunakan CSS internal agar lebih aman saat generate PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        h1 { text-align: center; color: #1e40af; margin-bottom: 5px; }
        .header-info { text-align: center; margin-bottom: 20px; font-size: 10px; color: #666; }
        
        .profile-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .profile-table td { padding: 5px; border-bottom: 1px solid #ddd; }
        .label { font-weight: bold; width: 150px; }
        
        .chart-container { text-align: center; margin-bottom: 20px; }
        
        .history-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .history-table th, .history-table td { border: 1px solid #999; padding: 8px; text-align: left; }
        .history-table th { background-color: #eee; }
        
        .iod-badge { padding: 2px 5px; border-radius: 4px; font-size: 10px; font-weight: bold; color: white; background-color: #666; }
    </style>
</head>
<body>
    <h1>Laporan Detail Atlet</h1>
    <div class="header-info">Dicetak pada: ' . date('d-m-Y H:i') . '</div>

    <h3>Profil Atlet</h3>
    <table class="profile-table">
        <tr><td class="label">Nama</td><td>' . htmlspecialchars($selectedAthlete['name']) . '</td></tr>
        <tr><td class="label">Cabor</td><td>' . htmlspecialchars($selectedAthlete['sport']) . '</td></tr>
        <tr><td class="label">Asal</td><td>' . htmlspecialchars($selectedAthlete['origin']) . '</td></tr>
        <tr><td class="label">Gender</td><td>' . htmlspecialchars($selectedAthlete['gender']) . '</td></tr>
        <tr><td class="label">Usia</td><td>' . $selectedAthlete['age'] . ' Tahun</td></tr>
    </table>

    <h3>Grafik Perkembangan</h3>
    <div class="chart-container">
        <img src="' . $chartUrl . '" style="width: 100%; max-width: 600px;">
    </div>

    <h3>Riwayat Latihan</h3>
    <table class="history-table">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Observer</th>
                <th>Kategori</th>
                <th>IOD Score</th>
                <th>Klasifikasi</th>
            </tr>
        </thead>
        <tbody>';

        if (!empty($selectedAthlete['trainings'])) {
            // Urutkan dari terbaru
            $riwayat = array_reverse($selectedAthlete['trainings']);
            foreach ($riwayat as $t) {
                $iod = isset($t['iod']) ? number_format($t['iod'], 2) : '-';
                $class = $t['iodClass'] ?? '-';
                $manual = $t['manual_category'] ?? '-';
                
                $html .= '<tr>
                    <td>' . $t['date'] . '</td>
                    <td>' . $t['observer'] . '</td>
                    <td>' . $manual . '</td>
                    <td><strong>' . $iod . '</strong></td>
                    <td>' . $class . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="5" style="text-align:center">Belum ada data latihan</td></tr>';
        }

$html .= '
        </tbody>
    </table>
</body>
</html>';

// 3. GENERATE PDF MENGGUNAKAN DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true); // Penting agar bisa load gambar dari URL luar (QuickChart)
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output file PDF ke browser (Download/Preview)
$dompdf->stream("Laporan_Atlet_" . str_replace(' ', '_', $selectedAthlete['name']) . ".pdf", array("Attachment" => 0));

?>