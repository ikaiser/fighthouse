<?php

use Phinx\Migration\AbstractMigration;

final class CreateCoachWalletTransactionsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('coach_wallet_transactions');
        $table
            ->addColumn('coach_id', 'integer')
            ->addColumn('expense_id', 'integer', ['null' => true])
            ->addColumn('tipo', 'string', ['limit' => 20])
            ->addColumn('importo', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('saldo_dopo', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['coach_id'])
            ->addIndex(['expense_id'])
            ->addForeignKey('coach_id', 'coaches', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE coach_wallet_transactions ADD CONSTRAINT coach_wallet_transactions_tipo_check CHECK (tipo IN ('accredito', 'rettifica'))");
    }
}
