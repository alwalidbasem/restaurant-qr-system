<?php


include (__DIR__."/hash.php");

    $Hashing = new AdminPasswordHasher();
    $Password = $Hashing->hash("123123123");
    echo $Password;

?>