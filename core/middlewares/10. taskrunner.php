<?php
// Clean expired session files
$sessions = glob(Config::path('sessions') . '*');
foreach($sessions as $session){
    if(stripos($session, 'conflicted') !== false){
        unlink($session);
    }
}