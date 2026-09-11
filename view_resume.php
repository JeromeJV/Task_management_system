<?php
$file = $_GET['file'] ?? '';
$filepath = 'uploads/' . basename($file);

if (!empty($file) && file_exists($filepath)): 
?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>View Resume</title>
    </head>
    <body style="text-align: center; background: #222; padding: 20px;">
        <!-- Manual Back / Close Button -->
        <div style="margin-bottom: 15px;">
            <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">
                Back
            </button>
        </div>
        
        <!-- Display Image -->
        <img src="<?= htmlspecialchars($filepath); ?>" style="max-width: 30%; height: auto; border: 2px solid white;">
    </body> 
    </html>
<?php 
else: 
    echo "File not found.";
endif; 
?>