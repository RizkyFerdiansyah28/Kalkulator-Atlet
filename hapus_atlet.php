<?php 
// Pastikan nama file include sesuai, di file Anda namanya 'functions.php' (pakai 's')
require 'functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    if (delete_athlete($id) > 0) {
        echo "<script>
            alert('Data atlet dan riwayat latihannya berhasil dihapus!');
            document.location.href = 'list_athlete.php';
       </script>";
    } else {
        echo "<script>
            alert('Gagal menghapus data. Data mungkin tidak ditemukan.');
            document.location.href = 'list_athlete.php';
       </script>";
    }
} else {
    // Jika tidak ada ID, kembalikan ke list
    header("Location: list_athlete.php");
    exit;
}
?>