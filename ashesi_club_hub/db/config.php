<?php 
    $servername='localhost';
    $username='nana.nyarko';
    $password='o$einyarko';
    $dbname='webtech_fall2024_nana_nyarko';

    // making the connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    //
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
        }
?>