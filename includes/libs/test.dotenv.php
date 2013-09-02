<?php
# Get settings from our .env file.
include("./includes/libs/Dotenv.php");
Dotenv::load(__DIR__);
print_r($_ENV);