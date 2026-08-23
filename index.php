<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/variables.php';


if(str_starts_with($uri,"admin")){
    require __DIR__ . '/public/admin/view.php';
}else{
    require __DIR__ . '/public/client/view.php';
}

    
