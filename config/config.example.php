<?php
/**
 * Contoh konfigurasi Examora.
 *
 * File ini TIDAK dipakai oleh aplikasi. Installer membuat config/config.php
 * secara otomatis. Salin file ini menjadi config/config.php hanya bila Anda
 * ingin mengonfigurasi secara manual.
 */
return [
    // Kunci acak 64 karakter hex. Bisa dibuat dengan: bin2hex(random_bytes(32))
    'app_key' => 'ganti-dengan-kunci-acak-64-karakter-hex',

    // true hanya saat pengembangan. Jangan aktifkan di server produksi.
    'debug' => false,

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'cbt_bebas',
        'user' => 'root',
        'pass' => '',
    ],

    // Sesi login (opsional). Default di bawah sudah aman untuk kebanyakan
    // sekolah; ubah hanya bila perlu.
    // 'session' => [
    //     'cookie_lifetime' => 43200, // masa berlaku cookie login (detik, 12 jam)
    //     'gc_maxlifetime' => 43200,  // umur file sesi sebelum dibersihkan (detik)
    // ],
];
