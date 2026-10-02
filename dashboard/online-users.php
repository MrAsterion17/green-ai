<?php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Users</title>
    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js";
        import { getAuth, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-auth.js";
        import { getDatabase, ref, onValue } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-database.js";

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
        const auth = getAuth(app);
        const db = getDatabase(app);

        const list = document.getElementById('onlineUsersList');
        const statusText = document.getElementById('statusText');

        onAuthStateChanged(auth, (user) => {
            if (!user) {
                statusText.textContent = 'Please sign in to view online users.';
                return;
            }

            const usersRef = ref(db, 'status');
            onValue(usersRef, (snapshot) => {
                const data = snapshot.val() || {};
                const entries = Object.entries(data)
                    .filter(([, value]) => value && value.state === 'online')
                    .map(([uid, value]) => ({ uid, ...value }));

                if (entries.length === 0) {
                    list.innerHTML = '<li class="text-gray-500">No users are currently online.</li>';
                    statusText.textContent = 'No active users right now.';
                    return;
                }

                list.innerHTML = entries.map((userEntry) => `
                    <li class="border rounded-lg p-3 flex justify-between items-center">
                        <div>
                            <div class="font-semibold text-gray-800">${userEntry.email || 'Unknown user'}</div>
                            <div class="text-sm text-gray-500">UID: ${userEntry.uid}</div>
                        </div>
                        <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-sm font-semibold">Online</span>
                    </li>
                `).join('');

                statusText.textContent = `Showing ${entries.length} online user(s).`;
            });
        });
    </script>
</head>
<body style="font-family: Arial, sans-serif; background: #f7f7f7; margin: 0; padding: 2rem;">
    <div style="max-width: 700px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
        <h2 style="margin-top: 0;">Online Users</h2>
        <p id="statusText" style="color: #4b5563;">Loading online users...</p>
        <ul id="onlineUsersList" style="list-style: none; padding: 0; margin-top: 1rem; display: grid; gap: 0.75rem;"></ul>
    </div>
</body>
</html>
