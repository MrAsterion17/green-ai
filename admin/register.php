<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_schema.php';
greenai_ensure_support_tables($pdo);

$error = '';
$generatedKey = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($fullname) || empty($email) || empty($password)) {
        $error = "Please fill out all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = "An admin account with this email already exists.";
        } else {
            // Each admin gets their own unique key, required alongside email + password at every login.
            $rawKey = strtoupper(implode('-', str_split(bin2hex(random_bytes(8)), 4)));
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $hashedKey = password_hash($rawKey, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO admins (fullname, email, password, admin_key_hash) VALUES (?, ?, ?, ?)");

            if ($stmt->execute([$fullname, $email, $hashedPassword, $hashedKey])) {
                $generatedKey = $rawKey;
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Console - Admin Sign Up | Green-AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f4f7f6] font-sans min-h-screen flex items-center justify-center m-0 p-4 text-slate-800 antialiased">

    <div class="max-w-sm w-full bg-white p-8 rounded-3xl border border-slate-100 shadow-xs space-y-5">

        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 bg-[#15803d] text-white rounded-xl flex items-center justify-center text-sm shadow-xs">
                <i class="fa-solid fa-headset"></i>
            </div>
            <div>
                <h1 class="text-sm font-black text-slate-900 leading-none">Green-AI Support Console</h1>
                <span class="text-[10px] font-bold text-slate-400 tracking-wide">After-Sales Admin Sign Up</span>
            </div>
        </div>

        <?php if (!empty($generatedKey)): ?>

            <div class="bg-emerald-50 text-emerald-700 border border-emerald-100 p-3 rounded-xl text-xs font-bold">
                <i class="fa-solid fa-circle-check mr-1.5"></i> Admin account created successfully.
            </div>

            <div class="bg-amber-50 border border-amber-100 p-4 rounded-xl space-y-2">
                <p class="text-[11px] font-black text-amber-800 uppercase tracking-wide"><i class="fa-solid fa-key mr-1"></i> Your Admin Key</p>
                <p class="text-[10px] text-amber-700 font-semibold leading-relaxed">Save this now &mdash; it is shown only once and is required (with your email and password) every time you log in.</p>
                <div class="flex items-center gap-2">
                    <input id="adminKeyValue" type="text" readonly value="<?php echo htmlspecialchars($generatedKey); ?>" class="flex-grow bg-white border border-amber-200 rounded-lg px-3 py-2 text-xs font-black tracking-wider text-slate-800 outline-hidden">
                    <button type="button" onclick="copyAdminKey()" id="copyKeyBtn" class="shrink-0 bg-amber-500 hover:bg-amber-600 text-white text-[11px] font-bold px-3 py-2 rounded-lg transition-all cursor-pointer border-0">
                        Copy
                    </button>
                </div>
            </div>

            <a href="../login.php?as=admin" class="w-full inline-flex items-center justify-center bg-[#1b5e20] hover:bg-[#144517] text-white font-bold py-3 rounded-xl transition-all shadow-xs cursor-pointer text-xs tracking-wide no-underline">
                Continue to Login
            </a>

            <script>
            function copyAdminKey() {
                const input = document.getElementById('adminKeyValue');
                input.select();
                navigator.clipboard.writeText(input.value).then(() => {
                    const btn = document.getElementById('copyKeyBtn');
                    btn.textContent = 'Copied!';
                    setTimeout(() => { btn.textContent = 'Copy'; }, 1500);
                });
            }
            </script>

        <?php else: ?>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-600 border border-red-100 p-3 rounded-xl text-xs font-bold">
                    <i class="fa-solid fa-circle-exclamation mr-1.5"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Full Name</label>
                    <input type="text" name="fullname" placeholder="Support agent name" required class="w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl px-4 py-2.5 text-xs outline-hidden transition-all bg-white font-medium text-slate-800">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Work Email</label>
                    <input type="email" name="email" placeholder="you@greenai.support" required class="w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl px-4 py-2.5 text-xs outline-hidden transition-all bg-white font-medium text-slate-800">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Password</label>
                    <input type="password" name="password" placeholder="Choose a password" required class="w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl px-4 py-2.5 text-xs outline-hidden transition-all bg-white font-medium text-slate-800">
                </div>

                <button type="submit" class="w-full bg-[#1b5e20] hover:bg-[#144517] text-white font-bold py-3 rounded-xl transition-all shadow-xs cursor-pointer text-xs tracking-wide mt-1">
                    Create Admin Account
                </button>
            </form>

            <p class="text-center text-xs text-gray-400 font-bold pt-1">
                Already have an account? <a href="../login.php?as=admin" class="text-emerald-700 hover:underline font-extrabold ml-0.5">Log in</a>
            </p>

        <?php endif; ?>
    </div>

</body>
</html>
