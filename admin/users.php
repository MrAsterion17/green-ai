<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
require_once __DIR__ . '/../includes/user_schema.php';
greenai_ensure_support_tables($pdo);
greenai_ensure_user_contact_columns($pdo);

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php?as=admin");
    exit();
}

$adminName = $_SESSION['admin']['fullname'] ?? 'Support Agent';
$activeNav = 'users';

$stmt = $pdo->query("SELECT id, fullname, email, Residence, block_no, lot_no, last_login, created_at FROM users ORDER BY last_login DESC, created_at DESC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Console - Users | Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased m-0 p-0">

    <header class="w-full bg-[#15803d] px-4 sm:px-8 py-4 flex justify-between items-center shadow-xs">
        <div class="flex items-center gap-2.5 text-white">
            <div class="w-8 h-8 bg-white/15 rounded-xl flex items-center justify-center text-sm"><i class="fa-solid fa-headset"></i></div>
            <div>
                <h1 class="text-sm font-black leading-none">Green-AI Support Console</h1>
                <span class="text-[10px] font-bold text-emerald-100 tracking-wide">After-Sales Admin Panel</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs font-black text-white"><i class="fa-regular fa-user mr-1"></i> <?php echo htmlspecialchars($adminName); ?></span>
            <a href="logout.php" class="text-white/80 hover:text-rose-200 transition-colors text-xs" title="Sign Out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </header>

    <main class="p-4 sm:p-8 max-w-6xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-black text-slate-900 tracking-tight">Residents</h2>
                <p class="text-slate-500 text-xs mt-1">Everyone registered on Green-AI, sorted by most recent sign-in.</p>
            </div>
            <div class="flex items-center flex-wrap gap-1 bg-white p-1 rounded-xl border border-slate-100 shadow-2xs text-[11px] font-bold text-gray-500 w-fit">
                <a href="dashboard.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 <?php echo $activeNav === 'tickets' ? 'bg-[#15803d] text-white' : 'hover:text-slate-700'; ?>"><i class="fa-solid fa-ticket"></i> Support Tickets</a>
                <a href="users.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 <?php echo $activeNav === 'users' ? 'bg-[#15803d] text-white' : 'hover:text-slate-700'; ?>"><i class="fa-solid fa-users"></i> Users</a>
                <a href="site-assessments.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 <?php echo $activeNav === 'site-assessments' ? 'bg-[#15803d] text-white' : 'hover:text-slate-700'; ?>"><i class="fa-solid fa-solar-panel"></i> Site Assessments</a>
                <a href="solar-monitoring.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 <?php echo $activeNav === 'solar-monitoring' ? 'bg-[#15803d] text-white' : 'hover:text-slate-700'; ?>"><i class="fa-solid fa-chart-line"></i> Live Monitoring</a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Total Residents</p>
                <h3 class="text-xl font-black text-slate-900 mt-1"><?php echo count($users); ?></h3>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Signed In (Last 24h)</p>
                <h3 class="text-xl font-black text-slate-900 mt-1"><?php echo count(array_filter($users, fn($u) => !empty($u['last_login']) && strtotime($u['last_login']) >= strtotime('-1 day'))); ?></h3>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-2xs">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Never Signed In</p>
                <h3 class="text-xl font-black text-slate-900 mt-1"><?php echo count(array_filter($users, fn($u) => empty($u['last_login']))); ?></h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Resident</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Residence</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Block / Lot</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Registered</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Last Login</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="p-6 text-center text-slate-400 font-bold">No registered residents yet.</td>
                        </tr>
                        <?php else: foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors cursor-pointer" onclick="window.location.href='user.php?id=<?php echo (int)$u['id']; ?>'">
                            <td class="p-4">
                                <span class="block text-slate-900 font-bold"><?php echo htmlspecialchars($u['fullname']); ?></span>
                                <span class="block text-[10px] text-slate-400"><?php echo htmlspecialchars($u['email']); ?></span>
                            </td>
                            <td class="p-4 text-slate-500"><?php echo htmlspecialchars($u['Residence'] ?? 'Not set'); ?></td>
                            <td class="p-4 text-slate-500"><?php echo ($u['block_no'] ?? '') !== '' || ($u['lot_no'] ?? '') !== '' ? 'Blk ' . htmlspecialchars($u['block_no'] ?? '-') . ' Lot ' . htmlspecialchars($u['lot_no'] ?? '-') : 'Not set'; ?></td>
                            <td class="p-4 text-slate-500"><?php echo htmlspecialchars(date('M j, Y', strtotime($u['created_at']))); ?></td>
                            <td class="p-4">
                                <?php if (!empty($u['last_login'])): ?>
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-lg border border-emerald-100"><?php echo htmlspecialchars(date('M j, g:i A', strtotime($u['last_login']))); ?></span>
                                <?php else: ?>
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-500 text-[10px] font-bold rounded-lg border border-slate-200">Never</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>
</html>
