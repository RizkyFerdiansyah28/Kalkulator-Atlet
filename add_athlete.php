<?php
include 'functions.php';

$message = null;

// 1. LOGIKA TAMBAH CABOR BARU (POPUP)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_new_sport'])) {
    $msgSport = add_sport_category($_POST['new_sport_name']);
    echo "<script>alert('$msgSport');</script>";
}

// 2. LOGIKA TAMBAH ATLET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_athlete'])) {
    $message = add_new_athlete($_POST);
}

// Ambil list cabor terbaru
$sportsList = get_all_sports();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Atlet - Sistem Manajemen Atlet</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Style Modal Popup */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 300px; border-radius: 8px; text-align: center; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        .btn-small { padding: 4px 8px; font-size: 12px; margin-left: 5px; cursor: pointer; background: #e2e8f0; border: 1px solid #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body>
    <header><div class="container"><h1>Human Indicator Overview Device</h1></div></header>

    <nav>
        <div class="container">
            <div class="nav-buttons">
                <a href="dashboard.php" class="nav-button">Dashboard</a>
                <a href="list_athlete.php" class="nav-button">Daftar Atlet</a>
                <a href="add_athlete.php" class="nav-button active">Input Atlet</a>
                <a href="input_training.php" class="nav-button">Input Latihan</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <?php if ($message): ?><div class="alert-box"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <div class="panel" style="max-width: 600px; margin: 0 auto;">
            <h2>Input Data Atlet</h2>
            <form method="POST">
                <div class="form-group"><label>Nama</label><input type="text" name="name" class="form-input" required></div>
                <div class="form-grid">
                    <div class="form-group"><label>Gender</label><select name="gender" class="form-select"><option>Laki-laki</option><option>Perempuan</option></select></div>
                    <div class="form-group"><label>Usia</label><input type="number" name="age" class="form-input" required></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Asal</label><input type="text" name="origin" class="form-input" required></div>
                    
                    <div class="form-group">
                        <label>Cabor <button type="button" class="btn-small" onclick="openModal('modalSport')">+ Baru</button></label>
                        <select name="sport" class="form-select" required>
                            <option value="">-- Pilih Cabor --</option>
                            <?php foreach ($sportsList as $s): ?>
                                <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Berat (kg)</label><input type="number" step="0.1" name="weight" class="form-input" required></div>
                    <div class="form-group"><label>Tinggi (cm)</label><input type="number" name="height" class="form-input" required></div>
                </div>
                <button type="submit" name="submit_athlete" class="btn btn-primary" style="margin-top: 1rem;">Simpan</button>
            </form>
        </div>
    </main>

    <div id="modalSport" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('modalSport')">&times;</span>
            <h3>Tambah Cabor</h3>
            <form method="POST">
                <input type="text" name="new_sport_name" class="form-input" placeholder="Nama Cabor Baru" required style="margin-bottom: 10px;">
                <button type="submit" name="submit_new_sport" class="btn btn-primary">Simpan Cabor</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = "block"; }
        function closeModal(id) { document.getElementById(id).style.display = "none"; }
        window.onclick = function(event) { if (event.target.classList.contains('modal')) event.target.style.display = "none"; }
    </script>
</body>
</html>