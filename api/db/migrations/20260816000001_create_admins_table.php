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
