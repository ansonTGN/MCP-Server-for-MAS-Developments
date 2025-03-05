<?php
/**
 * MCPKeygenClient.php
 *
 * A PHP script that acts as a Keygen Client. It connects to a server via TCP,
 * sends a keygen request, and receives the server's response.
 *
 * Usage:
 * php MCPKeygenClient.php --server-ip <IP> --server-port <Port> --token <Token> --password <Password>
 */

/**
 * Function to parse command line arguments
 *
 * @param array $args The command line arguments
 * @return array An associative array with the parsed arguments
 */
function parseArguments($args) {
    $parsedArgs = [];
    $argc = count($args);
    for ($i = 1; $i < $argc; $i++) {
        switch ($args[$i]) {
            case '--server-ip':
                if (isset($args[$i + 1]) && !startsWith($args[$i + 1], '--')) {
                    $parsedArgs['serverIp'] = $args[++$i];
                } else {
                    fwrite(STDERR, "⚠️ Warning: No value provided for --server-ip.\n");
                }
                break;
            case '--server-port':
                if (isset($args[$i + 1]) && !startsWith($args[$i + 1], '--')) {
                    $parsedArgs['serverPort'] = intval($args[++$i]);
                } else {
                    fwrite(STDERR, "⚠️ Warning: No value provided for --server-port.\n");
                }
                break;
            case '--token':
                if (isset($args[$i + 1]) && !startsWith($args[$i + 1], '--')) {
                    $parsedArgs['token'] = $args[++$i];
                } else {
                    fwrite(STDERR, "⚠️ Warning: No value provided for --token.\n");
                }
                break;
            case '--password':
                if (isset($args[$i + 1]) && !startsWith($args[$i + 1], '--')) {
                    $parsedArgs['password'] = $args[++$i];
                } else {
                    fwrite(STDERR, "⚠️ Warning: No value provided for --password.\n");
                }
                break;
            default:
                fwrite(STDERR, "⚠️ Warning: Unknown argument: {$args[$i]}\n");
        }
    }
    return $parsedArgs;
}

/**
 * Helper function to check if a string starts with a specific prefix
 *
 * @param string $string The string to check
 * @param string $prefix The prefix
 * @return bool True if the string starts with the prefix, otherwise False
 */
function startsWith($string, $prefix) {
    return substr($string, 0, strlen($prefix)) === $prefix;
}

/**
 * Function to send a Keygen request over a TCP connection
 *
 * @param string $serverIp The server's IP address
 * @param int $serverPort The server's port
 * @param string $token The authentication token
 * @param string $password The password for key generation
 * @return array The response received from the server as an associative array
 * @throws Exception On connection errors or JSON parsing errors
 */
function sendKeygenRequest($serverIp, $serverPort, $token, $password) {
    $payload = [
        "command" => "keygen",
        "token" => $token,
        "arguments" => [
            "password" => $password
        ]
    ];

    $jsonPayload = json_encode($payload);
    if ($jsonPayload === false) {
        throw new Exception("Error encoding JSON payload: " . json_last_error_msg());
    }

    $errno = 0;
    $errstr = '';
    $timeoutDuration = 10; // Seconds (10 seconds timeout)
    $client = @fsockopen($serverIp, $serverPort, $errno, $errstr, $timeoutDuration);

    if (!$client) {
        throw new Exception("Connection error: $errstr ($errno)");
    }

    echo "🔗 Connected to server ({$serverIp}:{$serverPort}).\n";
    echo "📤 Sending Payload: {$jsonPayload}\n";

    fwrite($client, $jsonPayload);

    $responseData = '';
    stream_set_timeout($client, $timeoutDuration);

    while (!feof($client)) {
        $data = fread($client, 1024);
        if ($data === false) {
            throw new Exception("Error reading data from server.");
        }
        if ($data === '') {
            break; // No more data
        }
        echo "📥 Received data: {$data}\n";
        $responseData .= $data;

        // Attempt to parse the received data as JSON
        $parsedData = json_decode($responseData, true);
        if ($parsedData !== null) {
            echo "✅ JSON response successfully parsed.\n";
            fclose($client);
            return $parsedData;
        }

        // Check if the stream has timed out
        $info = stream_get_meta_data($client);
        if ($info['timed_out']) {
            throw new Exception("Timeout while waiting for data from server.");
        }
    }

    fclose($client);
    throw new Exception("Connection to server was closed before a complete response was received.");
}

/**
 * Main function of the script
 */
function main($argv) {
    $parsedArgs = parseArguments($argv);
    $serverIp = $parsedArgs['serverIp'] ?? null;
    $serverPort = $parsedArgs['serverPort'] ?? null;
    $token = $parsedArgs['token'] ?? null;
    $password = $parsedArgs['password'] ?? null;

    // Check if all required parameters are present
    if (!$serverIp || !$serverPort || !$token || !$password) {
        fwrite(STDERR, "❌ Error: --server-ip, --server-port, --token, and --password are required.\n");
        fwrite(STDOUT, "📖 Usage: php MCPKeygenClient.php --server-ip <IP> --server-port <Port> --token <Token> --password <Password>\n");
        exit(1);
    }

    try {
        echo "🔑 Sending Keygen request...\n";
        $response = sendKeygenRequest(
            $serverIp,
            $serverPort,
            $token,
            $password
        );
        echo "✔️ Server response:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        fwrite(STDERR, "❌ Error during Keygen request: " . $e->getMessage() . "\n");
    }
}

// Check if PHP version is at least 7.1 (for better features)
if (version_compare(PHP_VERSION, '7.1.0') < 0) {
    fwrite(STDERR, "❌ ERROR: This script requires PHP version 7.1 or higher.\n");
    exit(1);
}

// Call the main function
main($argv);
?>
