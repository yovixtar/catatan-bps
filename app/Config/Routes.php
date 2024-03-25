<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::Index');
$routes->post('/login', 'Auth::Login');

$routes->get('/kegiatan', 'Kegiatan::DaftarKegiatan', ['filter' => 'userfilter']);
$routes->post('/kegiatan/target', 'Kegiatan::TambahTarget', ['filter' => 'userfilter']);
$routes->post('/kegiatan/realisasi', 'Kegiatan::TambahRealisasi', ['filter' => 'userfilter']);
$routes->delete('/kegiatan/(:segment)', 'Kegiatan::HapusKegiatan/$1', ['filter' => 'userfilter']);

$routes->get('/pengguna', 'Auth::DaftarPengguna', ['filter' => 'adminfilter']);
$routes->post('/pengguna', 'Auth::TambahPengguna', ['filter' => 'adminfilter']);
$routes->delete('/pengguna/(:segment)', 'Auth::HapusPengguna/$1', ['filter' => 'adminfilter']);
$routes->post('/pengguna/ganti-password', 'Auth::GantiPassword', ['filter' => 'adminfilter']);
$routes->post('/pengguna/ganti-nama', 'Auth::GantiNama', ['filter' => 'adminfilter']);
$routes->post('/pengguna/switch-status', 'Auth::SwitchStatusPengguna', ['filter' => 'adminfilter']);
