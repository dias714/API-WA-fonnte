<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) { flash('error','ID user tidak valid.'); redirect('index.php'); }

$stmt = $pdo->prepare('SELECT id,nama,email,no_hp,alamat FROM users WHERE id=:id');
$stmt->execute(['id'=>$id]);
$user = $stmt->fetch();
if (!$user) { flash('error','User tidak ditemukan.'); redirect('index.php'); }

$errors = [];
$data = ['nama'=>$user['nama'],'email'=>$user['email'],'no_hp'=>$user['no_hp'],'alamat'=>$user['alamat']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach ($data as $key => $_) $data[$key] = trim((string) ($_POST[$key] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($data['nama'] === '' || mb_strlen($data['nama']) > 100) $errors[]='Nama wajib diisi dan maksimal 100 karakter.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 150) $errors[]='Email tidak valid.';
    $data['no_hp'] = preg_replace('/\D+/', '', $data['no_hp']) ?? '';
    if (str_starts_with($data['no_hp'], '0')) $data['no_hp']='62'.substr($data['no_hp'],1);
    elseif (str_starts_with($data['no_hp'], '8')) $data['no_hp']='62'.$data['no_hp'];
    if (!preg_match('/^628[1-9][0-9]{7,10}$/',$data['no_hp'])) $errors[]='Nomor WhatsApp Indonesia tidak valid.';
    if ($data['alamat']==='' || mb_strlen($data['alamat'])>500) $errors[]='Alamat wajib diisi dan maksimal 500 karakter.';
    if ($password !== '' && (strlen($password)<8 || strlen($password)>72)) $errors[]='Password baru harus 8–72 karakter.';

    if (!$errors) {
        $check=$pdo->prepare('SELECT id FROM users WHERE (email=:email OR no_hp=:no_hp) AND id<>:id LIMIT 1');
        $check->execute(['email'=>strtolower($data['email']),'no_hp'=>$data['no_hp'],'id'=>$id]);
        if($check->fetch()) $errors[]='Email atau nomor WhatsApp sudah digunakan user lain.';
    }

    if (!$errors) {
        try {
            if ($password !== '') {
                $stmt=$pdo->prepare('UPDATE users SET nama=:nama,email=:email,no_hp=:no_hp,alamat=:alamat,password=:password WHERE id=:id');
                $stmt->execute(['nama'=>$data['nama'],'email'=>strtolower($data['email']),'no_hp'=>$data['no_hp'],'alamat'=>$data['alamat'],'password'=>password_hash($password,PASSWORD_BCRYPT),'id'=>$id]);
            } else {
                $stmt=$pdo->prepare('UPDATE users SET nama=:nama,email=:email,no_hp=:no_hp,alamat=:alamat WHERE id=:id');
                $stmt->execute(['nama'=>$data['nama'],'email'=>strtolower($data['email']),'no_hp'=>$data['no_hp'],'alamat'=>$data['alamat'],'id'=>$id]);
            }

            $notif = notifyUserCrud($pdo, (int) $id, $data['no_hp'], $data['nama'], 'update', [
                'email' => strtolower($data['email']),
                'no_hp' => $data['no_hp'],
                'password_changed' => $password !== '',
            ]);
            if ($notif['success']) {
                flash('success', 'User berhasil diperbarui dan notifikasi WhatsApp berhasil dikirim.');
            } else {
                flash('success', 'User berhasil diperbarui, tetapi notifikasi WhatsApp gagal dikirim: ' . $notif['error']);
            }
            redirect('index.php');
        } catch(PDOException $e) {
            $errors[] = $e->getCode()==='23000' ? 'Email atau nomor WhatsApp sudah digunakan.' : 'Gagal memperbarui user.';
        }
    }
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit User</title>
<style>
:root{--bg:#f3f7f5;--card:#fff;--text:#1e2d2b;--muted:#5d6d68;--primary:#0d7a4f;--border:#dfeae3;--shadow:0 12px 28px rgba(20,36,32,.08)}
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:var(--bg);color:var(--text)}.container{max-width:760px;margin:32px auto;padding:0 20px 50px}.panel{background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);padding:24px}h1{margin-top:0}.muted{color:var(--muted)}.field{margin:0 0 16px}.field label{display:block;font-weight:700;margin-bottom:7px}.field input,.field textarea{width:100%;padding:11px 13px;border:1px solid var(--border);border-radius:9px;font:inherit}.field textarea{min-height:100px;resize:vertical}.actions{display:flex;gap:10px;margin-top:20px}.btn{border:0;border-radius:9px;padding:11px 15px;font-weight:700;cursor:pointer;text-decoration:none}.primary{background:var(--primary);color:white}.secondary{background:#eaf4ef;color:#075d3d}.alert{padding:12px 14px;border-radius:9px;margin-bottom:16px;background:#fff0f0;color:#8d2929}.alert ul{margin:0;padding-left:20px}
</style></head><body><div class="container"><div class="panel">
<h1>Edit User #<?= e($id) ?></h1><p class="muted">Kosongkan password jika tidak ingin mengubahnya.</p>
<?php if($errors): ?><div class="alert"><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
<div class="field"><label>Nama</label><input name="nama" maxlength="100" required value="<?= e($data['nama']) ?>"></div>
<div class="field"><label>Email</label><input name="email" type="email" maxlength="150" required value="<?= e($data['email']) ?>"></div>
<div class="field"><label>No. WhatsApp</label><input name="no_hp" type="tel" required value="<?= e($data['no_hp']) ?>"></div>
<div class="field"><label>Alamat</label><textarea name="alamat" maxlength="500" required><?= e($data['alamat']) ?></textarea></div>
<div class="field"><label>Password Baru <small>(opsional)</small></label><input name="password" type="password" minlength="8" maxlength="72"></div>
<div class="actions"><a class="btn secondary" href="index.php">Batal</a><button class="btn primary" type="submit">Update User</button></div>
</form></div></div></body></html>
