<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateParaguayRegionsTables extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Esta función es responsable de crear las tablas 'departments', 'cities' y 'neighborhoods' en la base de datos.
     * Estas tablas están diseñadas para almacenar información geográfica sobre Paraguay.
     *
     * @return void
     */
    public function up()
    {
        // Crea la tabla 'departments' para almacenar información sobre los departamentos de Paraguay.
        Schema::create('departments', function (Blueprint $table) {
            $table->bigIncrements('id'); // Clave primaria autoincremental (tipo bigInt).
            $table->string('name'); // Nombre del departamento (ej.: "Central").
            $table->string('capital'); // Nombre de la capital del departamento (ej.: "Asunción").
            $table->timestamps(); // Columnas 'created_at' y 'updated_at' para registrar fechas de creación y modificación.
        });

        // Crea la tabla 'cities' para almacenar información sobre las ciudades de Paraguay.
        Schema::create('cities', function (Blueprint $table) {
            $table->bigIncrements('id'); // Clave primaria autoincremental (tipo bigInt).
            $table->bigInteger('department_id')->unsigned(); // Clave foránea que referencia la tabla 'departments' (tipo bigInt).
            $table->string('name'); // Nombre de la ciudad (ej.: "Lambaré").
            $table->integer('population')->nullable(); // Población de la ciudad (opcional, tipo int).
            $table->timestamps(); // Columnas 'created_at' y 'updated_at' para registrar fechas de creación y modificación.
            // Define la relación de clave foránea entre 'cities' y 'departments'
            $table->foreign('department_id') // La columna 'department_id' en 'cities'...
                ->references('id') // ...referencia la columna 'id' en 'departments'
                ->on('departments'); // ...en la tabla 'departments'
        });

        // Crea la tabla 'neighborhoods' para almacenar información sobre los barrios de Paraguay.
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->bigIncrements('id'); // Clave primaria autoincremental (tipo bigInt).
            $table->bigInteger('city_id')->unsigned(); // Clave foránea que referencia la tabla 'cities' (tipo bigInt).
            $table->string('name'); // Nombre del barrio (ej.: "Centro").
            $table->timestamps(); // Columnas 'created_at' y 'updated_at' para registrar fechas de creación y modificación.
            // Define la relación de clave foránea entre 'neighborhoods' y 'cities'
            $table->foreign('city_id') // La columna 'city_id' en 'neighborhoods'...
                ->references('id') // ...referencia la columna 'id' en 'cities'
                ->on('cities'); // ...en la tabla 'cities'
        });
    }

    /**
     * Revierte las migraciones.
     * Esta función elimina las tablas 'neighborhoods', 'cities', 'departments' en el orden inverso a su creación.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('neighborhoods'); // Primero elimina 'neighborhoods' (depende de 'cities')
        Schema::dropIfExists('cities');      // Luego elimina 'cities' (depende de 'departments')
        Schema::dropIfExists('departments');  // Finalmente elimina 'departments'
    }
}
