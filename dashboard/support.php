<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/weather.php';
require_once __DIR__ . '/../includes/support_schema.php';
greenai_ensure_support_tables($pdo);

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$fullname = $_SESSION['user']['fullname'] ?? 'User';
$userId = $_SESSION['user']['id'];

$weather = greenai_get_weather();
$hasWeather = empty($weather['error']) && isset($weather['current_weather']);
$weatherTemp = $hasWeather ? round($weather['current_weather']['temperature']) : null;
$weatherInfo = $hasWeather ? greenai_weather_code_info($weather['current_weather']['weathercode']) : ['label' => 'Unavailable', 'icon' => 'fa-cloud-question'];
$weatherLabel = $weather['label'] ?? GREENAI_WEATHER_LABEL;

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!in_array($category, ['equipment', 'website'], true) || empty($subject) || empty($message)) {
        $error = "Please fill out all fields.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, category, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $category, $subject, $message]);
        $success = "Your issue has been submitted. Our support team will follow up soon.";
    }
}

$stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$myTickets = $stmt->fetchAll();

$statusStyles = [
    'open'        => ['dot' => 'bg-rose-500',    'badge' => 'bg-rose-50 text-rose-700 border-rose-100'],
    'in_progress' => ['dot' => 'bg-amber-500',   'badge' => 'bg-amber-50 text-amber-700 border-amber-100'],
    'resolved'    => ['dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-100'],
];
$statusLabels = ['open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved'];

$prefillCategory = $_GET['category'] ?? '';
$prefillSubject = $_GET['subject'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>After-Sales Support - Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">
        <header class="w-full bg-white border-b border-gray-100 px-8 py-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-headset text-[#15803d]"></i> After-Sales Support
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-0.5">Report equipment or website issues and track their resolution.</p>
            </div>
            <div class="flex items-center gap-3 text-xs font-bold text-slate-600">
                <div class="flex items-center gap-2 bg-gray-50 border border-gray-100 px-3 py-1.5 rounded-xl">
                    <i class="fa-solid <?php echo $weatherInfo['icon']; ?> text-amber-500 text-sm"></i>
                    <div>
                        <span class="block font-black text-slate-800 leading-none"><?php echo $hasWeather ? $weatherTemp : '--'; ?>°C</span>
                        <span class="text-[9px] text-gray-400 leading-none"><?php echo htmlspecialchars(explode(',', $weatherLabel)[0]); ?></span>
                    </div>
                </div>
                <div class="inline-flex items-center gap-2 rounded-2xl bg-emerald-50 border border-emerald-100 px-3 py-2 text-xs font-black text-emerald-700">
                    <i class="fa-solid fa-circle-user"></i> <?php echo htmlspecialchars($fullname); ?>
                </div>
            </div>
        </header>

        <main class="p-8 space-y-6 flex-grow overflow-y-auto max-w-5xl w-full">

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

                <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-100 shadow-2xs space-y-4 h-fit">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Report an Issue</h3>

                    <?php if (!empty($error)): ?>
                        <div class="bg-red-50 text-red-600 border border-red-100 p-3 rounded-xl text-xs font-bold">
                            <i class="fa-solid fa-circle-exclamation mr-1.5"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="bg-emerald-50 text-emerald-700 border border-emerald-100 p-3 rounded-xl text-xs font-bold">
                            <i class="fa-solid fa-circle-check mr-1.5"></i><?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Category</label>
                            <select name="category" required class="w-full border border-gray-200 focus:border-[#2e7d32] rounded-xl px-4 py-2.5 text-xs outline-hidden bg-white font-medium text-slate-800">
                                <option value="">Select a category</option>
                                <option value="equipment" <?php echo $prefillCategory === 'equipment' ? 'selected' : ''; ?>>Solar Equipment</option>
                                <option value="website" <?php echo $prefillCategory === 'website' ? 'selected' : ''; ?>>Website / Dashboard</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Subject</label>
                            <input type="text" name="subject" value="<?php echo htmlspecialchars($prefillSubject); ?>" placeholder="Short summary of the issue" required class="w-full border border-gray-200 focus:border-[#2e7d32] rounded-xl px-4 py-2.5 text-xs outline-hidden bg-white font-medium text-slate-800">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Details</label>
                            <textarea name="message" rows="4" placeholder="Describe what's happening..." required class="w-full border border-gray-200 focus:border-[#2e7d32] rounded-xl px-4 py-2.5 text-xs outline-hidden bg-white font-medium text-slate-800"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-[#1b5e20] hover:bg-[#144517] text-white font-bold py-3 rounded-xl transition-all shadow-xs cursor-pointer text-xs tracking-wide">
                            Submit to Support Team
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-100 shadow-2xs overflow-hidden h-fit">
                    <div class="p-5 border-b border-slate-50">
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">My Tickets</h3>
                        <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Issues you've reported and their current status.</p>
                    </div>
                    <div class="divide-y divide-slate-50">
                        <?php if (empty($myTickets)): ?>
                        <p class="p-5 text-xs font-bold text-slate-400 text-center">You haven't reported any issues yet.</p>
                        <?php else: foreach ($myTickets as $t): $style = $statusStyles[$t['status']] ?? $statusStyles['open']; ?>
                        <div class="flex items-start gap-3 p-4">
                            <span class="w-2 h-2 rounded-full <?php echo $style['dot']; ?> mt-1.5 shrink-0"></span>
                            <div class="flex-grow space-y-0.5">
                                <p class="text-xs font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($t['subject']); ?></p>
                                <span class="block text-[10px] text-slate-400 font-medium capitalize"><?php echo htmlspecialchars($t['category']); ?> &middot; <?php echo htmlspecialchars(date('M j, Y', strtotime($t['created_at']))); ?></span>
                                <?php if (!empty($t['admin_note'])): ?>
                                <p class="text-[10px] text-slate-500 font-semibold bg-slate-50 border border-slate-100 rounded-lg px-2.5 py-1.5 mt-1"><i class="fa-solid fa-headset mr-1 text-slate-400"></i> <?php echo htmlspecialchars($t['admin_note']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="px-2.5 py-1 <?php echo $style['badge']; ?> text-[10px] font-bold rounded-lg border shrink-0"><?php echo $statusLabels[$t['status']] ?? htmlspecialchars($t['status']); ?></span>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

</body>
</html>