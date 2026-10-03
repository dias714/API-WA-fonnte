<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/../config/database.php';

$totalUsersStmt = $pdo->query('SELECT COUNT(*) FROM users');
$totalUsers = (int) $totalUsersStmt->fetchColumn();

$totalLogsStmt = $pdo->query('SELECT COUNT(*) FROM log_whatsapp');
$totalLogs = (int) $totalLogsStmt->fetchColumn();

$sentStmt = $pdo->query("SELECT COUNT(*) FROM log_whatsapp WHERE status = 'sent'");
$sentCount = (int) $sentStmt->fetchColumn();

$failedStmt = $pdo->query("SELECT COUNT(*) FROM log_whatsapp WHERE status = 'failed'");
$failedCount = (int) $failedStmt->fetchColumn();

$recentUsersStmt = $pdo->query(
    'SELECT id, nama, email, no_hp, alamat, created_at FROM users ORDER BY created_at DESC LIMIT 10'
);
$recentUsers = $recentUsersStmt->fetchAll();

$recentLogsStmt = $pdo->query(
    'SELECT l.id, l.user_id, l.no_tujuan, l.status, l.response, l.created_at, u.nama AS user_nama
     FROM log_whatsapp l
     LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC
     LIMIT 15'
);
$recentLogs = $recentLogsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin Fonnte</title>
    <style>
        :root {
            --bg: #f3f7f5;
            --card: #ffffff;
            --text: #1e2d2b;
            --muted: #5d6d68;
            --primary: #0d7a4f;
            --primary-dark: #085d3d;
            --success: #1d9e63;
            --danger: #c53d3d;
            --border: #dfeae3;
            --shadow: 0 12px 28px rgba(20, 36, 32, 0.08);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .container {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 20px 48px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }

        h1 {
            margin: 0;
            font-size: 1.75rem;
        }

        .badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            background: #edf9f3;
            color: var(--primary-dark);
            border: 1px solid #cdecdc;
            font-weight: 700;
            font-size: 0.82rem;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 20px;
        }

        .stat-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .status-sent { color: var(--success); }
        .status-failed { color: var(--danger); }

        .grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 22px;
        }

        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 18px;
        }

        .panel h2 {
            margin: 0 0 16px;
            font-size: 1.2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.94rem;
        }

        th, td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        th {
            color: var(--muted);
            background: #f7faf8;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        td small {
            color: var(--muted);
        }

        .tag {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .tag.sent {
            background: #eafaf2;
            color: var(--success);
        }

        .tag.failed {
            background: #fff0f0;
            color: var(--danger);
        }

        .log-response {
            max-width: 220px;
            white-space: normal;
            word-break: break-word;
            color: var(--muted);
            font-size: 0.8rem;
        }

        .empty {
            color: var(--muted);
            font-style: italic;
            padding: 16px 0;
        }

        @media (max-width: 800px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <h1>Dashboard Admin</h1>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <a href="users/index.php" style="text-decoration:none;background:#0d7a4f;color:#fff;padding:9px 13px;border-radius:9px;font-weight:700;font-size:.9rem">Kelola User</a>
                <span class="badge">Fonnte WhatsApp</span>
            </div>
        </div>

        <section class="stats">
            <div class="stat-card">
                <div class="stat-label">Total User</div>
                <p class="stat-value"><?= htmlspecialchars((string) $totalUsers, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Log</div>
                <p class="stat-value"><?= htmlspecialchars((string) $totalLogs, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="stat-card">
                <div class="stat-label">Status Sent</div>
                <p class="stat-value status-sent"><?= htmlspecialchars((string) $sentCount, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="stat-card">
                <div class="stat-label">Status Failed</div>
                <p class="stat-value status-failed"><?= htmlspecialchars((string) $failedCount, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </section>

        <section class="grid">
            <div class="panel">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><h2 style="margin:0">Data User Terbaru</h2><a href="users/create.php" style="text-decoration:none;color:#0d7a4f;font-weight:700;font-size:.9rem">+ Tambah User</a></div>
                <?php if ($recentUsers): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Nomor</th>
                                <th>Alamat</th>
                                <th>Daftar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($user['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars((string) ($user['nama'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars((string) ($user['no_hp'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars((string) ($user['alamat'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><small><?= htmlspecialchars((string) ($user['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">Belum ada data user.</div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>Log Pengiriman WhatsApp</h2>
                <?php if ($recentLogs): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Tujuan</th>
                                <th>Status</th>
                                <th>Response</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td><small><?= htmlspecialchars((string) ($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small></td>
                                    <td>
                                        <?= htmlspecialchars((string) ($log['user_nama'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?><br>
                                        <small><?= htmlspecialchars((string) ($log['no_tujuan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td>
                                        <span class="tag <?= strtolower((string) ($log['status'] ?? 'failed')); ?>">
                                            <?= htmlspecialchars((string) ($log['status'] ?? 'failed'), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="log-response"><?= htmlspecialchars((string) ($log['response'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">Belum ada log pengiriman.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</body>
</html>
