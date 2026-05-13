<?php 
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<h1>Memory Wiped.</h1>";
} else {
    echo "<h1>OPcache not active, checking browser...</h1>";
}
?>