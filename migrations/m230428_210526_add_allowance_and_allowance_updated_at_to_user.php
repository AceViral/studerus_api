<?php

use yii\db\Migration;

/**
 * Class m230428_210526_add_allowance_and_allowance_updated_at_to_user
 */
class m230428_210526_add_allowance_and_allowance_updated_at_to_user extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user', 'allowance', $this->integer());
        $this->addColumn('user', 'allowance_updated_at', $this->integer());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('user', 'allowance');
        $this->dropColumn('user', 'allowance_updated_at');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230428_210526_add_allowance_and_allowance_updated_at_to_user cannot be reverted.\n";

        return false;
    }
    */
}
