<?php
// app/Controllers/AdminQrController.php

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/settings.php';

class AdminQrController {

    public function show(): void {
        $settings = Settings::all();
        require BASE_PATH . '/app/Views/admin/qrcode.php';
    }

    public function save(): void {
        $id = trim($_POST['line_basic_id'] ?? '');
        // @を先頭に正規化
        if ($id && $id[0] !== '@') {
            $id = '@' . $id;
        }
        Settings::set('line_basic_id', $id);
        header('Location: /admin/qrcode?saved=1');
        exit;
    }

    public function delete(): void {
        Settings::set('line_basic_id', '');
        header('Location: /admin/qrcode?deleted=1');
        exit;
    }
}
