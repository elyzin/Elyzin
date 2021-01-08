<?php

return [
    ['id', 'int', '10', 0, 1, '', 'p', ''],
    ['fullname', 'varchar', '100', 1, 0, null],
    ['username', 'varchar', '30', 0, 0,''],
    ['email', 'varchar', '100', 0, 0,''],
    ['password', 'varchar', '100', 0, 0,''],
    ['forcepass', 'varchar', '12', 1, 0, null],
    ['regdate', 'varchar', '12', 1, 0, null],
    ['lastseen', 'varchar', '12', 1, 0, null],
    ['ontime', 'varchar', '12', 0, 0, '0'],
    ['group', 'tinyint', '1', 0, 0, '2'],
    ['org', 'varchar', '8', 1, 0, null],
    ['pref', 'text', '', 1, 0, ''],
    ['permit', 'text', '', 1, 0, ''],
    ['lastproj', 'varchar', '12', 1, 0, null],
];