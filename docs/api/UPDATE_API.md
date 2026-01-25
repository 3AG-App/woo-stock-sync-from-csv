# 3AG Update API Documentation

**Base URL:** `https://3ag.app/api/v3`

This API allows plugins to check for available updates for 3AG products.

---

## Authentication

The Update API requires a valid `license_key`, `domain`, and `product_slug` in the request body. The license must be active and the domain must be activated for the license. No bearer token or API key header is needed.

---

## Rate Limiting

| Endpoint | Limit |
|----------|-------|
| `/update/check` | 60 requests/minute |

---

## Endpoints

### Check for Updates

Checks if a newer version of a product is available and returns the current version and download URL.

**Endpoint:** `POST /update/check`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `license_key` | string | Yes | The license key |
| `product_slug` | string | Yes | The product identifier (e.g., `nalda`) |
| `domain` | string | Yes | The activated domain making the request |

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
    "version": "2.1.0",
    "download_url": "https://3ag.app/downloads/nalda-2.1.0.zip"
  }
}
```

> **Note:** Both `version` and `download_url` can be `null` if the product exists but no version or download URL has been set yet.

**Error Responses:**

| Status | Message | Description |
|--------|---------|-------------|
| 400 | `License key and domain are required.` | Missing license_key or domain |
| 401 | `Invalid license key.` | License key not found |
| 403 | `License is not active.` | License is suspended or cancelled |
| 403 | `License has expired.` | License expiration date has passed |
| 403 | `License is not activated on this domain.` | Domain is not activated for this license |
| 404 | `Product not found.` | Product slug doesn't exist or product is inactive |
| 422 | Validation errors | Missing or invalid fields |

---

## Implementation Guide

### Recommended Plugin Flow

1. **On Plugin Update Check (e.g., WordPress update checker):**
   - Call `/update/check` with stored license key, domain, and product slug
   - Compare returned `version` with installed version
   - If newer version available, display update notification with `download_url`

2. **Periodic Update Check (Daily/Weekly):**
   - Call `/update/check` in a scheduled task
   - Cache the response to reduce API calls
   - Notify admin if update is available

3. **Before Downloading Update:**
   - Verify license is still valid using `/licenses/check`
   - Use the `download_url` from the response to download the update package

### Version Comparison

The `version` field follows semantic versioning (e.g., `2.1.0`). Use appropriate version comparison logic in your plugin:

```php
// PHP example
if (version_compare($installed_version, $response['data']['version'], '<')) {
    // Update available
}
```

```javascript
// JavaScript example (simplified)
const isUpdateAvailable = (installed, latest) => {
  return installed.localeCompare(latest, undefined, { numeric: true }) < 0;
};
```

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

### Update Data Object

| Field | Type | Description |
|-------|------|-------------|
| `version` | string\|null | The latest available version (semantic versioning), or `null` if not set |
| `download_url` | string\|null | URL to download the latest version package, or `null` if not set |
