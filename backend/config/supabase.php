<?php
function supabaseRequest($method, $table, $data = null) {
    $SUPABASE_URL = "https://gkbxzlafgvooezhckyve.supabase.co"; 
    $SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImdrYnh6bGFmZ3Zvb2V6aGNreXZlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjI2MjU5MzAsImV4cCI6MjA3ODIwMTkzMH0.qEaycW2PBA6hmlJQA2gfRLNqCvu9NW-tE6A-OaYcKeE"; 

    $url = "$SUPABASE_URL/rest/v1/$table";
    $ch = curl_init($url);

    $headers = [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            break;
        case 'PATCH':
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            break;
    }

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'cURL error: ' . curl_error($ch);
    }
    curl_close($ch);
    return json_decode($response, true);
}
?>
