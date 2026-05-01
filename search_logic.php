<?php
$skills = [
    "Photography" => "Commercial, Airbnb, and Portraiture.",
    "Web Design" => "Full-stack PHP, Bootstrap, and HTML5.",
    "Signage" => "Manufacturing, LED, and Vinyl Installations.",
    "IT Work" => "Network infrastructure and hardware repair.",
    "Handyman" => "Structural repairs and maintenance."
];

$query = strtolower($_GET['query'] ?? '');
$results = [];

if ($query !== '') {
    foreach ($skills as $name => $desc) {
        if (strpos(strtolower($name), $query) !== false) {
            $results[] = "<strong>$name:</strong> $desc";
        }
    }
}

if (empty($results)) {
    echo "No matching skills found.";
} else {
    echo implode("<br>", $results);
}
?>