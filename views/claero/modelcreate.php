<script>
</script>
<h2>Model Code Generation</h2>
<p>It looks like you don't have a model set up yet.
The following tool can help generate a starting point from your database table.
Just select a table and click "create" to see some sample model code in the textarea below.</p>
<p>If you want to use this code, create a new file in your application/classes/model directory and name it objectname.php
and copy and paste the code in to this file.  You will then want to make sure the meta data is all correct, espcially
with respect to displaying sensitive data.</p>
<?php

	$db = Database::instance($db_group);
	$table_list = $db->list_tables();
	$table_list = array_combine($table_list, $table_list);

?>

Select a table to generate the cl4/orm model code:
<?php echo Form::select('m_table_name', $table_list, $table_name, array('id' => 'm_table_name')); ?>&nbsp;
<?php echo Form::input('create', 'Create', array('type' => 'button', 'onclick' => '$(\'#m_table_name\').change();')); ?>
<textarea id="model_code_container" style="width: 100%; height: 500px; margin: 15px 0;"></textarea>