<?php

use Phinx\Migration\AbstractMigration;

final class AddDataNascitaToCoaches extends AbstractMigration
{
    public function change(): void
    {
        $this->table('coaches')
            ->addColumn('data_nascita', 'date', ['null' => true, 'after' => 'email'])
            ->update();
    }
}
