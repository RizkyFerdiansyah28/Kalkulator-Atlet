<?php
// cetak_pdf.php - Versi Database dengan Grafik (QuickChart.io)

require 'vendor/autoload.php'; 
include 'functions.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Ambil ID Atlet
$athleteId = isset($_GET['athlete_id']) ? (int)$_GET['athlete_id'] : 0;
$athlete = get_athlete_full_detail($athleteId);

if (!$athlete) {
    die("Data atlet tidak ditemukan di database.");
}

// 2. Siapkan Data untuk Grafik (Diurutkan berdasarkan Tanggal Terlama -> Terbaru)
// Data dari get_athlete_full_detail sudah urut ASC (Terlama di awal)
$chartDates = [];
$chartIODs = [];

if (!empty($athlete['trainings'])) {
    foreach ($athlete['trainings'] as $t) {
        $chartDates[] = date('d/m', strtotime($t['date'])); // Format tgl: 25/12
        $chartIODs[] = (float)$t['iod'];
    }
}

// 3. Buat URL Gambar Grafik menggunakan QuickChart.io
// Kita buat konfigurasi Chart.js v2 dalam format JSON
$chartConfig = [
    'type' => 'line',
    'data' => [
        'labels' => $chartDates,
        'datasets' => [[
            'label' => 'Skor IOD',
            'backgroundColor' => 'rgba(59, 130, 246, 0.1)', // Warna biru transparan
            'borderColor' => '#3b82f6', // Warna garis biru
            'borderWidth' => 2,
            'pointRadius' => 3,
            'pointBackgroundColor' => '#1d4ed8',
            'data' => $chartIODs,
            'fill' => true,
        ]]
    ],
    'options' => [
        'title' => [
            'display' => true,
            'text' => 'Grafik Perkembangan IOD',
            'fontSize' => 14,
            'fontColor' => '#1e293b'
        ],
        'legend' => ['display' => false],
        'scales' => [
            'yAxes' => [[
                'ticks' => ['beginAtZero' => true],
                'gridLines' => ['color' => 'rgba(0, 0, 0, 0.05)']
            ]],
            'xAxes' => [[
                'gridLines' => ['display' => false]
            ]]
        ]
    ]
];

// Encode ke URL
$chartUrl = 'https://quickchart.io/chart?c=' . urlencode(json_encode($chartConfig)) . '&w=700&h=300';

// 4. Setup Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true); // WAJIB TRUE agar bisa load gambar dari URL (QuickChart)
$dompdf = new Dompdf($options);

// 5. Buat HTML Content
$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Atlet - '.htmlspecialchars($athlete['name']).'</title>
    <style>
        body { font-family: sans-serif; color: #333; font-size: 12px; }
        
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #1e293b; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; color: #1e293b; }
        .header p { margin: 5px 0 0; font-size: 12px; color: #64748b; }
        
        /* Section Profil */
        .profile-section { margin-bottom: 20px; background-color: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .profile-table { width: 100%; border-collapse: collapse; }
        .profile-table td { padding: 5px; font-size: 13px; vertical-align: top; }
        .label { font-weight: bold; width: 130px; color: #475569; }
        
        /* Section Grafik */
        .chart-section { text-align: center; margin-bottom: 30px; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; }
        .chart-img { width: 100%; height: auto; max-height: 300px; }

        /* Section History */
        .history-section h3 { 
            border-left: 5px solid #3b82f6; 
            padding-left: 10px; 
            color: #0f172a; 
            margin-top: 20px;
            margin-bottom: 15px;
        }
        
        .session-container { page-break-inside: avoid; margin-bottom: 25px; }
        .summary-box { 
            background: #eff6ff; 
            padding: 10px; 
            border: 1px solid #bfdbfe; 
            border-radius: 6px 6px 0 0; 
            margin-bottom: 0;
        }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 11px; border: 1px solid #cbd5e1; }
        .data-table th, .data-table td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        .data-table th { background-color: #e2e8f0; color: #334155; font-weight: bold; text-transform: uppercase; }
        .data-table tr:nth-child(even) { background-color: #f8fafc; }
        
        .badge { padding: 3px 8px; border-radius: 10px; font-size: 10px; color: white; font-weight: bold; display: inline-block; text-transform: uppercase; }
        .badge-super-maximal { background-color: #db2777; }
        .badge-maximum { background-color: #dc2626; }
        .badge-hard { background-color: #f97316; }
        .badge-medium { background-color: #eab308; color: black; }
        .badge-low { background-color: #22c55e; }
        .badge-very-low { background-color: #94a3b8; }
        
        .table-footer { background-color: #f1f5f9; font-weight: bold; font-size: 11px; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Human Indicator Overview Device</h1>
        <p>Laporan Riwayat Latihan Atlet</p>
    </div>

    <div class="profile-section">
        <table class="profile-table">
            <tr>
                <td class="label">Nama Lengkap</td><td>: '.htmlspecialchars($athlete['name']).'</td>
                <td class="label">Cabang Olahraga</td><td>: '.htmlspecialchars($athlete['sport']).'</td>
            </tr>
            <tr>
                <td class="label">Jenis Kelamin</td><td>: '.htmlspecialchars($athlete['gender']).'</td>
                <td class="label">Asal Daerah</td><td>: '.htmlspecialchars($athlete['origin']).'</td>
            </tr>
            <tr>
                <td class="label">Usia</td><td>: '.$athlete['age'].' Tahun</td>
                <td class="label">Postur</td><td>: '.$athlete['height'].' cm / '.$athlete['weight'].' kg</td>
            </tr>
            <tr>
                <td class="label">HR Max</td>
                <td>: '. (isset($athlete['hr_max']) && $athlete['hr_max'] > 0 ? $athlete['hr_max'] : (220 - $athlete['age'])) .' bpm</td>
                <td class="label"></td><td></td>
            </tr>
        </table>
    </div>';

    // Tampilkan Grafik jika ada data
    if (!empty($chartIODs)) {
        $html .= '
        <div class="chart-section">
            <img src="'.$chartUrl.'" class="chart-img" alt="Grafik IOD">
        </div>';
    }

    $html .= '
    <div class="history-section">
        <h3>Riwayat Detail Latihan</h3>';

        if (empty($athlete['trainings'])) {
            $html .= '<div style="padding: 20px; text-align: center; color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1;">Belum ada riwayat latihan yang tercatat.</div>';
        } else {
            // Loop data sesi latihan (urutkan dari terbaru ke terlama untuk tabel list)
            $trainings = array_reverse($athlete['trainings']);
            
            
            foreach ($trainings as $t) {
                $iodClass = $t['iod_class'] ?? '-';
                $badgeClass = getBadgeClass($iodClass);
                $manualCat = $t['manual_category'] ?? '-';
                
                $html .= '
                <div class="session-container">
                    <div class="summary-box">
                        <table style="width:100%; font-size:12px;">
                            <tr>
                                <td width="25%"><strong>Tanggal:</strong><br>'.date('d M Y', strtotime($t['date'])).'</td>
                                <td width="25%"><strong>Observer:</strong><br>'.htmlspecialchars($t['observer']).'</td>
                                <td width="25%"><strong>Kategori Latihan:</strong><br>'.$manualCat.'</td>
                                <td width="25%" style="text-align:right;">
                                    <span style="font-size:10px; color:#64748b;">IOD SCORE</span><br>
                                    <strong style="font-size:16px; color:#0f172a;">'.number_format($t['iod'], 2).'</strong>
                                    <br><span class="badge '.$badgeClass.'" style="margin-top:2px;">'.$iodClass.'</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th width="30%">Fase Latihan</th>
                                <th width="15%">Durasi (mnt)</th>
                                <th width="10%">Set</th>
                                <th width="10%">HRP</th>
                                <th width="20%">Intensitas Parsial</th>
                                <th width="15%">Rest (mnt)</th>
                            </tr>
                        </thead>
                        <tbody>';
                            
                            if (!empty($t['details'])) {
                                foreach ($t['details'] as $d) {
                                    $html .= '
                                    <tr>
                                        <td>'.htmlspecialchars($d['phase']).'</td>
                                        <td>'.number_format($d['duration'], 2).'</td>
                                        <td>'.$d['set'].'</td>
                                        <td>'.$d['hrp'].'</td>
                                        <td>'.number_format($d['partialIntensity'], 1).'%</td>
                                        <td style="color: #ea580c; font-weight:bold;">'.number_format($d['rest_after'], 2).'</td>
                                    </tr>';
                                }
                            } else {
                                $html .= '<tr><td colspan="6" style="text-align:center;">Detail tidak tersedia</td></tr>';
                            }
                            
                $html .= '
                        </tbody>
                        <tfoot>
                            <tr class="table-footer">
                                <td colspan="2">Vol. Abs: '.number_format($t['vol_absolute'], 2).' mnt</td>
                                <td colspan="2">Vol. Rel: '.number_format($t['vol_relatif'], 2).' mnt</td>
                                <td colspan="2" style="text-align:right;">Densitas: '.number_format($t['absolute_density'], 1).'%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>';
            }
        }

$html .= '
    </div>
</body>
</html>';

// 6. Render PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// 7. Output ke Browser
$dompdf->stream("Laporan_Atlet_".str_replace(' ', '_', $athlete['name']).".pdf", array("Attachment" => false));
?>