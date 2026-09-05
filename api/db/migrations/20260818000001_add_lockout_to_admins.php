<?php

use Phinx\Migration\AbstractMigration;

final class AddLockoutToAdmins extends AbstractMigration
{
    public function change(): void
    {
        $this->table('admins')
            ->addColumn('failed_attempts', 'integer', ['default' => 0])
            ->addColumn('locked_until', 'timestamp', ['null' => true])
            ->update();
    }
}
