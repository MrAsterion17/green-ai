<?php
// 1. LIFELINE SESSION SECURITY GATEWAY & ERROR DIAGNOSTICS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure the householder has passed login authentication securely
require_once '../includes/session.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../includes/config.php'; // provides $pdo
require_once '../includes/user_schema.php';
greenai_ensure_user_contact_columns($pdo);

$userId = (int) ($_SESSION['user']['id'] ?? 0);
$homeMessage = '';
$homeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_home_sms'])) {
    $residenceIn = trim($_POST['residence'] ?? '');
    $blockIn = trim($_POST['block_no'] ?? '');
    $lotIn = trim($_POST['lot_no'] ?? '');
    $fieldOk = '/^[A-Za-z0-9\- ]{1,20}$/';

    if (!in_array($residenceIn, greenai_subdivisions(), true)) {
        $homeError = 'Please choose your subdivision.';
    } elseif (!preg_match($fieldOk, $blockIn) || !preg_match($fieldOk, $lotIn)) {
        $homeError = 'Block and Lot are required and may only contain letters, numbers, spaces and dashes.';
    } else {
        $pdo->prepare("UPDATE users SET Residence = ?, block_no = ?, lot_no = ? WHERE id = ?")
            ->execute([$residenceIn, $blockIn, $lotIn, $userId]);
        $homeMessage = 'Home address saved.';
    }
}

$homeStmt = $pdo->prepare("SELECT Residence, block_no, lot_no FROM users WHERE id = ?");
$homeStmt->execute([$userId]);
$home = $homeStmt->fetch() ?: [];
if ($homeError) { // keep what the resident typed so they can fix it
    $home = ['Residence' => $_POST['residence'] ?? '', 'block_no' => $_POST['block_no'] ?? '', 'lot_no' => $_POST['lot_no'] ?? ''];
}

$fullname = $_SESSION['user']['fullname'] ?? 'User';
$email = $_SESSION['user']['email'] ?? 'user@example.com';
$initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $fullname), 0, 2));
if ($initials === '') {
    $initials = 'U';
}

// Default Smart Parameter Metrics (Fallback parameters loaded into fields)
$household_size = 4;
$region = "Calamba City, Laguna";
$solar_alert_threshold = 1.5; // kW
$battery_alert_threshold = 20; // %
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Green-AI Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f8fafc] font-sans min-h-screen text-slate-800 antialiased flex m-0 p-0">

    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow min-w-0 flex flex-col min-h-screen">
        
        <header class="w-full bg-white border-b border-slate-100 px-4 sm:px-8 py-4 sm:py-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 shrink-0">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-[#15803d]"></i> Portal Configuration
                </h1>
                <p class="text-gray-400 text-xs font-semibold mt-1">Keep your settings page aligned with the Green-AI dashboard design system.</p>
            </div>
            <div class="inline-flex items-center gap-2 rounded-2xl bg-emerald-50 border border-emerald-100 px-3 py-2 text-xs font-semibold text-emerald-700">
                <i class="fa-solid fa-shield-halved"></i>
                Secured environment
            </div>
        </header>

        <main class="p-4 sm:p-8 space-y-6 flex-grow overflow-x-hidden max-w-6xl w-full min-w-0">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-4 sm:p-6 rounded-3xl border border-slate-100 shadow-xs space-y-4">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-3xl bg-[#15803d] text-white flex items-center justify-center text-2xl font-black uppercase shadow-md">
                                <?php echo htmlspecialchars($initials); ?>
                            </div>
                            <div>
                                <h2 class="text-lg font-black text-slate-900">Welcome back, <?php echo htmlspecialchars(explode(' ', $fullname)[0]); ?>.</h2>
                                <p class="text-[11px] font-semibold text-slate-500 mt-1">Update your profile and system preferences from the same dashboard language.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div class="rounded-3xl bg-slate-50 border border-slate-100 p-4">
                                <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Session</p>
                                <p class="mt-2 font-black text-slate-900">Active</p>
                            </div>
                            <div class="rounded-3xl bg-slate-50 border border-slate-100 p-4">
                                <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Email</p>
                                <p class="mt-2 font-black text-slate-900 break-all"><?php echo htmlspecialchars($email); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-4 sm:p-6 rounded-3xl border border-slate-100 shadow-xs space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Configuration summary</h3>
                                <p class="text-[10px] text-slate-400 mt-1">High-level settings matched to the dashboard style.</p>
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-2xl bg-emerald-50 px-3 py-2 text-[10px] font-black text-emerald-700 border border-emerald-100">
                                <i class="fa-solid fa-check-circle"></i>
                                Dashboard style
                            </span>
                        </div>
                        <div class="space-y-3 text-sm text-slate-600">
                            <div class="rounded-3xl bg-slate-50 border border-slate-100 p-4">
                                <span class="block text-[10px] uppercase font-black text-slate-400">Household size</span>
                                <span class="block mt-2 font-black text-slate-900"><?php echo htmlspecialchars($household_size); ?> occupants</span>
                            </div>
                            <div class="rounded-3xl bg-slate-50 border border-slate-100 p-4">
                                <span class="block text-[10px] uppercase font-black text-slate-400">Alert thresholds</span>
                                <span class="block mt-2 font-black text-slate-900"><?php echo htmlspecialchars($solar_alert_threshold); ?> kW / <?php echo htmlspecialchars($battery_alert_threshold); ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 sm:p-6 rounded-3xl border border-slate-100 shadow-xs space-y-5">
                    <div class="border-b border-slate-100 pb-3">
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-cloud-sun text-[#15803d] text-sm"></i> Weather sync
                        </h3>
                        <p class="text-[10px] font-semibold text-slate-400 mt-1">Live regional weather data helps your AI thresholds stay accurate.</p>
                    </div>
                    <div class="text-sm text-slate-700 space-y-2">
                        <div class="rounded-3xl bg-slate-50 border border-slate-100 p-4">
                            <p class="font-black text-slate-900">Region</p>
                            <p class="text-slate-500 mt-1"><?php echo htmlspecialchars($region); ?></p>
                        </div>
                        <div class="rounded-3xl bg-emerald-50 border border-emerald-100 p-4 text-emerald-700 font-semibold">
                            <div class="flex items-start gap-2">
                                <span>📡</span>
                                <span>Weather telemetry updates automatically every 15 minutes for better AI performance.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form action="" method="POST" class="bg-white p-4 sm:p-6 rounded-3xl border border-slate-100 shadow-xs space-y-5">
                <input type="hidden" name="save_home_sms" value="1">
                <div class="border-b border-slate-100 pb-3">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-location-dot text-[#15803d] text-sm"></i> Home Address
                    </h3>
                    <p class="text-[10px] font-semibold text-slate-400 mt-1">Your subdivision, block and lot.</p>
                </div>
                <?php if ($homeMessage): ?>
                <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-3 text-xs font-semibold text-emerald-700"><?php echo htmlspecialchars($homeMessage); ?></div>
                <?php endif; ?>
                <?php if ($homeError): ?>
                <div class="rounded-2xl bg-red-50 border border-red-100 p-3 text-xs font-semibold text-red-600"><?php echo htmlspecialchars($homeError); ?></div>
                <?php endif; ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Subdivision</label>
                        <select name="residence" required class="w-full bg-slate-50 border border-slate-200 rounded-3xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-[#15803d] focus:bg-white transition-all">
                            <option value="">Select subdivision</option>
                            <?php foreach (greenai_subdivisions() as $sub): ?>
                            <option value="<?php echo htmlspecialchars($sub); ?>" <?php echo ($home['Residence'] ?? '') === $sub ? 'selected' : ''; ?>><?php echo htmlspecialchars($sub); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Block</label>
                        <input type="text" name="block_no" required maxlength="20" value="<?php echo htmlspecialchars($home['block_no'] ?? ''); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-3xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-[#15803d] focus:bg-white transition-all" />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Lot</label>
                        <input type="text" name="lot_no" required maxlength="20" value="<?php echo htmlspecialchars($home['lot_no'] ?? ''); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-3xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-[#15803d] focus:bg-white transition-all" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-[#15803d] hover:bg-[#0f4f1b] text-white text-xs font-black rounded-2xl transition-all shadow-xs hover:shadow-md tracking-wide flex items-center gap-2">
                        <i class="fa-regular fa-floppy-disk text-sm"></i> Save Address
                    </button>
                </div>
            </form>

            <form action="" method="POST" class="space-y-6" id="portalSettingsForm">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white p-4 sm:p-6 rounded-3xl border border-slate-100 shadow-xs space-y-5">
                        <div class="border-b border-slate-100 pb-3">
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-id-card text-[#15803d] text-sm"></i> User Credentials
                            </h3>
                            <p class="text-[10px] font-semibold text-slate-400 mt-1">Update your name and verify your registration email.</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Account Full Name</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-3 text-slate-400 text-xs"><i class="fa-regular fa-user"></i></span>
                                    <input type="text" name="fullname" value="<?php echo htmlspecialchars($fullname); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-3xl pl-11 pr-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-[#15803d] focus:bg-white transition-all" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Primary Email Registry</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-3 text-slate-400 text-xs"><i class="fa-regular fa-envelope"></i></span>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" readonly class="w-full bg-slate-100 border border-slate-200 rounded-3xl pl-11 pr-4 py-3 text-sm font-semibold text-slate-500 cursor-not-allowed" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-4 sm:p-6 rounded-3xl border border-slate-100 shadow-xs space-y-5">
                        <div class="border-b border-slate-100 pb-3">
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-house-chimney text-[#15803d] text-sm"></i> Property Metadata
                            </h3>
                            <p class="text-[10px] font-semibold text-slate-400 mt-1">Choose the household model used for energy forecasting.</p>
                        </div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Total Occupants Count</label>
                        <select name="household_size" class="w-full bg-slate-50 border border-slate-200 rounded-3xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-[#15803d] focus:bg-white transition-all">
                            <option value="1" <?php echo $household_size == 1 ? 'selected' : ''; ?>>Single Resident (1)</option>
                            <option value="2" <?php echo $household_size == 2 ? 'selected' : ''; ?>>Coupled Residents (2)</option>
                            <option value="3" <?php echo $household_size == 3 ? 'selected' : ''; ?>>Standard Small Family (3)</option>
                            <option value="4" <?php echo $household_size == 4 ? 'selected' : ''; ?>>Standard Medium Family (4)</option>
                            <option value="5" <?php echo $household_size == 5 ? 'selected' : ''; ?>>Large Household Residence (5+)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white p-4 sm:p-6 rounded-3xl border border-slate-100 shadow-xs space-y-5">
                        <div class="border-b border-slate-100 pb-3">
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-bell-concierge text-[#15803d] text-sm"></i> AI Telemetry Notification Thresholds
                            </h3>
                            <p class="text-[10px] font-semibold text-slate-400 mt-1">Define the alert thresholds that power your dashboard warnings.</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Low Generation Warning Trigger</label>
                                <div class="relative">
                                    <input type="number" step="0.1" name="solar_threshold" value="<?php echo $solar_alert_threshold; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-3xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-[#15803d] focus:bg-white transition-all pr-14" />
                                    <span class="absolute right-4 top-3 text-[10px] font-black text-slate-400 uppercase">kW</span>
                                </div>
                                <p class="text-[10px] text-slate-400">Alert when solar production falls below this threshold.</p>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Critical Battery Storage Level</label>
                                <div class="relative">
                                    <input type="number" name="battery_threshold" value="<?php echo $battery_alert_threshold; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-3xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-[#15803d] focus:bg-white transition-all pr-12" />
                                    <span class="absolute right-4 top-3 text-[10px] font-black text-slate-400 uppercase">%</span>
                                </div>
                                <p class="text-[10px] text-slate-400">Notify when battery reserves fall below the configured level.</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-4 sm:p-6 rounded-3xl border border-slate-100 shadow-xs space-y-4 flex flex-col justify-between">
                        <div>
                            <div class="border-b border-slate-100 pb-3">
                                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                    <i class="fa-solid fa-cloud-sun text-[#15803d] text-sm"></i> Location Weather Matrix
                                </h3>
                                <p class="text-[10px] font-semibold text-slate-400 mt-1">Align AI thresholds with your local weather region.</p>
                            </div>
                            <div class="space-y-3 pt-4">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Regional Grid Zone Reference</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-3 text-slate-400 text-xs"><i class="fa-solid fa-location-dot"></i></span>
                                    <input type="text" name="region" value="<?php echo htmlspecialchars($region); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-3xl pl-11 pr-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-[#15803d] focus:bg-white transition-all" />
                                </div>
                            </div>
                        </div>
                        <div class="rounded-3xl bg-emerald-50 border border-emerald-100 p-4 text-[10px] font-semibold text-emerald-700">
                            <div class="flex items-start gap-2">
                                <span>📡</span>
                                <span>Weather telemetry updates every 15 minutes to keep AI decisions in sync with conditions.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row justify-end items-center gap-3 pt-2">
                    <a href="../index.php" class="px-5 py-3 border border-slate-200 text-xs font-bold rounded-2xl text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-all">Discard Changes</a>
                    <button type="button" onclick="commitPortalSettings()" class="px-6 py-3 bg-[#15803d] hover:bg-[#0f4f1b] text-white text-xs font-black rounded-2xl transition-all shadow-xs hover:shadow-md tracking-wide flex items-center gap-2">
                        <i class="fa-regular fa-floppy-disk text-sm"></i> Save Configuration
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        function commitPortalSettings() {
            alert("💾 System Configuration Saved Successfully!\nYour household metrics, warning limitations, and regional criteria profiles have been safely synchronized to your local framework engine.");
        }
    </script>
</body>
</html>