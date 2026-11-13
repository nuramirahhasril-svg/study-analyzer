<?php
/**
 * FILE PEMBUKA
 *
 * Di-include paling atas oleh SEMUA file PHP di project ini.
 * Tugasnya: memulai session, membuka koneksi database ($conn),
 * dan memuat fungsi-fungsi bantuan.
 */
session_start();

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
