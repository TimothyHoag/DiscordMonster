<?php 

define('WEBHOOK_SALT', '15938268e524d0faed754156e9f5af001ac3aff9');
define('DISCORD_WEBHOOK_URL', 'https://ptb.discord.com/api/webhooks/810560489024913459/GaehTKYT7mrlH6DJtVpbiN1Tjp7ESAMeFhK0m_7D5kMQfzezls5DSfm9jKw5Luvia-7N');
define('ENABLE_DEBUG', false);
define('DEBUG_EMAIL', 'tjhoag@svsu.edu');

// Get the raw POST data
$postData = file_get_contents("php://input");

// Integrity check
if (!isset($_SERVER['HTTP_X_CASPER_WEBHOOK_INTEGRITY_HASH']) || $_SERVER['HTTP_X_CASPER_WEBHOOK_INTEGRITY_HASH'] !== sha1($postData) || !isset($_SERVER['HTTP_X_CASPER_WEBHOOK_VERIFY_HASH']))
{
    http_response_code(400);
    die('Corruption detected');
}

// Now we check if the request is actually intended for us

$hashCheck = sha1(sha1($postData).":".WEBHOOK_SALT);

if ($_SERVER['HTTP_X_CASPER_WEBHOOK_VERIFY_HASH'] !== $hashCheck)
{
    http_response_code(403);
    if (ENABLE_DEBUG) mail(DEBUG_EMAIL, 'Webhook Script Error', 'Unauthorised request');
    die('Unauthorised');

}

// By this stage we have a validated webhook event. Now decode the json

$data = json_decode($postData, true);

if ($data === false)
{
    // JSON decode failure. This should never happen since the payload is signed..
    if (ENABLE_DEBUG) mail(DEBUG_EMAIL, 'Webhook Script Error', 'JSON decode failure');
    die('Invalid payload');
}



echo "OK";
if (function_exists('fastcgi_finish_request'))
{
    // This releases the request so Casper's servers don't have to wait for discord
    fastcgi_finish_request();
}

$timestamp = date("c", strtotime("now"));

$embed = [];

$eventType = $data['metadata']['eventType'];
if ($eventType === 'vendor_sale')
{
    if ($data['event']['flags']['luckyChair'] === TRUE)
    {
        $embed = [
            "title" => $data['event']['avatars']['purchaser']['name'] . ' won a ' . $data['event']['product']['productName'] . ' from a Lucky Chair!',
            "color" => 15746887
        ];
    }
    else
    {
        if ($data['event']['flags']['midnightMadness'] === TRUE)
        {
            $embed = [
                "title" => $data['event']['avatars']['purchaser']['name'] . ' won a ' . $data['event']['product']['productName'] . ' from a Midnight Madness board!',
                "color" => 16426522
            ];
        }
        else
        {
            if ($data['event']['flags']['gatcha'] === TRUE)
            {
                $embed = [
                    "title" => $data['event']['avatars']['purchaser']['name'] . ' won a ' . $data['event']['product']['productName'] . ' from a gacha!',
                    "color" => 4437377
                ];
            }
            else
            {
                $embed = [
                    "title" => $data['event']['avatars']['purchaser']['name'] . ' bought a ' . $data['event']['product']['productName'],
                    "color" => 7506394
                ];
            }
        }
    }

    $embed['image'] = [
        "url" => "https://caspervend.casperdns.com/img.php?u=".$data['event']['product']['texture']."&g=SLIFE"
    ];

    $embed['description'] = '**Paid:** L$' . $data['event']['money']['gross']. "\n".
        '**Received:** L$' . $data['event']['money']['received']. "\n".
        '**Location:** ' . $data['event']['vendor']['location']. "\n";

    if ($data['event']['avatars']['recipient']['uuid'] !== $data['event']['avatars']['purchaser']['uuid'])
    {
        $embed['description'] = "**As a gift for " . $data['event']['avatars']['recipient']['name'] . "**\n" . $embed['description'];
    }

    if ($data['event']['flags']['giftCard'] === true || $data['event']['flags']['giftCardV3'] === true) {
        $embed['description'] .= "*This was a gift card purchase*\n";
    }
}
else if ($eventType === 'marketplace_sale')
{
    $embed = [
        "title" => $data['event']['PayerName'] . ' bought a ' . $data['event']['ItemName'] . ' from the Marketplace',
        "color" => 814798,
        "description" => "**Paid** L$" .$data['event']['PaymentGross']. "\n**Fee** L$" . $data['event']['PaymentFee']
    ];

    if ($data['event']['ReceiverKey'] !== $data['event']['PayerKey'])
    {
        $embed['description'] = "**As a gift for " . $data['event']['ReceiverNAme'] . "**\n" . $embed['description'];
    }
}
else
{
    $embed = [
        "title" => 'Received unsupported event of type ' . $eventType,
        "color" => 7506394
    ];
}


$json_data = json_encode([
    "username" => "Store",

    "embeds" => [
        $embed
    ]

], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );


$ch = curl_init( DISCORD_WEBHOOK_URL );
curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
curl_setopt( $ch, CURLOPT_POST, 1);
curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt( $ch, CURLOPT_HEADER, 0);
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

$response = curl_exec( $ch );

if (ENABLE_DEBUG)
{
    mail(DEBUG_EMAIL, 'Discord Webhook Response', $response);
}

curl_close( $ch );

?>
