<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$search = trim((string) ($_GET['search'] ?? ''));

if ($search !== '') {
    $stmt = $pdo->prepare(
        'SELECT id, nama, email, no_hp, alamat, created_at
         FROM users
         WHERE nama LIKE :search OR email LIKE :search OR no_hp LIKE :search
         ORDER BY id DESC'
    );
    $stmt->execute(['search' => '%' . $search . '%']);
} else {
    $stmt = $pdo->query(
        'SELECT id, nama, email, no_hp, alamat, created_at
         FROM users ORDER BY id DESC'
    );
}
$users = $stmt->fetchAll();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CRUD User</title>
<style>
:root{--bg:#f3f7f5;--card:#fff;--text:#1e2d2b;--muted:#5d6d68;--primary:#0d7a4f;--danger:#c53d3d;--border:#dfeae3;--shadow:0 12px 28px rgba(20,36,32,.08)}
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:var(--bg);color:var(--text)}
.container{max-width:1250px;margin:32px auto;padding:0 20px 50px}.topbar{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:18px 22px;box-shadow:var(--shadow);display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:20px}
h1{margin:0;font-size:1.65rem}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-block;text-decoration:none;border:0;border-radius:9px;padding:10px 14px;font-weight:700;cursor:pointer;font-size:.9rem}.primary{background:var(--primary);color:white}.secondary{background:#eaf4ef;color:#075d3d}.danger{background:#fff0f0;color:var(--danger)}
.panel{background:var(--card);border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);padding:18px}
.search{display:flex;gap:10px;margin-bottom:18px}.search input{flex:1;padding:11px 13px;border:1px solid var(--border);border-radius:9px;font-size:1rem}
.alert{padding:12px 14px;border-radius:9px;margin-bottom:16px}.success{background:#eafaf2;color:#17683f}.error{background:#fff0f0;color:#8d2929}
.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:900px}th,td{text-align:left;padding:12px 10px;border-bottom:1px solid var(--border);vertical-align:top}th{font-size:.78rem;color:var(--muted);text-transform:uppercase;background:#f7faf8}td small{color:var(--muted)}
.row-actions{display:flex;gap:7px}.empty{padding:25px;text-align:center;color:var(--muted)}
@media(max-width:700px){.topbar{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div><h1>Manajemen User</h1><small>CRUD data pengguna</small></div>
        <div class="actions">
            <a class="btn secondary" href="../index.php">← Dashboard</a>
            <a class="btn primary" href="create.php">+ Tambah User</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="panel">
        <form class="search" method="get">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Cari nama, email, atau nomor WhatsApp...">
            <button class="btn primary" type="submit">Cari</button>
            <?php if ($search !== ''): ?><a class="btn secondary" href="index.php">Reset</a><?php endif; ?>
        </form>

        <?php if ($users): ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>No. WhatsApp</th><th>Alamat</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= e($user['id']) ?></td>
                    <td><strong><?= e($user['nama']) ?></strong></td>
                    <td><?= e($user['email']) ?></td>
                    <td><?= e($user['no_hp']) ?></td>
                    <td><?= e($user['alamat']) ?></td>
                    <td><small><?= e($user['created_at']) ?></small></td>
                    <td>
                        <div class="row-actions">
                            <a class="btn secondary" href="edit.php?id=<?= e($user['id']) ?>">Edit</a>
                            <form method="post" action="delete.php" onsubmit="return confirm('Yakin ingin menghapus user ini?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($user['id']) ?>">
                                <button class="btn danger" type="submit">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?><div class="empty">Belum ada data user.</div><?php endif; ?>
    </div>
</div>
</body>
</html>
