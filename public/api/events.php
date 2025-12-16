<?php
// Thin proxy in the public folder so /api paths remain reachable even
// when only /public is exposed as document root.
require __DIR__ . '/../../api/events.php';
