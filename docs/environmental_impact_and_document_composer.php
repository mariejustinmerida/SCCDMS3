<?php
header("Content-Type: application/msword; charset=UTF-8");
header('Content-Disposition: attachment; filename="Environmental_Impact_and_Document_Composer.doc"');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

echo "<!DOCTYPE html>";
echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
echo "<style>body{font-family:Calibri,Arial,sans-serif;line-height:1.4;color:#111;} h1,h2,h3{color:#0b4f2f;} code,pre{font-family:Consolas, 'Courier New', monospace;background:#f7f7f7;border:1px solid #ddd;border-radius:6px;padding:10px;display:block;white-space:pre-wrap;word-wrap:break-word;} .small{color:#555;font-size:12px;} ul{margin-top:6px;margin-bottom:6px;} .pill{display:inline-block;padding:2px 8px;border-radius:999px;background:#e8f5e9;color:#0b4f2f;border:1px solid #bde0c2;font-size:12px;font-weight:bold;} table{border-collapse:collapse;width:100%;} td,th{border:1px solid #ddd;padding:8px;} .section{margin-bottom:28px;} .muted{color:#666;} .kbd{font-family:Consolas, 'Courier New', monospace;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:4px;padding:1px 5px;}</style>";
echo "<title>Environmental Impact & Document Composer</title></head><body>";

echo "<h1>Environmental Impact & Document Composer</h1>";
echo "<p class=\"muted\">This document explains the Environmental Impact calculations (with formulas) and the Document Composition process (with source code and a clear walkthrough). You can adjust factors to suit local data.</p>";

echo "<div class=\"section\">";
echo "<h2>1) Environmental Impact</h2>";
echo "<p><span class=\"pill\">What it does</span> Estimates paper saved, water saved, CO<sub>2</sub> avoided, and trees saved resulting from digitized documents.</p>";
echo "<h3>Assumptions</h3>";
echo "<ul>";
echo "<li>Average pages per document: <strong>5</strong></li>";
echo "<li>Paper mass per page: <strong>5 g/page (0.005 kg/page)</strong></li>";
echo "<li>Water intensity: <strong>10 L/kg paper</strong></li>";
echo "<li>CO<sub>2</sub> intensity: <strong>3 kg CO<sub>2</sub>/kg paper</strong></li>";
echo "<li>Trees per ton of paper: <strong>17 trees/ton</strong></li>";
echo "</ul>";

echo "<h3>Formulas</h3>";
echo "<table><tbody>";
echo "<tr><th>Metric</th><th>Formula</th></tr>";
echo "<tr><td>Pages saved</td><td><em>pagesSaved</em> = <em>totalDocuments</em> × <em>avgPagesPerDoc</em></td></tr>";
echo "<tr><td>Paper saved (kg)</td><td><em>paperKg</em> = <em>pagesSaved</em> × 0.005</td></tr>";
echo "<tr><td>Water saved (L)</td><td><em>waterL</em> = <em>paperKg</em> × 10</td></tr>";
echo "<tr><td>CO<sub>2</sub> avoided (kg)</td><td><em>co2Kg</em> = <em>paperKg</em> × 3</td></tr>";
echo "<tr><td>Trees saved</td><td><em>trees</em> = (<em>paperKg</em> / 1000) × 17</td></tr>";
echo "</tbody></table>";

echo "<h3>Reference PHP</h3>";
echo "<pre><code>class EnvironmentalImpactCalculator\n{\n\tprivate float \$avgPagesPerDoc;\n\tprivate float \$gramsPerPage;\n\tprivate float \$waterPerKgPaper;\n\tprivate float \$co2PerKgPaper;\n\tprivate float \$treesPerTonPaper;\n\n\tpublic function __construct(\n\t\tfloat \$avgPagesPerDoc = 5.0,\n\t\tfloat \$gramsPerPage = 5.0,\n\t\tfloat \$waterPerKgPaper = 10.0,\n\t\tfloat \$co2PerKgPaper = 3.0,\n\t\tfloat \$treesPerTonPaper = 17.0\n\t) {\n\t\t\$this-&gt;avgPagesPerDoc = \$avgPagesPerDoc;\n\t\t\$this-&gt;gramsPerPage = \$gramsPerPage;\n\t\t\$this-&gt;waterPerKgPaper = \$waterPerKgPaper;\n\t\t\$this-&gt;co2PerKgPaper = \$co2PerKgPaper;\n\t\t\$this-&gt;treesPerTonPaper = \$treesPerTonPaper;\n\t}\n\n\tpublic function compute(int \$totalDocuments): array\n\t{\n\t\t\$pagesSaved = (int) round(\$totalDocuments * \$this-&gt;avgPagesPerDoc);\n\t\t\$paperSavedKg = (\$pagesSaved * \$this-&gt;gramsPerPage) / 1000.0;\n\t\t\$waterSavedLiters = \$paperSavedKg * \$this-&gt;waterPerKgPaper;\n\t\t\$co2AvoidedKg = \$paperSavedKg * \$this-&gt;co2PerKgPaper;\n\t\t\$treesSaved = (\$paperSavedKg / 1000.0) * \$this-&gt;treesPerTonPaper;\n\n\t\treturn [\n\t\t\t'pages_saved' =&gt; \$pagesSaved,\n\t\t\t'paper_saved_kg' =&gt; round(\$paperSavedKg, 3),\n\t\t\t'water_saved_liters' =&gt; round(\$waterSavedLiters, 2),\n\t\t\t'co2_avoided_kg' =&gt; round(\$co2AvoidedKg, 2),\n\t\t\t'trees_saved' =&gt; round(\$treesSaved, 3),\n\t\t];\n\t}\n}\n</code></pre>";
echo "<p class=\"small\"><strong>Tip:</strong> Tune the constructor values to match local/usual factors if you have better data.</p>";
echo "</div>";

echo "<div class=\"section\">";
echo "<h2>2) Document Composer (Create Document Module)</h2>";
echo "<p><span class=\"pill\">What it does</span> Renders a document from a template with placeholders and optional/repeating sections.</p>";
echo "<h3>Placeholders</h3>";
echo "<ul>";
echo "<li><strong>{{var}}</strong>: simple variable (HTML-escaped)</li>";
echo "<li><strong>{{{var}}}</strong>: unescaped/trusted HTML</li>";
echo "<li><strong>{{?section}}...{{/section}}</strong>: optional block (renders if data['section'])</li>";
echo "<li><strong>{{#list}}...{{/list}}</strong>: repeat block for array items</li>";
echo "</ul>";

echo "<h3>Reference PHP</h3>";
echo "<pre><code>class DocumentComposer\n{\n\tpublic function render(string \$template, array \$data): string\n\t{\n\t\t\$template = \$this-&gt;renderRepeatingSections(\$template, \$data);\n\t\t\$template = \$this-&gt;renderOptionalSections(\$template, \$data);\n\t\t\$template = preg_replace_callback('/\{\{\{([\\w\\.]+)\}\}\}/', function(\$m) use (\$data) {\n\t\t\treturn \$this-&gt;resolvePath(\$data, \$m[1], '');\n\t\t}, \$template);\n\t\t\$template = preg_replace_callback('/\{\{([\\w\\.]+)\}\}/', function(\$m) use (\$data) {\n\t\t\t\$value = \$this-&gt;resolvePath(\$data, \$m[1], '');\n\t\t\treturn htmlspecialchars((string)\$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');\n\t\t}, \$template);\n\t\treturn \$template;\n\t}\n\n\tprivate function renderOptionalSections(string \$template, array \$data): string\n\t{\n\t\treturn preg_replace_callback('/\{\{\?(\\w+)\}\}([\\s\\S]*?)\{\{\/\\1\}\}/', function (\$m) use (\$data) {\n\t\t\t\$key = \$m[1];\n\t\t\t\$inner = \$m[2];\n\t\t\tif (!empty(\$data[\$key])) {\n\t\t\t\treturn \$this-&gt;render(\$inner, \$data);\n\t\t\t}\n\t\t\treturn '';\n\t\t}, \$template);\n\t}\n\n\tprivate function renderRepeatingSections(string \$template, array \$data): string\n\t{\n\t\treturn preg_replace_callback('/\{\{#(\\w+)\}\}([\\s\\S]*?)\{\{\/\\1\}\}/', function (\$m) use (\$data) {\n\t\t\t\$listKey = \$m[1];\n\t\t\t\$block = \$m[2];\n\t\t\t\$out = '';\n\t\t\t\$items = \$data[\$listKey] ?? [];\n\t\t\tif (!is_array(\$items)) { return ''; }\n\t\t\tforeach (\$items as \$item) {\n\t\t\t\t\$out .= \$this-&gt;render(\$block, is_array(\$item) ? \$item : ['value' =&gt; \$item]);\n\t\t\t}\n\t\t\treturn \$out;\n\t\t}, \$template);\n\t}\n\n\tprivate function resolvePath(array \$data, string \$path, \$default = null)\n\t{\n\t\t\$parts = explode('.', \$path);\n\t\t\$cursor = \$data;\n\t\tforeach (\$parts as \$p) {\n\t\t\tif (is_array(\$cursor) &amp;&amp; array_key_exists(\$p, \$cursor)) {\n\t\t\t\t\$cursor = \$cursor[\$p];\n\t\t\t} else {\n\t\t\t\treturn \$default;\n\t\t\t}\n\t\t}\n\t\treturn \$cursor;\n\t}\n}\n</code></pre>";

echo "<h3>Example Template and Data</h3>";
echo "<pre><code>&lt;article&gt;\n  &lt;h1&gt;{{title}}&lt;/h1&gt;\n  &lt;p&gt;By {{author.name}} — {{date}}&lt;/p&gt;\n\n  {{?summary}}\n    &lt;section&gt;&lt;strong&gt;Summary:&lt;/strong&gt; {{summary}}&lt;/section&gt;\n  {{/summary}}\n\n  &lt;section&gt;{{{body_html}}}&lt;/section&gt;\n\n  &lt;h3&gt;Attachments&lt;/h3&gt;\n  &lt;ul&gt;\n    {{#attachments}}&lt;li&gt;{{name}} ({{size_kb}} KB)&lt;/li&gt;{{/attachments}}\n  &lt;/ul&gt;\n\n  {{?footer}}&lt;footer&gt;{{footer}}&lt;/footer&gt;{{/footer}}\n&lt;/article&gt;\n</code></pre>";

echo "<p class=\"small\"><strong>How it works:</strong> Variables replace tags, lists repeat blocks, and optional sections appear only when data is present. Use triple braces for trusted HTML.</p>";
echo "</div>";

echo "<div class=\"section\">";
echo "<h2>3) How to use in this project</h2>";
echo "<ul>";
echo "<li>Add classes under <code>includes/EnvironmentalImpactCalculator.php</code> and <code>includes/DocumentComposer.php</code> (or inline in your module).</li>";
echo "<li>Compute yearly metrics using your documents table and the date range: <code>created_at BETWEEN 'YYYY-01-01' AND 'YYYY-12-31'</code>.</li>";
echo "<li>Render composed documents to HTML and save to <code>uploads/</code>, or convert to PDF using your preferred tool.</li>";
echo "</ul>";
echo "</div>";

echo "<hr><p class=\"small\">Generated by SCCDMS2 – You can re-download this file any time.</p>";
echo "</body></html>";
?>

