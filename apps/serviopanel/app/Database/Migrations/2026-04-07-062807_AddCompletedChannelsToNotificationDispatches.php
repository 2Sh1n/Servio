<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCompletedChannelsToNotificationDispatches extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('notification_dispatches') || $db->fieldExists('completed_channels', 'notification_dispatches')) {
            return;
        }

        $this->forge->addColumn('notification_dispatches', [
            'completed_channels' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => '',
                'after'      => 'channels',
                'comment'    => 'Comma-separated channels that reached total_recipients',
            ],
        ]);
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('notification_dispatches') || ! $db->fieldExists('completed_channels', 'notification_dispatches')) {
            return;
        }

        $this->forge->dropColumn('notification_dispatches', 'completed_channels');
    }
}
