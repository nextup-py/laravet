<?php

it('la pantalla de Roles y permisos carga sin errores para el admin', function () {
    actingAsRole('admin');

    $this->get('/shield/roles')->assertOk();
});

it('la pantalla de Roles y permisos no es accesible para un rol sin permisos de Role', function () {
    actingAsRole('assistant');

    $this->get('/shield/roles')->assertForbidden();
});
