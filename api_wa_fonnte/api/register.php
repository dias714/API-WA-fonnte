<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function kirimJson(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ambilString(array $input, string $key): string
{
    $value = $input[$key] ?? '';
    return is_string($value) || is_numeric($value) ? (string) $value : '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    kirimJson(405, ['success' => false, 'message' => 'Metode yang diizinkan hanya POST.']);
}

require_once __DIR__ . '/../functions/fonnte.php';
require_once __DIR__ . '/../functions/user_notification.php';

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput === false ? '' : $rawInput, true);
    if (!is_array($input)) {
        kirimJson(400, ['success' => false, 'message' => 'Format JSON tidak valid.']);
    }
} else {
    $input = $_POST;
}

$nama = trim(ambilString($input, 'nama'));
$email = strtolower(trim(ambilString($input, 'email')));
$nomorInput = trim(ambilString($input, 'no_hp'));
$alamat = trim(ambilString($input, 'alamat'));
$password = ambilString($input, 'password');
$noHp = formatNomorWA($nomorInput);

$errors = [];
if ($nama === '' || mb_strlen($nama) > 100 || preg_match('/[\r\n]/', $nama)) {
    $errors['nama'] = 'Nama wajib diisi, maksimal 100 karakter, dan tidak boleh memuat baris baru.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    $errors['email'] = 'Masukkan alamat email yang valid (maksimal 150 karakter).';
}
if (!preg_match('/^628[1-9][0-9]{7,10}$/', $noHp)) {
    $errors['no_hp'] = 'Masukkan nomor WhatsApp Indonesia yang valid, misalnya 081234567890.';
}
if ($alamat === '' || mb_strlen($alamat) > 500) {
    $errors['alamat'] = 'Alamat wajib diisi dan maksimal 500 karakter.';
}
if (strlen($password) < 8 || strlen($password) > 72) {
    $errors['password'] = 'Password harus terdiri dari 8 sampai 72 byte.';
}

if ($errors !== []) {
    kirimJson(422, [
        'success' => false,
        'message' => 'Periksa kembali data yang dimasukkan.',
        'errors' => $errors,
    ]);
}

try {
    $pdo = require __DIR__ . '/../config/database.php';

    $check = $pdo->prepare('SELECT email, no_hp FROM users WHERE email = :email OR no_hp = :no_hp');
    $check->execute(['email' => $email, 'no_hp' => $noHp]);
    $existing = $check->fetchAll();

    foreach ($existing as $row) {
        if ($row['email'] === $email) {
            $errors['email'] = 'Email sudah terdaftar.';
        }
        if ($row['no_hp'] === $noHp) {
            $errors['no_hp'] = 'Nomor WhatsApp sudah terdaftar.';
        }
    }

    if ($errors !== []) {
        kirimJson(409, [
            'success' => false,
            'message' => 'Email atau nomor WhatsApp sudah digunakan.',
            'errors' => $errors,
        ]);
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    if ($passwordHash === false) {
        throw new RuntimeException('Password tidak dapat diproses.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (nama, email, no_hp, alamat, password)
         VALUES (:nama, :email, :no_hp, :alamat, :password)'
    );
    $insert->execute([
        'nama' => $nama,
        'email' => $email,
        'no_hp' => $noHp,
        'alamat' => $alamat,
        'password' => $passwordHash,
    ]);
    $userId = (int) $pdo->lastInsertId();
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        kirimJson(409, [
            'success' => false,
            'message' => 'Email atau nomor WhatsApp sudah digunakan.',
        ]);
    }

    error_log('Database registration error: ' . $exception->getMessage());
    kirimJson(500, ['success' => false, 'message' => 'Registrasi gagal karena masalah database.']);
} catch (RuntimeException $exception) {
    error_log('Password hashing error: ' . $exception->getMessage());
    kirimJson(500, ['success' => false, 'message' => 'Registrasi tidak dapat diproses.']);
}

$hasilNotif = notifyUserCrud($pdo, $userId, $noHp, $nama, 'create', ['email' => $email]);
$statusLog = $hasilNotif['success'] ? 'sent' : 'failed';
$logSaved = true;

// notifyUserCrud sudah menyimpan log WhatsApp.

kirimJson(201, [
    'success' => true,
    'message' => 'Registrasi berhasil.',
    'user_id' => $userId,
    'whatsapp' => [
        'status' => $statusLog,
        'message' => $hasilNotif['success']
            ? 'Pesan konfirmasi WhatsApp berhasil dikirim.'
            : 'Akun tersimpan, tetapi pesan WhatsApp tidak berhasil dikirim: ' . $hasilNotif['error'],
    ],
]);
