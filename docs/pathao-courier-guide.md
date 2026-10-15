# Pathao Courier Integration Guide & Hosting Instructions

## 1. Overview & Integration Boundary
This integration implements a resilient, idempotent service boundary (`App\Services\Courier\PathaoService`) for Pathao Merchant Logistics API v1.

- **Package Compatibility Note**: The third-party package `enan/pathao-courier` was evaluated and found incompatible with Laravel 13 due to a strict dependency constraint on `guzzlehttp/guzzle ^7.8` (Laravel 13 uses Guzzle 8.2). Per architectural specification, an internal service `PathaoService` was implemented using Laravel's native HTTP client, mirroring the official Pathao Hermès Merchant REST API endpoints directly with zero third-party dependencies.

---

## 2. Environment Variables (.env)
Credentials are strictly managed through environment variables and never exposed in UI payloads, client props, error responses, or database plaintext:

```dotenv
PATHAO_CLIENT_ID=your_pathao_client_id
PATHAO_CLIENT_SECRET=your_pathao_client_secret
PATHAO_SECRET_TOKEN=your_pathao_secret_token_or_password
PATHAO_USERNAME=your_pathao_account_email
PATHAO_PASSWORD=your_pathao_account_password
PATHAO_BASE_URL=https://api-hermes.pathao.com
PATHAO_SANDBOX=false
```

- When credentials are not present or sandbox is active, the service falls back gracefully without failing sale creation.
- Bearer tokens are cached with automatic refresh and expiry checking (warns if under 7 days left).

---

## 3. Queue Worker Configuration

Sales dispatches are executed asynchronously via `App\Jobs\DispatchPathaoOrderJob` so that sale transactions commit instantly regardless of courier latency or network downtime.

### Local Development
Run the worker in a separate terminal:
```bash
php artisan queue:work --tries=3 --backoff=30
```

### Production (Supervisor Configuration)
Create `/etc/supervisor/conf.d/tasa-worker.conf`:
```ini
[program:tasa-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/tasa-pos/artisan queue:work --sleep=3 --tries=3 --backoff=30 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/tasa-pos/storage/logs/worker.log
stopwaitsecs=3600
```

Update Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start tasa-worker:*
```

---

## 4. Scheduled Status Polling (Scheduler)

Delivery statuses for all non-final active orders are polled every 30 minutes via the command:
```bash
php artisan courier:sync-pathao
```
Registered in `routes/console.php`:
```php
Schedule::command('courier:sync-pathao')->everyThirtyMinutes();
```

### Local Development
To test the scheduler locally:
```bash
php artisan schedule:work
```
Or run the sync command directly:
```bash
php artisan courier:sync-pathao
```

### Production (Crontab)
Add the following single cron entry to your server:
```bash
* * * * * cd /path/to/tasa-pos && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Idempotency & Expense Accounting
- **Order Idempotency**: Each order dispatches with `merchant_order_id = invoice_no`. If a consignment ID is already assigned, subsequent retries do not call Pathao order creation again.
- **Single Linked Expense**: Delivery fees automatically generate exactly one linked record in the `expenses` table (`source = 'pathao_charge'`). A database unique constraint on `(courier_order_id, source)` combined with `Expense::firstOrCreate` ensures retries never double-count courier charges.
- **COD Settlement**: When Cash on Delivery orders are marked Settled, a `sale_payments` record with `method = 'cod'` is created, updating `paid_total` and clearing `due_amount` without inflating gross sales revenue.
