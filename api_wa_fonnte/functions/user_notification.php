<?php
declare(strict_types=1);

require_once __DIR__ . '/fonnte.php';

/**
 * Kirim pesan interaktif untuk aktivitas akun.
 * @return array{success: bool, error: ?string, message: string, http_code: int, response: string}
 */
function notifyUserCrud(PDO $pdo, int $userId, string $noHp, string $nama, string $aksi, array $extra = []): array
{
    $nomor = formatNomorWA($noHp);
    $namaAman = trim($nama) !== '' ? trim($nama) : 'Pengguna';

    switch ($aksi) {
        case 'create':
            $pesan = "👋 *Halo {$namaAman}!*

" .
                "🎉 *PENDAFTARAN BERHASIL!*

" .
                "Akun kamu berhasil dibuat dan sudah siap digunakan. 🚀

" .
                "📋 *Detail akun*
" .
                "🆔 ID User: {$userId}
" .
                "📧 Email: " . ($extra['email'] ?? '-') . "
" .
                "📱 WhatsApp: {$nomor}

" .
                "🔐 Password tersimpan dengan aman dan tidak ditampilkan melalui WhatsApp.

" .
                "💡 *Langkah selanjutnya:* kamu sudah bisa menggunakan akunmu.

" .
                "❓ Jika ada data yang salah atau membutuhkan bantuan, balas pesan ini dengan *BANTUAN* dan hubungi admin.

" .
                "━━━━━━━━━━━━━━━━━━
" .
                "🤖 Notifikasi otomatis
" .
                "Sistem API WhatsApp Fonnte";
            break;

        case 'update':
            $passwordInfo = !empty($extra['password_changed'])
                ? "\n🔑 Password: *berhasil diubah*"
                : "\n🔑 Password: *tidak diubah*";

            $pesan = "👋 *Halo {$namaAman}!*

" .
                "✏️ *DATA AKUN DIPERBARUI*

" .
                "Admin baru saja memperbarui data akun kamu.

" .
                "📋 *Data terbaru*
" .
                "🆔 ID User: {$userId}
" .
                "📧 Email: " . ($extra['email'] ?? '-') . "
" .
                "📱 WhatsApp: {$nomor}" . $passwordInfo . "

" .
                "✅ Perubahan sudah tersimpan.

" .
                "🔎 Bukan kamu yang melakukan perubahan? Balas pesan ini dengan *BANTUAN* dan segera hubungi admin.

" .
                "━━━━━━━━━━━━━━━━━━
" .
                "🤖 Notifikasi otomatis
" .
                "Sistem API WhatsApp Fonnte";
            break;

        case 'delete':
            $pesan = "👋 *Halo {$namaAman}!*

" .
                "⚠️ *AKUN TELAH DIHAPUS*

" .
                "Akun kamu telah dihapus dari sistem oleh admin.

" .
                "🆔 ID User: {$userId}
" .
                "📱 Nomor: {$nomor}

" .
                "Jika penghapusan ini tidak kamu kenali, balas pesan ini dengan *BANTUAN* dan hubungi admin.

" .
                "━━━━━━━━━━━━━━━━━━
" .
                "🤖 Notifikasi otomatis
" .
                "Sistem API WhatsApp Fonnte";
            break;

        default:
            throw new InvalidArgumentException('Aksi notifikasi tidak valid.');
    }

    $hasil = kirimWhatsApp($nomor, $pesan);
    $status = $hasil['success'] ? 'sent' : 'failed';

    try {
        $log = $pdo->prepare(
            'INSERT INTO log_whatsapp (user_id, no_tujuan, pesan, status, response)
             VALUES (:user_id, :no_tujuan, :pesan, :status, :response)'
        );
        $log->execute([
            'user_id' => $userId,
            'no_tujuan' => $nomor,
            'pesan' => $pesan,
            'status' => $status,
            'response' => json_encode([
                'http_code' => $hasil['http_code'],
                'response' => $hasil['response'],
                'error' => $hasil['error'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (PDOException $e) {
        error_log('WhatsApp notification log error: ' . $e->getMessage());
    }

    return [
        'success' => $hasil['success'],
        'error' => $hasil['success'] ? null : ($hasil['error'] ?? 'Notifikasi WhatsApp gagal dikirim.'),
        'message' => $pesan,
        'http_code' => $hasil['http_code'],
        'response' => $hasil['response'],
    ];
}
