# Green-AI

PHP + MySQL web app for solar energy monitoring, site assessment, and prediction.

## Running locally (XAMPP)

1. Copy this folder into `xampp/htdocs/`.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. In phpMyAdmin, create a database named `greenai_db` and import your schema.
4. Open `http://localhost/<folder-name>/` in your browser.

With no environment variables set, the app uses `localhost` / `root` / no password / `greenai_db`
(defined in `includes/db_env.php`).

## Deploying to Railway

The repo includes a `Dockerfile` (PHP 8.3 + Apache) and `railway.json`, so Railway builds it automatically.

1. Push this folder to GitHub (the `oracleJdk-26/` folder is ignored).
2. In Railway: **New Project → Deploy from GitHub repo** and pick the repo.
3. In the same project: **New → Database → MySQL**.
4. Open the web service → **Variables** and add these references to the MySQL service:
   ```
   MYSQLHOST=${{MySQL.MYSQLHOST}}
   MYSQLPORT=${{MySQL.MYSQLPORT}}
   MYSQLUSER=${{MySQL.MYSQLUSER}}
   MYSQLPASSWORD=${{MySQL.MYSQLPASSWORD}}
   MYSQLDATABASE=${{MySQL.MYSQLDATABASE}}
   ```
   Optional: `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` for Google sign-in.
5. Import your schema into the Railway MySQL database: export `greenai_db` from phpMyAdmin
   as `.sql`, then use the MySQL service's **Data** tab or connect with the public
   connection URL (MySQL Workbench / `mysql` CLI) and run the file.
6. Web service → **Settings → Networking → Generate Domain** to get a public URL.

If you use Google sign-in, add `https://<your-railway-domain>/login.php` as an authorized
redirect URI in Google Cloud Console.

## Rain SMS alerts

Residents give their subdivision, block, lot and mobile number at sign-up (or later under
**Settings → Home Address & SMS Alerts**). When rain is forecast within the next 3 hours
(60%+ chance), opted-in residents get a text telling them to harvest and save solar energy.
Each resident is texted at most once every 6 hours.

Railway variables on the web service:

```
SEMAPHORE_API_KEY=<from semaphore.co>
SEMAPHORE_SENDER_NAME=<optional approved sender name>
CRON_SECRET=<any long random string>
```

Then have a scheduler (cron-job.org, a Railway cron service, etc.) call this URL every 15-30 minutes:

```
https://<your-domain>/api/rainalert.php?key=<CRON_SECRET>
```

Add `&dry=1` to preview who would be texted without sending. Sent/failed texts are recorded in `sms_alert_log`.
