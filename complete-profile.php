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

$inputClass = 'w-full border border-gray-200 focus:border-[#2e7d32] rounded-xl px-4 py-2.5 text-sm outline-hidden transition-all bg-white font-medium text-slate-800';
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
<body class="bg-slate-50 font-sans min-h-screen flex items-center justify-center p-4 text-slate-800 antialiased">
    <form method="POST" class="w-full max-w-md bg-white rounded-3xl border border-slate-100 shadow-lg p-6 sm:p-8 space-y-5">
        <div>
            <div class="flex items-center gap-2 text-2xl font-black tracking-wider text-slate-800">🔌 GREEN-<span class="text-emerald-600">AI</span></div>
            <h1 class="text-lg font-black text-slate-900 mt-4">Welcome, <?php echo htmlspecialchars(explode(' ', $fullname)[0]); ?>!</h1>
            <p class="text-xs font-semibold text-slate-500 mt-1">One last step: tell us where your home is so we can set up your solar monitoring.</p>
        </div>

        <?php if ($error): ?>
        <div class="rounded-2xl bg-red-50 border border-red-100 p-3 text-xs font-semibold text-red-600"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide uppercase">Subdivision</label>
            <select name="residence" required class="<?php echo $inputClass; ?>">
                <option value="">Select subdivision</option>
                <?php foreach (greenai_subdivisions() as $sub): ?>
                <option value="<?php echo htmlspecialchars($sub); ?>" <?php echo ($home['Residence'] ?? '') === $sub ? 'selected' : ''; ?>><?php echo htmlspecialchars($sub); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide uppercase">Block</label>
                <input type="text" name="block_no" required maxlength="20" placeholder="e.g. 12" value="<?php echo htmlspecialchars($home['block_no'] ?? ''); ?>" class="<?php echo $inputClass; ?>">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide uppercase">Lot</label>
                <input type="text" name="lot_no" required maxlength="20" placeholder="e.g. 5" value="<?php echo htmlspecialchars($home['lot_no'] ?? ''); ?>" class="<?php echo $inputClass; ?>">
            </div>
        </div>

        <button type="submit" class="w-full py-3 bg-[#15803d] hover:bg-[#0f4f1b] text-white text-sm font-black rounded-xl transition-all">Continue to Dashboard</button>
        <p class="text-center text-[11px] font-semibold text-slate-400"><a href="logout.php" class="hover:text-slate-600 underline">Log out</a></p>
    </form>
</body>
</html>
