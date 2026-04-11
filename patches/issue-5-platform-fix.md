# Platform Fix: Issue #5 — Array to String Conversion in `generate_course` + `generate_post` Fallback

> **Apply these changes in the platform app (fabiopacifici.com), NOT in this package.**

---

## Bug 1: `generate_course` crashes with `Array to string conversion`

### Root Cause

The `curriculum` field is passed as a PHP array to the DB insert. Laravel cannot bind a raw array as a SQL parameter — it throws `Array to string conversion` at query time.

### Fix: Add `$casts` to the `Course` Eloquent model

**File to edit:** `app/Models/Course.php`

**Exact change — add `curriculum` to the `$casts` array:**

```php
// Before (or if $casts doesn't exist yet, add the whole block):
protected $casts = [
    // ... existing casts ...
];

// After:
protected $casts = [
    // ... existing casts ...
    'curriculum' => 'array',   // ← ADD THIS LINE
];
```

With this cast in place, Laravel will:
- **On write:** automatically `json_encode()` the PHP array before inserting/updating
- **On read:** automatically `json_decode()` the JSON column back into a PHP array

> **Note:** The `curriculum` DB column must be `TEXT` or `JSON` type. Run a migration if it is currently `VARCHAR`.

**Alternative cast if you prefer explicit JSON:**
```php
'curriculum' => 'json',
```

Both work identically for this use case.

---

## Bug 2: `generate_post` returns `(generate_post fallback)` for Italian content or structured input

### Root Cause

The tool dispatcher (likely `App\Services\AgentToolHandler` or a routing method inside it) appears to perform a keyword or language check before routing the incoming message to `generate_post`. When the message is in Italian or contains structured JSON/array content, the dispatcher fails to match and falls through to a fallback branch.

### Fix: Make the dispatcher language-agnostic

**File to edit:** `app/Services/AgentToolHandler.php` (or wherever tool routing lives)

**Locate the routing logic** — look for something like:

```php
// Example of problematic pattern:
if (str_contains(strtolower($message), 'post') || str_contains(strtolower($message), 'write')) {
    return $this->generate_post($args);
}
```

**Replace with tool-name-based routing, not content-based:**

```php
// Recommended pattern — route by the explicit tool name, not message content:
public function executeTool(string $toolName, mixed $arguments): mixed
{
    return match ($toolName) {
        'generate_post'   => $this->generate_post($arguments),
        'generate_course' => $this->generate_course($arguments),
        // ... other tools ...
        default           => $this->fallback($toolName, $arguments),
    };
}
```

**Key principle:** The tool name is already resolved by the AI model at the OpenAI/agent layer — the dispatcher should trust it and route directly. Never filter by message language or content at the dispatcher level.

---

## Summary

| Issue | Location | Fix |
|-------|----------|-----|
| `Array to string conversion` on `curriculum` | `app/Models/Course.php` | Add `'curriculum' => 'array'` to `$casts` |
| `generate_post` fallback on Italian / structured input | `app/Services/AgentToolHandler.php` | Route by tool name, not message content/language |
