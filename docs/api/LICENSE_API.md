# 3AG License API Documentation

**Base URL:** `https://3ag.app/api/v3`

This API allows plugins to validate, activate, deactivate, and check license status for 3AG products.

---

## Authentication

All License API endpoints require a valid `license_key` and `product_slug` in the request body. No bearer token or API key header is needed.

---

## Rate Limiting

| Endpoint | Limit |
|----------|-------|
| `/licenses/validate` | 60 requests/minute |
| `/licenses/activate` | 20 requests/minute |
| `/licenses/deactivate` | 20 requests/minute |
| `/licenses/check` | 60 requests/minute |

---

## Endpoints

### 1. Validate License

Validates a license key and returns license details.

**Endpoint:** `POST /licenses/validate`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `license_key` | string | Yes | The license key to validate |
| `product_slug` | string | Yes | The product identifier (e.g., `nalda`) |

**Example Request:**

```json
{
  "license_key": "XXXX-XXXX-XXXX-XXXX",
  "product_slug": "nalda"
}
```

**Success Response (200):**

```json
{
  "data": {
    "expires_at": "2025-12-31T23:59:59+00:00",
    "activations": {
      "limit": 3,
      "used": 1
    },
    "product": "Nalda Integration",
    "package": "Professional"
  }
}
```

**Error Responses:**

| Status | Message | Description |
|--------|---------|-------------|
| 401 | `Invalid license key.` | License key not found or product doesn't exist |
| 422 | Validation errors | Missing or invalid fields |

---

### 2. Activate License

Activates a license for a specific domain. Call this when the plugin is first installed or activated on a website.

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

### 4. Check License Status

Checks if a license is active and properly activated for a specific domain. Use this for periodic license verification (e.g., daily cron job).

**Endpoint:** `POST /licenses/check`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `license_key` | string | Yes | The license key |
| `product_slug` | string | Yes | The product identifier |
| `domain` | string | Yes | The domain to check |

**Example Request:**

```json
{
  "license_key": "XXXX-XXXX-XXXX-XXXX",
  "product_slug": "nalda",
  "domain": "mystore.com"
}
```

**Success Response - Activated (200):**

```json
{
  "data": {
    "activated": true,
    "license": {
      "expires_at": "2025-12-31T23:59:59+00:00",
      "activations": {
        "limit": 3,
        "used": 2
      },
      "product": "Nalda Integration",
      "package": "Professional"
    }
  }
}
```

**Success Response - Not Activated (200):**

```json
{
  "data": {
    "activated": false
  }
}
```

This response is returned when:
- The license exists but is not active (suspended/cancelled/expired)
- The domain is not activated for this license

**Error Responses:**

| Status | Message | Description |
|--------|---------|-------------|
| 401 | `Invalid license key.` | License key not found |
| 422 | Validation errors | Missing or invalid fields |

---

## Implementation Guide

### Recommended Plugin Flow

1. **On Plugin Activation:**
   - Call `/licenses/activate` with stored license key and current domain
   - Store activation status locally
   - Show error to user if activation fails

2. **On Plugin Settings Page:**
   - Allow user to enter/update license key
   - Call `/licenses/validate` to show license details
   - Call `/licenses/activate` when saving new license key

3. **Periodic Verification (Daily):**
   - Call `/licenses/check` to verify license is still valid
   - Disable premium features if `activated: false`
   - Notify user of license issues

4. **On Plugin Deactivation/Uninstall:**
   - Call `/licenses/deactivate` to free up the activation slot

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
| `expires_at` | string\|null | ISO 8601 expiration date, or `null` for lifetime licenses |
| `activations.limit` | integer | Maximum allowed domain activations |
| `activations.used` | integer | Current number of active domains |
| `product` | string | Product name |
| `package` | string | Package/tier name |
