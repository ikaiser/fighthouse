<?php

namespace Tests;

use Illuminate\Database\Capsule\Manager as Capsule;

class TestDatabase
{
    private static bool $booted = false;
    private static bool $migrated = false;

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
        if (self::$migrated) {
            return;
        }
        self::$migrated = true;

        $schema = Capsule::schema();

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
            $t->string('tipo', 20);
            $t->decimal('importo', 10, 2);
            $t->decimal('saldo_dopo', 10, 2);
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public static function reset(): void
    {
        foreach (['wallet_transactions', 'expenses', 'users', 'admins'] as $table) {
            Capsule::table($table)->delete();
        }
    }
}
