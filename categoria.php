<?php
require_once __DIR__.'/cms/core.php';
$category=isset($_GET['categoria']) && is_string($_GET['categoria']) && $_GET['categoria']!=='' ? cms_category($_GET['categoria']) : null;
require __DIR__.'/rss/listing.php';
