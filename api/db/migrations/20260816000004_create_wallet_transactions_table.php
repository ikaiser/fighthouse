<?php

use Phinx\Migration\AbstractMigration;

final class CreateWalletTransactionsTable extends AbstractMigration
{
    public function change(): void
    {
        // FK verso 'users': vedi nota in 20260816000003_create_expenses_table.php.
        $table = $this->table('wallet_transactions');
        $table
            ->addColumn('user_id', 'integer')
            ->addColumn('expense_id', 'integer', ['null' => true])
            ->addColumn('tipo', 'string', ['limit' => 20])
            ->addColumn('importo', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('saldo_dopo', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'])
            ->addIndex(['expense_id'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_tipo_check CHECK (tipo IN ('accredito', 'scarico', 'rettifica'))");
    }
}
