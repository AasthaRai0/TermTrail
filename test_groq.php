<?php
// .env file parse karein (true ka matlab associative array banega)
$env = parse_ini_file(__DIR__ . '/.env');

// API Key access karein
$apiKey = $env['GROQ_API_KEY'] ?? null;

if (!$apiKey) {
    die("Error: GROQ_API_KEY is missing from .env");
}

$url = "https://api.groq.com/openai/v1/chat/completions";

$data = [
    "model" => "llama-3.3-70b-versatile",
    "messages" => [
        ["role" => "user", "content" => "Hello, say hi if you are working!"]
    ],
    "temperature" => 0.1
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: " . $httpCode . "\n<br>";
echo "Response: " . $response;
?>