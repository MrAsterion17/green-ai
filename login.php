<?php
// 1. DATABASE CONNECTION & SESSION INITIALIZATION
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
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

require_once __DIR__ . '/includes/support_schema.php';
greenai_ensure_support_tables($pdo);

$error = '';
$isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (!empty($_POST['ajax']) && $_POST['ajax'] === '1');

$loginType = $_POST['login_type'] ?? ($_GET['as'] ?? 'user');
if ($loginType !== 'admin') {
    $loginType = 'user';
}

function sendJsonResponse($status, $message = '', $user = []) {
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message, 'user' => $user]);
    exit;
}

// 2. HANDLE GOOGLE AUTHENTICATION (AJAX REQUEST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['google_jwt'])) {
    $jwt = $_POST['google_jwt'];
    
    $parts = explode('.', $jwt);
    if (count($parts) === 3) {
        $payload = json_decode(base64_decode($parts[1]), true);
        
        if (isset($payload['email'])) {
            $email = $payload['email'];
            $fullname = $payload['name'] ?? 'Eco User';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$fullname, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT)]);
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
            }

            $_SESSION['user'] = [
                'id'                 => $user['id'],
                'fullname'           => $user['fullname'],
                'email'              => $user['email'],
                'score'              => 75,
                'waste_diverted_kg'  => 24.5,
                'co2_saved_kg'       => 12.8,
                'scans' => [
                    ['icon' => '🥤', 'name' => 'PET Plastic Bottle', 'destination' => 'Recycling Bin A', 'status' => 'Verified'],
                    ['icon' => '🍎', 'name' => 'Organic Food Scraps', 'destination' => 'Compost Bin', 'status' => 'Verified']
                ]
            ];

            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            if ($isAjaxRequest) {
                sendJsonResponse('success', 'Login successful', ['email' => $email, 'fullname' => $fullname]);
            }

            sendJsonResponse('success', 'Login successful', ['email' => $email, 'fullname' => $fullname]);
        }
    }
    if ($isAjaxRequest) {
        sendJsonResponse('error', 'Token Validation Failed');
    }
    sendJsonResponse('error', 'Token Validation Failed');
}

// 3. HANDLE FIREBASE AUTHENTICATION SYNC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['firebase_login'])) {
    $email = trim($_POST['firebase_email'] ?? '');
    $fullname = trim($_POST['firebase_fullname'] ?? 'Eco User');
    $source = trim($_POST['firebase_source'] ?? 'website');

    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$fullname, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT)]);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        }

        $_SESSION['user'] = [
            'id'                 => $user['id'],
            'fullname'           => $user['fullname'] ?? $fullname,
            'email'              => $user['email'],
            'score'              => 75,
            'waste_diverted_kg'  => 24.5,
            'co2_saved_kg'       => 12.8,
            'scans' => [
                ['icon' => '🥤', 'name' => 'PET Plastic Bottle', 'destination' => 'Recycling Bin A', 'status' => 'Verified'],
                ['icon' => '🍎', 'name' => 'Organic Food Scraps', 'destination' => 'Compost Bin', 'status' => 'Verified']
            ]
        ];

        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

        if ($isAjaxRequest) {
            sendJsonResponse('success', 'Login successful', ['email' => $email, 'fullname' => $fullname, 'source' => $source]);
        }

        header("Location: index.php");
        exit;
    }

    if ($isAjaxRequest) {
        sendJsonResponse('error', 'Firebase login failed.');
    }
    $error = 'Firebase login failed.';
}

// 3.5 HANDLE ADMIN LOGIN (email + password + unique admin key)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['google_jwt']) && empty($_POST['firebase_login']) && $loginType === 'admin') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $adminKey = trim($_POST['admin_key'] ?? '');

    if (!empty($email) && !empty($password) && !empty($adminKey)) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password']) && password_verify($adminKey, $admin['admin_key_hash'])) {
            $_SESSION['admin'] = [
                'id'       => $admin['id'],
                'fullname' => $admin['fullname'],
                'email'    => $admin['email'],
            ];

            header("Location: admin/dashboard.php");
            exit;
        } else {
            $error = "Invalid admin email, password, or admin key.";
        }
    } else {
        $error = "Please fill in all fields, including your admin key.";
    }
}

// 4. HANDLE STANDARD EMAIL/PASSWORD LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['google_jwt']) && empty($_POST['firebase_login']) && $loginType === 'user') {
    $email     = trim($_POST['email'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $residence = trim($_POST['residence'] ?? '');

    // Only residents of these approved areas are allowed to access the portal
    $allowedResidences = [
        "Sentrina",
        "Tierra Hermosa",
        "St. Augustine Village"
    ];

    if (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $columnCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'Residence'");
            if ($columnCheck->rowCount() === 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN Residence VARCHAR(255) DEFAULT NULL");
            }

            $existingResidence = $user['Residence'] ?? $user['residence'] ?? '';
            $effectiveResidence = !empty($residence) ? $residence : $existingResidence;

            if (empty($effectiveResidence)) {
                $msg = 'Please enter your residence so we can verify your account.';
                if ($isAjaxRequest) {
                    sendJsonResponse('error', $msg);
                }
                $error = $msg;
            } elseif (!in_array($effectiveResidence, $allowedResidences)) {
                $msg = 'Access denied: "' . $effectiveResidence . '" is not an approved Green-AI residence area.';
                if ($isAjaxRequest) {
                    sendJsonResponse('error', $msg);
                }
                $error = $msg;
            } else {
                if (!empty($residence) && $residence !== $existingResidence) {
                    $updateStmt = $pdo->prepare("UPDATE users SET Residence = ? WHERE id = ?");
                    $updateStmt->execute([$residence, $user['id']]);
                }

                $_SESSION['user'] = [
                    'id'                 => $user['id'],
                    'fullname'           => $user['fullname'],
                    'email'              => $user['email'],
                    'residence'          => $effectiveResidence,
                    'score'              => 75,
                    'waste_diverted_kg'  => 24.5,
                    'co2_saved_kg'       => 12.8,
                    'scans' => [
                        ['icon' => '🥤', 'name' => 'PET Plastic Bottle', 'destination' => 'Recycling Bin A', 'status' => 'Verified'],
                        ['icon' => '🍎', 'name' => 'Organic Food Scraps', 'destination' => 'Compost Bin', 'status' => 'Verified']
                    ]
                ];

                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

                if ($isAjaxRequest) {
                    sendJsonResponse('success', 'Login successful', ['email' => $email, 'fullname' => $user['fullname']]);
                }

                header("Location: index.php");
                exit;
            }
        } else {
            if ($isAjaxRequest) {
                sendJsonResponse('error', 'Invalid email or password.');
            }
            $error = "Invalid email or password.";
        }
    } else {
        if ($isAjaxRequest) {
            sendJsonResponse('error', 'Please fill in all fields.');
        }
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green-AI - Welcome Back</title>
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
                        <span class="block text-[9px] text-white/70 leading-tight">Reduce cost & energy waste</span>
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
                <button class="flex items-center gap-1.5 border border-gray-200 text-xs font-bold text-gray-500 px-3 py-1.5 rounded-xl hover:bg-gray-50 transition-colors cursor-pointer">
                    <i class="fa-solid fa-globe text-gray-400"></i> English <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 ml-0.5"></i>
                </button>
            </div>

            <div class="max-w-sm w-full mx-auto my-auto px-6 space-y-5">
                
                <div class="space-y-0.5">
                    <h2 class="text-2xl font-black text-gray-900 tracking-tight flex items-center gap-1.5" id="loginHeading">Welcome Back! <span class="text-xl text-[#2e7d32]">🍃</span></h2>
                    <p class="text-gray-400 text-xs font-bold" id="loginSubheading">Sign in to continue to Green-AI</p>
                </div>

                <div class="flex items-center gap-1 bg-gray-50 p-1 rounded-xl border border-gray-100 text-[11px] font-bold text-gray-400 w-fit">
                    <button type="button" onclick="setLoginMode('user')" id="tabUser" class="px-3.5 py-1.5 rounded-lg transition-all cursor-pointer">
                        <i class="fa-solid fa-house-user mr-1"></i> Resident
                    </button>
                    <button type="button" onclick="setLoginMode('admin')" id="tabAdmin" class="px-3.5 py-1.5 rounded-lg transition-all cursor-pointer">
                        <i class="fa-solid fa-headset mr-1"></i> Admin Staff
                    </button>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 text-red-600 border border-red-100 p-3 rounded-xl text-xs font-bold shadow-xs">
                        <i class="fa-solid fa-circle-exclamation mr-1.5"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form id="loginForm" action="login.php" method="POST" class="space-y-4">
                    <input type="hidden" name="login_type" id="loginTypeInput" value="<?php echo htmlspecialchars($loginType); ?>">

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Email Address</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"><i class="fa-regular fa-envelope text-sm"></i></span>
                            <input id="email" type="email" name="email" placeholder="Enter your email" required class="w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl pl-10 pr-4 py-2.5 text-xs outline-hidden transition-all bg-white font-medium text-slate-800">
                        </div>
                    </div>

                    <div id="residenceField">
                        <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Residence</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"><i class="fa-solid fa-house-user text-sm"></i></span>
                            <select name="residence" class="w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl pl-10 pr-4 py-2.5 text-xs outline-hidden transition-all bg-white font-medium text-slate-800">
                                <option value="">Use residence on file</option>
                                <option value="Sentrina">Sentrina</option>
                                <option value="Tierra Hermosa">Tierra Hermosa</option>
                                <option value="St. Augustine Village">St. Augustine Village</option>
                            </select>
                        </div>
                        <p class="mt-1 text-[10px] text-gray-400">Only approved Green-AI residence areas can sign in.</p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Password</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"><i class="fa-solid fa-lock text-sm"></i></span>
                            <input id="password" type="password" name="password" placeholder="Enter your password" required class="w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl pl-10 pr-10 py-2.5 text-xs outline-hidden transition-all bg-white font-medium text-slate-800">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 cursor-pointer hover:text-gray-600"><i class="fa-regular fa-eye text-sm"></i></span>
                        </div>
                        <div class="w-full flex justify-end mt-1.5" id="forgotPasswordRow">
                            <a href="#" class="text-[11px] font-bold text-emerald-700 hover:text-emerald-800 tracking-wide">Forgot password?</a>
                        </div>
                    </div>

                    <div id="adminKeyField" class="hidden">
                        <label class="block text-[11px] font-bold text-gray-500 mb-1 tracking-wide">Admin Key</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"><i class="fa-solid fa-key text-sm"></i></span>
                            <input type="text" name="admin_key" placeholder="XXXX-XXXX-XXXX-XXXX" class="w-full border border-gray-200 focus:border-[#2e7d32] focus:ring-3 focus:ring-green-700/5 rounded-xl pl-10 pr-4 py-2.5 text-xs outline-hidden transition-all bg-white font-medium text-slate-800 tracking-wider">
                        </div>
                        <p class="mt-1 text-[10px] text-gray-400">The unique key you were shown when your admin account was created.</p>
                    </div>

                    <div class="flex flex-col gap-2" id="residentButtons">
                        <button type="button" id="btnSignIn" class="w-full bg-[#1b5e20] hover:bg-[#144517] text-white font-bold py-3 rounded-xl transition-all shadow-xs cursor-pointer text-xs tracking-wide">
                            Log In
                        </button>
                    </div>

                    <div id="adminButtons" class="hidden">
                        <button type="submit" class="w-full bg-[#1b5e20] hover:bg-[#144517] text-white font-bold py-3 rounded-xl transition-all shadow-xs cursor-pointer text-xs tracking-wide">
                            Log In as Admin
                        </button>
                    </div>
                </form>

                <div id="googleSignInBlock" class="space-y-4">
                    <div class="relative flex items-center my-3">
                        <div class="flex-grow border-t border-gray-200/60"></div>
                        <span class="flex-shrink mx-3 text-gray-400 text-[10px] font-bold uppercase tracking-widest">or</span>
                        <div class="flex-grow border-t border-gray-200/60"></div>
                    </div>

                    <div class="w-full flex justify-center">
                        <button type="button" id="btnGoogle" class="w-full flex items-center justify-center gap-2 border border-gray-200 rounded-xl py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fa-brands fa-google text-red-500"></i>
                            Continue with Google
                        </button>
                    </div>
                </div>

                <p class="text-center text-xs text-gray-400 font-bold pt-1" id="signupRow">
                    Don't have an account? <a href="register.php" class="text-emerald-700 hover:underline font-extrabold ml-0.5">Sign up</a>
                </p>
                <p class="text-center text-xs text-gray-400 font-bold pt-1 hidden" id="adminSignupRow">
                    Need an admin account? <a href="admin/register.php" class="text-emerald-700 hover:underline font-extrabold ml-0.5">Sign up</a>
                </p>
            </div>

            <div class="w-full py-4 px-6 flex justify-between text-[10px] font-bold text-gray-400 border-t border-gray-50">
                <div class="flex items-center gap-1"><i class="fa-solid fa-shield-halved text-emerald-600"></i> Secure & Private</div>
                <div class="flex items-center gap-1"><i class="fa-solid fa-microchip text-emerald-600"></i> AI-Powered</div>
                <div class="flex items-center gap-1"><i class="fa-solid fa-leaf text-emerald-600"></i> Eco-Friendly</div>
            </div>
        </div>

    </div>

    <script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/12.16.0/firebase-app.js";
    import * as firebaseAuth from "https://www.gstatic.com/firebasejs/12.16.0/firebase-auth.js";
    import {
      getDatabase,
      ref,
      onValue,
      onDisconnect,
      set,
      serverTimestamp
    } from "https://www.gstatic.com/firebasejs/12.16.0/firebase-database.js";

    const firebaseConfig = {
      apiKey: "AIzaSyCAtkDtFSDuVxbHQ66SFtVLDbcPPSoeUdc",
      authDomain: "green-ai-sign-ins.firebaseapp.com",
      databaseURL: "https://green-ai-sign-ins-default-rtdb.asia-southeast1.firebasedatabase.app",
      projectId: "green-ai-sign-ins",
      storageBucket: "green-ai-sign-ins.firebasestorage.app",
      messagingSenderId: "526201010134",
      appId: "1:526201010134:web:f11a838c499f5acf9fd677",
      measurementId: "G-QDGRRZNSCC"
    };

    const app = initializeApp(firebaseConfig);
    const auth = firebaseAuth.getAuth(app);
    const db = getDatabase(app);

    function setupPresenceSystem(user) {
      const userStatusRef = ref(db, `/status/${user.uid}`);
      const connectedRef = ref(db, ".info/connected");

      onValue(connectedRef, (snapshot) => {
        if (snapshot.val() === false) return;

        onDisconnect(userStatusRef).set({
          state: "offline",
          email: user.email,
          lastChanged: serverTimestamp()
        }).then(() => {
          set(userStatusRef, {
            state: "online",
            email: user.email,
            lastChanged: serverTimestamp()
          });
        });
      });
    }

    firebaseAuth.onAuthStateChanged(auth, (user) => {
      if (user) {
        setupPresenceSystem(user);
      }
    });

    // Turn Firebase error codes into messages a resident can act on
    function friendlyAuthError(err) {
      const messages = {
        "auth/invalid-email": "Please enter a valid email address.",
        "auth/missing-password": "Please enter your password.",
        "auth/weak-password": "Password must be at least 6 characters.",
        "auth/email-already-in-use": "An account with this email already exists. Click Sign In instead.",
        "auth/invalid-credential": "Incorrect email or password.",
        "auth/wrong-password": "Incorrect email or password.",
        "auth/user-not-found": "No account found with this email. Click Sign Up to create one.",
        "auth/too-many-requests": "Too many attempts. Please wait a moment and try again.",
        "auth/network-request-failed": "Network error. Check your internet connection.",
        "auth/popup-closed-by-user": "Google sign-in was cancelled.",
        "auth/unauthorized-domain": "Google sign-in is not enabled for this website yet (add this domain in Firebase Authorized domains)."
      };
      return messages[err.code] || err.message;
    }

    function readCredentials() {
      const e = document.getElementById("email").value.trim();
      const p = document.getElementById("password").value;
      if (!e) { alert("Please enter your email address."); return null; }
      if (!p) { alert("Please enter your password."); return null; }
      return { e, p };
    }

    // Create the PHP session for a Firebase-authenticated user, then open the dashboard
    async function syncToServer(user, source) {
      const body = new FormData();
      body.append("firebase_login", "1");
      body.append("firebase_email", user.email);
      body.append("firebase_fullname", user.displayName || user.email.split("@")[0]);
      body.append("firebase_source", source);
      body.append("ajax", "1");
      const res = await fetch("login.php", { method: "POST", body });
      const data = await res.json();
      if (data.status !== "success") throw new Error(data.message || "Login failed.");
      window.location.href = "index.php";
    }


    document.getElementById("btnSignIn").addEventListener("click", async () => {
      const cred = readCredentials();
      if (!cred) return;
      try {
        const result = await firebaseAuth.signInWithEmailAndPassword(auth, cred.e, cred.p);
        await syncToServer(result.user, "signin");
      } catch (err) {
        // Not a Firebase account (e.g. created on the Sign up page): use the regular
        // email + password + residence login handled by PHP.
        document.getElementById("loginForm").submit();
      }
    });

    document.getElementById("btnGoogle").addEventListener("click", async () => {
      try {
        const provider = new firebaseAuth.GoogleAuthProvider();
        const result = await firebaseAuth.signInWithPopup(auth, provider);
        await syncToServer(result.user, "google");
      } catch (err) {
        alert("Google Error: " + friendlyAuthError(err));
      }
    });

    // Pressing Enter in resident mode goes through the same Firebase-then-PHP path as the Log In button
    document.getElementById("loginForm").addEventListener("submit", (e) => {
      if (document.getElementById("loginTypeInput").value === "admin") return;
      e.preventDefault();
      document.getElementById("btnSignIn").click();
    });
    </script>

    <script>
    function setLoginMode(mode) {
        const isAdmin = (mode === 'admin');
        document.getElementById('loginTypeInput').value = isAdmin ? 'admin' : 'user';

        document.getElementById('residenceField').classList.toggle('hidden', isAdmin);
        document.getElementById('adminKeyField').classList.toggle('hidden', !isAdmin);
        document.getElementById('residentButtons').classList.toggle('hidden', isAdmin);
        document.getElementById('adminButtons').classList.toggle('hidden', !isAdmin);
        document.getElementById('googleSignInBlock').classList.toggle('hidden', isAdmin);
        document.getElementById('signupRow').classList.toggle('hidden', isAdmin);
        document.getElementById('adminSignupRow').classList.toggle('hidden', !isAdmin);
        document.getElementById('forgotPasswordRow').classList.toggle('hidden', isAdmin);

        document.getElementById('loginHeading').innerHTML = isAdmin
            ? 'Support Console <span class="text-xl text-[#2e7d32]">🛠️</span>'
            : 'Welcome Back! <span class="text-xl text-[#2e7d32]">🍃</span>';
        document.getElementById('loginSubheading').textContent = isAdmin
            ? 'Sign in with your admin email, password, and unique key'
            : 'Sign in to continue to Green-AI';

        const tabUser = document.getElementById('tabUser');
        const tabAdmin = document.getElementById('tabAdmin');
        tabUser.className = 'px-3.5 py-1.5 rounded-lg transition-all cursor-pointer' + (isAdmin ? '' : ' bg-white text-slate-800 shadow-2xs');
        tabAdmin.className = 'px-3.5 py-1.5 rounded-lg transition-all cursor-pointer' + (isAdmin ? ' bg-white text-slate-800 shadow-2xs' : '');
    }

    document.addEventListener('DOMContentLoaded', function () {
        setLoginMode('<?php echo $loginType === 'admin' ? 'admin' : 'user'; ?>');
    });
    </script>

</body>
</html>