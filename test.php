<?php

$data = '';
$result = substr($data, 0, 1);
if ($result === '') {
    exit(0);
}
echo "substr('', 0, 1) = ";
var_dump($result);
exit (1);
