<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../functions/fonnte.php';
require_once __DIR__ . '/../functions/user_notification.php';
$pdo = require __DIR__ . '/../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirectWith(string $message, string $type = 'success'): never {
    header('Location: users.php?' . http_build_query(['message' => $message, 'type' => $type]));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        redirectWith('Token keamanan tidak valid.', 'error');
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $nama = trim((string)($_POST['nama'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $noHp = formatNomorWA(trim((string)($_POST['no_hp'] ?? '')));
            $alamat = trim((string)($_POST['alamat'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            $errors = [];
            if ($nama === '' || mb_strlen($nama) > 100) $errors[] = 'Nama wajib diisi maksimal 100 karakter.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors[] = 'Email tidak valid.';
            if (!preg_match('/^628[1-9][0-9]{7,10}$/', $noHp)) $errors[] = 'Nomor WhatsApp tidak valid.';
            if ($alamat === '' || mb_strlen($alamat) > 500) $errors[] = 'Alamat wajib diisi maksimal 500 karakter.';
            if (strlen($password) < 8 || strlen($password) > 72) $errors[] = 'Password harus 8-72 karakter.';

            if ($errors) redirectWith(implode(' ', $errors), 'error');

            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email OR no_hp = :no_hp LIMIT 1');
            $check->execute(['email' => $email, 'no_hp' => $noHp]);
            if ($check->fetch()) redirectWith('Email atau nomor WhatsApp sudah digunakan.', 'error');

            $stmt = $pdo->prepare(
                'INSERT INTO users (nama, email, no_hp, alamat, password)
                 VALUES (:nama, :email, :no_hp, :alamat, :password)'
            );
            $hash = password_hash($password, PASSWORD_BCRYPT);
            if ($hash === false) redirectWith('Password gagal diproses.', 'error');

            $stmt->execute([
                'nama' => $nama,
                'email' => $email,
                'no_hp' => $noHp,
                'alamat' => $alamat,
                'password' => $hash,
            ]);

            $userId = (int)$pdo->lastInsertId();
            $notif = notifyUserCrud($pdo, $userId, $noHp, $nama, 'create', ['email' => $email]);
            $msg = $notif['success']
                ? 'User berhasil ditambahkan dan notifikasi WhatsApp berhasil dikirim.'
                : 'User berhasil ditambahkan, tetapi notifikasi WhatsApp gagal dikirim: ' . $notif['error'];
            redirectWith($msg, $notif['success'] ? 'success' : 'error');
        }

        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim((string)($_POST['nama'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $noHp = formatNomorWA(trim((string)($_POST['no_hp'] ?? '')));
            $alamat = trim((string)($_POST['alamat'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            $errors = [];
            if ($id < 1) $errors[] = 'ID user tidak valid.';
            if ($nama === '' || mb_strlen($nama) > 100) $errors[] = 'Nama wajib diisi maksimal 100 karakter.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors[] = 'Email tidak valid.';
            if (!preg_match('/^628[1-9][0-9]{7,10}$/', $noHp)) $errors[] = 'Nomor WhatsApp tidak valid.';
            if ($alamat === '' || mb_strlen($alamat) > 500) $errors[] = 'Alamat wajib diisi maksimal 500 karakter.';
            if ($password !== '' && (strlen($password) < 8 || strlen($password) > 72)) $errors[] = 'Password baru harus 8-72 karakter.';

            if ($errors) redirectWith(implode(' ', $errors), 'error');

            $check = $pdo->prepare('SELECT id FROM users WHERE (email = :email OR no_hp = :no_hp) AND id <> :id LIMIT 1');
            $check->execute(['email' => $email, 'no_hp' => $noHp, 'id' => $id]);
            if ($check->fetch()) redirectWith('Email atau nomor WhatsApp sudah digunakan user lain.', 'error');

            $oldStmt = $pdo->prepare('SELECT id, nama, no_hp FROM users WHERE id=:id');
            $oldStmt->execute(['id' => $id]);
            $oldUser = $oldStmt->fetch();
            if (!$oldUser) redirectWith('User tidak ditemukan.', 'error');

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                if ($hash === false) redirectWith('Password gagal diproses.', 'error');
                $stmt = $pdo->prepare(
                    'UPDATE users SET nama=:nama, email=:email, no_hp=:no_hp, alamat=:alamat, password=:password WHERE id=:id'
                );
                $stmt->execute([
                    'nama'=>$nama, 'email'=>$email, 'no_hp'=>$noHp, 'alamat'=>$alamat,
                    'password'=>$hash, 'id'=>$id
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE users SET nama=:nama, email=:email, no_hp=:no_hp, alamat=:alamat WHERE id=:id'
                );
                $stmt->execute([
                    'nama'=>$nama, 'email'=>$email, 'no_hp'=>$noHp, 'alamat'=>$alamat, 'id'=>$id
                ]);
            }

            $notif = notifyUserCrud($pdo, $id, $noHp, $nama, 'update', [
                'email' => $email,
                'password_changed' => $password !== '',
            ]);
            $msg = $notif['success']
                ? 'User berhasil diperbarui dan notifikasi WhatsApp berhasil dikirim.'
                : 'User berhasil diperbarui, tetapi notifikasi WhatsApp gagal dikirim: ' . $notif['error'];
            redirectWith($msg, $notif['success'] ? 'success' : 'error');
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) redirectWith('ID user tidak valid.', 'error');

            $userStmt = $pdo->prepare('SELECT id, nama, no_hp FROM users WHERE id=:id');
            $userStmt->execute(['id' => $id]);
            $user = $userStmt->fetch();
            if (!$user) redirectWith('User tidak ditemukan.', 'error');

            // Kirim notifikasi sebelum DELETE agar log masih bisa memakai user_id.
            $notif = notifyUserCrud($pdo, (int)$user['id'], (string)$user['no_hp'], (string)$user['nama'], 'delete');

            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute(['id' => $id]);

            $msg = $notif['success']
                ? 'User berhasil dihapus dan notifikasi WhatsApp berhasil dikirim.'
                : 'User berhasil dihapus, tetapi notifikasi WhatsApp gagal dikirim: ' . $notif['error'];
            redirectWith($msg, $notif['success'] ? 'success' : 'error');
        }

        redirectWith('Aksi tidak dikenali.', 'error');
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            redirectWith('Email atau nomor WhatsApp sudah digunakan.', 'error');
        }
        error_log('CRUD users error: ' . $exception->getMessage());
        redirectWith('Terjadi kesalahan database.', 'error');
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$where = '';
$params = [];

if ($search !== '') {
    $where = 'WHERE nama LIKE :search OR email LIKE :search OR no_hp LIKE :search OR alamat LIKE :search';
    $params['search'] = '%' . $search . '%';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare(
    "SELECT id, nama, email, no_hp, alamat, created_at
     FROM users $where ORDER BY id DESC LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) $listStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$users = $listStmt->fetchAll();

$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT id, nama, email, no_hp, alamat FROM users WHERE id=:id');
    $stmt->execute(['id'=>$editId]);
    $editUser = $stmt->fetch() ?: null;
}

$message = (string)($_GET['message'] ?? '');
$type = (string)($_GET['type'] ?? 'success');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CRUD User - Dashboard Admin</title>
<style>
:root{--bg:#f3f7f5;--card:#fff;--text:#1e2d2b;--muted:#66756f;--primary:#0d7a4f;--dark:#085d3d;--danger:#c53d3d;--border:#dfeae3;--shadow:0 12px 28px rgba(20,36,32,.08)}
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:var(--bg);color:var(--text)}
.container{max-width:1250px;margin:28px auto;padding:0 20px 50px}.topbar{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:20px}
h1,h2{margin:0}.topbar a,.btn{display:inline-block;text-decoration:none;border:0;border-radius:9px;padding:10px 14px;font-weight:700;cursor:pointer;font-size:.9rem}
.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:var(--dark)}.btn-edit{background:#eaf3ff;color:#1769aa}.btn-delete{background:#fff0f0;color:var(--danger)}
.layout{display:grid;grid-template-columns:340px 1fr;gap:20px}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);padding:20px}
.field{margin-bottom:13px}label{display:block;font-weight:700;margin-bottom:6px;font-size:.9rem}
input,textarea{width:100%;padding:11px 12px;border:1px solid #cbd8d2;border-radius:9px;font:inherit}textarea{min-height:90px;resize:vertical}
.actions{display:flex;gap:8px;flex-wrap:wrap}.search{display:flex;gap:8px;margin-bottom:15px}.search input{flex:1}
table{width:100%;border-collapse:collapse;font-size:.9rem}th,td{text-align:left;padding:11px 9px;border-bottom:1px solid var(--border);vertical-align:top}th{font-size:.78rem;color:var(--muted);background:#f7faf8}
.alert{padding:12px 14px;border-radius:9px;margin-bottom:18px}.success{background:#e7f7ed;color:#125b35}.error{background:#fff0f0;color:#8a2020}
.pagination{display:flex;gap:6px;margin-top:16px}.pagination a{padding:8px 11px;border:1px solid var(--border);border-radius:8px;text-decoration:none;color:var(--text)}.pagination .active{background:var(--primary);color:white}
small{color:var(--muted)}form.inline{display:inline}
@media(max-width:900px){.layout{grid-template-columns:1fr}.table-wrap{overflow:auto}}
</style>
</head>
<body>
<div class="container">
  <div class="topbar">
    <div><h1>CRUD Data User</h1><small>Kelola akun yang tersimpan di database.</small></div>
    <a class="btn btn-primary" href="index.php">← Dashboard</a>
  </div>

  <?php if ($message !== ''): ?><div class="alert <?= $type === 'error' ? 'error' : 'success' ?>"><?= e($message) ?></div><?php endif; ?>

  <div class="layout">
    <section class="card">
      <h2><?= $editUser ? 'Edit User' : 'Tambah User' ?></h2>
      <p><small><?= $editUser ? 'Ubah data user. Kosongkan password jika tidak ingin menggantinya.' : 'Buat user baru tanpa harus melalui form registrasi utama.' ?></small></p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
        <?php if ($editUser): ?><input type="hidden" name="id" value="<?= e((string)$editUser['id']) ?>"><?php endif; ?>
        <div class="field"><label>Nama</label><input name="nama" maxlength="100" required value="<?= e((string)($editUser['nama'] ?? '')) ?>"></div>
        <div class="field"><label>Email</label><input name="email" type="email" maxlength="150" required value="<?= e((string)($editUser['email'] ?? '')) ?>"></div>
        <div class="field"><label>Nomor WhatsApp</label><input name="no_hp" maxlength="20" required placeholder="081234567890" value="<?= e((string)($editUser['no_hp'] ?? '')) ?>"></div>
        <div class="field"><label>Alamat</label><textarea name="alamat" maxlength="500" required><?= e((string)($editUser['alamat'] ?? '')) ?></textarea></div>
        <div class="field"><label>Password <?= $editUser ? '(opsional)' : '' ?></label><input name="password" type="password" minlength="8" maxlength="72" <?= $editUser ? '' : 'required' ?>></div>
        <div class="actions">
          <button class="btn btn-primary" type="submit"><?= $editUser ? 'Simpan Perubahan' : 'Tambah User' ?></button>
          <?php if ($editUser): ?><a class="btn btn-delete" href="users.php">Batal</a><?php endif; ?>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Daftar User (<?= $total ?>)</h2>
      <form class="search" method="get">
        <input name="search" placeholder="Cari nama, email, nomor..." value="<?= e($search) ?>">
        <button class="btn btn-primary" type="submit">Cari</button>
        <?php if ($search !== ''): ?><a class="btn btn-delete" href="users.php">Reset</a><?php endif; ?>
      </form>
      <div class="table-wrap">
      <table>
        <thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>WhatsApp</th><th>Alamat</th><th>Terdaftar</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td><?= e((string)$user['id']) ?></td>
            <td><?= e((string)$user['nama']) ?></td>
            <td><?= e((string)$user['email']) ?></td>
            <td><?= e((string)$user['no_hp']) ?></td>
            <td><?= e((string)$user['alamat']) ?></td>
            <td><small><?= e((string)$user['created_at']) ?></small></td>
            <td>
              <div class="actions">
                <a class="btn btn-edit" href="?edit=<?= (int)$user['id'] ?>">Edit</a>
                <form class="inline" method="post" onsubmit="return confirm('Yakin ingin menghapus user ini?');">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                  <button class="btn btn-delete" type="submit">Hapus</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="7">Belum ada user.</td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for($i=1;$i<=$totalPages;$i++): ?>
          <a class="<?= $i===$page?'active':'' ?>" href="?<?= http_build_query(['search'=>$search,'page'=>$i]) ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </section>
  </div>
</div>
</body>
</html>
