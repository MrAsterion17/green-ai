<?php
// Shown to a resident right after login until their subdivision, block and lot are on file
// (new sign-ups through Google never entered them). See the gate in includes/session.php.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/user_schema.php';
greenai_ensure_user_contact_columns($pdo);

$userId = (int) ($_SESSION['user']['id'] ?? 0);
$fullname = $_SESSION['user']['fullname'] ?? 'there';
$error = '';

$stmt = $pdo->prepare("SELECT Residence, block_no, lot_no FROM users WHERE id = ?");
$stmt->execute([$userId]);
$home = $stmt->fetch() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $residence = trim($_POST['residence'] ?? '');
    $blockNo = trim($_POST['block_no'] ?? '');
    $lotNo = trim($_POST['lot_no'] ?? '');
    $fieldOk = '/^[A-Za-z0-9\- ]{1,20}$/';
    $home = ['Residence' => $residence, 'block_no' => $blockNo, 'lot_no' => $lotNo];

    if (!in_array($residence, greenai_subdivisions(), true)) {
        $error = 'Please choose your subdivision.';
    } elseif (!preg_match($fieldOk, $blockNo) || !preg_match($fieldOk, $lotNo)) {
        $error = 'Block and Lot are required and may only contain letters, numbers, spaces and dashes.';
    } else {
        $pdo->prepare("UPDATE users SET Residence = ?, block_no = ?, lot_no = ? WHERE id = ?")
            ->execute([$residence, $blockNo, $lotNo, $userId]);
        $_SESSION['user']['residence'] = $residence;
        $_SESSION['user']['home_done'] = true;
        header('Location: index.php');
        exit();
    }
}

$inputClass = 'w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl pl-10 pr-4 py-2.5 text-xs outline-hidden transition-all bg-white font-medium text-slate-800';
$labelClass = 'block text-[11px] font-bold text-gray-500 mb-1 tracking-wide uppercase';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - Green-AI</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-white font-sans min-h-screen flex m-0 p-0 text-slate-800 antialiased selection:bg-green-500/20">

    <div class="w-full min-h-screen flex flex-col md:flex-row">

        <div class="hidden md:flex md:w-1/2 p-12 flex-col justify-between relative overflow-hidden bg-cover bg-center" style="background-image: linear-gradient(rgba(0, 0, 0, 0.05), rgba(0, 0, 0, 0.15)), url('https://images.unsplash.com/photo-1508514177221-188b1cf16e9d?q=80&w=1400&auto=format&fit=crop');">

            <div class="flex items-center space-x-2 relative z-10">
                <span class="text-3xl filter drop-shadow-xs">🔌</span>
                <span class="text-3xl font-black tracking-wider text-slate-800">GREEN-<span class="text-emerald-600">AI</span></span>
            </div>

            <div class="max-w-md w-full my-auto relative z-10 bg-white/10 backdrop-blur-md border border-white/20 p-8 rounded-2xl shadow-xs space-y-6">
                <div class="space-y-1">
                    <h1 class="text-2xl font-black text-white tracking-tight leading-tight">Intelligent Energy Management for a Sustainable Future</h1>
                </div>

                <div class="flex items-start gap-3 bg-white/10 border border-white/10 p-4 rounded-xl">
                    <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center text-white shrink-0"><i class="fa-solid fa-chart-line text-xs"></i></div>
                    <div class="space-y-0.5">
                        <strong class="block text-xs font-bold text-white">Smart Energy. Better Future.</strong>
                        <p class="text-white/80 text-[11px] leading-relaxed">Monitor your energy usage, predict consumption, and save more with AI-powered insights.</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 pt-2 text-center text-white">
                    <div class="space-y-1 bg-white/10 border border-white/5 p-2.5 rounded-xl flex flex-col items-center justify-center">
                        <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center"><i class="fa-solid fa-solar-panel text-xs"></i></div>
                        <span class="block text-[11px] font-bold">Monitor</span>
                        <span class="block text-[9px] text-white/70 leading-tight">Real-time energy data</span>
                    </div>
                    <div class="space-y-1 bg-white/10 border border-white/5 p-2.5 rounded-xl flex flex-col items-center justify-center">
                        <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center"><i class="fa-solid fa-microchip text-xs"></i></div>
                        <span class="block text-[11px] font-bold">Predict</span>
                        <span class="block text-[9px] text-white/70 leading-tight">AI-powered forecast</span>
                    </div>
                    <div class="space-y-1 bg-white/10 border border-white/5 p-2.5 rounded-xl flex flex-col items-center justify-center">
                        <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center"><i class="fa-solid fa-bolt text-xs"></i></div>
                        <span class="block text-[11px] font-bold">Save</span>
                        <span class="block text-[9px] text-white/70 leading-tight">Reduce cost &amp; energy waste</span>
                    </div>
                </div>
            </div>

            <div class="bg-white/20 border border-white/30 backdrop-blur-md p-3.5 rounded-xl flex items-center gap-2.5 relative z-10 shadow-xs max-w-sm">
                <span class="text-lg">🌱</span>
                <div class="text-left">
                    <span class="block text-xs font-bold text-white">Go Green. Save Energy.</span>
                    <p class="text-[10px] text-white/90 font-medium leading-normal">Every decision today builds a better tomorrow.</p>
                </div>
            </div>
        </div>

        <div class="w-full md:w-1/2 bg-white flex flex-col justify-between relative min-h-screen">

            <div class="w-full flex justify-end p-4">
                <button type="button" class="flex items-center gap-1.5 border border-gray-200 text-xs font-bold text-gray-500 px-3 py-1.5 rounded-xl hover:bg-gray-50 transition-colors cursor-pointer">
                    <i class="fa-solid fa-globe text-gray-400"></i> English <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 ml-0.5"></i>
                </button>
            </div>

            <div class="max-w-sm w-full mx-auto my-auto px-6 space-y-5">

                <div class="space-y-0.5">
                    <h2 class="text-2xl font-black text-gray-900 tracking-tight flex items-center gap-1.5">Welcome, <?php echo htmlspecialchars(explode(' ', $fullname)[0]); ?>! <span class="text-xl text-[#2e7d32]">🍃</span></h2>
                    <p class="text-gray-400 text-xs font-bold">One last step: tell us where your home is so we can set up your solar monitoring.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 text-red-600 border border-red-100 p-3 rounded-xl text-xs font-bold shadow-xs">
                        <i class="fa-solid fa-circle-exclamation mr-1.5"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="complete-profile.php" method="POST" class="space-y-4">
                    <div>
                        <label class="<?php echo $labelClass; ?>">Subdivision</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"><i class="fa-solid fa-location-dot text-sm"></i></span>
                            <select name="residence" required class="<?php echo $inputClass; ?>">
                                <option value="">Select subdivision</option>
                                <?php foreach (greenai_subdivisions() as $sub): ?>
                                <option value="<?php echo htmlspecialchars($sub); ?>" <?php echo ($home['Residence'] ?? '') === $sub ? 'selected' : ''; ?>><?php echo htmlspecialchars($sub); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="<?php echo $labelClass; ?>">Block</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"><i class="fa-solid fa-building text-sm"></i></span>
                                <input type="text" name="block_no" required maxlength="20" placeholder="e.g. 12" value="<?php echo htmlspecialchars($home['block_no'] ?? ''); ?>" class="<?php echo $inputClass; ?>">
                            </div>
                        </div>
                        <div>
                            <label class="<?php echo $labelClass; ?>">Lot</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"><i class="fa-solid fa-map-pin text-sm"></i></span>
                                <input type="text" name="lot_no" required maxlength="20" placeholder="e.g. 5" value="<?php echo htmlspecialchars($home['lot_no'] ?? ''); ?>" class="<?php echo $inputClass; ?>">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-[#1b5e20] hover:bg-[#144517] text-white font-bold py-3 rounded-xl transition-all shadow-xs cursor-pointer text-xs tracking-wide mt-2">
                        Continue to Dashboard
                    </button>
                </form>

                <p class="text-center text-xs text-gray-400 font-bold pt-1">
                    Not you? <a href="logout.php" class="text-emerald-700 hover:underline font-extrabold ml-0.5">Log out</a>
                </p>
            </div>

            <div class="w-full py-4 px-6 flex justify-between text-[10px] font-bold text-gray-400 border-t border-gray-50">
                <div class="flex items-center gap-1"><i class="fa-solid fa-shield-halved text-emerald-600"></i> Secure &amp; Private</div>
                <div class="flex items-center gap-1"><i class="fa-solid fa-microchip text-emerald-600"></i> AI-Powered</div>
                <div class="flex items-center gap-1"><i class="fa-solid fa-leaf text-emerald-600"></i> Eco-Friendly</div>
            </div>
        </div>

    </div>
</body>
</html>
