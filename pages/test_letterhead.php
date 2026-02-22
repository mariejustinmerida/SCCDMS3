<?php
// Test page to verify letterhead
?>
<!DOCTYPE html>
<html>
<head>
    <title>Letterhead Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #006400;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Letterhead Test</h1>
        <p>This page tests the Saint Columban College letterhead.</p>
        
        <h2>Server-side Letterhead</h2>
        <div style="border: 1px solid #ccc; margin-bottom: 30px;">
            <?php
            // Include the letterhead from get_template.php
            require_once '../actions/get_template.php';
            echo $letterhead_header;
            ?>
            <div style="text-align: right;"><?php echo date("F d, Y"); ?></div>
            <div style="margin-top: 20px;">
                <p>The Department Head<br>
                School of Computing and Communications<br>
                Saint Columban College<br>
                Pagadian City</p>
            </div>
            <div style="margin-top: 20px;">
                <p><strong>Subject: Test Letter</strong></p>
            </div>
            <div style="margin-top: 20px;">
                <p>Dear Sir/Madam,</p>
                <p>This is a test letter to verify that the letterhead is working correctly.</p>
                <p>The letterhead should include the Saint Columban College logo at the top and the SCC ACTs logo at the bottom.</p>
                <p>Thank you for your attention to this matter.</p>
            </div>
            <div style="margin-top: 30px;">
                <p>Respectfully yours,</p>
                <p>Test User<br>
                Test Position<br>
                Test Department<br>
                test@example.com</p>
            </div>
            <?php echo $letterhead_footer; ?>
        </div>
        
        <h2>Client-side Letterhead</h2>
        <div id="clientSideLetterhead" style="border: 1px solid #ccc;"></div>
    </div>
    
    <script>
        // Test client-side letterhead
        const letterheadHeader = `
        <div style="font-family: Arial, sans-serif; line-height: 1.6;">
            <!-- Letterhead Header -->
            <div style="background: linear-gradient(to right, #006400, #008000); padding: 15px; position: relative; overflow: hidden; border-bottom: 5px solid #FFD700;">
                <div style="display: flex; align-items: center;">
                    <div style="width: 80px; margin-right: 10px;">
                        <img src="assets/images/scc-logo.php" alt="SCC Logo" style="width: 100%; height: auto; border-radius: 50%;">
                    </div>
                    <div style="flex-grow: 1;">
                        <h1 style="color: #FFD700; font-size: 28px; margin: 0; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">SAINT COLUMBAN COLLEGE</h1>
                        <div style="color: white; font-size: 11px; margin-top: 5px;">
                            <p style="margin: 0;">Corner V. Cañizares - Sagunt Streets, San Francisco District, Pagadian City</p>
                            <p style="margin: 0;">Tel Nos: 2151799 / 2151800 | Fax No: 2141200 | Website: www.sccpag.edu.ph | E-mail: saintcolumbanpagadian@sccpag.edu.ph</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Document Content -->
            <div style="min-height: 600px; padding: 30px; background-image: url('assets/images/scc-watermark.php'); background-repeat: no-repeat; background-position: center; background-size: 50% auto; background-opacity: 0.05;">
        `;
        
        const letterheadFooter = `
            </div>
            
            <!-- Letterhead Footer -->
            <div style="background: linear-gradient(to right, #006400, #008000); padding: 10px; border-top: 5px solid #FFD700; position: relative;">
                <div style="display: flex; justify-content: center; align-items: center;">
                    <div style="text-align: center;">
                        <img src="assets/images/scc-acts-logo.php" alt="SCC ACTs Logo" style="height: 50px; width: auto;">
                        <p style="color: white; font-size: 10px; margin: 5px 0 0 0;">Achieves Excellence | Cultivates a peaceful environment | Takes care of Mother Earth | Serves humanity</p>
                    </div>
                </div>
            </div>
        </div>
        `;
        
        // Get current date in format "Month Day, Year"
        function getCurrentDate() {
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const now = new Date();
            return `${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
        }
        
        // Create test letter content
        const letterContent = `
            <div style="text-align: right;">${getCurrentDate()}</div>
            <div style="margin-top: 20px;">
                <p>The Department Head<br>
                School of Computing and Communications<br>
                Saint Columban College<br>
                Pagadian City</p>
            </div>
            <div style="margin-top: 20px;">
                <p><strong>Subject: Test Letter (Client-side)</strong></p>
            </div>
            <div style="margin-top: 20px;">
                <p>Dear Sir/Madam,</p>
                <p>This is a test letter to verify that the client-side letterhead is working correctly.</p>
                <p>The letterhead should include the Saint Columban College logo at the top and the SCC ACTs logo at the bottom.</p>
                <p>Thank you for your attention to this matter.</p>
            </div>
            <div style="margin-top: 30px;">
                <p>Respectfully yours,</p>
                <p>Test User<br>
                Test Position<br>
                Test Department<br>
                test@example.com</p>
            </div>
        `;
        
        // Combine letterhead and content
        document.getElementById('clientSideLetterhead').innerHTML = letterheadHeader + letterContent + letterheadFooter;
    </script>
</body>
</html> 