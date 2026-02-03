# 3AG License API Documentation

**Base URL:** `https://3ag.app/api/v3`

This API allows plugins to validate, activate, and deactivate licenses for 3AG products.

---

## Authentication

All License API endpoints require a valid `license_key`, `product_slug`, and `domain` in the request body. No bearer token or API key header is needed.

---

## Rate Limiting

| Endpoint | Limit |
|----------|-------|
| `/licenses/validate` | 60 requests/minute |
| `/licenses/activate` | 20 requests/minute |
| `/licenses/deactivate` | 20 requests/minute |

---

## Endpoints

### 1. Validate License

Validates a license key, checks if the domain is activated, and returns complete license details. Use this for:
- Displaying license status on settings pages
- Periodic license verification (daily cron)
- Checking if activation is required

**Endpoint:** `POST /licenses/validate`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `license_key` | string | Yes | The license key to validate |
| `product_slug` | string | Yes | The product identifier (e.g., `nalda`) |
| `domain` | string | Yes | The domain to check activation for |

**Example Request:**

```json
{
  "license_key": "XXXX-XXXX-XXXX-XXXX",
  "product_slug": "nalda",
  "domain": "mystore.com"
}
```

**Success Response (200):**

```json
{
  "data": {
    "valid": true,
    "status": "active",
    "activated": true,
    "expires_at": "2025-12-31T23:59:59+00:00",
    "activations": {
      "limit": 3,
      "used": 2
    },
    "product": "Nalda Integration",
    "package": "Professional"
  }
}
```

**Response Fields:**

| Field | Description |
|-------|-------------|
| `valid` | `true` if the license is active and not expired |
| `activated` | `true` if this specific domain is activated |

**Error Responses:**

| Status | Message | Description |
|--------|---------|-------------|
| 401 | `Invalid license key.` | License key not found or product doesn't exist |
| 422 | Validation errors | Missing or invalid fields |

---

### 2. Activate License

Activates a license for a specific domain. Call this when the plugin is first installed or when a user enters a license key.

**Endpoint:** `POST /licenses/activate`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `license_key` | string | Yes | The license key |
| `product_slug` | string | Yes | The product identifier |
| `domain` | string | Yes | The domain to activate (e.g., `example.com`) |

**Example Request:**

```json
{
  "license_key": "XXXX-XXXX-XXXX-XXXX",
  "product_slug": "nalda",
  "domain": "mystore.com"
}
```

**Success Response (201 - New Activation):**

```json
{
  "data": {
    "valid": true,
    "status": "active",
    "expires_at": "2025-12-31T23:59:59+00:00",
    "activations": {
      "limit": 3,
      "used": 2
    },
    "product": "Nalda Integration",
    "package": "Professional"
  }
}
```

**Success Response (200 - Already Activated):**

If the domain is already activated, the endpoint returns the current license state without creating a duplicate activation.

```json
{
  "data": {
    "valid": true,
    "status": "active",
    "expires_at": "2025-12-31T23:59:59+00:00",
    "activations": {
      "limit": 3,
      "used": 2
    },
    "product": "Nalda Integration",
    "package": "Professional"
  }
}
```

**Error Responses:**

| Status | Message | Description |
|--------|---------|-------------|
| 401 | `Invalid license key.` | License key not found |
| 403 | `License is not active.` | License is suspended, cancelled, or expired |
| 403 | `Domain limit reached. Maximum X domain(s) allowed.` | No more activations available |
| 422 | Validation errors | Missing or invalid fields |

---

### 3. Deactivate License

Removes a domain activation. Call this when the plugin is deactivated or uninstalled.

**Endpoint:** `POST /licenses/deactivate`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `license_key` | string | Yes | The license key |
| `product_slug` | string | Yes | The product identifier |
| `domain` | string | Yes | The domain to deactivate |

**Example Request:**

```json
{
  "license_key": "XXXX-XXXX-XXXX-XXXX",
  "product_slug": "nalda",
  "domain": "mystore.com"
}
```

**Success Response (204 No Content):**

Empty response body on successful deactivation.

**Error Responses:**

| Status | Message | Description |
|--------|---------|-------------|
| 401 | `Invalid license key.` | License key not found |
| 404 | `No active activation found for this domain.` | Domain is not currently activated |
| 422 | Validation errors | Missing or invalid fields |

---

## Implementation Guide

### Recommended Plugin Flow

1. **On Plugin Activation / Settings Save:**
   - Call `/licenses/activate` with the license key and current domain
   - Store activation status locally (use transients/cache)
   - Show error to user if activation fails

2. **On Plugin Settings Page Load:**
   - Call `/licenses/validate` to get license details and activation status
   - Display license info (expiry, activations used, etc.)
   - Show "Activate" button if `activated: false`

3. **Periodic Verification (Daily Cron):**
   - Call `/licenses/validate` to verify license is still valid
   - Check both `valid` and `activated` fields
   - Disable premium features if either is `false`
   - Notify user of license issues

4. **On Plugin Deactivation/Uninstall:**
   - Call `/licenses/deactivate` to free up the activation slot

### Example WordPress Integration

```php
class My_Plugin_License {
    
    public function check_license() {
        $response = wp_remote_post('https://3ag.app/api/v3/licenses/validate', [
            'body' => [
                'license_key'  => get_option('my_license_key'),
                'product_slug' => 'my-plugin',
                'domain'       => $this->get_domain(),
            ]
        ]);
        
        $data = json_decode(wp_remote_retrieve_body($response), true)['data'];
        
        if (!$data['valid']) {
            return 'invalid';  // License expired, suspended, or doesn't exist
        }
        
        if (!$data['activated']) {
            return 'not_activated';  // Valid license but not activated on this domain
        }
        
        return 'active';  // License is valid and activated
    }
    
    private function get_domain() {
        return wp_parse_url(home_url(), PHP_URL_HOST);
    }
}
```

### Domain Normalization

The API automatically normalizes domains:
- Removes `http://`, `https://`, `www.`
- Converts to lowercase
- Strips trailing slashes and paths

All these domains resolve to the same activation:
- `https://www.Example.com/shop/`
- `http://example.com`
- `EXAMPLE.COM`

### Error Handling

All error responses follow this format:

```json
{
  "message": "Error description here."
}
```

For validation errors (422):

```json
{
  "message": "The license key field is required.",
  "errors": {
    "license_key": ["The license key field is required."]
  }
}
```

---

## Response Field Reference

### License Data Object

| Field | Type | Description |
|-------|------|-------------|
| `valid` | boolean | `true` if the license is currently usable (active status and not expired) |
| `status` | string | License status: `active`, `paused`, `suspended`, `expired`, or `cancelled` |
| `activated` | boolean | `true` if the specified domain is activated for this license |
| `expires_at` | string\|null | ISO 8601 expiration date, or `null` for lifetime licenses |
| `activations.limit` | integer | Maximum allowed domain activations |
| `activations.used` | integer | Current number of active domains |
| `product` | string | Product name |
| `package` | string | Package/tier name |

### License Status Values

| Status | Description | `valid` |
|--------|-------------|------|
| `active` | License is valid and usable | `true` |
| `paused` | Subscription paused by user | `false` |
| `suspended` | Payment issue or manual suspension | `false` |
| `expired` | License has passed its expiration date | `false` |
| `cancelled` | Subscription cancelled | `false` |
