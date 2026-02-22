# Deploy SCCDMS3 to DigitalOcean App Platform — Step by Step

This guide walks you through deploying the SCC Document Management System (PHP + MySQL) on DigitalOcean App Platform.

---

## Before you start

- GitHub repo: **mariejustinmerida/SCCDMS3**, branch **main**
- DigitalOcean account with App Platform and (optionally) a managed MySQL database
- The app uses **PHP** and **MySQL**. App Platform will use the **PHP buildpack** (not Python). If the dashboard shows "Python build detected", follow **Step 3** to fix it.

---

## Before you create the app (checklist)

Do these **before** you click **Create app** so the first deploy works:

1. **Push your latest code to GitHub**  
   Commit and push the repo (including `includes/config.php` with `DATABASE_URL` support, `composer.json` with `"php": "^8.1"`). The app will build from whatever is on `main`.

2. **Database is attached and schema imported**  
   You already have **dev-db-055066** and **DATABASE_URL** in the app. Before (or right after) the first deploy, import the schema once:
   - From your machine or a DB client, connect to that database using the same credentials as `DATABASE_URL`.
   - Run the contents of **`database/scc_dms.sql`** (create tables, etc.).  
   If you don’t do this, the app will show “Database connection failed” or similar until the schema exists.

3. **Optional: add `.do/app.yaml` and push**  
   The repo now includes **`.do/app.yaml`** with `environment_slug: php`. After you create the app, you’ll set the runtime to PHP via **Settings → App Spec → Edit** (see Step 3). Having the file in the repo gives you a reference; you can copy `environment_slug: php` from it into the spec.

4. **Optional env vars**  
   If you use Google OAuth or OpenAI, add **GOOGLE_CLIENT_ID**, **GOOGLE_CLIENT_SECRET**, **OPENAI_API_KEY** in the app’s Environment variables (or in the sccdms3 component) before or after create.

Then click **Create app**. Right after the app is created, go to **Settings → App Spec → Edit**, add **`environment_slug: php`** under the sccdms3 service, and Save so it redeploys with PHP.

---

## Step 1: Create the app and connect GitHub

1. In DigitalOcean: **Apps → Create App**.
2. **Choose source**: select **GitHub**.
3. Choose **mariejustinmerida/SCCDMS3**, branch **main**.
4. Leave **Auto-deploy on push** enabled if you want every push to `main` to deploy.
5. Click **Next** (or **Configure app**).

---

## Step 2: Resource type and name

1. On **Configure app**:
   - The component is usually detected as a **Web Service** (or sometimes wrongly as Python).
   - **Name**: e.g. `sccdms3` (or the name you prefer; must be lowercase, 2–32 chars).
2. **App name** (later in the flow): choose a unique name, e.g. **lionfish-app** (or another name you like).
3. **Project**: e.g. **first-project** (or create/select another).

---

## Step 3: Make sure PHP is used (not Python)

The repo has both `composer.json` (PHP) and `requirements.txt` (Python). App Platform should detect PHP because of `index.php` and `composer.json`. If the UI shows **"Python build detected"**:

**Option A — In the dashboard (after the app exists)**

1. Go to [cloud.digitalocean.com/apps](https://cloud.digitalocean.com/apps) and click your app (e.g. **lionfish-app**).
2. Open the **Settings** tab (top of the app page, next to Overview).
3. Scroll to the **App Spec** section and click **Edit**.
4. In the YAML, find the `services:` block and the entry for your component (e.g. `sccdms3`). Add or change the line **`environment_slug: php`** for that service (see Option B for a full example). Save. The app will redeploy with PHP.

**During app creation:** On the “Configure app” / “Resource settings” screen, click **Edit** next to the component name (e.g. **sccdms3**) in the Resource settings table. Some accounts see a **Runtime** or **Buildpack** option there; if you do, set it to **PHP**. If you don’t see it, use Option A after creating the app or use Option B (app spec in repo).

**Option B — Use the app spec in the repo**

The repo can include a **`.do/app.yaml`** that forces PHP. If you already have one from the repo, App Platform will use it. Otherwise, you can add:

- **File**: `.do/app.yaml`
- **Content** (adjust `repo`/`branch` if different):

```yaml
name: lionfish-app
region: sgp1

services:
  - name: sccdms3
    environment_slug: php
    github:
      repo: mariejustinmerida/SCCDMS3
      branch: main
      deploy_on_push: true
    source_dir: /
    http_port: 8080
    instance_count: 2
    instance_size_slug: basic-xxs
```

Then commit, push, and create/update the app from this repo; the spec will set the runtime to PHP.

---

## Step 4: Build and run commands

- **Build command**: Leave **empty**. The PHP buildpack runs `composer install` automatically.
- **Run command**: Leave **empty**. The buildpack starts the PHP web process (Apache) on the port you set.
- **Public HTTP port**: Set to **8080** (default for the PHP buildpack).

No need to set a custom build or run command unless DigitalOcean support or docs say otherwise.

---

## Step 5: Database (MySQL)

**If you use a DigitalOcean managed database (recommended):**

1. In the app, go to **Resources** or **Add a database**.
2. **Create dev database** or **Attach DigitalOcean database** (e.g. **dev-db-055066**).
3. In your **Web Service** component, open **Environment variables**.
4. Add (or confirm) **DATABASE_URL** and set its value to:
   - **`${dev-db-055066.DATABASE_URL}`**  
   (replace `dev-db-055066` with your actual database resource name if different.)

The app’s `includes/config.php` now supports **DATABASE_URL**. It will parse it and connect to MySQL (host, port, user, password, database name) automatically. You do **not** need to set `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME` separately when using `DATABASE_URL`.

**If you use an external MySQL database:**

1. In the Web Service, add environment variables:
   - `DB_HOST` — e.g. `your-db-host.db.ondigitalocean.com`
   - `DB_USERNAME`
   - `DB_PASSWORD`
   - `DB_NAME` — e.g. `scc_dms`
   - `DB_PORT` — e.g. `25060` (if not 3306)

---

## Step 6: Import the database schema

Your app expects the **scc_dms** schema (tables, etc.) to already exist.

1. Get the DB host, user, password, and database name (from **DATABASE_URL** or from **DB_*** vars).
2. From your machine (or a one-off task), run:
   ```bash
   mysql -h YOUR_HOST -P YOUR_PORT -u YOUR_USER -p YOUR_DATABASE < database/scc_dms.sql
   ```
   Or use MySQL Workbench / another client to run the contents of `database/scc_dms.sql` against the same database.

Do this **once** per environment (e.g. after creating the managed DB or when switching to a new DB).

---

## Step 7: Other environment variables (optional)

If you use Google OAuth or OpenAI:

- **App-level** or **component-level** env vars (do **not** commit real values):
  - `GOOGLE_CLIENT_ID`
  - `GOOGLE_CLIENT_SECRET`
  - `OPENAI_API_KEY`

See `includes/.env.example` for the list. Set these in the App Platform UI (Encrypted) so they are available to the PHP app.

---

## Step 8: Region and instance size

1. **Datacenter region**: e.g. **Singapore (sgp1)** if that’s what you chose (or another region).
2. **Instance size**: e.g. **1 GB RAM / 1 Shared vCPU** (or larger if you need it).
3. **Containers**: e.g. **2** for basic redundancy.

---

## Step 9: Finalize and create the app

1. **Choose a unique app name**: e.g. **lionfish-app** (lowercase, 2–32 chars).
2. **Select a project**: e.g. **first-project**.
3. Review the **Summary** (cost, region, resources).
4. Click **Create app** (or **Create resources** then **Create app**).

App Platform will:

- Clone the repo
- Build with the PHP buildpack (`composer install`, etc.)
- Start the web process on port 8080
- Expose the app on the default URL (e.g. `https://lionfish-app-xxxxx.ondigitalocean.app`)

---

## Step 10: After first deploy

1. Open the app URL. You should be redirected to **auth/login.php**.
2. If you see **“System temporarily unavailable”** or a DB error:
   - Confirm **DATABASE_URL** (or **DB_***) is set and correct.
   - Confirm the schema was imported (Step 6).
   - Check the app’s **Logs** in the DigitalOcean dashboard.
3. **Storage**: Uploaded files under `storage/` on App Platform are **ephemeral** (lost on redeploy). For production, plan to use **Spaces** (S3-compatible) or another persistent storage and adapt the app to use that for uploads.

---

## Quick checklist

- [ ] Source: GitHub **mariejustinmerida/SCCDMS3**, branch **main**
- [ ] Web Service is using **PHP** (not Python)
- [ ] **HTTP port**: 8080
- [ ] **DATABASE_URL** (or **DB_HOST** / **DB_USERNAME** / **DB_PASSWORD** / **DB_NAME** / **DB_PORT**) set for the Web Service
- [ ] Database schema imported from `database/scc_dms.sql`
- [ ] Optional: **GOOGLE_CLIENT_ID**, **GOOGLE_CLIENT_SECRET**, **OPENAI_API_KEY** set if needed
- [ ] App name and project set; app created and first deploy finished

---

## Troubleshooting

| Issue | What to check |
|-------|----------------|
| “Python build detected” | Force PHP via dashboard (Step 3 Option A) or `.do/app.yaml` (Step 3 Option B). |
| “Database connection failed” | Correct **DATABASE_URL** or **DB_***; DB allows connections from App Platform (trusted sources / allowed IPs); schema imported. |
| 404 or wrong routes | PHP buildpack uses Apache; `index.php` and `.htaccess` are respected. Ensure **Document root** is repo root (default). |
| Composer errors on build | Run `composer install` locally and commit `composer.lock`; ensure **PHP** is set to `^8.1` in `composer.json`. |

For more on the PHP buildpack: [DigitalOcean PHP Buildpack](https://docs.digitalocean.com/products/app-platform/reference/buildpacks/php/).
