<?php
include 'functions.php';

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_athlete'])) {
    $message = add_new_athlete($_POST);
    // Redirect agar tidak resubmit saat refresh, atau cukup tampilkan pesan
    // header("Location: list_athlete.php"); // Opsional jika ingin langsung pindah
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Atlet - Sistem Manajemen Atlet</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header><div class="container"><h1>Sistem Pelatihan Atlet</h1></div></header>

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
                    <div class="form-group"><label>Asal</label><input type="text" name="origin" class="form-input"></div>
                    <div class="form-group"><label>Cabor</label><input type="text" name="sport" class="form-input"></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Berat (kg)</label><input type="number" step="0.1" name="weight" class="form-input"></div>
                    <div class="form-group"><label>Tinggi (cm)</label><input type="number" name="height" class="form-input"></div>
                </div>
                <button type="submit" name="submit_athlete" class="btn btn-primary" style="margin-top: 1rem;">Simpan</button>
            </form>
        </div>
    </main>
</body>
</html>