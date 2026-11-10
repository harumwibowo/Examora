<?php
declare(strict_types=1);

final class AuthController
{
    public function landing(): void
    {
        if (Auth::isStaff()) {
            redirect('dashboard');
        }
        if (Auth::isSiswa()) {
            redirect('siswa_home');
        }
        View::render('landing', [], 'auth');
    }

    public function loginForm(): void
    {
        if (Auth::isStaff()) {
            redirect('dashboard');
        }
        View::render('auth/login', [], 'auth');
    }

    public function loginPost(): void
    {
        Csrf::check();
        $email = strtolower(str_input('email'));
        $pass = (string) post('password', '');
        keep_old(['email' => $email]);

        if ($email === '' || $pass === '') {
            flash_error('Email dan password wajib diisi.');
            redirect('login');
        }
        if (!rate_limit('staff:' . $email, 5, 900)) {
            flash_error('Terlalu banyak percobaan. Coba lagi dalam 15 menit.');
            redirect('login');
        }

        $u = DB::one('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
        if (!$u || !(int) $u['aktif'] || !password_verify($pass, (string) $u['password_hash'])) {
            audit('login_gagal', 'users', $u['id'] ?? null, ['email' => $email]);
            flash_error('Email atau password tidak valid.');
            redirect('login');
        }

        if (password_needs_rehash((string) $u['password_hash'], PASSWORD_DEFAULT)) {
            DB::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
        }

        rate_limit_clear('staff:' . $email);
        clear_old();
        Auth::staff($u);
        DB::exec('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $u['id']]);
        audit('login', 'users', (int) $u['id']);
        redirect('dashboard');
    }

    public function siswaForm(): void
    {
        if (Auth::isSiswa()) {
            redirect('siswa_home');
        }
        View::render('auth/login_siswa', [
            'browserDitolak' => !browser_siswa_diizinkan(),
            'browserDiizinkan' => browser_siswa_allowed_list(),
        ], 'auth');
    }

    public function siswaPost(): void
    {
        Csrf::check();
        $username = str_input('username');
        $pass = (string) post('password', '');
        keep_old(['username' => $username]);

        if ($username === '' || $pass === '') {
            flash_error('Username dan password wajib diisi.');
            redirect('masuk_siswa');
        }

        if (!browser_siswa_diizinkan()) {
            $labels = array_map('browser_label', browser_siswa_allowed_list());
            audit('login_siswa_browser_ditolak', 'siswa', null, [
                'username' => $username,
                'browser' => browser_family(),
            ]);
            flash_error(
                'Login ditolak. Browser ini tidak diizinkan. Gunakan '
                . implode(', ', $labels) . '.'
            );
            redirect('masuk_siswa');
        }

        if (!rate_limit('siswa:' . $username, 5, 900)) {
            flash_error('Terlalu banyak percobaan. Coba lagi dalam 15 menit.');
            redirect('masuk_siswa');
        }

        $s = DB::one('SELECT * FROM siswa WHERE username = ? LIMIT 1', [$username]);
        if (!$s || $s['status'] !== 'Aktif' || !password_verify($pass, (string) $s['password_hash'])) {
            flash_error('Username atau password tidak valid, atau akun tidak aktif.');
            redirect('masuk_siswa');
        }

        rate_limit_clear('siswa:' . $username);

        $maks = maks_login_siswa();
        $aktif = Auth::jumlahSesiSiswa((int) $s['id']);
        if ($aktif >= $maks) {
            audit('login_siswa_ditolak_perangkat', 'siswa', (int) $s['id'], [
                'aktif' => $aktif,
                'maks' => $maks,
                'ip' => client_ip(),
            ]);
            flash_error($maks === 1
                ? 'Akun ini masih login di perangkat lain. Keluar (logout) dulu dari perangkat tersebut, atau minta admin/guru mereset login siswa.'
                : 'Akun ini sudah login di ' . $aktif . ' perangkat (maksimal ' . $maks . '). Keluar dulu dari salah satu perangkat, atau minta admin/guru mereset login siswa.');
            redirect('masuk_siswa');
        }

        clear_old();
        Auth::siswa($s);
        audit('login_siswa', 'siswa', (int) $s['id']);

        $aktif = DB::one(
            "SELECT id FROM riwayat_ujian WHERE id_siswa = ? AND status = 'Berlangsung' ORDER BY id DESC LIMIT 1",
            [$s['id']]
        );
        redirect($aktif ? 'ujian_kerjakan' : 'siswa_home', $aktif ? ['id' => $aktif['id']] : []);
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::check();
        }
        audit('logout');
        Auth::logout();
        flash('success', 'Anda telah keluar.');
        redirect('home');
    }
}
