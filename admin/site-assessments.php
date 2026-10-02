<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
require_once __DIR__ . '/../includes/site_assessment_schema.php';
greenai_ensure_support_tables($pdo);
greenai_ensure_site_assessment_tables($pdo);

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php?as=admin");
    exit();
}

$adminName = $_SESSION['admin']['fullname'] ?? 'Support Agent';
$activeNav = 'site-assessments';

$stmt = $pdo->prepare("SELECT * FROM solar_assessments WHERE admin_id = ? ORDER BY updated_at DESC");
$stmt->execute([$_SESSION['admin']['id']]);
$assessments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Assessments - Support Console | Green-AI</title>
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
            <a href="../logout.php" class="text-white/80 hover:text-rose-200 transition-colors text-xs" title="Sign Out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </header>

    <main class="p-4 sm:p-8 max-w-6xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-black text-slate-900 tracking-tight">Solar Site Assessments</h2>
                <p class="text-slate-500 text-xs mt-1">PVsyst-style pre-feasibility studies for potential PV installation sites.</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex items-center flex-wrap gap-1 bg-white p-1 rounded-xl border border-slate-100 shadow-2xs text-[11px] font-bold text-gray-500 w-fit">
                    <a href="dashboard.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 hover:text-slate-700"><i class="fa-solid fa-ticket"></i> Support Tickets</a>
                    <a href="users.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 hover:text-slate-700"><i class="fa-solid fa-users"></i> Users</a>
                    <a href="site-assessments.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 <?php echo $activeNav === 'site-assessments' ? 'bg-[#15803d] text-white' : 'hover:text-slate-700'; ?>"><i class="fa-solid fa-solar-panel"></i> Site Assessments</a>
                    <a href="solar-monitoring.php" class="px-3.5 py-1.5 rounded-lg transition-all inline-flex items-center gap-1.5 <?php echo $activeNav === 'solar-monitoring' ? 'bg-[#15803d] text-white' : 'hover:text-slate-700'; ?>"><i class="fa-solid fa-chart-line"></i> Live Monitoring</a>
                </div>
                <a href="site-assessment.php" class="shrink-0 inline-flex items-center gap-1.5 bg-[#1b5e20] hover:bg-[#144517] text-white text-[11px] font-black px-3.5 py-2 rounded-xl transition-all">
                    <i class="fa-solid fa-plus"></i> New Assessment
                </a>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-2xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Site</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Client</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Location</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Coordinates</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Status</th>
                            <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        <?php if (empty($assessments)): ?>
                        <tr>
                            <td colspan="6" class="p-6 text-center text-slate-400 font-bold">No site assessments yet &mdash; click "New Assessment" to start one.</td>
                        </tr>
                        <?php else: foreach ($assessments as $a): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors cursor-pointer" onclick="window.location.href='site-assessment.php?id=<?php echo (int)$a['id']; ?>'">
                            <td class="p-4 font-bold text-slate-900"><?php echo htmlspecialchars($a['site_name']); ?></td>
                            <td class="p-4 text-slate-500"><?php echo htmlspecialchars($a['client_name'] ?? '—'); ?></td>
                            <td class="p-4 text-slate-500"><?php $loc = trim(trim(($a['region'] ?? '') . ', ' . ($a['country'] ?? ''), ', ')); echo htmlspecialchars($loc !== ',' && $loc !== '' ? $loc : '—'); ?></td>
                            <td class="p-4 text-slate-500"><?php echo number_format((float)$a['latitude'], 4); ?>, <?php echo number_format((float)$a['longitude'], 4); ?></td>
                            <td class="p-4">
                                <span class="px-2.5 py-1 bg-amber-50 text-amber-700 text-[10px] font-bold rounded-lg border border-amber-100 capitalize"><?php echo htmlspecialchars($a['status']); ?></span>
                            </td>
                            <td class="p-4 text-slate-500"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($a['updated_at']))); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>
</html>
