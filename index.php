<?php
function getCsrfToken() {
    $csrfUrl = "https://www.blackbox.ai/api/auth/csrf";
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $csrfUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        die("Error fetching CSRF token: " . $err);
    }

    $data = json_decode($response, true);
    return $data['csrfToken'] ?? null;
}

// Get the CSRF token
$csrfToken = getCsrfToken();

if (!$csrfToken) {
    echo json_encode(["status" => false, "error" => "Failed to retrieve CSRF token."], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Check for the 'content' parameter
$content = isset($_GET['content']) ? trim($_GET['content']) : null;

if (empty($content)) {
    echo json_encode(["status" => false, "error" => "Missing 'content' parameter."], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Prepare the POST data
$postData = json_encode([
    "messages" => [
        [
            "id" => uniqid(),
            "content" => $content,
            "role" => "user"
        ]
    ],
    "id" => uniqid(),
    "previewToken" => null,
    "userId" => null,
    "codeModelMode" => true,
    "agentMode" => [
        "mode" => true,
        "id" => "ImageGenerationLV45LJp",
        "name" => "Image Generation"
    ],
    "trendingAgentMode" => new stdClass(),
    "isMicMode" => false,
    "maxTokens" => 1024,
]);

$contentLength = strlen($postData);

// Initialize cURL for the image generation request
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://www.blackbox.ai/api/chat",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => [
        "Host: www.blackbox.ai",
        "Content-Length: " . $contentLength,
        "Content-Type: application/json",
        "Cookie: __Host-authjs.csrf-token=" . $csrfToken,
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo json_encode(["status" => false, "error" => "cURL Error: " . $err], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Log the full response for debugging
// echo $response; // Uncomment this line for debugging if needed

// Improved regex pattern to avoid capturing unwanted characters
$imagePattern = '/https:\/\/storage\.googleapis\.com\/[^\s"\)]+/';
preg_match($imagePattern, $response, $matches);

$imageUrl = null; // Initialize imageUrl to null

if (!empty($matches)) {
    $imageUrl = $matches[0];

    // Fetch the image content
    $imageData = @file_get_contents($imageUrl); // Suppress warnings

    if ($imageData === false) {
        // Handle the HTTP response code for better debugging
        $http_response_header = isset($http_response_header) ? $http_response_header : [];
        $statusLine = explode(' ', $http_response_header[0]);
        $httpStatusCode = isset($statusLine[1]) ? $statusLine[1] : 'unknown';
        echo json_encode(["status" => false, "error" => "Failed to fetch the image from URL: $imageUrl with status code: $httpStatusCode"], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ImgBB API Key
    $apiKey = '3958ea418cb36ac6f3c201c8167f415e';

    // Upload to ImgBB
    $imgbbResponse = uploadToImgbb($apiKey, $imageData);

    // Decode the ImgBB response to check for success
    $imgbbResponseData = json_decode($imgbbResponse, true);
    if (isset($imgbbResponseData['data']['url'])) {
        $imageUrl = $imgbbResponseData['data']['url'];
    } else {
        echo json_encode(["status" => false, "error" => "Failed to upload to ImgBB."], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
} else {
    echo json_encode(["status" => false, "error" => "Image URL not found in the response."], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Prepare the final response
header('Content-Type: application/json');
echo json_encode([
    "status" => true,
    "image_url" => $imageUrl,
    "api_owner" => "@pikaapis"
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

function uploadToImgbb($apiKey, $imageData) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.imgbb.com/1/upload?key=" . $apiKey,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'image' => base64_encode($imageData)
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return json_encode(["error" => "Failed to upload to ImgBB: " . $err]);
    }

    return $response;
}
?>