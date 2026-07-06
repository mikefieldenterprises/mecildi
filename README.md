# MECILDI Language Detector

MECILDI is a specialized, asynchronous web crawling and data analysis framework designed to identify the language and multilingual status of web domains at scale. Specifically optimized for single, shared Linux hosting environments (PHP 7.4+ & MySQL), the system adheres to strict performance thresholds and "polite crawling" protocols to process up to 100,000 unique homepages within a 21-to-72-hour window.

To maintain transparency, the crawler identifies itself to webmasters using a custom User-Agent pointing to our project documentation:
**Project Info Page:** https://www.obdilci.org/projects/mecildi/detector/

---

## How to Cite

If you use MECILDI in academic research, please cite:

Pimienta, D. and Field, M. (2026). MECILDI: A Multilingual-Aware Computational Framework for Measuring Language Distribution on the Internet. Frontiers in Research Metrics and Analysis. [DOI to be added upon publication]


---

## System Architecture & Processing Pipeline

### Phase 1: Initialization & Environmental Setup
The process initializes via process-worker.php to establish an execution environment resilient against shared hosting constraints (e.g., script timeouts):

* **Process Management:** Captures the unique Process ID (PID) via getmypid(), locks execution inside a .pid file to prevent overlapping runs, sets set_time_limit(0), and enables ignore_user_abort(true).
* **Configuration Scoping:** The AppConfig class parses app.ini and preferences.json to configure the system "DNA" (such as parallel_batch_size).
* **Batch-Level Persistence Recovery:** Reads the /temp-input/ directory and matches files against the database LAST_LINE_PROCESSED. If a session was interrupted, the administrator must manually click "Resume Processing". The worker safely restarts at the beginning of the last uncompleted batch (any duplicated domains in that specific batch are re-logged cleanly).

### Phase 2: Asynchronous Batch Processing (batch-processor.php)
* **Intentional Throttling:** Domains are pulled from plaintext files in small batches of 10 (parallel_batch_size). This low-concurrency design prevents server CPU overload and ensures traffic doesn't mimic malicious or "spammy" scanning tools.
* **WAF & Blacklist Mitigation:** Limits concurrent requests to avoid triggering security blocks from Web Application Firewalls (Cloudflare, BitNinja, etc.) when multiple target domains share an underlying infrastructure or CDN.
* **Priority Protocol Routing:** Resolves root domains sequentially, stopping at the first successful HTTP 200 response:
  1. https://www. (Secure Subdomain)
  2. https:// (Secure Root)
  3. http://www. (Standard Subdomain)
  4. http:// (Standard Root)
* **Asynchronous Network I/O:** Utilizes curl_multi for non-blocking parallel connections and cross-references robots.txt compliance.
* **User-Agent Header:** Publicly announces its presence via the literal string:
  Mozilla/5.0 (compatible; MecildiLanguageDetector/1.0; +https://www.obdilci.org/projects/mecildi/detector/)

### Phase 3: Heuristic Filtering (heuristics.php)
Before invoking language APIs, raw HTML passes through an infrastructure filter to eliminate "noise" and false positives. Externalized, configurable string dictionaries reside in the /data/ directory (under_construction_phrases.txt, hosting_provider_phrases.txt, domain_parking_phrases.txt):

* **Technical Errors:** Flags WAF block walls, placeholder registrar landing pages, and default server software templates (e.g., cPanel's index.of /).
* **Content Placeholders & Short-Content Trigger:** Scans for domain parking, monetization text, and "Coming Soon" notes. If an under-construction phrase is found and the visible text is under 250 characters, it catches a custom early exit (Error Code 22) and skips downstream NLP mapping.

### Phase 4: Linguistic Analysis (language-detection.php)
* **Text Normalization:** Strips functional layout markup `<script>, <style>, <nav>, <footer>` to expose pure editorial prose.
* **Volume Control:** Samples only the first 1,500 characters (defined by max_chars_to_detect in app.ini) to control text size and API payload costs.
* **Primacy of Hreflang:** Evaluates structural HTML metadata. In Version 1, hreflang link attributes are treated as the definitive signal for measuring global multilingual intent.
* **NLP & Conflict Verification:** Compares the page's structural html lang tag against a third-party Natural Language Processing (NLP) API evaluation (Tomedes). If the NLP results contradict the code declarations (e.g., text is French but the tag says English), the system logs a "Language Equals Error" to track developer misconfigurations.

### Phase 5: Aggregation & Reporting (excel-conversion.php)
* **Memory Optimization:** Converting raw JSON telemetry data into spreadsheets using PhpSpreadsheet requires intensive system resources. The module expands its execution limits directly up to 2GB of RAM and flushes output buffers via ob_end_clean() to stabilize shared environment capacities.
* **Performance Benchmark:** Data transformation maps at roughly 0.019 seconds per domain. Scaled across a complete 100,000-domain set, this creates a 32-minute processing tail.
* **Execution Telemetry:** Processing a standard 100,000 domain dataset (split into 100 files of 1,000 domains each) runs for roughly 21 hours and 21 minutes end-to-end.
* **Matrix Summary & Archiving:** Synthesizes metrics across all 100 internal segment sub-files to yield macro-level averages, global language metrics, and error margins. Once compiled, all documents are packaged into a single ZIP archive for streamlined dashboard download.

---

## Installation Instructions

Follow these steps to deploy an instance of the MECILDI environment:

### Step 1: Checkout Code from Git
Navigate to your target public web directory and pull down the engine:

```bash
cd /your/base_directory/

# Clone the repository into the current directory
git clone https://github.com/mikefieldenterprises/mecildi.git .

# Switch to the development branch
git checkout main
```

### Step 2: Install Composer Dependencies
Pull down PhpSpreadsheet and core framework libraries:

```bash
composer install
```

### Step 3: Create Instance Configuration Dot-Files
Copy the example deployment templates and set up your environment rules:

1. Configure Web Access Protection (.htaccess):

   ```bash
   cp htaccess.example .htaccess
   vi .htaccess
   # Edit file to specify the correct absolute server path to your .htpasswd file
   ```

2. Generate Secure Credentials (.htpasswd):

   ```bash
   cp htaccess.htpasswd .htpasswd
   vi .htpasswd
   # Use an online htpasswd generator to append your encrypted admin login details
   ```

3. Initialize local PHP adjustments (.user.ini):

   ```bash
   cp user.ini.example .user.ini
   vi .user.ini
   # Review settings (default presets are usually optimized for core environments)
   ```

### Step 4: Import Database Schema & Seed Data
Initialize your relational storage tables, state trackers, and language dictionaries.

#### Critical Preparation Step:
Open `database/schema.sql` in a text editor. Look at the top of the file for the commented reference:
```sql
--
-- Database: `local_mecildi`
--
```
If your target MySQL database is named something else (e.g., `my_company_mecildi`), ensure you select or create that database name in your management engine before importing, or replace any structural reference context inside the SQL file to match your environment variables.

You can import this schema file using one of the two following methods:

#### Method A: Using phpMyAdmin (Recommended for Shared Hosting)
Log into your hosting control panel and open **phpMyAdmin**.

Select your newly created target database from the left-hand sidebar menu.

Click on the **"Import"** tab along the top navigation navbar menu.

Click **"Choose File"**, navigate to your local directory clone, and select `database/schema.sql`.

Keep all format properties at their defaults (SQL format) and click the **"Import" / "Go"** button at the bottom of the viewport.

#### Method B: Using MySQL Command Line Interface (CLI)
If you have SSH terminal access to your host environment, execute the structural injection script using the following command string:

```bash
mysql -u <your_database_username> -p <your_database_name> < database/schema.sql
```

### Step 5: Configure the Application Runtime Engine
Set up the environment profiles, tracking databases, and third-party verification credentials:

```bash
cd admin
cp app.ini.example app.ini
vi app.ini
```

Ensure you update the following parameters inside app.ini using the credentials created during the database steps above:

`version`

Database configuration keys (all 4 values: hostname, dbname, username, password)

`auth_key_owner`

`tomedes_auth_key` (Your NLP language verification identifier token)


### Step 6: Validation & Testing
1. Direct your browser to the application web address.
2. Log in using the credentials defined in your .htpasswd file.
3. Upload a test .txt file containing at least two sample domains (formatted with exactly one domain per line).
4. Initiate the worker loop to ensure connectivity, heuristic routing, and generation metrics work smoothly.

---

## Licence

This project is licensed under the MIT Licence. See LICENSE for details.
