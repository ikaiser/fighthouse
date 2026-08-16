# Fighthouse Wallet Cashback — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a working backend API (Slim 4 + Eloquent + MySQL) and admin/public SPA (Vue 3 + Vite) that lets an admin censire utenti, registrare spese con accredito automatico del 5% su un wallet, scaricare il wallet in blocchi da 25€, e lasciare che l'utente finale consulti saldo/storico da cellulare tramite numero di telefono.

**Architecture:** Backend REST API sotto `api/`, stateless tranne la sessione admin (cookie PHP nativo). Business logic critica (accredito/scarico/rettifica) isolata in un `WalletService` testato con PHPUnit. Frontend SPA sotto `web/`, compilata staticamente e servita dallo stesso dominio, con due macro-aree: `/admin/*` (autenticata) e `/wallet` (pubblica).

**Tech Stack:** PHP 8.1+, Slim 4, illuminate/database (Eloquent standalone), Phinx (migrazioni), PHPUnit, MySQL. Vue 3, Vite, Vue Router, Pinia, axios.

**Spec:** `docs/superpowers/specs/2026-08-16-fighthouse-wallet-design.md`

## Global Constraints

- PHP 8.1+ richiesto (per compatibilità con Slim 4 e Eloquent moderno).
- Nessuna dipendenza da SSH/Composer/Node lato hosting di produzione: `vendor/` e `dist/` vengono generati in locale e caricati via FTP.
- Il 5% di cashback si calcola **sempre sull'importo lordo** della spesa (mai sull'importo netto dopo uso wallet).
- Lo scarico wallet è ammesso **solo in blocchi esatti da 25€**, fino a `floor(wallet_balance / 25) * 25`.
- Accesso admin: singolo account condiviso, sessione via cookie PHP nativo (niente JWT/OAuth).
- Accesso pubblico `/wallet`: solo numero di telefono, nessuna password (limite di sicurezza noto e accettato, vedi spec).
- Tutti gli importi monetari: `DECIMAL(10,2)` a DB, arrotondati a 2 decimali in PHP con `round(x, 2)`.

---

## File Structure

**Backend (`api/`)**
- `api/composer.json` — dipendenze: slim/slim, slim/psr7, illuminate/database, robmorgan/phinx, vlucas/phpdotenv, phpunit/phpunit
- `api/.env.example`, `api/phinx.php` — config DB e migrazioni
- `api/public/index.php` — front controller Slim
- `api/src/bootstrap.php` — carica `.env`, avvia Eloquent Capsule, costruisce l'app Slim
- `api/src/routes.php` — definizione di tutte le rotte
- `api/db/migrations/*.php` — 4 migrazioni Phinx: admins, users, expenses, wallet_transactions
- `api/src/Models/Admin.php`, `User.php`, `Expense.php`, `WalletTransaction.php` — modelli Eloquent
- `api/src/Services/WalletService.php` — logica di accredito/scarico/rettifica (cuore del sistema)
- `api/src/Middleware/AuthMiddleware.php` — verifica sessione admin
- `api/src/Controllers/AuthController.php` — login/logout admin
- `api/src/Controllers/UserController.php` — CRUD utenti
- `api/src/Controllers/ExpenseController.php` — CRUD spese (usa `WalletService`)
- `api/src/Controllers/WalletController.php` — dettaglio wallet admin + lookup pubblico per telefono
- `api/tests/Services/WalletServiceTest.php` — test della logica critica
- `api/tests/Controllers/*Test.php` — test HTTP essenziali su ogni controller
- `api/bin/seed-admin.php` — script CLI una tantum per creare il primo admin

**Frontend (`web/`)**
- `web/package.json`, `web/vite.config.js`
- `web/src/main.js`, `web/src/App.vue`
- `web/src/api/client.js` — wrapper axios (baseURL `/api`, `withCredentials: true`)
- `web/src/stores/auth.js` — Pinia store sessione admin
- `web/src/router/index.js` — route + guard su `/admin/*`
- `web/src/views/admin/LoginView.vue`
- `web/src/views/admin/UsersListView.vue`
- `web/src/views/admin/UserDetailView.vue`
- `web/src/views/admin/ExpenseFormView.vue`
- `web/src/views/public/WalletLookupView.vue`

**Deploy**
- `deploy/.htaccess` — routing `/api/*` verso Slim, fallback SPA su `index.html`
- `README.md` — istruzioni di build e upload

---

### Task 1: Backend scaffolding e connessione DB

**Files:**
- Create: `api/composer.json`
- Create: `api/.env.example`
- Create: `api/src/bootstrap.php`
- Create: `api/public/index.php`
- Create: `api/phpunit.xml`
- Test: `api/tests/BootstrapTest.php`

**Interfaces:**
- Produces: funzione `App\bootstrapApp(): \Slim\App` in `api/src/bootstrap.php`, usata da tutti i task successivi come punto di ingresso.

- [ ] **Step 1: Creare `composer.json`**

```json
{
    "name": "fighthouse/api",
    "require": {
        "php": ">=8.1",
        "slim/slim": "^4.12",
        "slim/psr7": "^1.6",
        "illuminate/database": "^10.0",
        "robmorgan/phinx": "^0.15",
        "vlucas/phpdotenv": "^5.6"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}
```

- [ ] **Step 2: Installare le dipendenze**

Run: `cd api && composer install`
Expected: crea `vendor/` e `composer.lock` senza errori.

- [ ] **Step 3: Creare `.env.example`**

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fighthouse
DB_USERNAME=root
DB_PASSWORD=
APP_ENV=local
```

Copiare come `.env` in locale con le credenziali reali (file non versionato).

- [ ] **Step 4: Creare `api/src/bootstrap.php`**

```php
<?php

namespace App;

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Slim\Factory\AppFactory;

function bootstrapApp(): \Slim\App
{
    $root = dirname(__DIR__);

    if (file_exists($root . '/.env')) {
        Dotenv::createImmutable($root)->load();
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $capsule = new Capsule();
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? 'fighthouse',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $app = AppFactory::create();
    $app->addBodyParsingMiddleware();
    $app->addErrorMiddleware((bool) ($_ENV['APP_ENV'] === 'local'), true, true);

    require __DIR__ . '/routes.php';
    (require __DIR__ . '/routes.php')($app);

    return $app;
}
```

Nota: la riga `require` duplicata sopra va corretta — vedi Step 5 (routes.php ritorna una closure che riceve `$app`).

- [ ] **Step 5: Correggere il caricamento delle routes in `bootstrap.php`**

Sostituire le due righe di require con una sola:

```php
    $registerRoutes = require __DIR__ . '/routes.php';
    $registerRoutes($app);

    return $app;
}
```

- [ ] **Step 6: Creare un `routes.php` minimo (placeholder sostituito nei task successivi)**

```php
<?php

use Slim\App;

return function (App $app) {
    $app->get('/api/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });
};
```

- [ ] **Step 7: Creare `api/public/index.php`**

```php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\bootstrapApp;

$app = bootstrapApp();
$app->run();
```

- [ ] **Step 8: Creare `api/phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="api">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 9: Verificare che il server risponda**

Run: `cd api && php -S 127.0.0.1:8080 -t public`
In un altro terminale: `curl http://127.0.0.1:8080/api/health`
Expected: `{"status":"ok"}`

- [ ] **Step 10: Commit**

```bash
git add api/composer.json api/composer.lock api/.env.example api/src/bootstrap.php api/src/routes.php api/public/index.php api/phpunit.xml
git commit -m "Scaffold Slim API with health check endpoint"
```

---

### Task 2: Migrazioni database (Phinx)

**Files:**
- Create: `api/phinx.php`
- Create: `api/db/migrations/20260816000001_create_admins_table.php`
- Create: `api/db/migrations/20260816000002_create_users_table.php`
- Create: `api/db/migrations/20260816000003_create_expenses_table.php`
- Create: `api/db/migrations/20260816000004_create_wallet_transactions_table.php`

**Interfaces:**
- Produces: tabelle MySQL `admins`, `users`, `expenses`, `wallet_transactions` come da spec (sezione "Modello dati"), usate dai modelli Eloquent nel Task 3.

- [ ] **Step 1: Creare `api/phinx.php`**

```php
<?php

require __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->load();
}

return [
    'paths' => [
        'migrations' => __DIR__ . '/db/migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'production',
        'production' => [
            'adapter' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'name' => $_ENV['DB_DATABASE'] ?? 'fighthouse',
            'user' => $_ENV['DB_USERNAME'] ?? 'root',
            'pass' => $_ENV['DB_PASSWORD'] ?? '',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'charset' => 'utf8mb4',
        ],
    ],
];
```

- [ ] **Step 2: Migrazione `admins`**

```php
<?php

use Phinx\Migration\AbstractMigration;

final class CreateAdminsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('admins');
        $table
            ->addColumn('username', 'string', ['limit' => 255])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['username'], ['unique' => true])
            ->create();
    }
}
```

- [ ] **Step 3: Migrazione `users`**

```php
<?php

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table
            ->addColumn('nome', 'string', ['limit' => 255])
            ->addColumn('cognome', 'string', ['limit' => 255])
            ->addColumn('telefono', 'string', ['limit' => 32])
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('data_nascita', 'date')
            ->addColumn('wallet_balance', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['telefono'], ['unique' => true])
            ->create();
    }
}
```

- [ ] **Step 4: Migrazione `expenses`**

```php
<?php

use Phinx\Migration\AbstractMigration;

final class CreateExpensesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('expenses');
        $table
            ->addColumn('user_id', 'integer')
            ->addColumn('importo', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('wallet_usato', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('data_spesa', 'date')
            ->addColumn('note', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
```

- [ ] **Step 5: Migrazione `wallet_transactions`**

Nota: `expense_id` è nullable e **senza vincolo FK enforced** — quando una spesa viene eliminata, la transazione di storno collegata deve conservare l'id originale per l'audit, invece di essere azzerata da un `ON DELETE SET NULL`.

```php
<?php

use Phinx\Migration\AbstractMigration;

final class CreateWalletTransactionsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('wallet_transactions');
        $table
            ->addColumn('user_id', 'integer')
            ->addColumn('expense_id', 'integer', ['null' => true])
            ->addColumn('tipo', 'enum', ['values' => ['accredito', 'scarico', 'rettifica']])
            ->addColumn('importo', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('saldo_dopo', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'])
            ->addIndex(['expense_id'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
```

- [ ] **Step 6: Eseguire le migrazioni su un DB locale di sviluppo**

Run: `cd api && vendor/bin/phinx migrate`
Expected: le 4 tabelle vengono create senza errori (`phinxlog` mostra 4 migrazioni applicate).

- [ ] **Step 7: Commit**

```bash
git add api/phinx.php api/db/migrations
git commit -m "Add Phinx migrations for admins, users, expenses, wallet_transactions"
```

---

### Task 3: Modelli Eloquent

**Files:**
- Create: `api/src/Models/Admin.php`
- Create: `api/src/Models/User.php`
- Create: `api/src/Models/Expense.php`
- Create: `api/src/Models/WalletTransaction.php`
- Test: `api/tests/Models/ModelsTest.php`

**Interfaces:**
- Consumes: connessione Eloquent avviata in `bootstrap.php` (Task 1), tabelle create nel Task 2.
- Produces: `App\Models\User` (campi: `nome`, `cognome`, `telefono`, `email`, `data_nascita`, `wallet_balance`), `App\Models\Expense` (campi: `user_id`, `importo`, `wallet_usato`, `data_spesa`, `note`, relazione `user()`), `App\Models\WalletTransaction` (campi: `user_id`, `expense_id`, `tipo`, `importo`, `saldo_dopo`), `App\Models\Admin` (campi: `username`, `password_hash`) — usati da `WalletService` (Task 4) e da tutti i controller.

- [ ] **Step 1: Creare `api/src/Models/Admin.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    public $timestamps = false;
    protected $fillable = ['username', 'password_hash'];
}
```

- [ ] **Step 2: Creare `api/src/Models/User.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['nome', 'cognome', 'telefono', 'email', 'data_nascita', 'wallet_balance'];
    protected $casts = [
        'wallet_balance' => 'float',
        'data_nascita' => 'date:Y-m-d',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class)->orderByDesc('created_at');
    }
}
```

- [ ] **Step 3: Creare `api/src/Models/Expense.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['user_id', 'importo', 'wallet_usato', 'data_spesa', 'note'];
    protected $casts = [
        'importo' => 'float',
        'wallet_usato' => 'float',
        'data_spesa' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: Creare `api/src/Models/WalletTransaction.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'expense_id', 'tipo', 'importo', 'saldo_dopo'];
    protected $casts = [
        'importo' => 'float',
        'saldo_dopo' => 'float',
        'created_at' => 'datetime',
    ];

    protected $attributes = [];

    public function getCreatedAtAttribute($value)
    {
        return $value;
    }
}
```

Nota: `WalletTransaction` non ha `updated_at`; `created_at` viene popolato dal default MySQL `CURRENT_TIMESTAMP`, non da Eloquent.

- [ ] **Step 5: Scrivere il test dei modelli**

```php
<?php

namespace Tests\Models;

use App\Models\Expense;
use App\Models\User;
use App\Models\WalletTransaction;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../TestDatabase.php';

class ModelsTest extends TestCase
{
    protected function setUp(): void
    {
        \Tests\TestDatabase::boot();
        \Tests\TestDatabase::migrate();
    }

    protected function tearDown(): void
    {
        \Tests\TestDatabase::reset();
    }

    public function testUserHasManyExpensesAndTransactions(): void
    {
        $user = User::create([
            'nome' => 'Mario', 'cognome' => 'Rossi', 'telefono' => '3331112222',
            'email' => 'mario@example.com', 'data_nascita' => '1990-01-01', 'wallet_balance' => 0,
        ]);

        $expense = Expense::create([
            'user_id' => $user->id, 'importo' => 100, 'wallet_usato' => 0, 'data_spesa' => '2026-08-16',
        ]);

        WalletTransaction::create([
            'user_id' => $user->id, 'expense_id' => $expense->id, 'tipo' => 'accredito',
            'importo' => 5, 'saldo_dopo' => 5,
        ]);

        $this->assertCount(1, $user->expenses);
        $this->assertCount(1, $user->walletTransactions);
        $this->assertEquals('Mario', $expense->user->nome);
    }
}
```

- [ ] **Step 6: Creare `api/tests/TestDatabase.php` (helper condiviso per i test, SQLite in-memory)**

```php
<?php

namespace Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class TestDatabase
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        self::$booted = true;
    }

    public static function migrate(): void
    {
        $schema = \Illuminate\Database\Capsule\Manager::schema();

        $schema->create('admins', function ($t) {
            $t->increments('id');
            $t->string('username')->unique();
            $t->string('password_hash');
            $t->timestamp('created_at')->useCurrent();
        });

        $schema->create('users', function ($t) {
            $t->increments('id');
            $t->string('nome');
            $t->string('cognome');
            $t->string('telefono')->unique();
            $t->string('email');
            $t->date('data_nascita');
            $t->decimal('wallet_balance', 10, 2)->default(0);
            $t->timestamps();
        });

        $schema->create('expenses', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->decimal('importo', 10, 2);
            $t->decimal('wallet_usato', 10, 2)->default(0);
            $t->date('data_spesa');
            $t->string('note', 500)->nullable();
            $t->timestamps();
        });

        $schema->create('wallet_transactions', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('expense_id')->nullable();
            $t->enum('tipo', ['accredito', 'scarico', 'rettifica']);
            $t->decimal('importo', 10, 2);
            $t->decimal('saldo_dopo', 10, 2);
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public static function reset(): void
    {
        foreach (['wallet_transactions', 'expenses', 'users', 'admins'] as $table) {
            \Illuminate\Database\Capsule\Manager::table($table)->delete();
        }
    }
}
```

Aggiungere `"symfony/console": "^6.0"` non è necessario: questo helper usa solo lo Schema Builder di Eloquent (già incluso in `illuminate/database`), non Phinx — rimuovere gli `use Phinx\...` e `use Symfony\...` inutilizzati dal file sopra prima di salvarlo.

- [ ] **Step 7: Eseguire i test**

Run: `cd api && vendor/bin/phpunit tests/Models/ModelsTest.php`
Expected: PASS (2 assertions).

- [ ] **Step 8: Commit**

```bash
git add api/src/Models api/tests/Models api/tests/TestDatabase.php
git commit -m "Add Eloquent models for Admin, User, Expense, WalletTransaction"
```

---

### Task 4: WalletService — logica di accredito, scarico e rettifica

**Files:**
- Create: `api/src/Services/WalletService.php`
- Test: `api/tests/Services/WalletServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\User`, `App\Models\Expense`, `App\Models\WalletTransaction` (Task 3).
- Produces: `App\Services\WalletService` con metodi pubblici:
  - `maxRedeemable(User $user): float`
  - `createExpense(User $user, float $importo, string $dataSpesa, ?string $note, float $walletUsato): Expense`
  - `updateExpense(Expense $expense, float $importo, string $dataSpesa, ?string $note, float $walletUsato): Expense`
  - `deleteExpense(Expense $expense): void`

  Usati da `ExpenseController` (Task 7).

- [ ] **Step 1: Scrivere i test falliti per `maxRedeemable` e `createExpense`**

```php
<?php

namespace Tests\Services;

use App\Models\User;
use App\Services\WalletService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../TestDatabase.php';

class WalletServiceTest extends TestCase
{
    private WalletService $service;

    protected function setUp(): void
    {
        \Tests\TestDatabase::boot();
        \Tests\TestDatabase::migrate();
        $this->service = new WalletService();
    }

    protected function tearDown(): void
    {
        \Tests\TestDatabase::reset();
    }

    private function makeUser(float $balance = 0): User
    {
        return User::create([
            'nome' => 'Mario', 'cognome' => 'Rossi', 'telefono' => uniqid('3'),
            'email' => 'mario@example.com', 'data_nascita' => '1990-01-01', 'wallet_balance' => $balance,
        ]);
    }

    public function testMaxRedeemableFloorsToNearest25(): void
    {
        $user = $this->makeUser(83);
        $this->assertEquals(75.0, $this->service->maxRedeemable($user));
    }

    public function testMaxRedeemableIsZeroBelow25(): void
    {
        $user = $this->makeUser(24.99);
        $this->assertEquals(0.0, $this->service->maxRedeemable($user));
    }

    public function testCreateExpenseCreditsFivePercentOnGrossAmount(): void
    {
        $user = $this->makeUser(0);
        $expense = $this->service->createExpense($user, 100.0, '2026-08-16', null, 0);

        $this->assertEquals(5.0, $expense->user->wallet_balance);
        $this->assertEquals(100.0, $expense->importo);
    }

    public function testCreateExpenseWithWalletRedemptionDeductsFromBalance(): void
    {
        $user = $this->makeUser(50);
        $expense = $this->service->createExpense($user, 100.0, '2026-08-16', null, 50.0);

        // 50 (saldo iniziale) - 50 (scarico) + 5 (5% di 100) = 5
        $this->assertEquals(5.0, $expense->fresh()->user->wallet_balance);
        $this->assertEquals(50.0, $expense->wallet_usato);
    }

    public function testCreateExpenseRejectsWalletUsatoNotMatchingThreshold(): void
    {
        $user = $this->makeUser(50);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createExpense($user, 100.0, '2026-08-16', null, 30.0);
    }

    public function testCreateExpenseRejectsZeroOrNegativeImporto(): void
    {
        $user = $this->makeUser(0);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createExpense($user, 0.0, '2026-08-16', null, 0);
    }

    public function testUpdateExpenseRecalculatesBalanceWithRettifica(): void
    {
        $user = $this->makeUser(0);
        $expense = $this->service->createExpense($user, 100.0, '2026-08-16', null, 0);
        // saldo dopo creazione: 5

        $updated = $this->service->updateExpense($expense, 200.0, '2026-08-16', null, 0);
        // nuovo accredito: 10, vecchio: 5, delta: +5 -> saldo 10

        $this->assertEquals(10.0, $updated->fresh()->user->wallet_balance);
    }

    public function testUpdateExpenseRejectsIfBalanceWouldGoNegative(): void
    {
        $user = $this->makeUser(50);
        $expense = $this->service->createExpense($user, 100.0, '2026-08-16', null, 50.0);
        // saldo dopo creazione: 5

        $this->expectException(\RuntimeException::class);
        $this->service->updateExpense($expense, 0.01, '2026-08-16', null, 50.0);
    }

    public function testDeleteExpenseReversesAccreditoAndScarico(): void
    {
        $user = $this->makeUser(50);
        $expense = $this->service->createExpense($user, 100.0, '2026-08-16', null, 50.0);
        // saldo dopo creazione: 5

        $this->service->deleteExpense($expense);

        $this->assertEquals(50.0, $user->fresh()->wallet_balance);
        $this->assertNull(\App\Models\Expense::find($expense->id));
    }
}
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `cd api && vendor/bin/phpunit tests/Services/WalletServiceTest.php`
Expected: FAIL — `Class "App\Services\WalletService" not found`.

- [ ] **Step 3: Implementare `api/src/Services/WalletService.php`**

```php
<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Capsule\Manager as DB;

class WalletService
{
    public const CASHBACK_RATE = 0.05;
    public const REDEMPTION_STEP = 25.0;

    public function maxRedeemable(User $user): float
    {
        return floor($user->wallet_balance / self::REDEMPTION_STEP) * self::REDEMPTION_STEP;
    }

    public function createExpense(User $user, float $importo, string $dataSpesa, ?string $note, float $walletUsato): Expense
    {
        if ($importo <= 0) {
            throw new \InvalidArgumentException('importo deve essere maggiore di zero');
        }

        $maxRedeemable = $this->maxRedeemable($user);
        if ($walletUsato > 0 && abs($walletUsato - $maxRedeemable) > 0.001) {
            throw new \InvalidArgumentException('wallet_usato deve corrispondere alla soglia massima raggiunta');
        }

        return DB::connection()->transaction(function () use ($user, $importo, $dataSpesa, $note, $walletUsato) {
            $expense = Expense::create([
                'user_id' => $user->id,
                'importo' => $importo,
                'wallet_usato' => $walletUsato,
                'data_spesa' => $dataSpesa,
                'note' => $note,
            ]);

            $accredito = round($importo * self::CASHBACK_RATE, 2);
            $this->recordTransaction($user, $expense->id, 'accredito', $accredito);

            if ($walletUsato > 0) {
                $this->recordTransaction($user, $expense->id, 'scarico', -$walletUsato);
            }

            return $expense->fresh();
        });
    }

    public function updateExpense(Expense $expense, float $importo, string $dataSpesa, ?string $note, float $walletUsato): Expense
    {
        if ($importo <= 0) {
            throw new \InvalidArgumentException('importo deve essere maggiore di zero');
        }

        $user = $expense->user;

        return DB::connection()->transaction(function () use ($expense, $user, $importo, $dataSpesa, $note, $walletUsato) {
            $oldNet = round($expense->importo * self::CASHBACK_RATE, 2) - $expense->wallet_usato;
            $newNet = round($importo * self::CASHBACK_RATE, 2) - $walletUsato;
            $delta = round($newNet - $oldNet, 2);

            if ($user->wallet_balance + $delta < 0) {
                throw new \RuntimeException('operazione non consentita: saldo wallet risulterebbe negativo');
            }

            $expense->update([
                'importo' => $importo,
                'wallet_usato' => $walletUsato,
                'data_spesa' => $dataSpesa,
                'note' => $note,
            ]);

            if (abs($delta) > 0.001) {
                $this->recordTransaction($user, $expense->id, 'rettifica', $delta);
            }

            return $expense->fresh();
        });
    }

    public function deleteExpense(Expense $expense): void
    {
        $user = $expense->user;

        DB::connection()->transaction(function () use ($expense, $user) {
            $accredito = round($expense->importo * self::CASHBACK_RATE, 2);
            $scarico = $expense->wallet_usato;
            $storno = round(-($accredito - $scarico), 2);

            if (abs($storno) > 0.001) {
                $this->recordTransaction($user, $expense->id, 'rettifica', $storno);
            }

            $expense->delete();
        });
    }

    private function recordTransaction(User $user, ?int $expenseId, string $tipo, float $importo): WalletTransaction
    {
        $newBalance = round($user->wallet_balance + $importo, 2);

        $tx = WalletTransaction::create([
            'user_id' => $user->id,
            'expense_id' => $expenseId,
            'tipo' => $tipo,
            'importo' => $importo,
            'saldo_dopo' => $newBalance,
        ]);

        $user->wallet_balance = $newBalance;
        $user->save();

        return $tx;
    }
}
```

- [ ] **Step 4: Eseguire i test e verificare che passino**

Run: `cd api && vendor/bin/phpunit tests/Services/WalletServiceTest.php`
Expected: PASS (9 test, tutte le assertion verdi).

- [ ] **Step 5: Commit**

```bash
git add api/src/Services api/tests/Services
git commit -m "Add WalletService with cashback, redemption threshold and rettifica logic"
```

---

### Task 5: Autenticazione admin

**Files:**
- Create: `api/src/Controllers/AuthController.php`
- Create: `api/src/Middleware/AuthMiddleware.php`
- Create: `api/bin/seed-admin.php`
- Modify: `api/src/routes.php`
- Test: `api/tests/Controllers/AuthControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Admin` (Task 3).
- Produces: `POST /api/admin/login`, `POST /api/admin/logout`; `App\Middleware\AuthMiddleware` applicato come gruppo a tutte le rotte `/api/users/*`, `/api/expenses/*`, `/api/users/{id}/wallet` nei Task 6-8.

- [ ] **Step 1: Creare `api/src/Controllers/AuthController.php`**

```php
<?php

namespace App\Controllers;

use App\Models\Admin;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $admin = Admin::where('username', $data['username'] ?? '')->first();

        if (!$admin || !password_verify((string) ($data['password'] ?? ''), $admin->password_hash)) {
            $response->getBody()->write(json_encode(['error' => 'credenziali non valide']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $_SESSION['admin_id'] = $admin->id;
        $response->getBody()->write(json_encode(['ok' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function logout(Request $request, Response $response): Response
    {
        unset($_SESSION['admin_id']);
        $response->getBody()->write(json_encode(['ok' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

- [ ] **Step 2: Creare `api/src/Middleware/AuthMiddleware.php`**

```php
<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        if (empty($_SESSION['admin_id'])) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(['error' => 'non autenticato']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
```

- [ ] **Step 3: Creare `api/bin/seed-admin.php` (script CLI una tantum)**

```php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Admin;

$app = (require dirname(__DIR__) . '/src/bootstrap.php');
// bootstrap.php espone bootstrapApp(); qui basta la connessione DB già avviata dalla funzione.
\App\bootstrapApp();

$username = $argv[1] ?? null;
$password = $argv[2] ?? null;

if (!$username || !$password) {
    fwrite(STDERR, "Uso: php bin/seed-admin.php <username> <password>\n");
    exit(1);
}

Admin::updateOrCreate(
    ['username' => $username],
    ['password_hash' => password_hash($password, PASSWORD_BCRYPT)]
);

echo "Admin '{$username}' creato/aggiornato.\n";
```

- [ ] **Step 4: Aggiornare `api/src/routes.php` con le rotte di auth**

```php
<?php

use App\Controllers\AuthController;
use Slim\App;

return function (App $app) {
    $app->get('/api/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/api/admin/login', [AuthController::class, 'login']);
    $app->post('/api/admin/logout', [AuthController::class, 'logout']);
};
```

- [ ] **Step 5: Scrivere il test del login**

```php
<?php

namespace Tests\Controllers;

use App\Models\Admin;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once __DIR__ . '/../TestDatabase.php';

class AuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        \Tests\TestDatabase::boot();
        \Tests\TestDatabase::migrate();
    }

    protected function tearDown(): void
    {
        \Tests\TestDatabase::reset();
    }

    public function testLoginSucceedsWithValidCredentials(): void
    {
        Admin::create(['username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_BCRYPT)]);

        $controller = new \App\Controllers\AuthController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/login')
            ->withParsedBody(['username' => 'admin', 'password' => 'secret123']);
        $response = $controller->login($request, new \Slim\Psr7\Response());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('admin', Admin::find($_SESSION['admin_id'])->username);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        Admin::create(['username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_BCRYPT)]);

        $controller = new \App\Controllers\AuthController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/login')
            ->withParsedBody(['username' => 'admin', 'password' => 'wrong']);
        $response = $controller->login($request, new \Slim\Psr7\Response());

        $this->assertEquals(401, $response->getStatusCode());
    }
}
```

- [ ] **Step 6: Eseguire i test**

Run: `cd api && vendor/bin/phpunit tests/Controllers/AuthControllerTest.php`
Expected: PASS. Se PHPUnit lamenta `session_start()` in CLI/headers already sent, aggiungere in cima a `phpunit.xml` la direttiva `<php><ini name="session.use_cookies" value="0"/></php>` — la sessione in test gira solo in memoria via `$_SESSION` superglobale, senza inviare header.

- [ ] **Step 7: Commit**

```bash
git add api/src/Controllers/AuthController.php api/src/Middleware/AuthMiddleware.php api/bin/seed-admin.php api/src/routes.php api/tests/Controllers/AuthControllerTest.php
git commit -m "Add admin login/logout with session-based auth middleware"
```

---

### Task 6: CRUD utenti

**Files:**
- Create: `api/src/Controllers/UserController.php`
- Modify: `api/src/routes.php`
- Test: `api/tests/Controllers/UserControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (Task 3), `App\Middleware\AuthMiddleware` (Task 5).
- Produces: `GET/POST /api/users`, `GET/PUT/DELETE /api/users/{id}` — usati dalla SPA (Task 11, 12).

- [ ] **Step 1: Scrivere il test fallito per la creazione utente**

```php
<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once __DIR__ . '/../TestDatabase.php';

class UserControllerTest extends TestCase
{
    protected function setUp(): void
    {
        \Tests\TestDatabase::boot();
        \Tests\TestDatabase::migrate();
    }

    protected function tearDown(): void
    {
        \Tests\TestDatabase::reset();
    }

    public function testCreateUserPersistsAllFields(): void
    {
        $controller = new \App\Controllers\UserController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/users')
            ->withParsedBody([
                'nome' => 'Anna', 'cognome' => 'Bianchi', 'telefono' => '3339990000',
                'email' => 'anna@example.com', 'data_nascita' => '1995-05-20',
            ]);
        $response = $controller->create($request, new \Slim\Psr7\Response());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(1, \App\Models\User::count());
    }

    public function testCreateUserRejectsDuplicatePhone(): void
    {
        \App\Models\User::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '3339990000',
            'email' => 'a@example.com', 'data_nascita' => '1990-01-01', 'wallet_balance' => 0,
        ]);

        $controller = new \App\Controllers\UserController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/users')
            ->withParsedBody([
                'nome' => 'C', 'cognome' => 'D', 'telefono' => '3339990000',
                'email' => 'c@example.com', 'data_nascita' => '1990-01-01',
            ]);
        $response = $controller->create($request, new \Slim\Psr7\Response());

        $this->assertEquals(409, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `cd api && vendor/bin/phpunit tests/Controllers/UserControllerTest.php`
Expected: FAIL — `Class "App\Controllers\UserController" not found`.

- [ ] **Step 3: Implementare `api/src/Controllers/UserController.php`**

```php
<?php

namespace App\Controllers;

use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController
{
    public function index(Request $request, Response $response): Response
    {
        $search = $request->getQueryParams()['q'] ?? null;
        $query = User::query();
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('cognome', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%");
            });
        }
        $response->getBody()->write($query->orderBy('cognome')->get()->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $errors = $this->validate($data);
        if ($errors) {
            $response->getBody()->write(json_encode(['error' => implode(', ', $errors)]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (User::where('telefono', $data['telefono'])->exists()) {
            $response->getBody()->write(json_encode(['error' => 'telefono già registrato']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $user = User::create([
            'nome' => $data['nome'], 'cognome' => $data['cognome'], 'telefono' => $data['telefono'],
            'email' => $data['email'], 'data_nascita' => $data['data_nascita'], 'wallet_balance' => 0,
        ]);

        $response->getBody()->write($user->toJson());
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = User::find($args['id']);
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'utente non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write($user->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = User::find($args['id']);
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'utente non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->validate($data);
        if ($errors) {
            $response->getBody()->write(json_encode(['error' => implode(', ', $errors)]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (User::where('telefono', $data['telefono'])->where('id', '!=', $user->id)->exists()) {
            $response->getBody()->write(json_encode(['error' => 'telefono già registrato']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $user->update([
            'nome' => $data['nome'], 'cognome' => $data['cognome'], 'telefono' => $data['telefono'],
            'email' => $data['email'], 'data_nascita' => $data['data_nascita'],
        ]);

        $response->getBody()->write($user->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = User::find($args['id']);
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'utente non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        $user->delete();
        return $response->withStatus(204);
    }

    private function validate(array $data): array
    {
        $errors = [];
        foreach (['nome', 'cognome', 'telefono', 'email', 'data_nascita'] as $field) {
            if (empty($data[$field])) {
                $errors[] = "{$field} obbligatorio";
            }
        }
        if (!empty($data['data_nascita']) && !\DateTime::createFromFormat('Y-m-d', $data['data_nascita'])) {
            $errors[] = 'data_nascita non valida';
        }
        return $errors;
    }
}
```

- [ ] **Step 4: Aggiungere le rotte protette in `api/src/routes.php`**

```php
<?php

use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app) {
    $app->get('/api/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/api/admin/login', [AuthController::class, 'login']);
    $app->post('/api/admin/logout', [AuthController::class, 'logout']);

    $app->group('/api', function ($group) {
        $group->get('/users', [UserController::class, 'index']);
        $group->post('/users', [UserController::class, 'create']);
        $group->get('/users/{id}', [UserController::class, 'show']);
        $group->put('/users/{id}', [UserController::class, 'update']);
        $group->delete('/users/{id}', [UserController::class, 'delete']);
    })->add(new AuthMiddleware());
};
```

- [ ] **Step 5: Eseguire i test e verificare che passino**

Run: `cd api && vendor/bin/phpunit tests/Controllers/UserControllerTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add api/src/Controllers/UserController.php api/src/routes.php api/tests/Controllers/UserControllerTest.php
git commit -m "Add user CRUD endpoints protected by admin session"
```

---

### Task 7: Endpoint spese (collegati a WalletService)

**Files:**
- Create: `api/src/Controllers/ExpenseController.php`
- Modify: `api/src/routes.php`
- Test: `api/tests/Controllers/ExpenseControllerTest.php`

**Interfaces:**
- Consumes: `App\Services\WalletService` (Task 4), `App\Models\User`, `App\Models\Expense` (Task 3).
- Produces: `GET /api/expenses?user_id=&from=&to=`, `POST /api/expenses`, `PUT /api/expenses/{id}`, `DELETE /api/expenses/{id}` — usati dalla SPA (Task 13).

- [ ] **Step 1: Scrivere il test fallito per la creazione spesa**

```php
<?php

namespace Tests\Controllers;

use App\Models\User;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once __DIR__ . '/../TestDatabase.php';

class ExpenseControllerTest extends TestCase
{
    protected function setUp(): void
    {
        \Tests\TestDatabase::boot();
        \Tests\TestDatabase::migrate();
    }

    protected function tearDown(): void
    {
        \Tests\TestDatabase::reset();
    }

    public function testCreateExpenseAppliesCashback(): void
    {
        $user = User::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '333', 'email' => 'a@example.com',
            'data_nascita' => '1990-01-01', 'wallet_balance' => 0,
        ]);

        $controller = new \App\Controllers\ExpenseController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/expenses')
            ->withParsedBody([
                'user_id' => $user->id, 'importo' => 100, 'data_spesa' => '2026-08-16', 'wallet_usato' => 0,
            ]);
        $response = $controller->create($request, new \Slim\Psr7\Response());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(5.0, $user->fresh()->wallet_balance);
    }

    public function testCreateExpenseReturns400OnInvalidWalletUsato(): void
    {
        $user = User::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '333', 'email' => 'a@example.com',
            'data_nascita' => '1990-01-01', 'wallet_balance' => 10,
        ]);

        $controller = new \App\Controllers\ExpenseController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/expenses')
            ->withParsedBody([
                'user_id' => $user->id, 'importo' => 100, 'data_spesa' => '2026-08-16', 'wallet_usato' => 10,
            ]);
        $response = $controller->create($request, new \Slim\Psr7\Response());

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testDeleteExpenseReversesWallet(): void
    {
        $user = User::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '333', 'email' => 'a@example.com',
            'data_nascita' => '1990-01-01', 'wallet_balance' => 0,
        ]);
        $expense = (new \App\Services\WalletService())->createExpense($user, 100, '2026-08-16', null, 0);

        $controller = new \App\Controllers\ExpenseController();
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/api/expenses/{$expense->id}");
        $response = $controller->delete($request, new \Slim\Psr7\Response(), ['id' => $expense->id]);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals(0.0, $user->fresh()->wallet_balance);
    }
}
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `cd api && vendor/bin/phpunit tests/Controllers/ExpenseControllerTest.php`
Expected: FAIL — `Class "App\Controllers\ExpenseController" not found`.

- [ ] **Step 3: Implementare `api/src/Controllers/ExpenseController.php`**

```php
<?php

namespace App\Controllers;

use App\Models\Expense;
use App\Models\User;
use App\Services\WalletService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ExpenseController
{
    private WalletService $walletService;

    public function __construct(?WalletService $walletService = null)
    {
        $this->walletService = $walletService ?? new WalletService();
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $query = Expense::query()->orderByDesc('data_spesa');

        if (!empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }
        if (!empty($params['from'])) {
            $query->where('data_spesa', '>=', $params['from']);
        }
        if (!empty($params['to'])) {
            $query->where('data_spesa', '<=', $params['to']);
        }

        $response->getBody()->write($query->get()->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $user = User::find($data['user_id'] ?? null);
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'utente non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            $expense = $this->walletService->createExpense(
                $user,
                (float) ($data['importo'] ?? 0),
                (string) ($data['data_spesa'] ?? ''),
                $data['note'] ?? null,
                (float) ($data['wallet_usato'] ?? 0)
            );
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($expense->toJson());
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $expense = Expense::find($args['id']);
        if (!$expense) {
            $response->getBody()->write(json_encode(['error' => 'spesa non trovata']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $data = (array) $request->getParsedBody();

        try {
            $expense = $this->walletService->updateExpense(
                $expense,
                (float) ($data['importo'] ?? 0),
                (string) ($data['data_spesa'] ?? ''),
                $data['note'] ?? null,
                (float) ($data['wallet_usato'] ?? 0)
            );
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($expense->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $expense = Expense::find($args['id']);
        if (!$expense) {
            $response->getBody()->write(json_encode(['error' => 'spesa non trovata']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $this->walletService->deleteExpense($expense);
        return $response->withStatus(204);
    }
}
```

- [ ] **Step 4: Aggiungere le rotte in `api/src/routes.php`** (dentro il gruppo `/api` protetto da `AuthMiddleware`, insieme a quelle utenti)

```php
        $group->get('/expenses', [\App\Controllers\ExpenseController::class, 'index']);
        $group->post('/expenses', [\App\Controllers\ExpenseController::class, 'create']);
        $group->put('/expenses/{id}', [\App\Controllers\ExpenseController::class, 'update']);
        $group->delete('/expenses/{id}', [\App\Controllers\ExpenseController::class, 'delete']);
```

- [ ] **Step 5: Eseguire i test e verificare che passino**

Run: `cd api && vendor/bin/phpunit tests/Controllers/ExpenseControllerTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add api/src/Controllers/ExpenseController.php api/src/routes.php api/tests/Controllers/ExpenseControllerTest.php
git commit -m "Add expense CRUD endpoints wired to WalletService"
```

---

### Task 8: Endpoint wallet (dettaglio admin + lookup pubblico per telefono)

**Files:**
- Create: `api/src/Controllers/WalletController.php`
- Modify: `api/src/routes.php`
- Test: `api/tests/Controllers/WalletControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\User`, `App\Services\WalletService` (`maxRedeemable`).
- Produces: `GET /api/users/{id}/wallet` (admin), `POST /api/wallet/lookup` (pubblico) — usati dalla SPA (Task 12, 14).

- [ ] **Step 1: Scrivere il test fallito per il lookup pubblico**

```php
<?php

namespace Tests\Controllers;

use App\Models\User;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once __DIR__ . '/../TestDatabase.php';

class WalletControllerTest extends TestCase
{
    protected function setUp(): void
    {
        \Tests\TestDatabase::boot();
        \Tests\TestDatabase::migrate();
    }

    protected function tearDown(): void
    {
        \Tests\TestDatabase::reset();
    }

    public function testLookupByPhoneReturnsBalanceAndHistory(): void
    {
        $user = User::create([
            'nome' => 'Anna', 'cognome' => 'Bianchi', 'telefono' => '3339990000',
            'email' => 'anna@example.com', 'data_nascita' => '1995-05-20', 'wallet_balance' => 0,
        ]);
        (new \App\Services\WalletService())->createExpense($user, 100, '2026-08-16', null, 0);

        $controller = new \App\Controllers\WalletController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/wallet/lookup')
            ->withParsedBody(['telefono' => '3339990000']);
        $response = $controller->lookup($request, new \Slim\Psr7\Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(5.0, $body['wallet_balance']);
        $this->assertCount(1, $body['transactions']);
    }

    public function testLookupByUnknownPhoneReturns404(): void
    {
        $controller = new \App\Controllers\WalletController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/wallet/lookup')
            ->withParsedBody(['telefono' => '0000000000']);
        $response = $controller->lookup($request, new \Slim\Psr7\Response());

        $this->assertEquals(404, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `cd api && vendor/bin/phpunit tests/Controllers/WalletControllerTest.php`
Expected: FAIL — `Class "App\Controllers\WalletController" not found`.

- [ ] **Step 3: Implementare `api/src/Controllers/WalletController.php`**

```php
<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\WalletService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WalletController
{
    private WalletService $walletService;

    public function __construct(?WalletService $walletService = null)
    {
        $this->walletService = $walletService ?? new WalletService();
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = User::find($args['id']);
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'utente non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($this->buildPayload($user));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function lookup(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $user = User::where('telefono', $data['telefono'] ?? '')->first();

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'nessun utente con questo numero']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($this->buildPayload($user));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function buildPayload(User $user): string
    {
        return json_encode([
            'nome' => $user->nome,
            'cognome' => $user->cognome,
            'wallet_balance' => $user->wallet_balance,
            'max_redeemable' => $this->walletService->maxRedeemable($user),
            'transactions' => $user->walletTransactions()->get()->map(fn ($t) => [
                'tipo' => $t->tipo,
                'importo' => $t->importo,
                'saldo_dopo' => $t->saldo_dopo,
                'created_at' => (string) $t->created_at,
            ]),
        ]);
    }
}
```

- [ ] **Step 4: Aggiungere le rotte in `api/src/routes.php`**

Dentro il gruppo protetto `/api`:
```php
        $group->get('/users/{id}/wallet', [\App\Controllers\WalletController::class, 'show']);
```

Fuori dal gruppo protetto (pubblica), accanto alle rotte di login:
```php
    $app->post('/api/wallet/lookup', [\App\Controllers\WalletController::class, 'lookup']);
```

- [ ] **Step 5: Eseguire i test e verificare che passino**

Run: `cd api && vendor/bin/phpunit tests/Controllers/WalletControllerTest.php`
Expected: PASS.

- [ ] **Step 6: Eseguire l'intera suite backend**

Run: `cd api && vendor/bin/phpunit`
Expected: tutti i test verdi (Models, Services, Controllers).

- [ ] **Step 7: Commit**

```bash
git add api/src/Controllers/WalletController.php api/src/routes.php api/tests/Controllers/WalletControllerTest.php
git commit -m "Add admin wallet detail and public phone lookup endpoints"
```

---

### Task 9: Frontend scaffolding (Vite + Vue 3 + Router + Pinia)

**Files:**
- Create: `web/package.json`
- Create: `web/vite.config.js`
- Create: `web/index.html`
- Create: `web/src/main.js`
- Create: `web/src/App.vue`
- Create: `web/src/api/client.js`
- Create: `web/src/router/index.js`

**Interfaces:**
- Produces: `apiClient` (istanza axios, `web/src/api/client.js`, `baseURL: '/api'`, `withCredentials: true`) usato da tutti i task successivi; `router` (Vue Router) registrato in `main.js`.

- [ ] **Step 1: Creare `web/package.json`**

```json
{
  "name": "fighthouse-web",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build"
  },
  "dependencies": {
    "vue": "^3.4.0",
    "vue-router": "^4.3.0",
    "pinia": "^2.1.0",
    "axios": "^1.6.0"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.0.0",
    "vite": "^5.2.0"
  }
}
```

- [ ] **Step 2: Installare le dipendenze**

Run: `cd web && npm install`
Expected: crea `node_modules/` e `package-lock.json` senza errori.

- [ ] **Step 3: Creare `web/vite.config.js`** (proxy verso l'API in sviluppo, così `npm run dev` gira senza CORS)

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    proxy: {
      '/api': 'http://127.0.0.1:8080',
    },
  },
})
```

- [ ] **Step 4: Creare `web/index.html`**

```html
<!doctype html>
<html lang="it">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fighthouse</title>
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/main.js"></script>
  </body>
</html>
```

- [ ] **Step 5: Creare `web/src/api/client.js`**

```js
import axios from 'axios'

export const apiClient = axios.create({
  baseURL: '/api',
  withCredentials: true,
})
```

- [ ] **Step 6: Creare `web/src/App.vue`**

```vue
<template>
  <router-view />
</template>
```

- [ ] **Step 7: Creare `web/src/router/index.js`** (placeholder, rotte reali nei task 10-14)

```js
import { createRouter, createWebHistory } from 'vue-router'

const routes = []

export const router = createRouter({
  history: createWebHistory(),
  routes,
})
```

- [ ] **Step 8: Creare `web/src/main.js`**

```js
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { router } from './router'

createApp(App).use(createPinia()).use(router).mount('#app')
```

- [ ] **Step 9: Verificare l'avvio in locale**

Run: `cd web && npm run dev`
Expected: server Vite avviato su `http://localhost:5173` senza errori in console (pagina bianca attesa, non ci sono ancora rotte).

- [ ] **Step 10: Commit**

```bash
git add web/package.json web/package-lock.json web/vite.config.js web/index.html web/src/main.js web/src/App.vue web/src/api/client.js web/src/router/index.js
git commit -m "Scaffold Vue 3 + Vite SPA with router and pinia"
```

---

### Task 10: Autenticazione admin lato SPA

**Files:**
- Create: `web/src/stores/auth.js`
- Create: `web/src/views/admin/LoginView.vue`
- Modify: `web/src/router/index.js`

**Interfaces:**
- Consumes: `apiClient` (Task 9), `POST /api/admin/login` / `POST /api/admin/logout` (Task 5).
- Produces: store Pinia `useAuthStore()` con stato `isAuthenticated` e azioni `login(username, password)` / `logout()`, usato dal router guard e da tutte le view admin successive.

- [ ] **Step 1: Creare `web/src/stores/auth.js`**

```js
import { defineStore } from 'pinia'
import { apiClient } from '../api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    isAuthenticated: false,
  }),
  actions: {
    async login(username, password) {
      await apiClient.post('/admin/login', { username, password })
      this.isAuthenticated = true
    },
    async logout() {
      await apiClient.post('/admin/logout')
      this.isAuthenticated = false
    },
  },
})
```

- [ ] **Step 2: Creare `web/src/views/admin/LoginView.vue`**

```vue
<template>
  <div class="login">
    <h1>Accesso amministratore</h1>
    <form @submit.prevent="submit">
      <input v-model="username" placeholder="Username" required />
      <input v-model="password" type="password" placeholder="Password" required />
      <button type="submit" :disabled="loading">Accedi</button>
      <p v-if="error" class="error">{{ error }}</p>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const username = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const auth = useAuthStore()
const router = useRouter()

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(username.value, password.value)
    router.push('/admin/users')
  } catch (e) {
    error.value = 'Credenziali non valide'
  } finally {
    loading.value = false
  }
}
</script>
```

- [ ] **Step 3: Aggiungere la rotta di login e il router guard in `web/src/router/index.js`**

```js
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LoginView from '../views/admin/LoginView.vue'

const routes = [
  { path: '/admin/login', component: LoginView },
  {
    path: '/admin',
    redirect: '/admin/users',
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.path.startsWith('/admin') && to.path !== '/admin/login' && !auth.isAuthenticated) {
    return '/admin/login'
  }
})
```

Nota: `auth.isAuthenticated` è in memoria e si resetta al reload della pagina — accettabile per questa scala (l'utente rifà login se ricarica). Le rotte admin successive (Task 11-13) vanno aggiunte a questo stesso array `routes`.

- [ ] **Step 4: Verificare manualmente il flusso di login**

Run: `cd api && php -S 127.0.0.1:8080 -t public` (in un terminale) e `cd web && npm run dev` (in un altro).
Creare un admin di test: `cd api && php bin/seed-admin.php admin secret123`.
Aprire `http://localhost:5173/admin/login`, inserire `admin`/`secret123`.
Expected: redirect a `/admin/users` (pagina vuota per ora, la view arriva nel Task 11) senza errori in console.

- [ ] **Step 5: Commit**

```bash
git add web/src/stores/auth.js web/src/views/admin/LoginView.vue web/src/router/index.js
git commit -m "Add admin login view, auth store and router guard"
```

---

### Task 11: Elenco e censimento utenti

**Files:**
- Create: `web/src/views/admin/UsersListView.vue`
- Modify: `web/src/router/index.js`

**Interfaces:**
- Consumes: `apiClient`, `GET/POST /api/users` (Task 6).
- Produces: rotta `/admin/users`, link verso `/admin/users/:id` (Task 12).

- [ ] **Step 1: Creare `web/src/views/admin/UsersListView.vue`**

```vue
<template>
  <div class="users-list">
    <h1>Utenti</h1>
    <input v-model="search" placeholder="Cerca per nome, cognome, telefono" @input="load" />

    <form @submit.prevent="createUser" class="new-user">
      <input v-model="form.nome" placeholder="Nome" required />
      <input v-model="form.cognome" placeholder="Cognome" required />
      <input v-model="form.telefono" placeholder="Telefono" required />
      <input v-model="form.email" placeholder="Email" required />
      <input v-model="form.data_nascita" type="date" required />
      <button type="submit">Aggiungi utente</button>
      <p v-if="error" class="error">{{ error }}</p>
    </form>

    <ul>
      <li v-for="u in users" :key="u.id">
        <router-link :to="`/admin/users/${u.id}`">{{ u.nome }} {{ u.cognome }} — {{ u.telefono }}</router-link>
        (saldo: {{ u.wallet_balance }}€)
      </li>
    </ul>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { apiClient } from '../../api/client'

const users = ref([])
const search = ref('')
const error = ref('')
const form = reactive({ nome: '', cognome: '', telefono: '', email: '', data_nascita: '' })

async function load() {
  const { data } = await apiClient.get('/users', { params: { q: search.value || undefined } })
  users.value = data
}

async function createUser() {
  error.value = ''
  try {
    await apiClient.post('/users', form)
    Object.keys(form).forEach((k) => (form[k] = ''))
    await load()
  } catch (e) {
    error.value = e.response?.data?.error || 'errore durante la creazione'
  }
}

onMounted(load)
</script>
```

- [ ] **Step 2: Aggiungere la rotta in `web/src/router/index.js`**

```js
import UsersListView from '../views/admin/UsersListView.vue'
// ...
const routes = [
  { path: '/admin/login', component: LoginView },
  { path: '/admin', redirect: '/admin/users' },
  { path: '/admin/users', component: UsersListView },
]
```

- [ ] **Step 3: Verificare manualmente**

Con backend e frontend avviati (Task 10), fare login e su `/admin/users` creare un utente di test.
Expected: l'utente compare in lista con saldo `0€`.

- [ ] **Step 4: Commit**

```bash
git add web/src/views/admin/UsersListView.vue web/src/router/index.js
git commit -m "Add users list and creation form"
```

---

### Task 12: Dettaglio utente (wallet, storico movimenti, storico spese)

**Files:**
- Create: `web/src/views/admin/UserDetailView.vue`
- Modify: `web/src/router/index.js`

**Interfaces:**
- Consumes: `apiClient`, `GET /api/users/{id}`, `GET /api/users/{id}/wallet`, `GET /api/expenses?user_id=` (Task 6, 7, 8).
- Produces: rotta `/admin/users/:id`, link verso `/admin/expenses/new?user_id=:id` (Task 13).

- [ ] **Step 1: Creare `web/src/views/admin/UserDetailView.vue`**

```vue
<template>
  <div v-if="user" class="user-detail">
    <h1>{{ user.nome }} {{ user.cognome }}</h1>
    <p>Telefono: {{ user.telefono }} — Email: {{ user.email }}</p>
    <p class="balance">Saldo wallet: <strong>{{ wallet.wallet_balance }}€</strong></p>
    <router-link :to="`/admin/expenses/new?user_id=${user.id}`">Nuova spesa</router-link>

    <h2>Storico movimenti wallet</h2>
    <ul>
      <li v-for="(t, i) in wallet.transactions" :key="i">
        {{ t.created_at }} — {{ t.tipo }}: {{ t.importo }}€ (saldo dopo: {{ t.saldo_dopo }}€)
      </li>
    </ul>

    <h2>Storico spese</h2>
    <ul>
      <li v-for="e in expenses" :key="e.id">
        {{ e.data_spesa }} — {{ e.importo }}€ (wallet usato: {{ e.wallet_usato }}€)
      </li>
    </ul>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { apiClient } from '../../api/client'

const route = useRoute()
const user = ref(null)
const wallet = ref({ wallet_balance: 0, transactions: [] })
const expenses = ref([])

onMounted(async () => {
  const id = route.params.id
  const [userRes, walletRes, expensesRes] = await Promise.all([
    apiClient.get(`/users/${id}`),
    apiClient.get(`/users/${id}/wallet`),
    apiClient.get('/expenses', { params: { user_id: id } }),
  ])
  user.value = userRes.data
  wallet.value = walletRes.data
  expenses.value = expensesRes.data
})
</script>
```

- [ ] **Step 2: Aggiungere la rotta in `web/src/router/index.js`**

```js
import UserDetailView from '../views/admin/UserDetailView.vue'
// ...
  { path: '/admin/users/:id', component: UserDetailView },
```

- [ ] **Step 3: Verificare manualmente**

Cliccare su un utente dalla lista (Task 11). Expected: pagina di dettaglio con saldo, storico movimenti vuoto, storico spese vuoto.

- [ ] **Step 4: Commit**

```bash
git add web/src/views/admin/UserDetailView.vue web/src/router/index.js
git commit -m "Add user detail view with wallet balance and history"
```

---

### Task 13: Form inserimento spesa (con soglia wallet)

**Files:**
- Create: `web/src/views/admin/ExpenseFormView.vue`
- Modify: `web/src/router/index.js`

**Interfaces:**
- Consumes: `apiClient`, `GET /api/users/{id}/wallet` (per `max_redeemable`), `POST /api/expenses` (Task 7, 8).

- [ ] **Step 1: Creare `web/src/views/admin/ExpenseFormView.vue`**

```vue
<template>
  <div class="expense-form">
    <h1>Nuova spesa</h1>
    <form @submit.prevent="submit">
      <label>Importo (€)</label>
      <input v-model.number="importo" type="number" step="0.01" min="0.01" required @change="refreshMax" />

      <label>Data</label>
      <input v-model="dataSpesa" type="date" required />

      <label>Note</label>
      <input v-model="note" placeholder="opzionale" />

      <label v-if="maxRedeemable > 0">
        <input type="checkbox" v-model="useWallet" />
        Usa {{ maxRedeemable }}€ di wallet accumulato
      </label>
      <p v-else class="hint">Nessun blocco da 25€ disponibile per lo scarico wallet al momento.</p>

      <button type="submit">Registra spesa</button>
      <p v-if="error" class="error">{{ error }}</p>
    </form>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiClient } from '../../api/client'

const route = useRoute()
const router = useRouter()
const userId = route.query.user_id

const importo = ref(null)
const dataSpesa = ref(new Date().toISOString().slice(0, 10))
const note = ref('')
const maxRedeemable = ref(0)
const useWallet = ref(false)
const error = ref('')

async function refreshMax() {
  const { data } = await apiClient.get(`/users/${userId}/wallet`)
  maxRedeemable.value = data.max_redeemable
}

async function submit() {
  error.value = ''
  try {
    await apiClient.post('/expenses', {
      user_id: userId,
      importo: importo.value,
      data_spesa: dataSpesa.value,
      note: note.value || null,
      wallet_usato: useWallet.value ? maxRedeemable.value : 0,
    })
    router.push(`/admin/users/${userId}`)
  } catch (e) {
    error.value = e.response?.data?.error || 'errore durante il salvataggio'
  }
}

onMounted(refreshMax)
</script>
```

- [ ] **Step 2: Aggiungere la rotta in `web/src/router/index.js`**

```js
import ExpenseFormView from '../views/admin/ExpenseFormView.vue'
// ...
  { path: '/admin/expenses/new', component: ExpenseFormView },
```

- [ ] **Step 3: Verificare manualmente il flusso completo**

Dal dettaglio utente (Task 12), cliccare "Nuova spesa", inserire un importo (es. 100€), registrare.
Expected: redirect al dettaglio utente, saldo wallet aggiornato a 5€, movimento visibile nello storico.
Ripetere fino a raggiungere un saldo >= 25€ e verificare che l'opzione "usa wallet" compaia con l'importo corretto.

- [ ] **Step 4: Commit**

```bash
git add web/src/views/admin/ExpenseFormView.vue web/src/router/index.js
git commit -m "Add expense form with wallet redemption threshold option"
```

---

### Task 14: Pagina pubblica lookup wallet

**Files:**
- Create: `web/src/views/public/WalletLookupView.vue`
- Modify: `web/src/router/index.js`

**Interfaces:**
- Consumes: `apiClient`, `POST /api/wallet/lookup` (Task 8).

- [ ] **Step 1: Creare `web/src/views/public/WalletLookupView.vue`**

```vue
<template>
  <div class="wallet-lookup">
    <h1>Il tuo wallet</h1>
    <form @submit.prevent="lookup">
      <input v-model="telefono" placeholder="Numero di telefono" required />
      <button type="submit">Vedi saldo</button>
    </form>

    <p v-if="error" class="error">{{ error }}</p>

    <div v-if="result">
      <h2>Ciao {{ result.nome }}!</h2>
      <p class="balance">Saldo wallet: <strong>{{ result.wallet_balance }}€</strong></p>
      <h3>Storico movimenti</h3>
      <ul>
        <li v-for="(t, i) in result.transactions" :key="i">
          {{ t.created_at }} — {{ t.tipo }}: {{ t.importo }}€
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { apiClient } from '../../api/client'

const telefono = ref('')
const result = ref(null)
const error = ref('')

async function lookup() {
  error.value = ''
  result.value = null
  try {
    const { data } = await apiClient.post('/wallet/lookup', { telefono: telefono.value })
    result.value = data
  } catch (e) {
    error.value = e.response?.data?.error || 'numero non trovato'
  }
}
</script>
```

- [ ] **Step 2: Aggiungere la rotta pubblica in `web/src/router/index.js`**

```js
import WalletLookupView from '../views/public/WalletLookupView.vue'
// ...
  { path: '/wallet', component: WalletLookupView },
```

- [ ] **Step 3: Verificare manualmente**

Aprire `http://localhost:5173/wallet`, inserire il telefono di un utente creato nei task precedenti.
Expected: saldo e storico movimenti mostrati correttamente; un numero non esistente mostra l'errore.

- [ ] **Step 4: Commit**

```bash
git add web/src/views/public/WalletLookupView.vue web/src/router/index.js
git commit -m "Add public wallet lookup page by phone number"
```

---

### Task 15: Configurazione di deploy per hosting condiviso

**Files:**
- Create: `deploy/.htaccess`
- Create: `README.md`

**Interfaces:**
- Nessuna — task di packaging finale, non introduce codice consumato da altri task.

- [ ] **Step 1: Creare `deploy/.htaccess`** (da copiare nella document root del dominio insieme a `dist/` e alla cartella `api/`)

```apache
RewriteEngine On

# Instrada le richieste /api/* verso il front controller di Slim
RewriteCond %{REQUEST_URI} ^/api/
RewriteRule ^api/(.*)$ api/public/index.php [QSA,L]

# Fallback SPA: tutte le altre richieste non corrispondenti a file/cartelle esistenti vanno a index.html
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/api/
RewriteRule ^ index.html [L]
```

- [ ] **Step 2: Scrivere `README.md` con le istruzioni di build e deploy**

```markdown
# Fighthouse

Backend Slim 4 + Eloquent + MySQL, SPA Vue 3 + Vite.

## Sviluppo locale

1. `cp api/.env.example api/.env` e inserire le credenziali del MySQL locale.
2. `cd api && composer install && vendor/bin/phinx migrate`
3. `cd api && php bin/seed-admin.php admin <password>`
4. `cd api && php -S 127.0.0.1:8080 -t public`
5. `cd web && npm install && npm run dev`
6. Aprire `http://localhost:5173/admin/login` (area admin) e `http://localhost:5173/wallet` (pagina pubblica).

## Deploy su hosting condiviso (cPanel)

1. In locale: `cd api && composer install --no-dev` (genera `vendor/` senza dipendenze di sviluppo).
2. In locale: `cd web && npm run build` (genera `web/dist/`).
3. Creare il database MySQL dal pannello cPanel e aggiornare `api/.env` con le credenziali reali.
4. Eseguire le migrazioni contro il DB di produzione: `cd api && vendor/bin/phinx migrate` (da locale, se il DB è raggiungibile dall'esterno, oppure da un accesso SSH temporaneo se disponibile).
5. Eseguire `php bin/seed-admin.php <username> <password>` per creare il primo account admin.
6. Via FTP, caricare nella document root del dominio:
   - il contenuto di `web/dist/` (i file compilati della SPA)
   - la cartella `api/` (incluso `vendor/`, escluso `.env` se non già presente/configurato lato server)
   - `deploy/.htaccess` rinominato in `.htaccess` nella document root
7. Verificare `https://tuodominio.it/api/health` → `{"status":"ok"}` e `https://tuodominio.it/admin/login`.
```

- [ ] **Step 3: Commit**

```bash
git add deploy/.htaccess README.md
git commit -m "Add deployment config and setup instructions for shared hosting"
```

---

## Self-Review Summary

- **Copertura spec:** modello dati (Task 2-3), logica accredito/scarico/rettifica (Task 4), auth admin (Task 5), CRUD utenti (Task 6), CRUD spese (Task 7), wallet admin + lookup pubblico (Task 8), SPA admin completa (Task 9-13), pagina pubblica (Task 14), deploy hosting condiviso (Task 15). Nessuna sezione della spec priva di task corrispondente.
- **Placeholder:** nessun TBD/TODO residuo; ogni step contiene codice concreto o comando eseguibile con output atteso esplicito.
- **Coerenza tipi/firme:** `WalletService::createExpense/updateExpense/deleteExpense/maxRedeemable` hanno la stessa firma ovunque vengano richiamati (Task 4, 7); `apiClient` e le rotte `/api/*` sono usate in modo coerente in tutte le view (Task 9-14); i nomi dei campi DB (`nome`, `cognome`, `telefono`, `email`, `data_nascita`, `wallet_balance`, `importo`, `wallet_usato`, `tipo`, `saldo_dopo`) sono identici tra migrazioni, modelli, controller e componenti Vue.
