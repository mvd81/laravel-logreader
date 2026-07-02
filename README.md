# Laravel Logreader

This is the client package for [logreader.dev](https://logreader.dev). Install it on your Laravel application to allow the Logreader to remotely read your log files via a secure API.

## Installation

```bash
composer require mvd81/laravel-logreader
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=logreader-config
```

### Token

Add the token you received from the Logreader application to your `.env`:

```env
LOGREADER_TOKEN=your-token-here
```

You can find this token in the [logreader.dev](https://logreader.dev) dashboard after registering your application.

### Options

| Option | Env variable | Default | Description |
| --- | --- | --- | --- |
| `enabled` | `LOGREADER_ENABLED` | `true` | Enable or disable the API |
| `token` | `LOGREADER_TOKEN` | `null` | Token provided by the Logreader app |
| `exclude_logs` | `LOGREADER_EXCLUDE_LOGS` | `''` | Comma-separated list of files/patterns to exclude |
| `context.enabled` | `LOGREADER_CONTEXT_ENABLED` | `false` | Add request URL and user ID to every log entry |
| `context.include_user_id` | `LOGREADER_CONTEXT_USER_ID` | `true` | Include the authenticated user ID in the log context |

To disable the logreader without removing the package:

```env
LOGREADER_ENABLED=false
```

To exclude specific log files or directories:

```env
LOGREADER_EXCLUDE_LOGS=passwords.log,private/*,*.tmp
```

### Request context

When enabled, a global middleware is automatically registered that adds the following to every log entry using `Log::shareContext()`:

| Field | Description |
| --- | --- |
| `url` | Full URL of the request |
| `method` | HTTP method (GET, POST, etc.) |
| `request_id` | Unique ID per request — use this to group all log entries from a single request |
| `user_id` | Authenticated user ID (can be disabled separately) |

This makes it easier to correlate log entries with the request and user that caused them when browsing logs in the dashboard.

```env
LOGREADER_CONTEXT_ENABLED=true
```

If you want the URL but not the user ID (for privacy reasons), disable user ID logging separately:

```env
LOGREADER_CONTEXT_ENABLED=true
LOGREADER_CONTEXT_USER_ID=false
```

> **Privacy note:** When `LOGREADER_CONTEXT_USER_ID` is enabled, user IDs are written to your log files. User IDs are personal data — make sure your log retention and access policies account for this.

## License

MIT
