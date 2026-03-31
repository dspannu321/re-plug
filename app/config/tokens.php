<?php

function generate_token(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}