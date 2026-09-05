<?php

use Phinx\Migration\AbstractMigration;

final class CreateExpensesTable extends AbstractMigration
{
    public function change(): void
    {
        // FK verso 'users': a questo punto della cronologia delle migrazioni la
        // tabella si chiama ancora così (rinominata in 'athletes' solo dalla
        // migrazione 20260817000002). Non cambiare: Postgres segue il rename
        // della tabella a livello di OID, quindi il vincolo resta valido, ma
        // modificare qui il nome romperebbe un setup eseguito da zero.
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
