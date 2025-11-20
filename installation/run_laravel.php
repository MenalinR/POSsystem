<?php
// Set error handling and execution time limits
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

// Custom error handler
function handleFatalError() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Show user-friendly error page
        showErrorPage($error);
        exit();
    }
}

// Register shutdown function
register_shutdown_function('handleFatalError');

// Start output buffering
ob_start();

session_start();

// Function to execute command and return output with timeout protection
function execCommand($command, $timeout = 300) {
    $output = [];
    $returnVar = 0;

    // Set execution time limit for this command
    $originalTimeLimit = ini_get('max_execution_time');
    set_time_limit($timeout);

    try {
        // Execute command without timeout wrapper to avoid syntax conflicts
        exec($command . ' 2>&1', $output, $returnVar);

        // Restore original time limit
        set_time_limit($originalTimeLimit);

        return [
            'output' => implode("\n", $output),
            'success' => $returnVar === 0,
            'timeout' => false
        ];
    } catch (Exception $e) {
        // Restore original time limit
        set_time_limit($originalTimeLimit);

        return [
            'output' => 'Command execution failed: ' . $e->getMessage(),
            'success' => false,
            'timeout' => strpos($e->getMessage(), 'time') !== false
        ];
    }
}

// Function to check PHP version and extensions
function checkPHPRequirements() {
    $requirements = [
        'php_version' => ['required' => '8.1.0', 'current' => PHP_VERSION, 'status' => version_compare(PHP_VERSION, '8.1.0', '>=')],
        'openssl' => ['required' => 'enabled', 'current' => extension_loaded('openssl') ? 'enabled' : 'disabled', 'status' => extension_loaded('openssl')],
        'pdo' => ['required' => 'enabled', 'current' => extension_loaded('pdo') ? 'enabled' : 'disabled', 'status' => extension_loaded('pdo')],
        'mbstring' => ['required' => 'enabled', 'current' => extension_loaded('mbstring') ? 'enabled' : 'disabled', 'status' => extension_loaded('mbstring')],
        'tokenizer' => ['required' => 'enabled', 'current' => extension_loaded('tokenizer') ? 'enabled' : 'disabled', 'status' => extension_loaded('tokenizer')],
        'xml' => ['required' => 'enabled', 'current' => extension_loaded('xml') ? 'enabled' : 'disabled', 'status' => extension_loaded('xml')],
        'ctype' => ['required' => 'enabled', 'current' => extension_loaded('ctype') ? 'enabled' : 'disabled', 'status' => extension_loaded('ctype')],
        'json' => ['required' => 'enabled', 'current' => extension_loaded('json') ? 'enabled' : 'disabled', 'status' => extension_loaded('json')],
        'bcmath' => ['required' => 'enabled', 'current' => extension_loaded('bcmath') ? 'enabled' : 'disabled', 'status' => extension_loaded('bcmath')],
    ];

    $allPassed = true;
    foreach ($requirements as $req) {
        if (!$req['status']) {
            $allPassed = false;
            break;
        }
    }

    return ['passed' => $allPassed, 'details' => $requirements];
}

// Function to check Composer installation
function checkComposer() {
    $result = execCommand('composer --version');
    if ($result['success']) {
        preg_match('/Composer version ([0-9.]+)/', $result['output'], $matches);
        $version = $matches[1] ?? 'unknown';
        return ['installed' => true, 'version' => $version, 'output' => $result['output']];
    }
    return ['installed' => false, 'version' => null, 'output' => $result['output']];
}

// Function to check Node.js installation
function checkNodeJS() {
    $nodeResult = execCommand('node --version');
    $npmResult = execCommand('npm --version');

    $nodeVersion = null;
    $npmVersion = null;

    if ($nodeResult['success']) {
        $nodeVersion = trim(str_replace('v', '', $nodeResult['output']));
    }

    if ($npmResult['success']) {
        $npmVersion = trim($npmResult['output']);
    }

    $nodeVersionOk = $nodeVersion ? version_compare($nodeVersion, '16.0.0', '>=') : false;

    return [
        'node_installed' => $nodeResult['success'],
        'npm_installed' => $npmResult['success'],
        'node_version' => $nodeVersion,
        'npm_version' => $npmVersion,
        'node_version_ok' => $nodeVersionOk,
        'node_output' => $nodeResult['output'],
        'npm_output' => $npmResult['output']
    ];
}

// Function to check Laravel project requirements
function checkLaravelProject() {
    $checks = [
        'composer_json' => file_exists('composer.json'),
        'artisan' => file_exists('artisan'),
        'env_example' => file_exists('.env.example'),
        'package_json' => file_exists('package.json'),
        'app_directory' => is_dir('app'),
        'vendor_exists' => is_dir('vendor'),
        'writable_storage' => is_writable('storage'),
        'writable_bootstrap' => is_writable('bootstrap/cache')
    ];

    return $checks;
}

// Function to get system information
function getSystemInfo() {
    return [
        'os' => PHP_OS_FAMILY,
        'php_sapi' => php_sapi_name(),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    ];
}

// Function to check if .env exists
function envExists() {
    return file_exists('.env');
}

// Function to check if node_modules exists
function nodeModulesExists() {
    return is_dir('node_modules');
}

// Function to check if server is running
function isServerRunning() {
    $output = shell_exec('netstat -an | findstr :8000');
    return !empty($output);
}

// Function to check database connection
function testDatabaseConnection($host = null, $port = null, $database = null, $username = null, $password = null) {
    try {
        if ($host === null) {
            $env = parse_ini_file('.env');
            $host = $env['DB_HOST'] ?? 'localhost';
            $port = $env['DB_PORT'] ?? 3306;
            $database = $env['DB_DATABASE'] ?? '';
            $username = $env['DB_USERNAME'] ?? '';
            $password = $env['DB_PASSWORD'] ?? '';
        }

        $dsn = "mysql:host={$host};port={$port}";
        $pdo = new PDO($dsn, $username, $password);

        // Check if database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE '{$database}'");
        $dbExists = $stmt->rowCount() > 0;

        return ['success' => true, 'db_exists' => $dbExists, 'database' => $database, 'host' => $host];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'host' => $host];
    }
}

// Function to test both local and remote database connections
function testHibernateConnections($localConfig, $remoteConfig) {
    $localTest = testDatabaseConnection(
        $localConfig['host'],
        $localConfig['port'],
        $localConfig['database'],
        $localConfig['username'],
        $localConfig['password']
    );

    $remoteTest = testDatabaseConnection(
        $remoteConfig['host'],
        $remoteConfig['port'],
        $remoteConfig['database'],
        $remoteConfig['username'],
        $remoteConfig['password']
    );

    return [
        'local' => $localTest,
        'remote' => $remoteTest,
        'both_success' => $localTest['success'] && $remoteTest['success']
    ];
}

// Initialize variables
$message = '';
$status = '';
$step = $_GET['step'] ?? ($_POST['step'] ?? 'system_check');
$setupComplete = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {
        case 'proceed_setup':
            $step = 'check';
            break;

        case 'composer_install':
            try {
                $result = execCommand('composer install', 600); // 10 minutes for composer
                if ($result['success']) {
                    $message = "Composer packages installed successfully!";
                    $status = "success";
                    $step = 'npm_install';
                } else {
                    if ($result['timeout']) {
                        $message = "Composer installation is taking longer than expected. This is normal for first-time installations. <a href='javascript:location.reload()' style='color: #007bff;'>Click here to retry</a> or wait a few minutes and try again.";
                    } else {
                        $message = "Error installing composer packages: " . $result['output'] . "<br><a href='javascript:location.reload()' style='color: #007bff;'>🔄 Retry Installation</a>";
                    }
                    $status = "error";
                }
            } catch (Exception $e) {
                $message = "An unexpected error occurred during Composer installation. <a href='javascript:location.reload()' style='color: #007bff;'>🔄 Please try again</a>";
                $status = "error";
            }
            break;

        case 'npm_install':
            try {
                $result = execCommand('npm install', 600); // 10 minutes for npm
                if ($result['success']) {
                    $message = "NPM packages installed successfully!";
                    $status = "success";
                    $step = 'npm_build';
                } else {
                    if ($result['timeout']) {
                        $message = "NPM installation is taking longer than expected. This is normal for first-time installations. <a href='javascript:location.reload()' style='color: #007bff;'>Click here to retry</a> or wait a few minutes and try again.";
                    } else {
                        $message = "Error installing npm packages: " . $result['output'] . "<br><a href='javascript:location.reload()' style='color: #007bff;'>🔄 Retry Installation</a>";
                    }
                    $status = "error";
                }
            } catch (Exception $e) {
                $message = "An unexpected error occurred during NPM installation. <a href='javascript:location.reload()' style='color: #007bff;'>🔄 Please try again</a>";
                $status = "error";
            }
            break;

        case 'npm_build':
            try {
                $result = execCommand('npm run build', 300); // 5 minutes for build
                if ($result['success']) {
                    $message = "Assets built successfully!";
                    $status = "success";
                    $step = 'env_setup';
                } else {
                    if ($result['timeout']) {
                        $message = "Asset building is taking longer than expected. <a href='javascript:location.reload()' style='color: #007bff;'>Click here to retry</a> or wait a few minutes and try again.";
                    } else {
                        $message = "Error building assets: " . $result['output'] . "<br><a href='javascript:location.reload()' style='color: #007bff;'>🔄 Retry Build</a>";
                    }
                    $status = "error";
                }
            } catch (Exception $e) {
                $message = "An unexpected error occurred during asset building. <a href='javascript:location.reload()' style='color: #007bff;'>🔄 Please try again</a>";
                $status = "error";
            }
            break;

        case 'create_env':
            if (copy('.env.example', '.env')) {
                $message = ".env file created from .env.example";
                $status = "success";
                $step = 'env_config';
            } else {
                $message = "Error creating .env file";
                $status = "error";
            }
            break;

        case 'update_env':
            $envContent = file_get_contents('.env');

            // Update primary database configuration
            $envContent = preg_replace('/DB_HOST=.*/', 'DB_HOST=' . $_POST['db_host'], $envContent);
            $envContent = preg_replace('/DB_PORT=.*/', 'DB_PORT=' . $_POST['db_port'], $envContent);
            $envContent = preg_replace('/DB_DATABASE=.*/', 'DB_DATABASE=' . $_POST['db_name'], $envContent);
            $envContent = preg_replace('/DB_USERNAME=.*/', 'DB_USERNAME=' . $_POST['db_user'], $envContent);
            $envContent = preg_replace('/DB_PASSWORD=.*/', 'DB_PASSWORD=' . $_POST['db_pass'], $envContent);

            // Add hibernate configuration if enabled
            if (isset($_POST['hibernate']) && $_POST['hibernate'] === '1') {
                // Add remote database configuration
                $remoteConfig = "\n\n# Remote Database Configuration (Hibernate)\n";
                $remoteConfig .= "REMOTE_DB_HOST=" . $_POST['remote_db_host'] . "\n";
                $remoteConfig .= "REMOTE_DB_PORT=" . $_POST['remote_db_port'] . "\n";
                $remoteConfig .= "REMOTE_DB_DATABASE=" . $_POST['remote_db_name'] . "\n";
                $remoteConfig .= "REMOTE_DB_USERNAME=" . $_POST['remote_db_user'] . "\n";
                $remoteConfig .= "REMOTE_DB_PASSWORD=" . $_POST['remote_db_pass'] . "\n";
                $remoteConfig .= "HIBERNATE_ENABLED=true\n";

                $envContent .= $remoteConfig;
                $_SESSION['hibernate_enabled'] = true;
                $_SESSION['remote_config'] = [
                    'host' => $_POST['remote_db_host'],
                    'port' => $_POST['remote_db_port'],
                    'database' => $_POST['remote_db_name'],
                    'username' => $_POST['remote_db_user'],
                    'password' => $_POST['remote_db_pass']
                ];
            } else {
                $_SESSION['hibernate_enabled'] = false;
            }

            $_SESSION['local_config'] = [
                'host' => $_POST['db_host'],
                'port' => $_POST['db_port'],
                'database' => $_POST['db_name'],
                'username' => $_POST['db_user'],
                'password' => $_POST['db_pass']
            ];

            file_put_contents('.env', $envContent);
            $message = "Database configuration updated!";
            $status = "success";
            $step = 'db_test';
            break;

        case 'create_database':
            $hibernateEnabled = $_SESSION['hibernate_enabled'] ?? false;
            $successMessages = [];
            $errorMessages = [];

            try {
                // Create local database
                $localConfig = $_SESSION['local_config'];
                $dsn = "mysql:host={$localConfig['host']};port={$localConfig['port']}";
                $pdo = new PDO($dsn, $localConfig['username'], $localConfig['password']);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$localConfig['database']}`");
                $successMessages[] = "Local database '{$localConfig['database']}' created successfully!";

                // Create remote database if hibernate is enabled
                if ($hibernateEnabled && isset($_SESSION['remote_config'])) {
                    $remoteConfig = $_SESSION['remote_config'];
                    $remoteDsn = "mysql:host={$remoteConfig['host']};port={$remoteConfig['port']}";
                    $remotePdo = new PDO($remoteDsn, $remoteConfig['username'], $remoteConfig['password']);
                    $remotePdo->exec("CREATE DATABASE IF NOT EXISTS `{$remoteConfig['database']}`");
                    $successMessages[] = "Remote database '{$remoteConfig['database']}' created successfully!";
                }

                $message = implode('<br>', $successMessages);
                $status = "success";
                $step = 'migrate';
            } catch (Exception $e) {
                $message = "Error creating database(s): " . $e->getMessage();
                $status = "error";
            }
            break;

        case 'migrate':
            $hibernateEnabled = $_SESSION['hibernate_enabled'] ?? false;
            $migrationResults = [];
            $allSuccess = true;

            // Run migrations on local database
            $localResult = execCommand('php artisan migrate --force');
            if ($localResult['success']) {
                $migrationResults[] = "✅ Local database migrations completed successfully!";
            } else {
                $migrationResults[] = "❌ Local database migration failed: " . $localResult['output'];
                $allSuccess = false;
            }

            // Run migrations on remote database if hibernate is enabled
            if ($hibernateEnabled && isset($_SESSION['remote_config'])) {
                $remoteConfig = $_SESSION['remote_config'];

                // Temporarily switch database configuration for remote migration
                $originalEnv = file_get_contents('.env');
                $tempEnv = preg_replace('/DB_HOST=.*/', 'DB_HOST=' . $remoteConfig['host'], $originalEnv);
                $tempEnv = preg_replace('/DB_PORT=.*/', 'DB_PORT=' . $remoteConfig['port'], $tempEnv);
                $tempEnv = preg_replace('/DB_DATABASE=.*/', 'DB_DATABASE=' . $remoteConfig['database'], $tempEnv);
                $tempEnv = preg_replace('/DB_USERNAME=.*/', 'DB_USERNAME=' . $remoteConfig['username'], $tempEnv);
                $tempEnv = preg_replace('/DB_PASSWORD=.*/', 'DB_PASSWORD=' . $remoteConfig['password'], $tempEnv);

                file_put_contents('.env', $tempEnv);

                // Clear config cache and run remote migration
                execCommand('php artisan config:clear');
                $remoteResult = execCommand('php artisan migrate --force');

                // Restore original configuration
                file_put_contents('.env', $originalEnv);
                execCommand('php artisan config:clear');

                if ($remoteResult['success']) {
                    $migrationResults[] = "✅ Remote database migrations completed successfully!";
                } else {
                    $migrationResults[] = "❌ Remote database migration failed: " . $remoteResult['output'];
                    $allSuccess = false;
                }
            }

            $message = implode('<br>', $migrationResults);
            if ($allSuccess) {
                $status = "success";
                $step = $hibernateEnabled ? 'seed_databases' : 'generate_key';
            } else {
                $status = "error";
            }
            break;

        case 'seed_databases':
            $hibernateEnabled = $_SESSION['hibernate_enabled'] ?? false;
            $seedResults = [];
            $allSuccess = true;

            if ($hibernateEnabled) {
                // Seed local database
                $localSeedResult = execCommand('php artisan db:seed --force');
                if ($localSeedResult['success']) {
                    $seedResults[] = "✅ Local database seeded successfully!";
                } else {
                    $seedResults[] = "⚠️ Local database seeding skipped (no seeders or failed): " . $localSeedResult['output'];
                }

                // Seed remote database
                if (isset($_SESSION['remote_config'])) {
                    $remoteConfig = $_SESSION['remote_config'];

                    // Temporarily switch to remote database
                    $originalEnv = file_get_contents('.env');
                    $tempEnv = preg_replace('/DB_HOST=.*/', 'DB_HOST=' . $remoteConfig['host'], $originalEnv);
                    $tempEnv = preg_replace('/DB_PORT=.*/', 'DB_PORT=' . $remoteConfig['port'], $tempEnv);
                    $tempEnv = preg_replace('/DB_DATABASE=.*/', 'DB_DATABASE=' . $remoteConfig['database'], $tempEnv);
                    $tempEnv = preg_replace('/DB_USERNAME=.*/', 'DB_USERNAME=' . $remoteConfig['username'], $tempEnv);
                    $tempEnv = preg_replace('/DB_PASSWORD=.*/', 'DB_PASSWORD=' . $remoteConfig['password'], $tempEnv);

                    file_put_contents('.env', $tempEnv);

                    // Clear config cache and seed remote database
                    execCommand('php artisan config:clear');
                    $remoteSeedResult = execCommand('php artisan db:seed --force');

                    // Restore original configuration
                    file_put_contents('.env', $originalEnv);
                    execCommand('php artisan config:clear');

                    if ($remoteSeedResult['success']) {
                        $seedResults[] = "✅ Remote database seeded successfully!";
                    } else {
                        $seedResults[] = "⚠️ Remote database seeding skipped (no seeders or failed): " . $remoteSeedResult['output'];
                    }
                }

                $message = "Database seeding completed:<br>" . implode('<br>', $seedResults);
                $status = "success";
                $step = 'generate_key';
            }
            break;

        case 'generate_key':
            $result = execCommand('php artisan key:generate --force');
            if ($result['success']) {
                $message = "Application key generated successfully!";
                $status = "success";
                $step = 'storage_link';
            } else {
                $message = "Error generating key: " . $result['output'];
                $status = "error";
            }
            break;

        case 'storage_link':
            $result = execCommand('php artisan storage:link');
            if ($result['success']) {
                $message = "Storage link created successfully!";
                $status = "success";
                $step = 'complete';
                $setupComplete = true;
            } else {
                $message = "Error creating storage link: " . $result['output'];
                $status = "error";
            }
            break;

        case 'run_server':
            if (!isServerRunning()) {
                $command = 'start /B php artisan serve';
                pclose(popen($command, 'r'));
                $message = "Laravel development server started on http://127.0.0.1:8000";
                $status = "success";
            } else {
                $message = "Server is already running on http://127.0.0.1:8000";
                $status = "info";
            }
            break;

        case 'stop_server':
            execCommand('for /f "tokens=5" %a in (\"netstat -aon | findstr :8000\") do taskkill /f /pid %a');
            $message = "Laravel development server stopped";
            $status = "success";
            break;

        case 'back_to_web':
            header('Location: http://127.0.0.1:8000');
            exit();
            break;

        case 'reset_setup':
            // Delete .env file if it exists
            if (file_exists('.env')) {
                unlink('.env');
            }

            // Clear all session data
            session_destroy();
            session_start();

            // Reset to initial step
            $step = 'system_check';
            $setupComplete = false;
            $message = "Setup has been reset! All configuration has been cleared.";
            $status = "info";
            break;
    }
}

// Check initial setup status
if ($step === 'check') {
    if (envExists()) {
        $setupComplete = true;
        $step = 'complete';
        $message = "Setup already completed! Your Laravel project is ready.";
        $status = "success";
    } else {
        if (!is_dir('vendor')) {
            $step = 'composer';
        } elseif (!nodeModulesExists()) {
            $step = 'npm_install';
        } else {
            $step = 'env_setup';
        }
    }
}

// Function to show user-friendly error page
function showErrorPage($error) {
    $errorType = 'Unknown Error';
    $errorMessage = 'An unexpected error occurred.';
    $suggestions = [];

    if (isset($error['message'])) {
        if (strpos($error['message'], 'Maximum execution time') !== false) {
            $errorType = 'Execution Time Exceeded';
            $errorMessage = 'The operation took too long to complete and was stopped after 5 minutes.';
            $suggestions = [
                'The server might be overloaded - try again in a few minutes',
                'Your internet connection might be slow - check your connection',
                'The operation might be processing large files - this is normal for first-time setup',
                'Try refreshing the page and running the operation again'
            ];
        } elseif (strpos($error['message'], 'memory') !== false) {
            $errorType = 'Memory Limit Exceeded';
            $errorMessage = 'The application ran out of available memory.';
            $suggestions = [
                'Try closing other applications to free up memory',
                'Restart your web server (Apache/Nginx)',
                'The operation might need more resources - this is normal for large projects'
            ];
        } elseif (strpos($error['message'], 'network') !== false || strpos($error['message'], 'connection') !== false) {
            $errorType = 'Network Connection Error';
            $errorMessage = 'Unable to connect to required services.';
            $suggestions = [
                'Check your internet connection',
                'Verify that your firewall is not blocking the connection',
                'Try again in a few minutes - the service might be temporarily unavailable'
            ];
        }
    }

    echo '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Error - Laravel Application</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .error-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 600px;
            width: 100%;
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .error-icon {
            font-size: 64px;
            margin-bottom: 20px;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        h1 {
            color: #dc3545;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .error-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        .suggestions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }
        .suggestions h3 {
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .suggestions ul {
            list-style: none;
            padding: 0;
        }
        .suggestions li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        .suggestions li:last-child { border-bottom: none; }
        .suggestions li:before {
            content: "💡";
            margin-right: 10px;
        }
        .retry-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.4);
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100());
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        }
        .error-details {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
            font-family: monospace;
            font-size: 12px;
            color: #856404;
            display: none;
        }
        .show-details {
            color: #007bff;
            cursor: pointer;
            text-decoration: underline;
            font-size: 14px;
            margin: 10px 0;
        }
        .countdown {
            font-size: 14px;
            color: #666;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>' . htmlspecialchars($errorType) . '</h1>
        <div class="error-message">' . htmlspecialchars($errorMessage) . '</div>

        <div class="suggestions">
            <h3>💡 What you can do:</h3>
            <ul>';

    foreach ($suggestions as $suggestion) {
        echo '<li>' . htmlspecialchars($suggestion) . '</li>';
    }

    echo '            </ul>
        </div>

        <div class="retry-buttons">
            <button onclick="location.reload()" class="btn">
                🔄 Try Again
            </button>
            <a href="?step=system_check" class="btn btn-secondary">
                🏠 Start Over
            </a>
            <a href="?action=reset_setup" class="btn btn-danger" onclick="return confirm(\"This will reset all configuration. Continue?\")">
                🔄 Reset Setup
            </a>
        </div>

        <div class="show-details" onclick="toggleDetails()">📋 Show Technical Details</div>
        <div class="error-details" id="errorDetails">
            <strong>Error Type:</strong> ' . (isset($error['type']) ? $error['type'] : 'Unknown') . '<br>
            <strong>File:</strong> ' . (isset($error['file']) ? htmlspecialchars($error['file']) : 'Unknown') . '<br>
            <strong>Line:</strong> ' . (isset($error['line']) ? $error['line'] : 'Unknown') . '<br>
            <strong>Message:</strong> ' . (isset($error['message']) ? htmlspecialchars($error['message']) : 'Unknown') . '
        </div>

        <div class="countdown" id="countdown"></div>
    </div>

    <script>
        function toggleDetails() {
            const details = document.getElementById("errorDetails");
            const toggle = document.querySelector(".show-details");
            if (details.style.display === "none" || details.style.display === "") {
                details.style.display = "block";
                toggle.textContent = "📋 Hide Technical Details";
            } else {
                details.style.display = "none";
                toggle.textContent = "📋 Show Technical Details";
            }
        }

        // Auto-retry countdown (optional)
        let countdown = 30;
        function updateCountdown() {
            if (countdown > 0) {
                document.getElementById("countdown").innerHTML =
                    `<small>💡 Tip: Page will auto-refresh in ${countdown} seconds, or click \'Try Again\' now</small>`;
                countdown--;
                setTimeout(updateCountdown, 1000);
            } else {
                location.reload();
            }
        }

        // Start countdown after 5 seconds
        setTimeout(() => {
            // Only start countdown if error is execution time related
            if ("' . addslashes($errorType) . '".includes("Execution")) {
                updateCountdown();
            }
        }, 5000);
    </script>
</body>
</html>';
}

// System requirements check
if ($step === 'system_check') {
    $phpCheck = checkPHPRequirements();
    $composerCheck = checkComposer();
    $nodeCheck = checkNodeJS();
    $laravelCheck = checkLaravelProject();
    $systemInfo = getSystemInfo();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Run Laravel App</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 18px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn:active {
            transform: translateY(0);
        }

        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            font-size: 16px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            font-size: 14px;
            text-align: left;
        }

        .info strong {
            display: block;
            margin-bottom: 10px;
        }

        .setup-steps {
            text-align: left;
            margin-top: 20px;
        }

        .step {
            margin-bottom: 15px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f8f9fa;
        }

        .step.active {
            background: #e3f2fd;
            border-color: #2196f3;
        }

        .step.completed {
            background: #e8f5e8;
            border-color: #4caf50;
        }

        .step h3 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn-small {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            font-size: 14px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-small:hover {
            transform: translateY(-1px);
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-btn {
            background: #6c757d;
            margin-right: 10px;
        }

        .requirement-check {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .requirement-check.passed {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .requirement-check.failed {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .requirement-check.warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .requirement-name {
            font-weight: bold;
        }

        .requirement-value {
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .system-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 14px;
        }

        .system-info h4 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }

        /* Loading Effects */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(102, 126, 234, 0.95);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(10px);
        }

        .loading-spinner {
            text-align: center;
            color: white;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .loading-subtitle {
            font-size: 14px;
            opacity: 0.8;
        }

        /* Progress Bar */
        .progress-container {
            width: 300px;
            height: 6px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
            margin: 20px auto;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #fff, #f0f0f0, #fff);
            border-radius: 3px;
            animation: progress 2s ease-in-out infinite;
        }

        @keyframes progress {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 100%; }
        }

        /* Button Animations */
        .btn.processing {
            position: relative;
            color: transparent;
            pointer-events: none;
        }

        .btn.processing::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: button-spin 0.8s linear infinite;
        }

        @keyframes button-spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Success Animation */
        .success-animation {
            animation: successPulse 0.6s ease-in-out;
        }

        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); box-shadow: 0 0 30px rgba(40, 167, 69, 0.6); }
            100% { transform: scale(1); }
        }

        /* Step Animations */
        .step {
            transition: all 0.3s ease;
            transform: translateX(0);
        }

        .step.slide-in {
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Glowing Effect */
        .glow {
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from {
                box-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
            }
            to {
                box-shadow: 0 0 20px rgba(102, 126, 234, 0.8), 0 0 30px rgba(102, 126, 234, 0.6);
            }
        }

        /* Floating Elements */
        .floating {
            animation: floating 3s ease-in-out infinite;
        }

        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        /* Cool Hover Effects */
        .requirement-check {
            transition: all 0.3s ease;
        }

        .requirement-check:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* Pulse Animation for Important Messages */
        .pulse {
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        /* Typewriter Effect */
        .typewriter {
            overflow: hidden;
            border-right: .15em solid orange;
            white-space: nowrap;
            margin: 0 auto;
            letter-spacing: .15em;
            animation: typing 3.5s steps(40, end), blink-caret .75s step-end infinite;
        }

        @keyframes typing {
            from { width: 0 }
            to { width: 100% }
        }

        @keyframes blink-caret {
            from, to { border-color: transparent }
            50% { border-color: orange; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="floating">🚀 Laravel Application Setup</h1>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $status; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 'system_check'): ?>
            <div class="step active slide-in glow">
                <h3>🔍 System Requirements Check</h3>
                <p class="typewriter">Checking your system compatibility with Laravel requirements...</p>

                <div class="system-info">
                    <h4>📋 System Information</h4>
                    <div class="info-grid">
                        <div><strong>OS:</strong> <?php echo $systemInfo['os']; ?></div>
                        <div><strong>PHP SAPI:</strong> <?php echo $systemInfo['php_sapi']; ?></div>
                        <div><strong>Memory Limit:</strong> <?php echo $systemInfo['memory_limit']; ?></div>
                        <div><strong>Max Execution Time:</strong> <?php echo $systemInfo['max_execution_time']; ?>s</div>
                    </div>
                </div>

                <h4>🔧 PHP Requirements</h4>
                <?php foreach ($phpCheck['details'] as $name => $req): ?>
                    <div class="requirement-check <?php echo $req['status'] ? 'passed' : 'failed'; ?>">
                        <div class="requirement-name">
                            <?php echo ucfirst(str_replace('_', ' ', $name)); ?>
                        </div>
                        <div class="requirement-value">
                            <?php echo $req['current']; ?>
                            <?php echo $req['status'] ? '✅' : '❌'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <h4 style="margin-top: 20px;">📦 Development Tools</h4>
                <div class="requirement-check <?php echo $composerCheck['installed'] ? 'passed' : 'failed'; ?>">
                    <div class="requirement-name">Composer</div>
                    <div class="requirement-value">
                        <?php echo $composerCheck['installed'] ? 'v' . $composerCheck['version'] . ' ✅' : 'Not installed ❌'; ?>
                    </div>
                </div>

                <div class="requirement-check <?php echo $nodeCheck['node_installed'] ? 'passed' : 'failed'; ?>">
                    <div class="requirement-name">Node.js</div>
                    <div class="requirement-value">
                        <?php echo $nodeCheck['node_installed'] ? 'v' . $nodeCheck['node_version'] . ' ' . ($nodeCheck['node_version_ok'] ? '✅' : '⚠️') : 'Not installed ❌'; ?>
                    </div>
                </div>

                <div class="requirement-check <?php echo $nodeCheck['npm_installed'] ? 'passed' : 'failed'; ?>">
                    <div class="requirement-name">NPM</div>
                    <div class="requirement-value">
                        <?php echo $nodeCheck['npm_installed'] ? 'v' . $nodeCheck['npm_version'] . ' ✅' : 'Not installed ❌'; ?>
                    </div>
                </div>

                <h4 style="margin-top: 20px;">📁 Laravel Project Structure</h4>
                <?php foreach ($laravelCheck as $name => $status): ?>
                    <div class="requirement-check <?php echo $status ? 'passed' : 'failed'; ?>">
                        <div class="requirement-name">
                            <?php echo ucfirst(str_replace('_', ' ', $name)); ?>
                        </div>
                        <div class="requirement-value">
                            <?php echo $status ? 'Found ✅' : 'Missing ❌'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php
                $canProceed = $phpCheck['passed'] && $composerCheck['installed'] && $nodeCheck['node_installed'] && $nodeCheck['npm_installed'];
                $allLaravelFilesExist = $laravelCheck['composer_json'] && $laravelCheck['artisan'] && $laravelCheck['app_directory'];
                ?>

                <?php if ($canProceed && $allLaravelFilesExist): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #d4edda; border-radius: 5px; color: #155724;">
                        <strong>✅ All system requirements are met!</strong><br>
                        You can proceed with the Laravel setup.
                    </div>
                    <form method="POST" style="margin-top: 15px;">
                        <input type="hidden" name="action" value="proceed_setup">
                        <button type="submit" class="btn">Proceed with Setup</button>
                    </form>
                <?php else: ?>
                    <div style="margin-top: 20px; padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24;">
                        <strong>❌ System requirements not met!</strong><br>
                        Please install the missing requirements before proceeding:
                        <ul style="margin: 10px 0; text-align: left;">
                            <?php if (!$phpCheck['passed']): ?>
                                <li>Fix PHP extensions and version requirements</li>
                            <?php endif; ?>
                            <?php if (!$composerCheck['installed']): ?>
                                <li>Install Composer from <a href="https://getcomposer.org" target="_blank">getcomposer.org</a></li>
                            <?php endif; ?>
                            <?php if (!$nodeCheck['node_installed']): ?>
                                <li>Install Node.js from <a href="https://nodejs.org" target="_blank">nodejs.org</a></li>
                            <?php endif; ?>
                            <?php if (!$allLaravelFilesExist): ?>
                                <li>Ensure you're in a valid Laravel project directory</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <form method="POST" style="margin-top: 15px;">
                        <input type="hidden" name="action" value="proceed_setup">
                        <button type="submit" class="btn" style="background: #6c757d;" onclick="return confirm('Are you sure you want to proceed despite missing requirements?')">Proceed Anyway (Not Recommended)</button>
                    </form>
                <?php endif; ?>

                <?php if (file_exists('.env')): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px; border: 1px solid #ffeaa7;">
                        <strong>⚠️ Existing Configuration Found</strong><br>
                        <p style="font-size: 12px; margin: 5px 0;">An .env file already exists. You can reset to start fresh.</p>
                        <form method="POST" style="margin-top: 10px;" onsubmit="return confirm('This will delete your current .env file and restart setup. Continue?')">
                            <input type="hidden" name="action" value="reset_setup">
                            <button type="submit" class="btn" style="background: #dc3545; font-size: 14px; padding: 8px 16px;">🔄 Reset Configuration</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($step === 'complete' && $setupComplete): ?>
            <div class="step completed slide-in success-animation">
                <h3 class="floating">✅ Setup Complete!</h3>
                <p class="pulse">Your Laravel application is ready to use.</p>
                <?php if (isServerRunning()): ?>
                    <p style="color: green; margin: 15px 0;">🟢 Server is running on http://127.0.0.1:8000</p>
                    <form method="POST" style="display: inline-block; margin-right: 10px;">
                        <input type="hidden" name="action" value="stop_server">
                        <button type="submit" class="btn" style="background: #dc3545;">Stop Server</button>
                    </form>
                    <form method="POST" style="display: inline-block; margin-right: 10px;">
                        <input type="hidden" name="action" value="back_to_web">
                        <button type="submit" class="btn">Go to Application</button>
                    </form>
                <?php else: ?>
                    <p style="color: orange; margin: 15px 0;">🟠 Server is not running</p>
                    <form method="POST" style="display: inline-block; margin-right: 10px;">
                        <input type="hidden" name="action" value="run_server">
                        <button type="submit" class="btn">Start Server</button>
                    </form>
                <?php endif; ?>
                <div style="margin-top: 15px;">
                    <a href="http://127.0.0.1:8000" target="_blank" class="btn" style="text-decoration: none; display: inline-block; background: #28a745;">Open in New Tab</a>
                </div>

                <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px; border: 1px dashed #ccc;">
                    <h4 style="margin: 0 0 10px 0; color: #666;">🔄 Reset Configuration</h4>
                    <p style="font-size: 12px; color: #666; margin: 0 0 15px 0;">Need to reconfigure? This will delete the .env file and restart the setup process.</p>
                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure? This will delete all configuration and restart the setup process.')">
                        <input type="hidden" name="action" value="reset_setup">
                        <button type="submit" class="btn" style="background: #dc3545; font-size: 14px; padding: 10px 20px;">🔄 Reset to Defaults</button>
                    </form>
                </div>
            </div>

        <?php elseif ($step === 'composer'): ?>
            <div class="step active slide-in glow">
                <h3>1. Install Composer Dependencies</h3>
                <p>Installing Composer packages...</p>
                <form method="POST">
                    <input type="hidden" name="action" value="composer_install">
                    <button type="submit" class="btn">Install Composer Packages</button>
                </form>
            </div>

        <?php elseif ($step === 'npm_install'): ?>
            <div class="step active slide-in glow">
                <h3>2. Install NPM Dependencies</h3>
                <p>Installing Node.js packages...</p>
                <form method="POST">
                    <input type="hidden" name="action" value="npm_install">
                    <button type="submit" class="btn">Install NPM Packages</button>
                </form>
            </div>

        <?php elseif ($step === 'npm_build'): ?>
            <div class="step active slide-in glow">
                <h3>3. Build Assets</h3>
                <p>Building CSS and JavaScript assets...</p>
                <form method="POST">
                    <input type="hidden" name="action" value="npm_build">
                    <button type="submit" class="btn">Build Assets</button>
                </form>
            </div>

        <?php elseif ($step === 'env_setup'): ?>
            <div class="step active slide-in glow">
                <h3>4. Create Environment File</h3>
                <p>Create .env file from .env.example</p>
                <form method="POST">
                    <input type="hidden" name="action" value="create_env">
                    <button type="submit" class="btn">Create .env File</button>
                </form>
            </div>

        <?php elseif ($step === 'env_config'): ?>
            <div class="step active slide-in glow">
                <h3>5. Configure Database</h3>
                <p>Set up your database connection:</p>
                <form method="POST" id="dbConfigForm">
                    <input type="hidden" name="action" value="update_env">

                    <div style="margin-bottom: 20px; padding: 15px; background: #e3f2fd; border-radius: 5px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" name="hibernate" value="1" id="hibernateCheck" onchange="toggleRemoteDB()" style="margin-right: 10px;">
                            <strong>🔄 Enable Hibernate (Dual Database Support)</strong>
                        </label>
                        <p style="font-size: 12px; margin: 5px 0 0 25px; color: #666;">Enable this to configure both local and remote database connections</p>
                    </div>

                    <h4>📍 Local Database Configuration</h4>
                    <div class="form-group">
                        <label>Database Host:</label>
                        <input type="text" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label>Database Port:</label>
                        <input type="text" name="db_port" value="3306" required>
                    </div>
                    <div class="form-group">
                        <label>Database Name:</label>
                        <input type="text" name="db_name" value="mini_zone" required>
                    </div>
                    <div class="form-group">
                        <label>Database Username:</label>
                        <input type="text" name="db_user" value="root" required>
                    </div>
                    <div class="form-group">
                        <label>Database Password:</label>
                        <input type="password" name="db_pass" value="">
                    </div>

                    <div id="remoteDbConfig" style="display: none; margin-top: 30px; padding: 20px; border: 2px dashed #ccc; border-radius: 5px;">
                        <h4>🌐 Remote Database Configuration</h4>
                        <div class="form-group">
                            <label>Remote Database Host:</label>
                            <input type="text" name="remote_db_host" placeholder="e.g., remote.example.com">
                        </div>
                        <div class="form-group">
                            <label>Remote Database Port:</label>
                            <input type="text" name="remote_db_port" value="3306">
                        </div>
                        <div class="form-group">
                            <label>Remote Database Name:</label>
                            <input type="text" name="remote_db_name" placeholder="e.g., mini_zone_remote">
                        </div>
                        <div class="form-group">
                            <label>Remote Database Username:</label>
                            <input type="text" name="remote_db_user" placeholder="Remote DB username">
                        </div>
                        <div class="form-group">
                            <label>Remote Database Password:</label>
                            <input type="password" name="remote_db_pass" placeholder="Remote DB password">
                        </div>
                        <div class="form-group">
                            <label>Database Type:</label>
                            <input type="text" value="MySQL" disabled style="background: #f8f9fa; color: #666;">
                            <small style="color: #666;">MySQL is the only supported database type</small>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="margin-top: 20px;">Update Configuration</button>
                </form>

                <script>
                function toggleRemoteDB() {
                    const hibernateCheck = document.getElementById('hibernateCheck');
                    const remoteDbConfig = document.getElementById('remoteDbConfig');
                    const remoteInputs = remoteDbConfig.querySelectorAll('input[name^="remote_"]');

                    if (hibernateCheck.checked) {
                        remoteDbConfig.style.display = 'block';
                        remoteInputs.forEach(input => {
                            if (input.name !== 'remote_db_pass') {
                                input.required = true;
                            }
                        });
                    } else {
                        remoteDbConfig.style.display = 'none';
                        remoteInputs.forEach(input => {
                            input.required = false;
                        });
                    }
                }
                </script>
            </div>

        <?php elseif ($step === 'db_test'): ?>
            <div class="step active slide-in glow">
                <h3>6. Test Database Connection</h3>
                <?php
                $hibernateEnabled = $_SESSION['hibernate_enabled'] ?? false;

                if ($hibernateEnabled && isset($_SESSION['local_config']) && isset($_SESSION['remote_config'])) {
                    $hibernateTest = testHibernateConnections($_SESSION['local_config'], $_SESSION['remote_config']);
                    $localTest = $hibernateTest['local'];
                    $remoteTest = $hibernateTest['remote'];
                ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                        <div style="padding: 15px; border-radius: 5px; <?php echo $localTest['success'] ? 'background: #d4edda; border: 1px solid #c3e6cb;' : 'background: #f8d7da; border: 1px solid #f5c6cb;'; ?>">
                            <h4>📍 Local Database (<?php echo $localTest['host']; ?>)</h4>
                            <?php if ($localTest['success']): ?>
                                <p style="color: #155724;">✅ Connection successful!</p>
                                <p style="font-size: 12px; color: #155724;">Database: <?php echo $localTest['database']; ?> <?php echo $localTest['db_exists'] ? '(exists)' : '(will be created)'; ?></p>
                            <?php else: ?>
                                <p style="color: #721c24;">❌ Connection failed!</p>
                                <p style="font-size: 12px; color: #721c24;"><?php echo $localTest['error']; ?></p>
                            <?php endif; ?>
                        </div>

                        <div style="padding: 15px; border-radius: 5px; <?php echo $remoteTest['success'] ? 'background: #d4edda; border: 1px solid #c3e6cb;' : 'background: #f8d7da; border: 1px solid #f5c6cb;'; ?>">
                            <h4>🌐 Remote Database (<?php echo $remoteTest['host']; ?>)</h4>
                            <?php if ($remoteTest['success']): ?>
                                <p style="color: #155724;">✅ Connection successful!</p>
                                <p style="font-size: 12px; color: #155724;">Database: <?php echo $remoteTest['database']; ?> <?php echo $remoteTest['db_exists'] ? '(exists)' : '(will be created)'; ?></p>
                            <?php else: ?>
                                <p style="color: #721c24;">❌ Connection failed!</p>
                                <p style="font-size: 12px; color: #721c24;"><?php echo $remoteTest['error']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($hibernateTest['both_success']): ?>
                        <div style="padding: 15px; background: #d4edda; border-radius: 5px; color: #155724; margin: 20px 0;">
                            <strong>🎉 Both database connections are successful!</strong><br>
                            Your hibernate setup is ready to proceed.
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="migrate">
                            <button type="submit" class="btn">Run Migrations on Both Databases</button>
                        </form>
                    <?php else: ?>
                        <div style="padding: 15px; background: #f8d7da; border-radius: 5px; color: #721c24; margin: 20px 0;">
                            <strong>❌ One or both database connections failed!</strong><br>
                            Please fix the connection issues before proceeding.
                        </div>
                        <a href="?step=env_config" class="btn back-btn">Back to Configuration</a>
                    <?php endif; ?>

                <?php } else {
                    $dbTest = testDatabaseConnection();
                    if ($dbTest['success']):
                        if ($dbTest['db_exists']):
                ?>
                    <p style="color: green;">✅ Database connection successful! Database exists.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="migrate">
                        <button type="submit" class="btn">Run Migrations</button>
                    </form>
                <?php else: ?>
                    <p style="color: orange;">⚠️ Database connection successful, but database doesn't exist.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_database">
                        <input type="hidden" name="db_host" value="<?php echo $_SESSION['local_config']['host'] ?? 'localhost'; ?>">
                        <input type="hidden" name="db_port" value="<?php echo $_SESSION['local_config']['port'] ?? '3306'; ?>">
                        <input type="hidden" name="db_name" value="<?php echo $dbTest['database']; ?>">
                        <input type="hidden" name="db_user" value="<?php echo $_SESSION['local_config']['username'] ?? 'root'; ?>">
                        <input type="hidden" name="db_pass" value="<?php echo $_SESSION['local_config']['password'] ?? ''; ?>">
                        <button type="submit" class="btn">Create Database</button>
                    </form>
                <?php endif; ?>
                <?php else: ?>
                    <p style="color: red;">❌ Database connection failed: <?php echo $dbTest['error']; ?></p>
                    <a href="?step=env_config" class="btn back-btn">Back to Configuration</a>
                <?php endif; ?>
                <?php } ?>
            </div>

        <?php elseif ($step === 'migrate'): ?>
            <div class="step active slide-in glow">
                <h3>7. Run Database Migrations</h3>
                <?php if ($_SESSION['hibernate_enabled'] ?? false): ?>
                    <div style="padding: 15px; background: #e3f2fd; border-radius: 5px; margin: 15px 0;">
                        <strong>🔄 Hibernate Mode Active</strong><br>
                        <p style="font-size: 14px; margin: 5px 0;">Migrations will run on both local and remote databases.</p>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="migrate">
                    <button type="submit" class="btn">Run Migrations on All Databases</button>
                </form>
            </div>

        <?php elseif ($step === 'seed_databases'): ?>
            <div class="step active slide-in glow">
                <h3>8. Seed Databases</h3>
                <div style="padding: 15px; background: #fff3cd; border-radius: 5px; margin: 15px 0;">
                    <strong>🌱 Database Seeding</strong><br>
                    <p style="font-size: 14px; margin: 5px 0;">This will populate both local and remote databases with initial data (if seeders exist).</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="seed_databases">
                    <button type="submit" class="btn">Seed Both Databases</button>
                </form>
                <form method="POST" style="margin-top: 10px;">
                    <input type="hidden" name="action" value="generate_key">
                    <button type="submit" class="btn" style="background: #6c757d;">Skip Seeding</button>
                </form>
            </div>

        <?php elseif ($step === 'generate_key'): ?>
            <div class="step active slide-in glow">
                <h3>9. Generate Application Key</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="generate_key">
                    <button type="submit" class="btn">Generate Key</button>
                </form>
            </div>

        <?php elseif ($step === 'storage_link'): ?>
            <div class="step active slide-in glow">
                <h3>10. Create Storage Link</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="storage_link">
                    <button type="submit" class="btn">Create Storage Link</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="info">
            <strong>Laravel Setup Wizard</strong>
            This wizard will guide you through the complete Laravel project setup process including:
            <br>• Composer package installation
            <br>• NPM package installation and asset building
            <br>• Environment configuration
            <br>• Database setup and migration
            <br>• Application key generation
            <br>• Storage link creation
            <br>• Development server management

            <?php if (file_exists('.env') && $step !== 'system_check'): ?>
                <div style="margin-top: 15px; padding: 10px; background: rgba(220, 53, 69, 0.1); border-radius: 5px; border: 1px dashed #dc3545;">
                    <strong style="font-size: 12px; color: #dc3545;">🔄 Need to Start Over?</strong>
                    <form method="POST" style="margin: 5px 0 0 0; display: inline-block;" onsubmit="return confirm('This will delete all configuration and restart setup. Are you sure?')">
                        <input type="hidden" name="action" value="reset_setup">
                        <button type="submit" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;">Reset Setup</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div class="loading-text" id="loadingText">Processing...</div>
            <div class="loading-subtitle" id="loadingSubtitle">Please wait while we set up your Laravel application</div>
            <div class="progress-container">
                <div class="progress-bar"></div>
            </div>
        </div>
    </div>

    <script>
        // Loading Messages
        const loadingMessages = {
            'composer_install': {
                text: 'Installing Composer Dependencies',
                subtitle: 'Downloading and installing PHP packages...'
            },
            'npm_install': {
                text: 'Installing NPM Dependencies',
                subtitle: 'Setting up Node.js packages...'
            },
            'npm_build': {
                text: 'Building Assets',
                subtitle: 'Compiling CSS and JavaScript files...'
            },
            'create_env': {
                text: 'Creating Environment File',
                subtitle: 'Setting up configuration...'
            },
            'update_env': {
                text: 'Updating Database Configuration',
                subtitle: 'Configuring database connections...'
            },
            'create_database': {
                text: 'Creating Databases',
                subtitle: 'Setting up database structures...'
            },
            'migrate': {
                text: 'Running Database Migrations',
                subtitle: 'Creating database tables and structure...'
            },
            'seed_databases': {
                text: 'Seeding Databases',
                subtitle: 'Populating databases with initial data...'
            },
            'generate_key': {
                text: 'Generating Application Key',
                subtitle: 'Creating security keys...'
            },
            'storage_link': {
                text: 'Creating Storage Links',
                subtitle: 'Setting up file storage...'
            },
            'run_server': {
                text: 'Starting Development Server',
                subtitle: 'Launching Laravel application...'
            },
            'stop_server': {
                text: 'Stopping Server',
                subtitle: 'Shutting down development server...'
            },
            'reset_setup': {
                text: 'Resetting Setup',
                subtitle: 'Clearing configuration and restarting...'
            }
        };

        // Show loading overlay
        function showLoading(action) {
            const overlay = document.getElementById('loadingOverlay');
            const loadingText = document.getElementById('loadingText');
            const loadingSubtitle = document.getElementById('loadingSubtitle');

            if (loadingMessages[action]) {
                loadingText.textContent = loadingMessages[action].text;
                loadingSubtitle.textContent = loadingMessages[action].subtitle;
            }

            overlay.style.display = 'flex';

            // Add some randomness to make it feel more dynamic
            setTimeout(() => {
                const spinner = document.querySelector('.spinner');
                spinner.style.borderTopColor = getRandomColor();
            }, 500);
        }

        // Hide loading overlay
        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            overlay.style.display = 'none';
        }

        // Get random color for spinner
        function getRandomColor() {
            const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ffeaa7', '#dda0dd'];
            return colors[Math.floor(Math.random() * colors.length)];
        }

        // Add loading effects to buttons
        function addButtonLoading(button, action) {
            button.classList.add('processing');
            button.disabled = true;
            showLoading(action);
        }

        // Enhanced form submission with loading
        document.addEventListener('DOMContentLoaded', function() {
            // Add slide-in animation to steps
            const steps = document.querySelectorAll('.step');
            steps.forEach((step, index) => {
                setTimeout(() => {
                    step.classList.add('slide-in');
                }, index * 100);
            });

            // Add glow effect to active elements
            const activeElements = document.querySelectorAll('.step.active');
            activeElements.forEach(element => {
                element.classList.add('glow');
            });

            // Add floating effect to the main title
            const title = document.querySelector('h1');
            if (title) {
                title.classList.add('floating');
            }

            // Enhanced button click handling
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const button = form.querySelector('button[type="submit"]');
                    const action = form.querySelector('input[name="action"]')?.value;

                    if (button && action) {
                        addButtonLoading(button, action);
                    }
                });
            });

            // Add hover effects to requirement checks
            const reqChecks = document.querySelectorAll('.requirement-check');
            reqChecks.forEach(check => {
                check.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px) scale(1.02)';
                });

                check.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0) scale(1)';
                });
            });

            // Add success animation to success messages
            const successMessages = document.querySelectorAll('.message.success');
            successMessages.forEach(message => {
                message.classList.add('success-animation');
            });

            // Add pulse effect to important warnings
            const errorMessages = document.querySelectorAll('.message.error');
            errorMessages.forEach(message => {
                message.classList.add('pulse');
            });

            // Auto-hide loading on page load if there are messages
            if (document.querySelector('.message')) {
                hideLoading();
            }
        });

        // Cool particle effect for background
        function createParticles() {
            const container = document.querySelector('.container');

            for (let i = 0; i < 5; i++) {
                const particle = document.createElement('div');
                particle.style.position = 'absolute';
                particle.style.width = '4px';
                particle.style.height = '4px';
                particle.style.background = 'rgba(255, 255, 255, 0.5)';
                particle.style.borderRadius = '50%';
                particle.style.pointerEvents = 'none';
                particle.style.animation = `floating ${3 + Math.random() * 2}s ease-in-out infinite`;
                particle.style.top = Math.random() * 100 + '%';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 2 + 's';

                container.appendChild(particle);
            }
        }

        // Initialize particles
        document.addEventListener('DOMContentLoaded', createParticles);
    </script>
</body>
</html>
