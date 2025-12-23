# API Documentation

## Overview

The WHMCS LLM Provisioning System provides several APIs for managing LLM containers:

1. **WHMCS Module API** - PHP-based API for WHMCS integration
2. **Portainer REST API** - Container management
3. **CyberPanel API** - Reverse proxy management
4. **Container REST API** - LLM inference endpoints

## WHMCS Module API

### Module Functions

#### llmprovisioning_CreateAccount

Creates a new LLM container with specified configuration.

**Parameters:**
```php
array $params = [
    'serviceid' => int,           // WHMCS service ID
    'serverhostname' => string,   // Portainer hostname
    'serverpassword' => string,   // Portainer API key
    'serverport' => int,          // Portainer port (default: 9443)
    'configoption1' => string,    // LLM model
    'configoption2' => string,    // GPU type
    'configoption3' => string,    // Memory limit (GB)
    'configoption4' => string,    // CPU cores
    'configoption5' => string,    // Storage size (GB)
    'configoption6' => string,    // Enable API
    'configoption7' => string,    // Enable monitoring
]
```

**Returns:**
- `'success'` on successful creation
- Error message string on failure

**Example:**
```php
$result = llmprovisioning_CreateAccount($params);
if ($result === 'success') {
    echo "Container created successfully";
}
```

#### llmprovisioning_SuspendAccount

Stops a running container without removing it.

**Parameters:**
```php
array $params = [
    'serviceid' => int,
    'serverhostname' => string,
    'serverpassword' => string,
    'serverport' => int,
]
```

**Returns:**
- `'success'` on successful suspension
- Error message string on failure

#### llmprovisioning_TerminateAccount

Stops and removes a container and its volumes.

**Parameters:**
```php
array $params = [
    'serviceid' => int,
    'serverhostname' => string,
    'serverpassword' => string,
    'serverport' => int,
]
```

**Returns:**
- `'success'` on successful termination
- Error message string on failure

#### llmprovisioning_RestartContainer

Restarts a container (custom button action).

**Parameters:**
```php
array $params = [
    'serviceid' => int,
    'serverhostname' => string,
    'serverpassword' => string,
    'serverport' => int,
]
```

**Returns:**
- `'success'` on successful restart
- Error message string on failure

## Portainer API

The system uses Portainer API v2 for container management.

### Base URL

```
https://{hostname}:{port}/api
```

### Authentication

All requests require an API key in the header:

```
X-API-Key: {your-api-key}
```

### Endpoints Used

#### Create Container

```http
POST /endpoints/{id}/docker/containers/create?name={name}
```

**Request Body:**
```json
{
  "Image": "ghcr.io/huggingface/text-generation-inference:latest",
  "Hostname": "llm-123",
  "Env": [
    "MODEL_ID=meta-llama/Llama-2-7b-chat-hf",
    "NUM_SHARD=1"
  ],
  "HostConfig": {
    "Memory": 8589934592,
    "NanoCpus": 4000000000,
    "Binds": ["llm-123-data:/data"],
    "PortBindings": {
      "8000/tcp": [{"HostPort": "0"}]
    },
    "Runtime": "nvidia"
  }
}
```

**Response:**
```json
{
  "Id": "container-id-here",
  "Warnings": []
}
```

#### Start Container

```http
POST /endpoints/{id}/docker/containers/{containerId}/start
```

#### Stop Container

```http
POST /endpoints/{id}/docker/containers/{containerId}/stop
```

#### Remove Container

```http
DELETE /endpoints/{id}/docker/containers/{containerId}?force=true
```

#### Inspect Container

```http
GET /endpoints/{id}/docker/containers/{containerId}/json
```

**Response:**
```json
{
  "Id": "container-id",
  "Name": "/llm-123",
  "State": {
    "Status": "running",
    "Running": true
  },
  "NetworkSettings": {
    "Ports": {
      "8000/tcp": [
        {
          "HostIp": "0.0.0.0",
          "HostPort": "32768"
        }
      ]
    }
  }
}
```

## CyberPanel API

### Base URL

```
https://{hostname}:8090/api
```

### Authentication

Include token in request body:

```json
{
  "token": "your-api-token"
}
```

### Endpoints

#### Create Reverse Proxy

```http
POST /api/createProxyRule
```

**Request:**
```json
{
  "token": "your-token",
  "domain": "llm-api.example.com",
  "url": "http://localhost:32768",
  "ssl": 1
}
```

**Response:**
```json
{
  "status": 1,
  "message": "Proxy rule created successfully"
}
```

#### Delete Reverse Proxy

```http
POST /api/deleteProxyRule
```

**Request:**
```json
{
  "token": "your-token",
  "domain": "llm-api.example.com"
}
```

#### Issue SSL Certificate

```http
POST /api/issueSSL
```

**Request:**
```json
{
  "token": "your-token",
  "domainName": "llm-api.example.com"
}
```

## LLM Container API

Each provisioned container exposes an inference API.

### Base URL

```
http://{hostname}:{assigned-port}
```

### Endpoints

#### Health Check

```http
GET /health
```

**Response:**
```json
{
  "status": "healthy",
  "model_id": "meta-llama/Llama-2-7b-chat-hf",
  "version": "1.0.0"
}
```

#### Generate Text

```http
POST /generate
```

**Request:**
```json
{
  "inputs": "What is the meaning of life?",
  "parameters": {
    "max_new_tokens": 100,
    "temperature": 0.7,
    "top_p": 0.95,
    "do_sample": true
  }
}
```

**Response:**
```json
{
  "generated_text": "The meaning of life is...",
  "details": {
    "finish_reason": "length",
    "generated_tokens": 100,
    "seed": null
  }
}
```

#### Stream Generate

```http
POST /generate_stream
```

Streams response as Server-Sent Events (SSE).

**Request:**
```json
{
  "inputs": "Tell me a story",
  "parameters": {
    "max_new_tokens": 200,
    "temperature": 0.8
  }
}
```

**Response Stream:**
```
data: {"token": {"text": "Once"}}

data: {"token": {"text": " upon"}}

data: {"token": {"text": " a"}}

data: {"generated_text": "Once upon a time...", "details": {...}}
```

#### Metrics

```http
GET /metrics
```

Returns Prometheus metrics.

**Response:**
```
# HELP tgi_request_duration_seconds Request duration
# TYPE tgi_request_duration_seconds histogram
tgi_request_duration_seconds_bucket{le="0.1"} 42
tgi_request_duration_seconds_bucket{le="0.5"} 89
...
```

#### Model Info

```http
GET /info
```

**Response:**
```json
{
  "model_id": "meta-llama/Llama-2-7b-chat-hf",
  "model_dtype": "float16",
  "model_device_type": "cuda",
  "max_input_length": 2048,
  "max_total_tokens": 4096,
  "waiting_served_ratio": 1.2,
  "max_batch_total_tokens": 8192
}
```

## Error Responses

All APIs return consistent error responses:

```json
{
  "error": "Error message",
  "error_type": "ValidationError",
  "details": "Additional details about the error"
}
```

### Common HTTP Status Codes

- `200 OK` - Request successful
- `201 Created` - Resource created
- `400 Bad Request` - Invalid parameters
- `401 Unauthorized` - Authentication failed
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - Server error
- `503 Service Unavailable` - Service temporarily unavailable

## Rate Limiting

### NGINX Rate Limits

- **API endpoints**: 10 requests/second per IP
- **Burst**: 20 requests
- **Connection limit**: 10 concurrent connections per IP

### Portainer Rate Limits

- Follows Portainer's default rate limiting
- Typically: 100 requests/minute per API key

## Authentication Examples

### PHP (WHMCS Module)

```php
$portainerClient = new PortainerClient(
    $hostname,
    $apiKey,
    $port
);

$container = $portainerClient->createContainer($config);
```

### cURL (Portainer)

```bash
curl -X POST \
  https://portainer.example.com:9443/api/endpoints/1/docker/containers/create \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "Image": "ghcr.io/huggingface/text-generation-inference:latest",
    "HostConfig": {
      "Memory": 8589934592
    }
  }'
```

### Python (LLM Inference)

```python
import requests

url = "http://llm-api.example.com:8000/generate"
headers = {"Content-Type": "application/json"}
data = {
    "inputs": "What is AI?",
    "parameters": {
        "max_new_tokens": 100,
        "temperature": 0.7
    }
}

response = requests.post(url, json=data, headers=headers)
print(response.json()["generated_text"])
```

## Webhooks

### Container Status Webhooks

Configure webhook URLs in WHMCS custom fields to receive container status updates:

**Webhook Payload:**
```json
{
  "event": "container.status.changed",
  "service_id": 123,
  "container_id": "abc123",
  "status": "running",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

**Events:**
- `container.created`
- `container.started`
- `container.stopped`
- `container.removed`
- `container.status.changed`

## SDK Examples

### PHP Client Example

```php
<?php
require_once 'modules/servers/llmprovisioning/llmprovisioning.php';

// Initialize client
$client = new PortainerClient(
    'portainer.example.com',
    'your-api-key',
    9443
);

// Create container
$containerConfig = [
    'name' => 'llm-test',
    'image' => 'ghcr.io/huggingface/text-generation-inference:latest',
    'env' => ['MODEL_ID=meta-llama/Llama-2-7b-chat-hf'],
    'resources' => [
        'memory' => 8 * 1024 * 1024 * 1024,
        'cpu' => 4
    ],
    'volumes' => ['llm-data:/data'],
    'ports' => [
        ['host' => 0, 'container' => 8000, 'protocol' => 'tcp']
    ],
    'labels' => [
        'whmcs.service.id' => '123'
    ]
];

$container = $client->createContainer($containerConfig);
$client->startContainer($container['Id']);

// Get container info
$info = $client->inspectContainer($container['Id']);
echo "Container running on port: " . 
     $info['NetworkSettings']['Ports']['8000/tcp'][0]['HostPort'];
```

### Python Client Example

```python
import requests
from typing import Dict, Optional

class LLMClient:
    def __init__(self, base_url: str):
        self.base_url = base_url.rstrip('/')
    
    def generate(self, prompt: str, 
                 max_tokens: int = 100,
                 temperature: float = 0.7) -> str:
        """Generate text from prompt"""
        response = requests.post(
            f"{self.base_url}/generate",
            json={
                "inputs": prompt,
                "parameters": {
                    "max_new_tokens": max_tokens,
                    "temperature": temperature
                }
            }
        )
        response.raise_for_status()
        return response.json()["generated_text"]
    
    def health(self) -> Dict:
        """Check container health"""
        response = requests.get(f"{self.base_url}/health")
        response.raise_for_status()
        return response.json()

# Usage
client = LLMClient("http://llm-api.example.com:8000")
result = client.generate("What is machine learning?")
print(result)
```

## Best Practices

1. **Always check health endpoint** before making inference requests
2. **Implement retry logic** for transient failures
3. **Use streaming** for long responses to improve user experience
4. **Monitor rate limits** to avoid throttling
5. **Cache responses** when appropriate to reduce load
6. **Set appropriate timeouts** (300s recommended for inference)
7. **Handle errors gracefully** with proper error messages
8. **Use HTTPS** for all production deployments
9. **Rotate API keys regularly** for security
10. **Log all API calls** for debugging and monitoring
