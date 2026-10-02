<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
greenai_ensure_support_tables($pdo);

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php?as=admin");
    exit();
}

$adminName = $_SESSION['admin']['fullname'] ?? 'Support Agent';
$ticketId = (int)($_GET['id'] ?? 0);

if ($ticketId <= 0) {
    header("Location: dashboard.php");
    exit();
}

$allowedStatuses = ['open', 'in_progress', 'resolved'];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? 'open';
    $adminNote = trim($_POST['admin_note'] ?? '');

    if (in_array($newStatus, $allowedStatuses, true)) {
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = ?, admin_note = ?, resolved_by = ? WHERE id = ?");
        $stmt->execute([$newStatus, $adminNote, $_SESSION['admin']['id'], $ticketId]);
        $saved = true;
    }
}

$stmt = $pdo->prepare("SELECT t.*, u.fullname, u.email, u.Residence FROM support_tickets t JOIN users u ON u.id = t.user_id WHERE t.id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header("Location: dashboard.php");
    exit();
}

$statusLabels = ['open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo (int)$ticket['id']; ?> - Support Console | Green-AI</title>
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

    <main class="p-4 sm:p-8 max-w-3xl mx-auto space-y-6">

        <a href="dashboard.php" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-slate-400 hover:text-slate-600"><i class="fa-solid fa-arrow-left text-[10px]"></i> Back to all tickets</a>

        <?php if ($saved): ?>
        <div class="bg-emerald-50 text-emerald-700 border border-emerald-100 p-3 rounded-xl text-xs font-bold">
            <i class="fa-solid fa-circle-check mr-1.5"></i> Ticket updated.
        </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-4">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-base font-black text-slate-900"><?php echo htmlspecialchars($ticket['subject']); ?></h2>
                    <p class="text-[11px] text-slate-400 font-bold mt-0.5 capitalize"><?php echo htmlspecialchars($ticket['category']); ?> issue &middot; Submitted <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($ticket['created_at']))); ?></p>
                </div>
                <span class="px-2.5 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-lg border border-slate-200"><?php echo $statusLabels[$ticket['status']] ?? htmlspecialchars($ticket['status']); ?></span>
            </div>

            <div class="bg-slate-50 border border-slate-100 p-3.5 rounded-xl flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center text-[11px] font-black text-slate-600"><i class="fa-regular fa-user"></i></div>
                <div>
                    <span class="block text-xs font-black text-slate-800"><?php echo htmlspecialchars($ticket['fullname']); ?></span>
                    <span class="block text-[10px] text-slate-400"><?php echo htmlspecialchars($ticket['email']); ?> &middot; <?php echo htmlspecialchars($ticket['Residence'] ?? 'Residence not set'); ?></span>
                </div>
            </div>

            <div class="space-y-1">
                <span class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Reported Issue</span>
                <p class="text-xs font-semibold text-slate-700 leading-relaxed whitespace-pre-line"><?php echo htmlspecialchars($ticket['message']); ?></p>
            </div>
        </div>

        <form method="POST" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-4">
            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Resolve Ticket</h3>

            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Status</label>
                <select name="status" class="w-full border border-gray-200 focus:border-[#2e7d32] rounded-xl px-4 py-2.5 text-xs outline-hidden bg-white font-medium text-slate-800">
                    <?php foreach ($allowedStatuses as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $ticket['status'] === $s ? 'selected' : ''; ?>><?php echo $statusLabels[$s]; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Internal Note (visible to resident)</label>
                <textarea name="admin_note" rows="3" placeholder="e.g. Technician scheduled for Thursday." class="w-full border border-gray-200 focus:border-[#2e7d32] rounded-xl px-4 py-2.5 text-xs outline-hidden bg-white font-medium text-slate-800"><?php echo htmlspecialchars($ticket['admin_note'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="bg-[#1b5e20] hover:bg-[#144517] text-white font-bold py-2.5 px-5 rounded-xl transition-all shadow-xs cursor-pointer text-xs tracking-wide">
                Save Update
            </button>
        </form>
    </main>

</body>
</html>
