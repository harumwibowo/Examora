<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

require APP_PATH . '/ctrl_brand.php';
require APP_PATH . '/ctrl_setup.php';
require APP_PATH . '/ctrl_auth.php';
require APP_PATH . '/ctrl_admin.php';
require APP_PATH . '/ctrl_soal.php';
require APP_PATH . '/ctrl_ujian.php';
require APP_PATH . '/ctrl_nilai.php';
require APP_PATH . '/ctrl_database.php';
require APP_PATH . '/ctrl_update.php';

$route = (string) ($_GET['r'] ?? 'home');
$route = preg_replace('/[^a-z0-9_]/', '', $route) ?: 'home';

$installed = is_file(BASE_PATH . '/install.lock') && is_file(BASE_PATH . '/config/config.php');

if (!$installed && $route !== 'setup' && $route !== 'favicon') {
    redirect('setup');
}
if ($installed && $route === 'setup') {
    redirect('home');
}

try {
    switch ($route) {
        // ---------- publik ----------
        case 'favicon':
            (new BrandController())->favicon();
            break;
        case 'home':
            (new AuthController())->landing();
            break;
        case 'setup':
            (new SetupController())->handle();
            break;
        case 'login':
            (new AuthController())->loginForm();
            break;
        case 'login_proses':
            (new AuthController())->loginPost();
            break;
        case 'masuk_siswa':
            (new AuthController())->siswaForm();
            break;
        case 'masuk_siswa_proses':
            (new AuthController())->siswaPost();
            break;
        case 'logout':
            (new AuthController())->logout();
            break;

        // ---------- staf ----------
        case 'dashboard':
            (new AdminController())->dashboard();
            break;

        case 'guru':
            (new AdminController())->guruIndex();
            break;
        case 'guru_form':
            (new AdminController())->guruForm();
            break;
        case 'guru_simpan':
            (new AdminController())->guruSave();
            break;
        case 'guru_hapus':
            (new AdminController())->guruDelete();
            break;
        case 'guru_toggle':
            (new AdminController())->guruToggle();
            break;
        case 'guru_impor':
            (new AdminController())->guruImport();
            break;
        case 'guru_template':
            (new AdminController())->guruTemplate();
            break;

        case 'kelas':
            (new AdminController())->kelasIndex();
            break;
        case 'kelas_simpan':
            (new AdminController())->kelasSave();
            break;
        case 'kelas_hapus':
            (new AdminController())->kelasDelete();
            break;
        case 'kelas_impor':
            (new AdminController())->kelasImport();
            break;
        case 'kelas_template':
            (new AdminController())->kelasTemplate();
            break;

        case 'siswa':
            (new AdminController())->siswaIndex();
            break;
        case 'siswa_form':
            (new AdminController())->siswaForm();
            break;
        case 'siswa_simpan':
            (new AdminController())->siswaSave();
            break;
        case 'siswa_hapus':
            (new AdminController())->siswaDelete();
            break;
        case 'siswa_impor':
            (new AdminController())->siswaImport();
            break;
        case 'siswa_template':
            (new AdminController())->siswaTemplate();
            break;
        case 'siswa_regenerate':
            (new AdminController())->siswaRegenerate();
            break;
        case 'siswa_reset_sesi':
            (new AdminController())->siswaResetSession();
            break;
        case 'siswa_toggle_all':
            (new AdminController())->siswaToggleAll();
            break;
        case 'siswa_delete_all':
            (new AdminController())->siswaDeleteAll();
            break;
        case 'siswa_kartu':
            (new AdminController())->siswaKartu();
            break;
        case 'siswa_ekspor':
            (new AdminController())->siswaExport();
            break;
        case 'siswa_reset_password':
            (new AdminController())->siswaResetPassword();
            break;

        case 'login_siswa':
            (new AdminController())->loginSiswa();
            break;
        case 'login_siswa_reset':
            (new AdminController())->loginSiswaReset();
            break;
        case 'login_siswa_reset_siswa':
            (new AdminController())->loginSiswaResetSiswa();
            break;
        case 'login_siswa_reset_semua':
            (new AdminController())->loginSiswaResetSemua();
            break;
        case 'login_siswa_reset_massal':
            (new AdminController())->loginSiswaResetMassal();
            break;

        case 'soal':
            (new SoalController())->index();
            break;
        case 'soal_form':
            (new SoalController())->form();
            break;
        case 'soal_simpan':
            (new SoalController())->save();
            break;
        case 'soal_hapus':
            (new SoalController())->delete();
            break;
        case 'soal_toggle':
            (new SoalController())->toggle();
            break;
        case 'soal_duplikat':
            (new SoalController())->duplicate();
            break;
        case 'pdf_soal':
            (new SoalController())->pdf();
            break;
        case 'soal_kolaborator':
            (new SoalController())->kolaborator();
            break;
        case 'soal_kolaborator_tambah':
            (new SoalController())->kolaboratorTambah();
            break;
        case 'soal_kolaborator_hapus':
            (new SoalController())->kolaboratorHapus();
            break;
        case 'kunci':
            (new SoalController())->kunci();
            break;
        case 'kunci_simpan':
            (new SoalController())->kunciSave();
            break;
        case 'kunci_reset':
            (new SoalController())->kunciReset();
            break;

        case 'nilai':
            (new NilaiController())->ujian();
            break;
        case 'nilai_buka_kunci':
            (new NilaiController())->bukaKunci();
            break;
        case 'nilai_kelas':
            (new NilaiController())->kelas();
            break;
        case 'koreksi':
            (new NilaiController())->koreksi();
            break;
        case 'koreksi_massal':
            (new NilaiController())->koreksiMassal();
            break;
        case 'koreksi_simpan':
            (new NilaiController())->koreksiSimpan();
            break;
        case 'regrade':
            (new NilaiController())->regrade();
            break;
        case 'hasil_hapus':
            (new NilaiController())->hapus();
            break;
        case 'ekspor':
            (new NilaiController())->ekspor();
            break;
        case 'analisis':
            (new NilaiController())->analisis();
            break;

        case 'pengaturan':
            (new AdminController())->pengaturan();
            break;
        case 'pengaturan_simpan':
            (new AdminController())->pengaturanSave();
            break;
        case 'audit':
            (new AdminController())->audit();
            break;
        case 'database':
            (new DatabaseController())->index();
            break;
        case 'database_hapus':
            (new DatabaseController())->hapus();
            break;
        case 'database_backup':
            (new DatabaseController())->backup();
            break;
        case 'database_restore':
            (new DatabaseController())->restore();
            break;
        case 'update':
            (new UpdateController())->index();
            break;
        case 'update_cek':
            (new UpdateController())->cek();
            break;
        case 'update_jalan':
            (new UpdateController())->jalan();
            break;
        case 'profil':
            (new AdminController())->profil();
            break;
        case 'profil_simpan':
            (new AdminController())->profilSave();
            break;

        // ---------- siswa ----------
        case 'siswa_home':
            (new UjianController())->dashboard();
            break;
        case 'ujian':
            (new UjianController())->konfirmasi();
            break;
        case 'ujian_mulai':
            (new UjianController())->mulai();
            break;
        case 'ujian_kerjakan':
            (new UjianController())->kerjakan();
            break;
        case 'ujian_selesai':
            (new UjianController())->selesaiPage();
            break;
        case 'ujian_hasil':
            (new UjianController())->hasil();
            break;

        // ---------- API ujian ----------
        case 'api_mulai':
            (new UjianController())->apiMulai();
            break;
        case 'api_jawab':
            (new UjianController())->apiJawab();
            break;
        case 'api_selesai':
            (new UjianController())->apiSelesai();
            break;
        case 'api_status':
            (new UjianController())->apiStatus();
            break;
        case 'api_pelanggaran':
            (new UjianController())->apiPelanggaran();
            break;

        default:
            http_response_code(404);
            View::render('errors/404', [], 'layout');
    }
} catch (Abort $e) {
    if (!headers_sent()) {
        http_response_code(400);
    }
    View::render('errors/400', ['pesan' => $e->getMessage()], 'layout');
}
