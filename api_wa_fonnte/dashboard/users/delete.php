<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verifyCsrf();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    flash('error', 'ID user tidak valid.');
    redirect('index.php');
}

try {
    // Ambil data sebelum dihapus agar nomor WhatsApp masih tersedia untuk notifikasi.
    $find = $pdo->prepare('SELECT nama, no_hp FROM users WHERE id = :id LIMIT 1');
    $find->execute(['id' => $id]);
    $user = $find->fetch();

    if (!$user) {
        flash('error', 'User tidak ditemukan atau sudah dihapus.');
        redirect('index.php');
    }

    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);

    $notif = notifyUserCrud($pdo, (int) $id, (string) $user['no_hp'], (string) $user['nama'], 'delete');
    if ($notif['success']) {
        flash('success', 'User berhasil dihapus dan notifikasi WhatsApp berhasil dikirim.');
    } else {
        flash('success', 'User berhasil dihapus, tetapi notifikasi WhatsApp gagal dikirim: ' . $notif['error']);
    }
} catch (PDOException $e) {
    error_log('Delete user error: ' . $e->getMessage());
    flash('error', 'User gagal dihapus.');
}

redirect('index.php');
