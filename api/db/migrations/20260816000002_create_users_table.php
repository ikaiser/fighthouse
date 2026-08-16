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
