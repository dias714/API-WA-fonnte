<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/fonnte.php';

function formatNomorWA(string $nomor): string
{
    $nomor = preg_replace('/\D+/', '', trim($nomor)) ?? '';

    if (str_starts_with($nomor, '0')) {
        return '62' . substr($nomor, 1);
    }

    if (str_starts_with($nomor, '8')) {
        return '62' . $nomor;
    }

    return $nomor;
}

/**
 * @return array{success: bool, http_code: int, response: string, error: ?string}
 */
function kirimWhatsApp(string $nomor, string $pesan): array
{
    if (FONNTE_API_TOKEN === '') {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => '',
            'error' => 'FONNTE_API_TOKEN belum dikonfigurasi.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => '',
            'error' => 'Ekstensi cURL PHP belum aktif.',
        ];
    }

    $curl = curl_init(FONNTE_API_ENDPOINT);
    if ($curl === false) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => '',
            'error' => 'Tidak dapat memulai koneksi cURL.',
        ];
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'target' => $nomor,
            'message' => $pesan,
        ],
        CURLOPT_HTTPHEADER => ['Authorization: ' . FONNTE_API_TOKEN],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($curl);
    $curlError = $response === false ? curl_error($curl) : null;
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false) {
        return [
            'success' => false,
            'http_code' => $httpCode,
            'response' => '',
            'error' => $curlError ?: 'Permintaan ke Fonnte gagal.',
        ];
    }

    $decoded = json_decode($response, true);
    $apiSuccess = is_array($decoded) && ($decoded['status'] ?? false) === true;
    $success = $httpCode >= 200 && $httpCode < 300 && $apiSuccess;

    return [
        'success' => $success,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $success ? null : 'Fonnte menolak atau gagal memproses pengiriman.',
    ];
}
