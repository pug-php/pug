<?php

include 'bootstrap.php';

extract(get_tests_results($verbose = true));

print "Success: $success\n";
print "Failures: $failures\n";
print "Cover: " . round(100 * $success / ($success + $failures)) . "%\n";
