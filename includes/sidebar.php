<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Identify the current page file name dynamically to assign accurate active state styles
$current_page = basename($_SERVER['PHP_SELF']);

$auth_user = $_SESSION['user'] ?? [];
$sidebar_fullname = $auth_user['fullname'] ?? 'User';
$sidebar_email = $auth_user['email'] ?? '';
$sidebar_initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $sidebar_fullname), 0, 2));
if ($sidebar_initials === '') {
    $sidebar_initials = 'U';
}
?>
<style>
@media (max-width: 767px) {
    body { padding-top: 56px !important; }
}
</style>

<div id="mobileTopbar" class="md:hidden fixed top-0 left-0 right-0 z-20 h-14 bg-white/95 backdrop-blur-sm border-b border-slate-100 shadow-sm flex items-center gap-3 px-4">
    <button onclick="toggleMobileSidebar()" aria-label="Open menu" class="w-9 h-9 rounded-lg flex items-center justify-center text-slate-700 hover:bg-slate-100 transition-all cursor-pointer border-0 bg-transparent text-base">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div class="flex items-center gap-2">
        <span class="w-6 h-6 bg-[#15803d] text-white rounded-lg flex items-center justify-center text-[10px]"><i class="fa-solid fa-leaf"></i></span>
        <span class="text-xs font-black text-slate-800 tracking-wide uppercase">Green-AI</span>
    </div>
</div>

<div id="mobileSidebarBackdrop" onclick="toggleMobileSidebar(false)" class="md:hidden fixed inset-0 bg-slate-900/40 z-30 hidden"></div>

<div id="mainSidebar" class="w-64 min-h-screen bg-[#15803d] text-slate-100 flex flex-col justify-between shrink-0 border-r border-emerald-800 shadow-xl font-sans fixed inset-y-0 left-0 z-40 -translate-x-full transition-transform duration-300 ease-in-out md:relative md:inset-auto md:translate-x-0 md:z-auto">

    <div class="flex flex-col flex-grow">
        
        <div class="p-6 border-b border-emerald-700/40 flex items-center gap-3">
            <div class="w-8 h-8 bg-white text-[#15803d] rounded-xl flex items-center justify-center text-lg font-black shadow-xs">
                <i class="fa-solid fa-leaf"></i>
            </div>
            <div>
                <h2 class="text-sm font-black tracking-wider uppercase text-white m-0 leading-none">Green-AI</h2>
                <span class="text-[9px] font-bold text-emerald-200 tracking-widest uppercase block mt-1">Smart Energy Housing</span>
            </div>
        </div>

        <nav class="p-4 flex-grow space-y-1">
            
            <?php $is_db = ($current_page == 'index.php' || $current_page == 'home.php'); ?>
            <a href="/index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-black tracking-wide transition-all group <?php echo $is_db ? 'text-white bg-white/10 border border-white/10 shadow-xs' : 'text-emerald-100 hover:bg-white/10 hover:text-white'; ?>">
                <span class="w-5 text-center <?php echo $is_db ? 'text-white' : 'text-emerald-200 group-hover:text-white'; ?>"><i class="fa-solid fa-chart-pie"></i></span>
                Dashboard
            </a>

            <?php $is_mon = ($current_page == 'monitoring.php'); ?>
            <a href="/dashboard/monitoring.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-black tracking-wide transition-all group <?php echo $is_mon ? 'text-white bg-white/10 border border-white/10 shadow-xs' : 'text-emerald-100 hover:bg-white/10 hover:text-white'; ?>">
                <span class="w-5 text-center <?php echo $is_mon ? 'text-white' : 'text-emerald-200 group-hover:text-white'; ?>"><i class="fa-solid fa-bolt"></i></span>
                Live Monitoring
            </a>

            <?php $is_hist = ($current_page == 'history.php'); ?>
            <a href="/dashboard/history.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-black tracking-wide transition-all group <?php echo $is_hist ? 'text-white bg-white/10 border border-white/10 shadow-xs' : 'text-emerald-100 hover:bg-white/10 hover:text-white'; ?>">
                <span class="w-5 text-center <?php echo $is_hist ? 'text-white' : 'text-emerald-200 group-hover:text-white'; ?>"><i class="fa-solid fa-clock-rotate-left"></i></span>
                Energy History
            </a>

            <?php $is_pred = ($current_page == 'prediction.php'); ?>
            <a href="/dashboard/prediction.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-black tracking-wide transition-all group <?php echo $is_pred ? 'text-white bg-white/10 border border-white/10 shadow-xs' : 'text-emerald-100 hover:bg-white/10 hover:text-white'; ?>">
                <span class="w-5 text-center <?php echo $is_pred ? 'text-white' : 'text-emerald-200 group-hover:text-white'; ?>"><i class="fa-solid fa-brain"></i></span>
                AI Predictions
            </a>

            <?php $is_ins = ($current_page == 'insights.php'); ?>
            <a href="/dashboard/insights.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-black tracking-wide transition-all group <?php echo $is_ins ? 'text-white bg-white/10 border border-white/10 shadow-xs' : 'text-emerald-100 hover:bg-white/10 hover:text-white'; ?>">
                <span class="w-5 text-center <?php echo $is_ins ? 'text-white' : 'text-emerald-200 group-hover:text-white'; ?>"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                Insights & Tips
            </a>

            <?php $is_dev = ($current_page == 'devices.php'); ?>
            <a href="/dashboard/devices.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-black tracking-wide transition-all group <?php echo $is_dev ? 'text-white bg-white/10 border border-white/10 shadow-xs' : 'text-emerald-100 hover:bg-white/10 hover:text-white'; ?>">
                <span class="w-5 text-center <?php echo $is_dev ? 'text-white' : 'text-emerald-200 group-hover:text-white'; ?>"><i class="fa-solid fa-microchip"></i></span>
                Devices
            </a>

            <?php $is_alr = ($current_page == 'alerts.php'); ?>
            <a href="/dashboard/alerts.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-black tracking-wide transition-all group <?php echo $is_alr ? 'text-white bg-white/10 border border-white/10 shadow-xs' : 'text-emerald-100 hover:bg-white/10 hover:text-white'; ?>">
                <span class="w-5 text-center <?php echo $is_alr ? 'text-white' : 'text-emerald-200 group-hover:text-white'; ?>"><i class="fa-solid fa-bell"></i></span>
                Alerts
            </a>

            <?php $is_sup = ($current_page == 'support.php'); ?>
            <a href="/dashboard/support.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-black tracking-wide transition-all group <?php echo $is_sup ? 'text-white bg-white/10 border border-white/10 shadow-xs' : 'text-emerald-100 hover:bg-white/10 hover:text-white'; ?>">
                <span class="w-5 text-center <?php echo $is_sup ? 'text-white' : 'text-emerald-200 group-hover:text-white'; ?>"><i class="fa-solid fa-headset"></i></span>
                After Sales Support
            </a>

            <?php $is_rep = ($current_page == 'reports.php'); ?>
            <a href="/dashboard/reports.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-black tracking-wide transition-all group <?php echo $is_rep ? 'text-white bg-white/10 border border-white/10 shadow-xs' : 'text-emerald-100 hover:bg-white/10 hover:text-white'; ?>">
                <span class="w-5 text-center <?php echo $is_rep ? 'text-white' : 'text-emerald-200 group-hover:text-white'; ?>"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                Reports
            </a>

            <?php $is_wthr = ($current_page == 'weather.php'); ?>
            <a href="/dashboard/weather.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-black tracking-wide transition-all group <?php echo $is_wthr ? 'text-white bg-white/10 border border-white/10 shadow-xs' : 'text-emerald-100 hover:bg-white/10 hover:text-white'; ?>">
                <span class="w-5 text-center <?php echo $is_wthr ? 'text-white' : 'text-emerald-200 group-hover:text-white'; ?>"><i class="fa-solid fa-cloud-sun"></i></span>
                Real-time Forecasting
            </a>

            <?php $is_set = ($current_page == 'settings.php'); ?>
            <a href="/dashboard/settings.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-black tracking-wide transition-all group <?php echo $is_set ? 'text-white bg-white/10 border border-white/10 shadow-xs' : 'text-emerald-100 hover:bg-white/10 hover:text-white'; ?>">
                <span class="w-5 text-center <?php echo $is_set ? 'text-white' : 'text-emerald-200 group-hover:text-white'; ?>"><i class="fa-solid fa-sliders"></i></span>
                Settings
            </a>
            
        </nav>
    </div>

    <div class="p-4 border-t border-emerald-700/40 bg-emerald-900/20">
        <div class="flex items-center justify-between bg-[#166534] p-3 rounded-xl border border-emerald-700/30">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-7 h-7 bg-emerald-800 text-white rounded-lg flex items-center justify-center text-xs font-black uppercase shrink-0 border border-emerald-600/30">
                    <?php echo htmlspecialchars($sidebar_initials); ?>
                </div>
                <div class="truncate leading-tight">
                    <p class="text-[10px] font-black text-white truncate m-0"><?php echo htmlspecialchars($sidebar_fullname); ?></p>
                    <span class="text-[9px] font-bold text-emerald-300/80 truncate block mt-0.5"><?php echo htmlspecialchars($sidebar_email ?: 'Signed in'); ?></span>
                </div>
            </div>
            
            <button onclick="toggleLogoutModal(true)" class="w-7 h-7 rounded-lg text-emerald-200 hover:bg-rose-500/10 hover:text-rose-400 flex items-center justify-center transition-all text-xs cursor-pointer border-0 bg-transparent" title="Sign Out">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </button>
        </div>
    </div>
</div>

<div id="logoutConfirmationModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center hidden opacity-0 transition-opacity duration-200">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-2xl max-w-sm w-full mx-4 transform scale-95 transition-transform duration-200">
        <div class="flex items-center gap-3.5 mb-4">
            <div class="w-10 h-10 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center text-md shrink-0">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <h3 class="text-sm font-black text-slate-900 m-0 leading-tight">Confirm Sign Out</h3>
                <p class="text-[11px] font-semibold text-slate-400 mt-1">Are you sure you want to log out of Green-AI?</p>
            </div>
        </div>
        <div class="flex gap-2 justify-end pt-2">
            <button onclick="toggleLogoutModal(false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-xl transition-all cursor-pointer border-0">
                Cancel
            </button>
            <a href="/logout.php" class="px-4 py-2 bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold rounded-xl transition-all flex items-center justify-center no-underline">
                Sign Out
            </a>
        </div>
    </div>
</div>

<script>
function toggleMobileSidebar(show) {
    const sidebar = document.getElementById('mainSidebar');
    const backdrop = document.getElementById('mobileSidebarBackdrop');
    if (!sidebar || !backdrop) return;

    const isOpen = !sidebar.classList.contains('-translate-x-full');
    const shouldOpen = (typeof show === 'boolean') ? show : !isOpen;

    sidebar.classList.toggle('-translate-x-full', !shouldOpen);
    backdrop.classList.toggle('hidden', !shouldOpen);
}

document.querySelectorAll('#mainSidebar a').forEach(function (link) {
    link.addEventListener('click', function () {
        if (window.innerWidth < 768) toggleMobileSidebar(false);
    });
});

function toggleLogoutModal(show) {
    const modal = document.getElementById('logoutConfirmationModal');
    if (!modal) return;
    
    if (show) {
        modal.classList.remove('hidden');
        // Small layout delay allows smooth step-in transitions to animate
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('scale-95');
        }, 10);
    } else {
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 200);
    }
}
</script>