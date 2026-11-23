<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_landing_page_publik_menampilkan_identitas_dan_aksi_utama(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Badan Pusat Statistik')
            ->assertSee('Sistem Pendaftaran')
            ->assertSee('Magang BPS')
            ->assertSee('Platform untuk memfasilitasi pengajuan dan pengelolaan permohonan magang di lingkungan Badan Pusat Statistik.')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('operator.login').'"', false)
            ->assertSee('href="'.route('intern.register').'"', false)
            ->assertSee('id="tentang"', false)
            ->assertSee('id="alur"', false);
    }

    public function test_halaman_login_dan_registrasi_tetap_dapat_dibuka(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('admin.login'))->assertOk();
        $this->get(route('operator.login'))->assertOk();
        $this->get(route('intern.login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get(route('intern.register'))->assertOk();
    }
}
