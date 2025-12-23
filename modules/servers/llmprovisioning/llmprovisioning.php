<?php
/**
 * WHMCS LLM Container Provisioning Module
 *
 * @copyright Copyright (c) 2024
 * @license MIT License
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Module metadata
 */
function llmprovisioning_MetaData()
{
    return [
        'DisplayName' => 'LLM Container Provisioning',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '9000',
        'DefaultSSLPort' => '9443',
        'ServiceSingleSignOnLabel' => 'Login to Portainer',
        'AdminSingleSignOnLabel' => 'Login to Portainer as Admin',
    ];
}

/**
 * Configuration options
 */
function llmprovisioning_ConfigOptions()
{
    return [
        'llm_model' => [
            'FriendlyName' => 'LLM Model',
            'Type' => 'dropdown',
            'Options' => [
                'llama2-7b' => 'Llama 2 7B',
                'llama2-13b' => 'Llama 2 13B',
                'mistral-7b' => 'Mistral 7B',
                'codellama-7b' => 'CodeLlama 7B',
                'phi-2' => 'Phi-2 2.7B',
                'custom' => 'Custom Model',
            ],
            'Default' => 'llama2-7b',
            'Description' => 'Select the LLM model to deploy',
        ],
        'gpu_type' => [
            'FriendlyName' => 'GPU Configuration',
            'Type' => 'dropdown',
            'Options' => [
                'nvidia-t4' => 'NVIDIA T4 (16GB)',
                'nvidia-a10' => 'NVIDIA A10 (24GB)',
                'nvidia-a100' => 'NVIDIA A100 (40GB)',
                'nvidia-a100-80' => 'NVIDIA A100 (80GB)',
                'none' => 'CPU Only',
            ],
            'Default' => 'nvidia-t4',
            'Description' => 'GPU type for inference acceleration',
        ],
        'memory_limit' => [
            'FriendlyName' => 'Memory Limit (GB)',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '8',
            'Description' => 'Container memory limit in GB',
        ],
        'cpu_limit' => [
            'FriendlyName' => 'CPU Cores',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '4',
            'Description' => 'Number of CPU cores to allocate',
        ],
        'storage_size' => [
            'FriendlyName' => 'Storage Size (GB)',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '50',
            'Description' => 'Storage volume size in GB',
        ],
        'enable_api' => [
            'FriendlyName' => 'Enable API',
            'Type' => 'yesno',
            'Default' => 'yes',
            'Description' => 'Enable REST API endpoint',
        ],
        'enable_monitoring' => [
            'FriendlyName' => 'Enable Monitoring',
            'Type' => 'yesno',
            'Default' => 'yes',
            'Description' => 'Enable Prometheus monitoring',
        ],
    ];
}

/**
 * Create a new container instance
 */
function llmprovisioning_CreateAccount(array $params)
{
    try {
        $portainerUrl = $params['serverhostname'];
        $portainerApiKey = $params['serverpassword'];
        $portainerPort = $params['serverport'] ?: 9443;
        
        $containerName = 'llm-' . $params['serviceid'];
        $model = $params['configoption1'];
        $gpuType = $params['configoption2'];
        $memoryLimit = (int)$params['configoption3'];
        $cpuLimit = (int)$params['configoption4'];
        $storageSize = (int)$params['configoption5'];
        $enableApi = $params['configoption6'] === 'on';
        $enableMonitoring = $params['configoption7'] === 'on';
        
        // Initialize Portainer API client
        $portainerClient = new PortainerClient($portainerUrl, $portainerApiKey, $portainerPort);
        
        // Create volume for model storage
        $volumeName = $containerName . '-data';
        $portainerClient->createVolume($volumeName, $storageSize);
        
        // Prepare container configuration
        $containerConfig = [
            'name' => $containerName,
            'image' => getModelImage($model),
            'env' => [
                'MODEL_NAME=' . $model,
                'GPU_TYPE=' . $gpuType,
                'API_ENABLED=' . ($enableApi ? 'true' : 'false'),
            ],
            'resources' => [
                'memory' => $memoryLimit * 1024 * 1024 * 1024,
                'cpu' => $cpuLimit,
            ],
            'volumes' => [
                $volumeName . ':/models',
            ],
            'ports' => [
                ['host' => 0, 'container' => 8000, 'protocol' => 'tcp'],
            ],
            'labels' => [
                'whmcs.service.id' => $params['serviceid'],
                'whmcs.client.id' => $params['clientsdetails']['userid'],
            ],
        ];
        
        // Add GPU runtime if applicable
        if ($gpuType !== 'none') {
            $containerConfig['runtime'] = 'nvidia';
            $containerConfig['env'][] = 'NVIDIA_VISIBLE_DEVICES=all';
        }
        
        // Create container via Portainer
        $container = $portainerClient->createContainer($containerConfig);
        
        // Start the container
        $portainerClient->startContainer($container['Id']);
        
        // Get assigned port
        $containerInfo = $portainerClient->inspectContainer($container['Id']);
        $assignedPort = $containerInfo['NetworkSettings']['Ports']['8000/tcp'][0]['HostPort'];
        
        // Store container metadata
        $metadata = [
            'container_id' => $container['Id'],
            'container_name' => $containerName,
            'assigned_port' => $assignedPort,
            'volume_name' => $volumeName,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        // Update custom fields or database
        saveContainerMetadata($params['serviceid'], $metadata);
        
        // Integrate with CyberPanel if configured
        if (!empty($params['customfields']['cyberpanel_domain'])) {
            integrateCyberPanel($params, $assignedPort);
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('llmprovisioning', 'CreateAccount', $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

/**
 * Suspend a container
 */
function llmprovisioning_SuspendAccount(array $params)
{
    try {
        $portainerUrl = $params['serverhostname'];
        $portainerApiKey = $params['serverpassword'];
        $portainerPort = $params['serverport'] ?: 9443;
        
        $portainerClient = new PortainerClient($portainerUrl, $portainerApiKey, $portainerPort);
        $metadata = getContainerMetadata($params['serviceid']);
        
        if (!empty($metadata['container_id'])) {
            $portainerClient->stopContainer($metadata['container_id']);
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('llmprovisioning', 'SuspendAccount', $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

/**
 * Unsuspend a container
 */
function llmprovisioning_UnsuspendAccount(array $params)
{
    try {
        $portainerUrl = $params['serverhostname'];
        $portainerApiKey = $params['serverpassword'];
        $portainerPort = $params['serverport'] ?: 9443;
        
        $portainerClient = new PortainerClient($portainerUrl, $portainerApiKey, $portainerPort);
        $metadata = getContainerMetadata($params['serviceid']);
        
        if (!empty($metadata['container_id'])) {
            $portainerClient->startContainer($metadata['container_id']);
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('llmprovisioning', 'UnsuspendAccount', $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

/**
 * Terminate and remove a container
 */
function llmprovisioning_TerminateAccount(array $params)
{
    try {
        $portainerUrl = $params['serverhostname'];
        $portainerApiKey = $params['serverpassword'];
        $portainerPort = $params['serverport'] ?: 9443;
        
        $portainerClient = new PortainerClient($portainerUrl, $portainerApiKey, $portainerPort);
        $metadata = getContainerMetadata($params['serviceid']);
        
        if (!empty($metadata['container_id'])) {
            // Stop and remove container
            $portainerClient->stopContainer($metadata['container_id']);
            $portainerClient->removeContainer($metadata['container_id']);
            
            // Remove volume
            if (!empty($metadata['volume_name'])) {
                $portainerClient->removeVolume($metadata['volume_name']);
            }
        }
        
        // Clean up CyberPanel integration
        if (!empty($params['customfields']['cyberpanel_domain'])) {
            cleanupCyberPanel($params);
        }
        
        // Remove metadata
        deleteContainerMetadata($params['serviceid']);
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('llmprovisioning', 'TerminateAccount', $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

/**
 * Admin area custom button array
 */
function llmprovisioning_AdminCustomButtonArray()
{
    return [
        'Restart Container' => 'RestartContainer',
        'View Logs' => 'ViewLogs',
        'Update Model' => 'UpdateModel',
    ];
}

/**
 * Client area custom button array
 */
function llmprovisioning_ClientAreaCustomButtonArray()
{
    return [
        'Restart Container' => 'RestartContainer',
        'View Stats' => 'ViewStats',
    ];
}

/**
 * Restart container function
 */
function llmprovisioning_RestartContainer(array $params)
{
    try {
        $portainerUrl = $params['serverhostname'];
        $portainerApiKey = $params['serverpassword'];
        $portainerPort = $params['serverport'] ?: 9443;
        
        $portainerClient = new PortainerClient($portainerUrl, $portainerApiKey, $portainerPort);
        $metadata = getContainerMetadata($params['serviceid']);
        
        if (!empty($metadata['container_id'])) {
            $portainerClient->restartContainer($metadata['container_id']);
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('llmprovisioning', 'RestartContainer', $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

/**
 * Helper function to get model Docker image
 */
function getModelImage($model)
{
    $images = [
        'llama2-7b' => 'ghcr.io/huggingface/text-generation-inference:latest',
        'llama2-13b' => 'ghcr.io/huggingface/text-generation-inference:latest',
        'mistral-7b' => 'ghcr.io/huggingface/text-generation-inference:latest',
        'codellama-7b' => 'ghcr.io/huggingface/text-generation-inference:latest',
        'phi-2' => 'ghcr.io/huggingface/text-generation-inference:latest',
    ];
    
    return $images[$model] ?? 'ghcr.io/huggingface/text-generation-inference:latest';
}

/**
 * Helper functions for metadata management
 */
function saveContainerMetadata($serviceId, $metadata)
{
    $table = 'mod_llmprovisioning_containers';
    
    $existingId = Capsule::table($table)
        ->where('service_id', $serviceId)
        ->value('id');
    
    if ($existingId) {
        Capsule::table($table)
            ->where('service_id', $serviceId)
            ->update([
                'container_id' => $metadata['container_id'],
                'container_name' => $metadata['container_name'],
                'assigned_port' => $metadata['assigned_port'],
                'volume_name' => $metadata['volume_name'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    } else {
        Capsule::table($table)->insert([
            'service_id' => $serviceId,
            'container_id' => $metadata['container_id'],
            'container_name' => $metadata['container_name'],
            'assigned_port' => $metadata['assigned_port'],
            'volume_name' => $metadata['volume_name'],
            'created_at' => $metadata['created_at'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

function getContainerMetadata($serviceId)
{
    $table = 'mod_llmprovisioning_containers';
    
    $result = Capsule::table($table)
        ->where('service_id', $serviceId)
        ->first();
    
    return $result ? (array)$result : [];
}

function deleteContainerMetadata($serviceId)
{
    $table = 'mod_llmprovisioning_containers';
    
    Capsule::table($table)
        ->where('service_id', $serviceId)
        ->delete();
}

/**
 * CyberPanel integration functions
 */
function integrateCyberPanel($params, $assignedPort)
{
    $domain = $params['customfields']['cyberpanel_domain'];
    $cyberPanelUrl = $params['customfields']['cyberpanel_url'] ?? 'https://cyberpanel.local:8090';
    $cyberPanelToken = $params['customfields']['cyberpanel_token'];
    
    if (empty($domain) || empty($cyberPanelToken)) {
        return;
    }
    
    $client = new CyberPanelClient($cyberPanelUrl, $cyberPanelToken);
    
    // Create reverse proxy configuration
    $proxyConfig = [
        'domain' => $domain,
        'target' => 'http://localhost:' . $assignedPort,
        'ssl' => true,
    ];
    
    $client->createReverseProxy($proxyConfig);
}

function cleanupCyberPanel($params)
{
    $domain = $params['customfields']['cyberpanel_domain'];
    $cyberPanelUrl = $params['customfields']['cyberpanel_url'] ?? 'https://cyberpanel.local:8090';
    $cyberPanelToken = $params['customfields']['cyberpanel_token'];
    
    if (empty($domain) || empty($cyberPanelToken)) {
        return;
    }
    
    $client = new CyberPanelClient($cyberPanelUrl, $cyberPanelToken);
    $client->removeReverseProxy($domain);
}

/**
 * Portainer API Client Class
 */
class PortainerClient
{
    private $baseUrl;
    private $apiKey;
    private $endpointId = 1;
    
    public function __construct($hostname, $apiKey, $port = 9443)
    {
        $this->baseUrl = "https://{$hostname}:{$port}/api";
        $this->apiKey = $apiKey;
    }
    
    private function request($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: application/json',
        ]);
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("Portainer API error: HTTP {$httpCode} - {$response}");
        }
        
        return json_decode($response, true);
    }
    
    public function createVolume($name, $sizeGb)
    {
        return $this->request('POST', "/endpoints/{$this->endpointId}/docker/volumes/create", [
            'Name' => $name,
            'Driver' => 'local',
            'DriverOpts' => [
                'size' => $sizeGb . 'G',
            ],
        ]);
    }
    
    public function removeVolume($name)
    {
        return $this->request('DELETE', "/endpoints/{$this->endpointId}/docker/volumes/{$name}");
    }
    
    public function createContainer($config)
    {
        $portainerConfig = [
            'Image' => $config['image'],
            'Hostname' => $config['name'],
            'Env' => $config['env'],
            'HostConfig' => [
                'Memory' => $config['resources']['memory'],
                'NanoCpus' => $config['resources']['cpu'] * 1000000000,
                'Binds' => $config['volumes'],
                'PortBindings' => [],
                'RestartPolicy' => [
                    'Name' => 'unless-stopped',
                ],
            ],
            'Labels' => $config['labels'],
        ];
        
        // Configure port bindings
        foreach ($config['ports'] as $port) {
            $containerPort = $port['container'] . '/' . $port['protocol'];
            $portainerConfig['HostConfig']['PortBindings'][$containerPort] = [
                ['HostPort' => (string)$port['host']],
            ];
        }
        
        // Add GPU runtime if specified
        if (!empty($config['runtime'])) {
            $portainerConfig['HostConfig']['Runtime'] = $config['runtime'];
        }
        
        return $this->request('POST', "/endpoints/{$this->endpointId}/docker/containers/create?name={$config['name']}", $portainerConfig);
    }
    
    public function startContainer($containerId)
    {
        return $this->request('POST', "/endpoints/{$this->endpointId}/docker/containers/{$containerId}/start");
    }
    
    public function stopContainer($containerId)
    {
        return $this->request('POST', "/endpoints/{$this->endpointId}/docker/containers/{$containerId}/stop");
    }
    
    public function restartContainer($containerId)
    {
        return $this->request('POST', "/endpoints/{$this->endpointId}/docker/containers/{$containerId}/restart");
    }
    
    public function removeContainer($containerId)
    {
        return $this->request('DELETE', "/endpoints/{$this->endpointId}/docker/containers/{$containerId}?force=true");
    }
    
    public function inspectContainer($containerId)
    {
        return $this->request('GET', "/endpoints/{$this->endpointId}/docker/containers/{$containerId}/json");
    }
}

/**
 * CyberPanel API Client Class
 */
class CyberPanelClient
{
    private $baseUrl;
    private $token;
    
    public function __construct($baseUrl, $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
    }
    
    private function request($endpoint, $data)
    {
        $url = $this->baseUrl . $endpoint;
        
        $data['token'] = $this->token;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("CyberPanel API error: HTTP {$httpCode}");
        }
        
        return json_decode($response, true);
    }
    
    public function createReverseProxy($config)
    {
        return $this->request('/api/createProxyRule', [
            'domain' => $config['domain'],
            'url' => $config['target'],
            'ssl' => $config['ssl'] ? 1 : 0,
        ]);
    }
    
    public function removeReverseProxy($domain)
    {
        return $this->request('/api/deleteProxyRule', [
            'domain' => $domain,
        ]);
    }
}

/**
 * Module activation hook - create database tables
 */
function llmprovisioning_activate()
{
    try {
        if (!Capsule::schema()->hasTable('mod_llmprovisioning_containers')) {
            Capsule::schema()->create('mod_llmprovisioning_containers', function ($table) {
                $table->increments('id');
                $table->integer('service_id')->unique();
                $table->string('container_id', 255);
                $table->string('container_name', 255);
                $table->integer('assigned_port');
                $table->string('volume_name', 255);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
        
        return [
            'status' => 'success',
            'description' => 'LLM Provisioning module activated successfully. Database tables created.',
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Unable to create module tables: ' . $e->getMessage(),
        ];
    }
}

/**
 * Module deactivation hook
 */
function llmprovisioning_deactivate()
{
    // Tables are left in place for data retention
    return [
        'status' => 'success',
        'description' => 'Module deactivated successfully. Database tables retained for data integrity.',
    ];
}

/**
 * Module upgrade hook
 */
function llmprovisioning_upgrade($vars)
{
    $currentlyInstalledVersion = $vars['version'];
    
    // Perform version-specific upgrades here
    
    return [];
}
