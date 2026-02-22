<?php
// Include necessary files
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/header.php';
require_once '../includes/settings_helper.php';

// Remove admin check - allow all authenticated users to access this page
// if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
//     // Redirect to dashboard if not admin
//     header("Location: dashboard.php");
//     exit;
// }

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_api_key'])) {
        $openai_key = isset($_POST['openai_api_key']) ? trim($_POST['openai_api_key']) : '';
        $gemini_key = isset($_POST['gemini_api_key']) ? trim($_POST['gemini_api_key']) : '';
        
        // Check if the settings table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'settings'");
        if ($table_check->num_rows == 0) {
            // Create settings table if it doesn't exist
            $create_table_sql = "CREATE TABLE settings (
                setting_id INT AUTO_INCREMENT PRIMARY KEY,
                setting_name VARCHAR(255) NOT NULL UNIQUE,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $conn->query($create_table_sql);
        }
        
        // Upsert helper
        $saveSetting = function($name, $value) use ($conn) {
            $check = $conn->prepare("SELECT setting_id FROM settings WHERE setting_name = ?");
            $check->bind_param("s", $name);
            $check->execute();
            $res = $check->get_result();
            if ($res->num_rows > 0) {
                $upd = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_name = ?");
                $upd->bind_param("ss", $value, $name);
                return $upd->execute();
            } else {
                $ins = $conn->prepare("INSERT INTO settings (setting_name, setting_value) VALUES (?, ?)");
                $ins->bind_param("ss", $name, $value);
                return $ins->execute();
            }
        };

        $ok = true;
        if ($openai_key !== '') { $ok = $ok && $saveSetting('openai_api_key', $openai_key); }
        if ($gemini_key !== '') { $ok = $ok && $saveSetting('gemini_api_key', $gemini_key); }

        if ($ok) {
            $message = "API keys saved successfully!";
            $message_type = "success";
        } else {
            $message = "Error saving API keys.";
            $message_type = "error";
        }
    } elseif (isset($_POST['save_ai_settings'])) {
        // Save other AI settings
        $model = trim($_POST['ai_model']);
        $max_tokens = (int)$_POST['max_tokens'];
        $temperature = (float)$_POST['temperature'];
        
        // Check if settings exist
        $check_model = $conn->query("SELECT * FROM settings WHERE setting_name = 'ai_model'");
        $check_tokens = $conn->query("SELECT * FROM settings WHERE setting_name = 'ai_max_tokens'");
        $check_temp = $conn->query("SELECT * FROM settings WHERE setting_name = 'ai_temperature'");
        
        // Update or insert model setting
        if ($check_model->num_rows > 0) {
            $conn->query("UPDATE settings SET setting_value = '$model' WHERE setting_name = 'ai_model'");
        } else {
            $conn->query("INSERT INTO settings (setting_name, setting_value) VALUES ('ai_model', '$model')");
        }
        
        // Update or insert max_tokens setting
        if ($check_tokens->num_rows > 0) {
            $conn->query("UPDATE settings SET setting_value = '$max_tokens' WHERE setting_name = 'ai_max_tokens'");
        } else {
            $conn->query("INSERT INTO settings (setting_name, setting_value) VALUES ('ai_max_tokens', '$max_tokens')");
        }
        
        // Update or insert temperature setting
        if ($check_temp->num_rows > 0) {
            $conn->query("UPDATE settings SET setting_value = '$temperature' WHERE setting_name = 'ai_temperature'");
        } else {
            $conn->query("INSERT INTO settings (setting_name, setting_value) VALUES ('ai_temperature', '$temperature')");
        }
        
        $message = "AI settings saved successfully!";
        $message_type = "success";
    }
}

// Get current settings
$api_key = '';
$gemini_api_key = '';
$model = 'gpt-3.5-turbo';
$max_tokens = 800;
$temperature = 0.5;

// Check if settings table exists
$table_check = $conn->query("SHOW TABLES LIKE 'settings'");
if ($table_check->num_rows > 0) {
    // Get API keys
    $api_key_result = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'openai_api_key'");
    if ($api_key_result && $api_key_result->num_rows > 0) {
        $row = $api_key_result->fetch_assoc();
        $api_key = $row['setting_value'];
    }
    $gemini_result = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'gemini_api_key'");
    if ($gemini_result && $gemini_result->num_rows > 0) {
        $row = $gemini_result->fetch_assoc();
        $gemini_api_key = $row['setting_value'];
    }
    
    // Get model
    $model_result = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'ai_model'");
    if ($model_result && $model_result->num_rows > 0) {
        $row = $model_result->fetch_assoc();
        $model = $row['setting_value'];
    }
    
    // Get max tokens
    $tokens_result = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'ai_max_tokens'");
    if ($tokens_result && $tokens_result->num_rows > 0) {
        $row = $tokens_result->fetch_assoc();
        $max_tokens = (int)$row['setting_value'];
    }
    
    // Get temperature
    $temp_result = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'ai_temperature'");
    if ($temp_result && $temp_result->num_rows > 0) {
        $row = $temp_result->fetch_assoc();
        $temperature = (float)$row['setting_value'];
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Settings</h1>
        <a href="dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded">
            Back to Dashboard
        </a>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <div class="border-b mb-4">
            <nav class="flex space-x-4 text-sm" id="settingsTabs">
                <button data-tab="ai" class="px-3 py-2 border-b-2 border-blue-600 text-blue-700">AI</button>
                <button data-tab="security" class="px-3 py-2">Security</button>
                <button data-tab="gdocs" class="px-3 py-2">Google Docs</button>
                <button data-tab="docs" class="px-3 py-2">Documents</button>
                <button data-tab="search" class="px-3 py-2">Search & UX</button>
                <button data-tab="branding" class="px-3 py-2">Branding</button>
                <button data-tab="logging" class="px-3 py-2">Logging</button>
            </nav>
        </div>

        <div id="tab-ai" class="tab space-y-4">
            <div>
                <label class="block text-sm font-medium">Gemini API Key</label>
                <input type="password" id="gemini_api_key" class="mt-1 w-full border rounded p-2" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium">Model</label>
                    <div class="mt-1 flex items-center gap-2">
                        <select id="ai_model" class="w-full border rounded p-2">
                            <option value="gemini-2.5-flash">gemini-2.5-flash</option>
                            <option value="gemini-2.5-pro">gemini-2.5-pro</option>
                            <option value="gemini-2.0-flash">gemini-2.0-flash</option>
                            <option value="gemini-2.0-flash-001">gemini-2.0-flash-001</option>
                            <option value="gemini-2.0-flash-lite-001">gemini-2.0-flash-lite-001</option>
                            <option value="gemini-2.5-flash-lite">gemini-2.5-flash-lite</option>
                        </select>
                        <button type="button" id="fetch_models" class="px-2 py-2 text-sm bg-gray-100 border rounded hover:bg-gray-200">Fetch from Google</button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Tip: Use <span class="font-semibold">gemini-2.5-flash</span> for best balance of cost and quality.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium">Temperature</label>
                    <input type="number" min="0" max="1" step="0.1" id="ai_temperature" class="mt-1 w-full border rounded p-2" />
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="inline-flex items-center space-x-2"><input type="checkbox" id="ai_enable_summarize"><span>Enable Summarize</span></label>
                <label class="inline-flex items-center space-x-2"><input type="checkbox" id="ai_enable_analyze"><span>Enable Analyze</span></label>
                <label class="inline-flex items-center space-x-2"><input type="checkbox" id="ai_enable_generator"><span>Enable Generator</span></label>
            </div>
        </div>

        <div id="tab-security" class="tab hidden space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium">Session timeout (minutes)</label>
                    <input type="number" id="session_timeout_minutes" class="mt-1 w-full border rounded p-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium">Anomaly attempts threshold</label>
                    <input type="number" id="anomaly_attempts_threshold" class="mt-1 w-full border rounded p-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium">Anomaly window (minutes)</label>
                    <input type="number" id="anomaly_window_minutes" class="mt-1 w-full border rounded p-2" />
                </div>
            </div>
            <label class="inline-flex items-center space-x-2"><input type="checkbox" id="force_https"><span>Force HTTPS</span></label>
        </div>

        <div id="tab-gdocs" class="tab hidden space-y-4">
            <div>
                <label class="block text-sm font-medium">Default Google Drive Folder ID</label>
                <input type="text" id="gdocs_default_folder" class="mt-1 w-full border rounded p-2" />
            </div>
            <div>
                <label class="block text-sm font-medium">Sharing Default</label>
                <select id="gdocs_sharing_default" class="mt-1 w-full border rounded p-2">
                    <option value="restricted">Restricted</option>
                    <option value="domain">Domain</option>
                </select>
            </div>
        </div>

        <div id="tab-docs" class="tab hidden space-y-4">
            <div>
                <label class="block text-sm font-medium">Numbering format</label>
                <input type="text" id="numbering_format" placeholder="e.g., MEMO-{YYYY}-{0001}" class="mt-1 w-full border rounded p-2" />
            </div>
            <div>
                <label class="block text-sm font-medium">Default routing template</label>
                <input type="text" id="default_routing_template" class="mt-1 w-full border rounded p-2" />
            </div>
            <div>
                <label class="block text-sm font-medium">Required fields by type (JSON)</label>
                <textarea id="required_fields_by_type" class="mt-1 w-full border rounded p-2" rows="3" placeholder='{"memo":["to","from","subject"]}'></textarea>
            </div>
        </div>

        <div id="tab-search" class="tab hidden space-y-4">
            <label class="inline-flex items-center space-x-2"><input type="checkbox" id="live_search_enabled"><span>Enable live search</span></label>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium">Debounce (ms)</label>
                    <input type="number" id="search_debounce_ms" class="mt-1 w-full border rounded p-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium">Rows per page</label>
                    <select id="rows_per_page" class="mt-1 w-full border rounded p-2">
                        <option>10</option>
                        <option>25</option>
                        <option>50</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="tab-branding" class="tab hidden space-y-4">
            <div>
                <label class="block text-sm font-medium">Institution name</label>
                <input type="text" id="institution_name" class="mt-1 w-full border rounded p-2" />
            </div>
            <div>
                <label class="block text-sm font-medium">Short name</label>
                <input type="text" id="institution_short_name" class="mt-1 w-full border rounded p-2" />
            </div>
            <div>
                <label class="block text-sm font-medium">Accent color</label>
                <select id="accent_color" class="mt-1 w-full border rounded p-2">
                    <option value="blue">Blue</option>
                    <option value="green">Green</option>
                    <option value="indigo">Indigo</option>
                    <option value="emerald">Emerald</option>
                </select>
            </div>
        </div>

        <div id="tab-logging" class="tab hidden space-y-4">
            <div>
                <label class="block text-sm font-medium">Log level</label>
                <select id="log_level" class="mt-1 w-full border rounded p-2">
                    <option value="error">Error</option>
                    <option value="warn">Warn</option>
                    <option value="info">Info</option>
                </select>
            </div>
            <button id="clear_logs" class="px-3 py-2 bg-red-600 text-white rounded">Clear logs</button>
        </div>

        <div class="mt-6 flex justify-end space-x-2">
            <button id="save_settings" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
        </div>
    </div>

    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-800 mb-2">About AI Document Features</h3>
        <p class="text-blue-700 mb-4">This system uses Gemini for document analysis, summarization, and generation.</p>
        <ul class="list-disc pl-5 text-blue-700 space-y-2">
            <li>Document Summarization: Generate concise summaries of your documents</li>
            <li>Content Analysis: Extract key entities, topics, and sentiment from documents</li>
            <li>Semantic Search: Find documents based on their meaning, not just keywords</li>
        </ul>
    </div>
</div>

<script>
    // Tabs
    const tabs = document.querySelectorAll('#settingsTabs button');
    const sections = document.querySelectorAll('.tab');
    tabs.forEach(btn => btn.addEventListener('click', () => {
      tabs.forEach(b=>{b.classList.remove('border-b-2','border-blue-600','text-blue-700')});
      sections.forEach(s=>s.classList.add('hidden'));
      btn.classList.add('border-b-2','border-blue-600','text-blue-700');
      document.getElementById('tab-' + btn.dataset.tab).classList.remove('hidden');
    }));

    function setVal(id, val){ const el = document.getElementById(id); if (!el) return; if (el.type === 'checkbox') el.checked = ['1','true','yes','on'].includes(String(val).toLowerCase()); else el.value = val ?? ''; }
    function getVal(id){ const el = document.getElementById(id); if (!el) return ''; if (el.type === 'checkbox') return el.checked ? '1':'0'; return el.value; }

    // Load existing settings
    fetch('../api/settings.php')
      .then(r=>r.json())
      .then(data=>{
        if (!data.success) return;
        const s = data.settings || {};
        Object.keys(s).forEach(k=>{ setVal(k, s[k]); });
        // Reconcile: if ai_model missing but gemini_model present, use it
        if (!s.ai_model && s.gemini_model) { setVal('ai_model', s.gemini_model); }
      });

    // Save
    document.getElementById('save_settings').addEventListener('click', () => {
      const payload = {};
      ['gemini_api_key','ai_model','ai_temperature','ai_enable_summarize','ai_enable_analyze','ai_enable_generator','session_timeout_minutes','anomaly_attempts_threshold','anomaly_window_minutes','force_https','gdocs_default_folder','gdocs_sharing_default','numbering_format','default_routing_template','required_fields_by_type','live_search_enabled','search_debounce_ms','rows_per_page','institution_name','institution_short_name','accent_color','log_level']
        .forEach(id => payload[id] = getVal(id));
      // Also save gemini_model in parallel so backend readers can use it
      payload['gemini_model'] = payload['ai_model'];
      fetch('../api/settings.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
        .then(r=>r.json())
        .then(()=> alert('Settings saved'));
    });

    // Fetch models from Google using the provided API key and populate the select
    const fetchBtn = document.getElementById('fetch_models');
    if (fetchBtn) {
      fetchBtn.addEventListener('click', async () => {
        const key = document.getElementById('gemini_api_key')?.value?.trim();
        if (!key) { alert('Enter your Gemini API key first.'); return; }
        fetchBtn.disabled = true; fetchBtn.textContent = 'Fetching...';
        try {
          const res = await fetch('https://generativelanguage.googleapis.com/v1/models?key=' + encodeURIComponent(key));
          const json = await res.json();
          if (!json.models) { throw new Error(json.error?.message || 'No models found'); }
          const models = json.models.filter(m => (m.supportedGenerationMethods||[]).includes('generateContent')).map(m => m.name.replace('models/',''));
          if (models.length === 0) { throw new Error('No text generation models available for this key'); }
          const select = document.getElementById('ai_model');
          // Clear existing options
          while (select.firstChild) select.removeChild(select.firstChild);
          // Add new options
          models.forEach(name => {
            const opt = document.createElement('option');
            opt.value = name; opt.textContent = name; select.appendChild(opt);
          });
          // Prefer gemini-2.5-flash if present
          const preferred = models.find(m => m === 'gemini-2.5-flash') || models[0];
          select.value = preferred;
          alert('Models updated from Google. Selected: ' + preferred);
        } catch (e) {
          alert('Failed to fetch models: ' + e.message);
        } finally {
          fetchBtn.disabled = false; fetchBtn.textContent = 'Fetch from Google';
        }
      });
    }

    // Clear logs
    const clr = document.getElementById('clear_logs');
    if (clr) {
      clr.addEventListener('click', () => {
        if (!confirm('Clear all logs?')) return;
        fetch('../api/settings.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'clear_logs' }) })
          .then(r=>r.json())
          .then(()=> alert('Logs cleared'));
      });
    }
</script>

<?php require_once '../includes/footer.php'; ?> 