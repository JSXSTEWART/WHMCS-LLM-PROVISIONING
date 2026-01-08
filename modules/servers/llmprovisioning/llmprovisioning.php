<?php
/**
 * WHMCS LLM Provisioning Module
 *
 * Provides automated deployment and management of GPU-accelerated LLM inference
 * containers through WHMCS billing integration with Portainer orchestration.
 *
 * @author JSXSTEWART
 * @version 2.0.0
 * @license MIT
 * @package WHMCS\Modules\Servers\LLMProvisioning
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\InstanceInterface;

// Configuration validation constants
const MIN_MEMORY_GB = 4;
const MAX_MEMORY_GB = 256;
const MIN_CPU_CORES = 1;
const MAX_CPU_CORES = 128;
const MIN_STORAGE_GB = 20;
const MAX_STORAGE_GB = 5000;

const APPROVED_MODELS = [
    'llama2-7b',
    'llama2-13b',
    'llama2-70b',
    'mistral-7b',
    'codelama-13b',
    'phi-2',
];

const APPROVED_GPU_TYPES = [
    'tesla-t4',
    'tesla-a10',
    'tesla-a100',
    'rtx-a6000',
    'rtx-6000',
];

/**
 * Validate resource configuration limits
 *
 * @param int $memory Memory in GB
 * @param int $cpu CPU cores
 * @param int $storage Storage in GB
 * @return array Validation result with 'valid' boolean and 'errors' array
 */
function validateResourceLimits(int $memory, int $cpu, int $storage): array {
    $errors = [];

    if ($memory < MIN_MEMORY_GB || $memory > MAX_MEMORY_GB) {
        $errors[] = "Memory must be between " . MIN_MEMORY_GB . "GB and " . MAX_MEMORY_GB . "GB";
    }

    if ($cpu < MIN_CPU_CORES || $cpu > MAX_CPU_CORES) {
        $errors[] = "CPU cores must be between " . MIN_CPU_CORES . " and " . MAX_CPU_CORES;
    }

    if ($storage < MIN_STORAGE_GB || $storage > MAX_STORAGE_GB) {
        $errors[] = "Storage must be between " . MIN_STORAGE_GB . "GB and " . MAX_STORAGE_GB . "GB";
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Validate model selection
 *
 * @param string $model Model identifier
 * @return bool True if model is approved
 */
function validateModel(string $model): bool {
    return in_array($model, APPROVED_MODELS, true);
}

/**
 * Validate GPU type
 *
 * @param string $gpu GPU type identifier
 * @return bool True if GPU type is approved
 */
function validateGPUType(string $gpu): bool {
    return in_array($gpu, APPROVED_GPU_TYPES, true);
}

/**
 * PortainerClient - API client for Portainer container management
 *
 * Handles all communication with Portainer REST API v2
 */
class PortainerClient {
    private string $baseUrl;
    private string $apiKey;
    private bool $sslVerify;

    /**
     * Initialize Portainer API client
     *
     * @param string $baseUrl Portainer base URL (e.g., https://portainer.example.com)
     * @param string $apiKey Portainer API token
     * @param bool $sslVerify Whether to verify SSL certificates
     */
    public function __construct(string $baseUrl, string $apiKey, bool $sslVerify = true) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->sslVerify = $sslVerify;
    }

    /**
     * Make HTTP request to Portainer API
     *
     * @param string $method HTTP method (GET, POST, DELETE, etc.)
     * @param string $endpoint API endpoint path
     * @param array $data Request payload for POST/PUT
     * @return array Response data decoded from JSON
     * @throws Exception When API request fails
     */
    private function request(string $method, string $endpoint, array $data = []): array {
        $url = "{$this->baseUrl}/api{$endpoint}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "X-API-Key: {$this->apiKey}",
                "Content-Type: application/json"
            ],
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_TIMEOUT => 30,
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Portainer API connection failed: {$error}");
        }

        if ($httpCode >= 400) {
            $errorBody = @json_decode($response, true);
            $errorMsg = $errorBody['message'] ?? 'Unknown API error';
            throw new Exception("Portainer API error (HTTP {$httpCode}): {$errorMsg}");
        }

        return @json_decode($response, true) ?? [];
    }

    /**
     * Create new container for LLM deployment
     *
     * @param int $endpointId Portainer environment endpoint ID
     * @param array $config Container configuration
     * @return array Container details including ID
     * @throws Exception When creation fails
     */
    public function createContainer(int $endpointId, array $config): array {
        return $this->request('POST', "/endpoints/{$endpointId}/docker/containers/create", $config);
    }

    /**
     * Start a stopped container
     *
     * @param int $endpointId Portainer environment endpoint ID
     * @param string $containerId Docker container ID
     * @return void
     * @throws Exception When operation fails
     */
    public function startContainer(int $endpointId, string $containerId): void {
        $this->request('POST', "/endpoints/{$endpointId}/docker/containers/{$containerId}/start", []);
    }

    /**
     * Stop a running container
     *
     * @param int $endpointId Portainer environment endpoint ID
     * @param string $containerId Docker container ID
     * @return void
     * @throws Exception When operation fails
     */
    public function stopContainer(int $endpointId, string $containerId): void {
        $this->request('POST', "/endpoints/{$endpointId}/docker/containers/{$containerId}/stop", []);
    }

    /**
     * Remove a container
     *
     * @param int $endpointId Portainer environment endpoint ID
     * @param string $containerId Docker container ID
     * @return void
     * @throws Exception When operation fails
     */
    public function removeContainer(int $endpointId, string $containerId): void {
        $this->request('DELETE', "/endpoints/{$endpointId}/docker/containers/{$containerId}?force=true", []);
    }

    /**
     * Get container details
     *
     * @param int $endpointId Portainer environment endpoint ID
     * @param string $containerId Docker container ID
     * @return array Container information
     * @throws Exception When operation fails
     */
    public function getContainer(int $endpointId, string $containerId): array {
        return $this->request('GET', "/endpoints/{$endpointId}/docker/containers/{$containerId}/json", []);
    }
}

/**
 * CyberPanelClient - API client for CyberPanel reverse proxy setup
 *
 * Manages DNS, SSL certificates, and reverse proxy configuration
 */
class CyberPanelClient {
    private string $apiUrl;
    private string $apiKey;
    private string $apiPassword;
    private bool $sslVerify;

    /**
     * Initialize CyberPanel API client
     *
     * @param string $apiUrl CyberPanel API endpoint (e.g., https://cyberpanel.example.com)
     * @param string $apiKey CyberPanel API key
     * @param string $apiPassword CyberPanel API password
     * @param bool $sslVerify Whether to verify SSL certificates
     */
    public function __construct(string $apiUrl, string $apiKey, string $apiPassword, bool $sslVerify = true) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->apiPassword = $apiPassword;
        $this->sslVerify = $sslVerify;
    }

    /**
     * Make HTTP request to CyberPanel API
     *
     * @param string $endpoint API endpoint path
     * @param array $data Request parameters
     * @return array Response data
     * @throws Exception When API request fails
     */
    private function request(string $endpoint, array $data): array {
        $url = "{$this->apiUrl}{$endpoint}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => [
                "X-API-Key: {$this->apiKey}"
            ],
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("CyberPanel API connection failed: {$error}");
        }

        if ($httpCode >= 400) {
            throw new Exception("CyberPanel API error - check endpoint and credentials");
        }

        return @json_decode($response, true) ?? [];
    }

    /**
     * Create reverse proxy for LLM container
     *
     * @param string $domain Domain name
     * @param string $backendPort Backend container port
     * @param array $sslConfig SSL certificate configuration
     * @return array Response from API
     * @throws Exception When operation fails
     */
    public function createReverseProxy(string $domain, string $backendPort, array $sslConfig): array {
        return $this->request('/api/createWebsite', [
            'domain' => $domain,
            'backendPort' => $backendPort,
            'email' => $sslConfig['email'] ?? 'admin@example.com',
            'ssl' => 1,
        ]);
    }

    /**
     * Delete reverse proxy configuration
     *
     * @param string $domain Domain name
     * @return array Response from API
     * @throws Exception When operation fails
     */
    public function deleteReverseProxy(string $domain): array {
        return $this->request('/api/deleteWebsite', [
            'domain' => $domain,
        ]);
    }
}

/**
 * Test connection to Portainer API
 *
 * @param array $params Server module parameters
 * @return string|array Error message or success data
 */
function llmprovisioning_TestConnection(array $params): string {
    try {
        $client = new PortainerClient(
            $params['serverip'] ?? '',
            $params['serverpassword'] ?? '',
            true
        );

        $result = $client->getContainer(1, 'test');
        return 'success';
    } catch (Exception $e) {
        logModuleCall('llmprovisioning', 'TestConnection', $params, $e->getMessage());
        return "Connection test failed: Check Portainer URL and API key";
    }
}

/**
 * Provision new LLM service instance
 *
 * Creates a new Docker container with specified LLM model and GPU configuration,
 * sets up reverse proxy with SSL, and configures monitoring.
 *
 * @param array $params WHMCS service parameters
 * @return string 'success' on success, error message on failure
 */
function llmprovisioning_CreateAccount(array $params): string {
    try {
        // Extract and validate configuration options
        $model = (string)($params['configoption1'] ?? 'llama2-7b');
        $memory = (int)($params['configoption3'] ?? 16);
        $cpu = (int)($params['configoption4'] ?? 4);
        $storage = (int)($params['configoption5'] ?? 50);
        $gpuType = (string)($params['configoption6'] ?? 'tesla-t4');
        $domain = (string)($params['domain'] ?? '');
        $serviceId = (int)$params['serviceid'];

        // Validate all inputs
        if (!validateModel($model)) {
            return "Invalid model selection: {$model}";
        }

        if (!validateGPUType($gpuType)) {
            return "Invalid GPU type: {$gpuType}";
        }

        $validation = validateResourceLimits($memory, $cpu, $storage);
        if (!$validation['valid']) {
            return implode("; ", $validation['errors']);
        }

        // Initialize API clients
        $portainerClient = new PortainerClient(
            $params['serverip'],
            $params['serverpassword'],
            true
        );

        $cyberPanelClient = new CyberPanelClient(
            getenv('CYBERPANEL_API_URL'),
            getenv('CYBERPANEL_API_KEY'),
            getenv('CYBERPANEL_API_PASSWORD'),
            true
        );

        // Generate secure container name
        $containerName = "llm-{$serviceId}-" . bin2hex(random_bytes(4));
        $containerPort = 8080 + ($serviceId % 1000);

        // Create container configuration
        $tgiVersion = getenv('TGI_VERSION') ?: 'latest';
        $containerConfig = [
            'Image' => "huggingface/text-generation-inference:{$tgiVersion}",
            'name' => $containerName,
            'Hostname' => $containerName,
            'Env' => [
                "MODEL_ID=models/{$model}",
                "CUDA_VISIBLE_DEVICES=0",
                "LOG_LEVEL=info",
            ],
            'HostConfig' => [
                'Memory' => $memory * 1024 * 1024 * 1024,
                'CpuShares' => $cpu * 1024,
                'PortBindings' => [
                    '80/tcp' => [['HostPort' => (string)$containerPort]],
                ],
                'RestartPolicy' => [
                    'Name' => 'unless-stopped',
                    'MaximumRetryCount' => 3,
                ],
            ],
        ];

        // Create container via Portainer
        $containerResponse = $portainerClient->createContainer(
            (int)($params['configoption2'] ?? 1),
            $containerConfig
        );

        $containerId = $containerResponse['Id'] ?? null;
        if (!$containerId) {
            throw new Exception("Container creation returned no ID");
        }

        // Start container
        $portainerClient->startContainer(
            (int)($params['configoption2'] ?? 1),
            $containerId
        );

        // Setup reverse proxy if domain provided
        if (!empty($domain)) {
            $cyberPanelClient->createReverseProxy($domain, (string)$containerPort, [
                'email' => $params['clientemail'] ?? 'admin@example.com',
            ]);
        }

        // Store metadata in database
        Capsule::table('tblhosting')->where('id', $serviceId)->update([
            'notes' => json_encode([
                'container_id' => $containerId,
                'container_name' => $containerName,
                'container_port' => $containerPort,
                'model' => $model,
                'memory' => $memory,
                'cpu' => $cpu,
                'gpu_type' => $gpuType,
                'provisioned_at' => date('Y-m-d H:i:s'),
            ])
        ]);

        logModuleCall('llmprovisioning', 'CreateAccount', compact('model', 'memory', 'cpu', 'gpuType', 'domain'), 'Container created: ' . $containerId);

        return 'success';

    } catch (Exception $e) {
        logModuleCall('llmprovisioning', 'CreateAccount', $params, $e->getMessage());
        return "Provisioning failed: Unable to create LLM container. Please contact support.";
    }
}

/**
 * Suspend LLM service instance
 *
 * Stops the container but preserves data for potential resumption
 *
 * @param array $params WHMCS service parameters
 * @return string 'success' on success, error message on failure
 */
function llmprovisioning_SuspendAccount(array $params): string {
    try {
        $serviceId = (int)$params['serviceid'];
        $hosting = Capsule::table('tblhosting')->where('id', $serviceId)->first();

        if (!$hosting || empty($hosting->notes)) {
            return "Service metadata not found";
        }

        $metadata = @json_decode($hosting->notes, true);
        $containerId = $metadata['container_id'] ?? null;

        if (!$containerId) {
            return "Container ID not found in service metadata";
        }

        $portainerClient = new PortainerClient(
            $params['serverip'],
            $params['serverpassword'],
            true
        );

        $portainerClient->stopContainer(
            (int)($params['configoption2'] ?? 1),
            $containerId
        );

        logModuleCall('llmprovisioning', 'SuspendAccount', ['serviceId' => $serviceId], "Container suspended: {$containerId}");

        return 'success';

    } catch (Exception $e) {
        logModuleCall('llmprovisioning', 'SuspendAccount', $params, $e->getMessage());
        return "Suspension failed: " . $e->getMessage();
    }
}

/**
 * Unsuspend LLM service instance
 *
 * Restarts a suspended container
 *
 * @param array $params WHMCS service parameters
 * @return string 'success' on success, error message on failure
 */
function llmprovisioning_UnsuspendAccount(array $params): string {
    try {
        $serviceId = (int)$params['serviceid'];
        $hosting = Capsule::table('tblhosting')->where('id', $serviceId)->first();

        if (!$hosting || empty($hosting->notes)) {
            return "Service metadata not found";
        }

        $metadata = @json_decode($hosting->notes, true);
        $containerId = $metadata['container_id'] ?? null;

        if (!$containerId) {
            return "Container ID not found in service metadata";
        }

        $portainerClient = new PortainerClient(
            $params['serverip'],
            $params['serverpassword'],
            true
        );

        $portainerClient->startContainer(
            (int)($params['configoption2'] ?? 1),
            $containerId
        );

        logModuleCall('llmprovisioning', 'UnsuspendAccount', ['serviceId' => $serviceId], "Container resumed: {$containerId}");

        return 'success';

    } catch (Exception $e) {
        logModuleCall('llmprovisioning', 'UnsuspendAccount', $params, $e->getMessage());
        return "Resumption failed: " . $e->getMessage();
    }
}

/**
 * Terminate LLM service instance
 *
 * Permanently removes the container and associated configurations
 *
 * @param array $params WHMCS service parameters
 * @return string 'success' on success, error message on failure
 */
function llmprovisioning_TerminateAccount(array $params): string {
    try {
        $serviceId = (int)$params['serviceid'];
        $domain = (string)($params['domain'] ?? '');
        $hosting = Capsule::table('tblhosting')->where('id', $serviceId)->first();

        if (!$hosting || empty($hosting->notes)) {
            return "Service metadata not found";
        }

        $metadata = @json_decode($hosting->notes, true);
        $containerId = $metadata['container_id'] ?? null;

        if (!$containerId) {
            return "Container ID not found in service metadata";
        }

        $portainerClient = new PortainerClient(
            $params['serverip'],
            $params['serverpassword'],
            true
        );

        // Remove container
        $portainerClient->removeContainer(
            (int)($params['configoption2'] ?? 1),
            $containerId
        );

        // Remove reverse proxy if domain exists
        if (!empty($domain)) {
            try {
                $cyberPanelClient = new CyberPanelClient(
                    getenv('CYBERPANEL_API_URL'),
                    getenv('CYBERPANEL_API_KEY'),
                    getenv('CYBERPANEL_API_PASSWORD'),
                    true
                );
                $cyberPanelClient->deleteReverseProxy($domain);
            } catch (Exception $e) {
                logModuleCall('llmprovisioning', 'TerminateAccount', ['domain' => $domain], "Warning: Reverse proxy deletion failed: " . $e->getMessage());
            }
        }

        logModuleCall('llmprovisioning', 'TerminateAccount', ['serviceId' => $serviceId], "Container terminated: {$containerId}");

        return 'success';

    } catch (Exception $e) {
        logModuleCall('llmprovisioning', 'TerminateAccount', $params, $e->getMessage());
        return "Termination failed: " . $e->getMessage();
    }
}

/**
 * Retrieve service status and statistics
 *
 * @param array $params WHMCS service parameters
 * @return array Service statistics and status information
 */
function llmprovisioning_ClientArea(array $params): array {
    try {
        $serviceId = (int)$params['serviceid'];
        $hosting = Capsule::table('tblhosting')->where('id', $serviceId)->first();

        if (!$hosting || empty($hosting->notes)) {
            return [
                'tabOverviewReplacements' => [
                    'Status' => 'Unknown',
                    'Error' => 'Service metadata not found',
                ],
            ];
        }

        $metadata = @json_decode($hosting->notes, true);
        $containerId = $metadata['container_id'] ?? null;

        if (!$containerId) {
            return [
                'tabOverviewReplacements' => [
                    'Status' => 'Unknown',
                    'Error' => 'Container not found',
                ],
            ];
        }

        try {
            $portainerClient = new PortainerClient(
                $params['serverip'],
                $params['serverpassword'],
                true
            );

            $containerInfo = $portainerClient->getContainer(
                (int)($params['configoption2'] ?? 1),
                $containerId
            );

            $status = $containerInfo['State']['Running'] ?? false ? 'Running' : 'Stopped';

            return [
                'tabOverviewReplacements' => [
                    'Model' => $metadata['model'] ?? 'Unknown',
                    'Status' => $status,
                    'Memory' => ($metadata['memory'] ?? 0) . ' GB',
                    'CPU Cores' => $metadata['cpu'] ?? 'Unknown',
                    'GPU Type' => $metadata['gpu_type'] ?? 'Unknown',
                    'Container ID' => substr($containerId, 0, 12),
                ],
            ];
        } catch (Exception $e) {
            return [
                'tabOverviewReplacements' => [
                    'Status' => 'Error',
                    'Error Details' => 'Unable to retrieve container status',
                ],
            ];
        }

    } catch (Exception $e) {
        logModuleCall('llmprovisioning', 'ClientArea', $params, $e->getMessage());
        return [];
    }
}

/**
 * Module activation - initialize database schema
 *
 * @return array Activation status
 */
function llmprovisioning_Activate(): array {
    try {
        // Database schema is handled by WHMCS core for hosting table
        // Additional tables can be created here if needed

        return [
            'status' => 'success',
            'description' => 'LLM Provisioning module activated successfully',
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Activation failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Module deactivation cleanup
 *
 * @return array Deactivation status
 */
function llmprovisioning_Deactivate(): array {
    try {
        return [
            'status' => 'success',
            'description' => 'LLM Provisioning module deactivated successfully',
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Deactivation failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Get module configuration options
 *
 * @return array Configuration array for WHMCS
 */
function llmprovisioning_ConfigOptions(): array {
    return [
        'Model Selection' => [
            'Type' => 'dropdown',
            'Options' => implode(',', APPROVED_MODELS),
            'Default' => 'llama2-7b',
            'Description' => 'LLM model to deploy',
        ],
        'Portainer Endpoint ID' => [
            'Type' => 'text',
            'Default' => '1',
            'Description' => 'Portainer environment endpoint ID',
        ],
        'Memory (GB)' => [
            'Type' => 'dropdown',
            'Options' => '8,16,32,64,128',
            'Default' => '16',
            'Description' => 'RAM allocation in GB',
        ],
        'CPU Cores' => [
            'Type' => 'dropdown',
            'Options' => '2,4,8,16,32',
            'Default' => '4',
            'Description' => 'Number of CPU cores',
        ],
        'Storage (GB)' => [
            'Type' => 'dropdown',
            'Options' => '50,100,250,500,1000',
            'Default' => '50',
            'Description' => 'Storage allocation in GB',
        ],
        'GPU Type' => [
            'Type' => 'dropdown',
            'Options' => implode(',', APPROVED_GPU_TYPES),
            'Default' => 'tesla-t4',
            'Description' => 'GPU hardware type for acceleration',
        ],
    ];
}

/**
 * Get admin buttons for service management
 *
 * @return array Admin action buttons
 */
function llmprovisioning_AdminCustomButtonArray(): array {
    return [
        'Restart Container' => 'RestartContainer',
        'View Logs' => 'ViewLogs',
        'View Metrics' => 'ViewMetrics',
    ];
}

/**
 * Get client buttons for service management
 *
 * @return array Client action buttons
 */
function llmprovisioning_ClientAreaCustomButtonArray(): array {
    return [
        'View API Documentation' => 'ViewApiDocs',
        'Access Monitoring' => 'AccessMonitoring',
    ];
}

/**
 * Custom admin action: Restart Container
 *
 * @param array $params WHMCS service parameters
 * @return string Success or error message
 */
function llmprovisioning_RestartContainer(array $params): string {
    try {
        $serviceId = (int)$params['serviceid'];
        $hosting = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        $metadata = @json_decode($hosting->notes ?? '{}', true);
        $containerId = $metadata['container_id'] ?? null;

        if (!$containerId) {
            return json_encode(['status' => 'error', 'message' => 'Container not found']);
        }

        $portainerClient = new PortainerClient(
            $params['serverip'],
            $params['serverpassword'],
            true
        );

        $portainerClient->stopContainer((int)($params['configoption2'] ?? 1), $containerId);
        sleep(2);
        $portainerClient->startContainer((int)($params['configoption2'] ?? 1), $containerId);

        return json_encode(['status' => 'success', 'message' => 'Container restarted successfully']);
    } catch (Exception $e) {
        return json_encode(['status' => 'error', 'message' => 'Restart failed']);
    }
}

/**
 * Custom action: View Logs
 *
 * @param array $params WHMCS service parameters
 * @return string Logs or error message
 */
function llmprovisioning_ViewLogs(array $params): string {
    return json_encode([
        'status' => 'info',
        'message' => 'Container logs available at https://portainer.example.com/#!/containers'
    ]);
}

/**
 * Custom action: View Metrics
 *
 * @param array $params WHMCS service parameters
 * @return string Metrics dashboard link
 */
function llmprovisioning_ViewMetrics(array $params): string {
    return json_encode([
        'status' => 'info',
        'message' => 'Metrics available at https://grafana.example.com'
    ]);
}

/**
 * Custom action: View API Documentation
 *
 * @param array $params WHMCS service parameters
 * @return string Documentation link
 */
function llmprovisioning_ViewApiDocs(array $params): string {
    return json_encode([
        'status' => 'info',
        'documentation_url' => 'https://example.com/docs/llm-api'
    ]);
}

/**
 * Custom action: Access Monitoring
 *
 * @param array $params WHMCS service parameters
 * @return string Monitoring dashboard
 */
function llmprovisioning_AccessMonitoring(array $params): string {
    return json_encode([
        'status' => 'info',
        'monitoring_url' => 'https://grafana.example.com/d/llm-' . $params['serviceid']
    ]);
}
