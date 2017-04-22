<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', 'HomeController@index');
Route::post('/upload', 'MigrationController@upload')->name('dump.upload');
Route::post('/process', 'MigrationController@processUploadedFile')->name('dump.process');
Route::post('/generate', 'MigrationController@generateMigrations')->name('migrations.generate');