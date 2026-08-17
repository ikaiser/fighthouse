<?php

use Phinx\Migration\AbstractMigration;

final class RenameUsersToAthletesAddCoach extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users')->rename('athletes')->save();

        $athletes = $this->table('athletes');
        $athletes->addColumn('coach_id', 'integer', ['null' => true])->save();

        // Assicura che esista almeno un coach a cui agganciare gli atleti già censiti
        // (dati di sviluppo pre-esistenti alla relazione Coach -> Atleti).
        $this->execute(
            "INSERT INTO coaches (nome, cognome, telefono, email, wallet_balance, created_at, updated_at)
             SELECT 'Coach', 'Predefinito', '0000000000', 'coach@fighthouse.local', 0, NOW(), NOW()
             WHERE NOT EXISTS (SELECT 1 FROM coaches)"
        );
        $this->execute(
            'UPDATE athletes SET coach_id = (SELECT id FROM coaches ORDER BY id LIMIT 1) WHERE coach_id IS NULL'
        );

        $athletes
            ->changeColumn('coach_id', 'integer', ['null' => false])
            ->addIndex(['coach_id'])
            ->addForeignKey('coach_id', 'coaches', 'id', ['delete' => 'RESTRICT'])
            ->update();
    }

    public function down(): void
    {
        $athletes = $this->table('athletes');
        $athletes->dropForeignKey('coach_id')->save();
        $athletes->removeColumn('coach_id')->save();
        $athletes->rename('users')->save();
    }
}
