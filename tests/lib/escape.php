<?php

function __escape($text) {
    return htmlspecialchars($text, ENT_QUOTES);
}
