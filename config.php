<?php
declare(strict_types=1);

const APP_NAME = 'BEACON TEAM';
const DB_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'data';
const DB_FILE = DB_DIR . DIRECTORY_SEPARATOR . 'cages.sqlite';
const SESSION_NAME = 'cage_ledger_session';
const DATABASE_URL_ENV = 'DATABASE_URL';
const INITIAL_ADMIN_USERNAME_ENCODED = 'TnVtQ2hh';
// เก็บข้อมูลเริ่มต้นแบบไม่แสดงค่าจริง และเก็บรหัสผ่านเป็น hash เท่านั้น
const INITIAL_ADMIN_PASSWORD_HASH = '$2y$10$qijDiVTaYBCqQkGePeX4p.S2BQZHMBBDd54nBqThxE9KMspnmxMEC';
const SECOND_ADMIN_USERNAME_ENCODED = 'LkRyYWdvblNsYXNo';
const SECOND_ADMIN_PASSWORD_HASH = '$2y$10$QwYSfAwem8TpMXDaHrdnn.qA87FJQedkk00sdxafe7D39gaNWnCaK';
