<?php
// DATABASE CONNECTION & SESSION INITIALIZATION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Railway env vars when set, XAMPP defaults otherwise (see includes/db_env.php)
require_once __DIR__ . '/includes/db_env.php';
$host = GREENAI_DB_HOST;
$port = GREENAI_DB_PORT;
$db   = GREENAI_DB_NAME;
$user = GREENAI_DB_USER;
$pass = GREENAI_DB_PASS;

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fullname  = trim($_POST["fullname"] ?? "");
    $residence = trim($_POST["Residence"] ?? "");
    $email     = trim($_POST["email"] ?? "");
    $password  = trim($_POST["password"] ?? "");

    // Allowed residences only
    $allowedResidences = [
        "Sentrina",
        "Tierra Hermosa",
        "St. Augustine Village"
    ];

    if (
        empty($fullname) ||
        empty($residence) ||
        empty($email) ||
        empty($password)
    ) {

        $error = "Please fill out all registration fields.";

    } elseif (!in_array($residence, $allowedResidences)) {

        $error = "Registration is only available for residents of Sentrina, Tierra Hermosa, and St. Augustine Village.";

    } else {

        // Check if email already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetch()) {

            $error = "An account with this email already exists.";

        } else {

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $insert = $pdo->prepare("
                INSERT INTO users
                (fullname, Residence, email, password)
                VALUES
                (?, ?, ?, ?)
            ");

            if ($insert->execute([
                $fullname,
                $residence,
                $email,
                $hashedPassword
            ])) {

                $success = "Account created successfully! Redirecting to login...";
                header("Refresh:2; url=login.php");

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
    <title>Green-AI - Get Started</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
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
                    <h1 class="text-2xl font-black text-white tracking-tight leading-tight">Start Tracking Your Carbon Offsets</h1>
                </div>

                <div class="space-y-4 text-white">
                    <div class="flex items-start gap-3">
                        <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center shrink-0"><i class="fa-solid fa-shield-halved text-xs"></i></div>
                        <div class="space-y-0.5">
                            <strong class="block text-xs font-bold">Secure & Private</strong>
                            <p class="text-white/80 text-[11px] leading-relaxed">Your localized analytics profile modules remain completely protected.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center shrink-0"><i class="fa-solid fa-leaf text-xs"></i></div>
                        <div class="space-y-0.5">
                            <strong class="block text-xs font-bold">Eco-Friendly Analytics</strong>
                            <p class="text-white/80 text-[11px] leading-relaxed">Calculated frameworks engineered from the ground up for clean green outcomes.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white/20 border border-white/30 backdrop-blur-md p-3.5 rounded-xl flex items-center gap-2.5 relative z-10 shadow-xs max-w-sm">
                <span class="text-lg">⚡</span>
                <div class="text-left">
                    <span class="block text-xs font-bold text-white">Smart AI Integration</span>
                    <p class="text-[10px] text-white/90 font-medium leading-normal">Immediate classification and predictive modeling step pipelines.</p>
                </div>
            </div>
        </div>

        <div class="w-full md:w-1/2 bg-white flex flex-col justify-between relative min-h-screen">
            
            <div class="w-full flex justify-end p-4">
                <button class="flex items-center gap-1.5 border border-gray-200 text-xs font-bold text-gray-500 px-3 py-1.5 rounded-xl hover:bg-gray-50 transition-colors cursor-pointer">
                    <i class="fa-solid fa-globe text-gray-400"></i> English <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 ml-0.5"></i>
                </button>
            </div>

            <div class="max-w-sm w-full mx-auto my-auto px-6 space-y-5">
                
                <div class="space-y-0.5">
                    <h2 class="text-2xl font-black text-gray-900 tracking-tight flex items-center gap-1.5">Get Started 🚀</h2>
                    <p class="text-gray-400 text-xs font-bold">Create your sustainability profile index now.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 text-red-600 border border-red-100 p-3 rounded-xl text-xs font-bold shadow-xs">
                        <i class="fa-solid fa-circle-exclamation mr-1.5"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="bg-emerald-50 text-emerald-700 border border-emerald-100 p-3 rounded-xl text-xs font-bold shadow-xs">
                        <i class="fa-solid fa-circle-check mr-1.5"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST" class="space-y-3.5">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide uppercase">Full Name</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"><i class="fa-regular fa-user text-sm"></i></span>
                            <input type="text" name="fullname" placeholder="John Doe" required class="w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl pl-10 pr-4 py-2.5 text-xs outline-hidden transition-all bg-white font-medium text-slate-800">
                        </div>
                    </div>

<!-- Residence -->
<div>
    <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide uppercase">
        Residence
    </label>

    <div class="relative">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
            <i class="fa-solid fa-location-dot text-sm"></i>
        </span>

        <select
            name="Residence"
            required
            class="w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl pl-10 pr-4 py-2.5 text-xs outline-hidden transition-all bg-white font-medium text-slate-800">

            <option value="">Select Residence</option>
            <option value="Sentrina">Sentrina</option>
            <option value="Tierra Hermosa">Tierra Hermosa</option>
            <option value="St. Augustine Village">St. Augustine Village</option>

        </select>
    </div>
</div>

<div>
    <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide uppercase">
        Email Address
    </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"><i class="fa-regular fa-envelope text-sm"></i></span>
                            <input type="email" name="email" placeholder="example@domain.com" required class="w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl pl-10 pr-4 py-2.5 text-xs outline-hidden transition-all bg-white font-medium text-slate-800">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide uppercase">Password</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"><i class="fa-solid fa-lock text-sm"></i></span>
                            <input type="password" name="password" placeholder="••••••••" required class="w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl pl-10 pr-10 py-2.5 text-xs outline-hidden transition-all bg-white font-medium text-slate-800">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-[#1b5e20] hover:bg-[#144517] text-white font-bold py-3 rounded-xl transition-all shadow-xs cursor-pointer text-xs tracking-wide mt-2">
                        Create Account
                    </button>
                </form>

                <div class="relative flex items-center my-3">
                    <div class="flex-grow border-t border-gray-200/60"></div>
                    <span class="flex-shrink mx-3 text-gray-400 text-[10px] font-bold uppercase tracking-widest">or</span>
                    <div class="flex-grow border-t border-gray-200/60"></div>
                </div>

                <div class="w-full flex justify-center">
                    <div class="g_id_signin w-full"
                        data-type="standard"
                        data-shape="rectangular"
                        data-theme="outline"
                        data-text="signup_with"
                        data-size="large"
                        data-logo_alignment="left"
                        data-width="384">
                    </div>
                </div>

                <p class="text-center text-xs text-gray-400 font-bold pt-1">
                    Already have an account? <a href="login.php" class="text-emerald-700 hover:underline font-extrabold ml-0.5">Log in</a>
                </p>
            </div>

            <div class="w-full py-4 px-6 flex justify-between text-[10px] font-bold text-gray-400 border-t border-gray-50">
                <div class="flex items-center gap-1"><i class="fa-solid fa-shield-halved text-emerald-600"></i> Secure & Private</div>
                <div class="flex items-center gap-1"><i class="fa-solid fa-microchip text-emerald-600"></i> AI-Powered</div>
                <div class="flex items-center gap-1"><i class="fa-solid fa-leaf text-emerald-600"></i> Eco-Friendly</div>
            </div>
        </div>

    </div>
</body>
</html>