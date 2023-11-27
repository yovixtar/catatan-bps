<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->post('/login', 'Auth::login');

$routes->get('/kegiatan/daftar', 'Kegiatan::daftar_kegiatan', ['filter' => 'jwtfilter']);
