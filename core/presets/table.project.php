<?php

return array(
	1 => array(
		'column' => 'id',
		'type' => 'int',
		'character' => '10',
		'null' => 0,
		'auto' => 1,
		'default' => '',
		'key' => 'p',
		'key_visibility' => ''
	),

	2 => array(
		'column' => 'code',
		'type' => 'varchar',
		'character' => '8',
		'null' => 0,
		'auto' => 0,
		'default' => ''
	),

	3 => array(
		'column' => 'shortname',
		'type' => 'varchar',
		'character' => '8',
		'null' => 0,
		'auto' => 0,
		'default' => ''
	),

	4 => array(
		'column' => 'name',
		'type' => 'text',
		'character' => '',
		'null' => 0,
		'auto' => 0
	),

	5 => array(
		'column' => 'desc',
		'type' => 'text',
		'character' => '',
		'null' => 1,
		'auto' => 0,
		'default' => NULL
	),

	6 => array(
		'column' => 'start',
		'type' => 'varchar',
		'character' => '12',
		'null' => 0,
		'auto' => 0,
		'default' => '0'
	),

	7 => array(
		'column' => 'finish',
		'type' => 'varchar',
		'character' => '12',
		'null' => 0,
		'auto' => 0,
		'default' => '0'
	),

	8 => array(
		'column' => 'setting',
		'type' => 'text',
		'character' => '',
		'null' => 1,
		'auto' => 0
	),

	9 => array(
		'column' => 'client',
		'type' => 'varchar',
		'character' => '8',
		'null' => 0,
		'auto' => 0
	),

	10 => array(
		'column' => 'consult',
		'type' => 'varchar',
		'character' => '8',
		'null' => 1,
		'auto' => 0
	),

	11 => array(
		'column' => 'tpi',
		'type' => 'text',
		'character' => '',
		'null' => 1,
		'auto' => 0
	),

	12 => array(
		'column' => 'agency',
		'type' => 'text',
		'character' => '',
		'null' => 1,
		'auto' => 0
	),

	13 => array(
		'column' => 'office',
		'type' => 'text',
		'character' => '',
		'null' => 1,
		'auto' => 0
	),

	14 => array(
		'column' => 'yard',
		'type' => 'text',
		'character' => '',
		'null' => 0,
		'auto' => 0
	),

	15 => array(
		'column' => 'budget',
		'type' => 'int',
		'character' => '15',
		'null' => 1,
		'auto' => 0,
		'default' => NULL
	),

	16 => array(
		'column' => 'status',
		'type' => 'tinyint',
		'character' => '1',
		'null' => 0,
		'auto' => 0,
		'default' => '1'
	)
);