<?php
$filename = 'codelines.php';
$lines = file($filename);

// Display the lines
foreach ($lines as $line_num => $line) {
    echo "Line " . ($line_num + 1) . ": " . htmlspecialchars($line);
}

// Or just get the array
print_r($lines);
?>